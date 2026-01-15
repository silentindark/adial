<?php
/**
 * A-Dial AMI Daemon
 * Manages campaign processing and call origination via AMI
 */

// Prevent running as web script
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line\n");
}

// Load dependencies
require_once __DIR__ . '/AmiClient.php';
require_once __DIR__ . '/Logger.php';

/**
 * Main Daemon Class
 */
class ADialDaemon {
    private $config;
    private $logger;
    private $ami;
    private $db;
    private $running = true;
    private $campaigns = [];
    private $activeCalls = [];
    private $lastCampaignReload = 0;
    private $lastCampaignProcess = [];

    public function __construct() {
        // Load configuration
        $this->config = require __DIR__ . '/config.php';

        // Initialize logger
        $this->logger = new Logger(
            $this->config['app']['log_file'],
            $this->config['app']['log_level']
        );

        $this->logger->info("A-Dial AMI Daemon initializing...");

        // Setup signal handlers
        $this->setupSignalHandlers();

        // Connect to database
        $this->connectDatabase();

        // Connect to AMI
        $this->connectAMI();

        // Write PID file
        $this->writePidFile();
    }

    /**
     * Setup signal handlers for graceful shutdown
     */
    private function setupSignalHandlers() {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            $this->logger->info("Signal handlers registered");
        } else {
            $this->logger->warning("pcntl extension not available - signals not handled");
        }
    }

    /**
     * Handle shutdown signals
     */
    public function handleSignal($signal) {
        $this->logger->info("Received signal $signal - shutting down gracefully");
        $this->running = false;
    }

    /**
     * Write PID file
     */
    private function writePidFile() {
        $pidFile = $this->config['app']['pid_file'];
        file_put_contents($pidFile, getmypid());
        $this->logger->info("PID file written: $pidFile");
    }

    /**
     * Remove PID file
     */
    private function removePidFile() {
        $pidFile = $this->config['app']['pid_file'];
        if (file_exists($pidFile)) {
            unlink($pidFile);
            $this->logger->info("PID file removed");
        }
    }

    /**
     * Connect to MySQL database
     */
    private function connectDatabase() {
        $dbConfig = $this->config['database'];

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['database'],
                $dbConfig['charset']
            );

            $this->db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);

            $this->logger->info("Connected to database successfully");
        } catch (PDOException $e) {
            $this->logger->error("Database connection failed: " . $e->getMessage());
            die("Failed to connect to database\n");
        }
    }

    /**
     * Connect to AMI
     */
    private function connectAMI() {
        $amiConfig = $this->config['ami'];

        try {
            $this->ami = new AmiClient(
                $amiConfig['host'],
                $amiConfig['port'],
                $amiConfig['username'],
                $amiConfig['password'],
                $this->logger
            );

            $this->ami->connect();

            // Register event handlers
            $this->registerEventHandlers();

            $this->logger->info("AMI connection established");
        } catch (Exception $e) {
            $this->logger->error("AMI connection failed: " . $e->getMessage());
            die("Failed to connect to AMI\n");
        }
    }

    /**
     * Register AMI event handlers
     */
    private function registerEventHandlers() {
        // Hangup event - most important for cleanup
        $this->ami->on('Hangup', function($event) {
            $this->handleHangupEvent($event);
        });

        // UserEvent - custom events from dialplan
        $this->ami->on('UserEvent', function($event) {
            $this->handleUserEvent($event);
        });

        // Newchannel - track channel creation
        $this->ami->on('Newchannel', function($event) {
            $this->handleNewchannelEvent($event);
        });

        $this->logger->info("AMI event handlers registered");
    }

    /**
     * Main daemon loop
     */
    public function run() {
        $this->logger->info("A-Dial AMI Daemon started");

        $lastPing = time();

        while ($this->running) {
            try {
                // Process signals (if pcntl available)
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                // Process AMI events (non-blocking)
                $this->ami->processEvents();

                // Reload campaigns every N seconds
                if (time() - $this->lastCampaignReload >= $this->config['campaigns']['reload_interval']) {
                    $this->loadCampaigns();
                    $this->lastCampaignReload = time();
                }

                // Process each campaign
                foreach ($this->campaigns as $campaignId => $campaign) {
                    $lastProcess = $this->lastCampaignProcess[$campaignId] ?? 0;

                    if (time() - $lastProcess >= $this->config['campaigns']['process_interval']) {
                        $this->processCampaign($campaignId);
                        $this->lastCampaignProcess[$campaignId] = time();
                    }
                }

                // Keep connection alive
                if (time() - $lastPing > 30) {
                    $this->ami->ping();
                    $lastPing = time();
                }

                // Sleep briefly to prevent CPU spinning
                usleep(100000); // 100ms

            } catch (Exception $e) {
                $this->logger->error("Error in main loop: " . $e->getMessage());
                sleep(1);
            }
        }

        $this->shutdown();
    }

    /**
     * Load active campaigns from database
     */
    private function loadCampaigns() {
        try {
            $stmt = $this->db->query("
                SELECT *
                FROM campaigns
                WHERE status = 'running'
                ORDER BY id
            ");

            $campaigns = $stmt->fetchAll();

            $this->logger->info("Loaded " . count($campaigns) . " active campaigns");

            // Update campaigns array
            $newCampaigns = [];
            foreach ($campaigns as $campaign) {
                $campaignId = $campaign->id;

                // Preserve currentCalls counter if campaign exists
                $currentCalls = $this->campaigns[$campaignId]['currentCalls'] ?? 0;

                // Get IVR menu ID if agent_dest_type is 'ivr'
                $ivr_menu_id = null;
                if ($campaign->agent_dest_type === 'ivr') {
                    $ivr_menu_id = $campaign->agent_dest_value;
                }

                $newCampaigns[$campaignId] = [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'trunk_type' => $campaign->trunk_type,
                    'trunk_value' => $campaign->trunk_value,
                    'callerid' => $campaign->callerid,
                    'concurrent_calls' => (int)$campaign->concurrent_calls,
                    'retry_times' => (int)$campaign->retry_times,
                    'retry_delay' => (int)$campaign->retry_delay,
                    'ivr_menu_id' => $ivr_menu_id,
                    'agent_dest_type' => $campaign->agent_dest_type,
                    'agent_dest_value' => $campaign->agent_dest_value,
                    'currentCalls' => $currentCalls
                ];
            }

            // Detect stopped campaigns
            foreach ($this->campaigns as $campaignId => $campaign) {
                if (!isset($newCampaigns[$campaignId])) {
                    $this->logger->info("Campaign $campaignId stopped - will hangup active calls on next hangup event");
                }
            }

            $this->campaigns = $newCampaigns;

        } catch (PDOException $e) {
            $this->logger->error("Failed to load campaigns: " . $e->getMessage());
        }
    }

    /**
     * Process a campaign - originate calls for pending numbers
     */
    private function processCampaign($campaignId) {
        $campaign = $this->campaigns[$campaignId];

        $availableSlots = $campaign['concurrent_calls'] - $campaign['currentCalls'];

        if ($availableSlots <= 0) {
            return;
        }

        $this->logger->debug("Campaign $campaignId: {$campaign['currentCalls']}/{$campaign['concurrent_calls']} calls, $availableSlots slots available");

        try {
            // Query pending numbers
            $stmt = $this->db->prepare("
                SELECT *
                FROM campaign_numbers
                WHERE campaign_id = ?
                  AND status = 'pending'
                ORDER BY id ASC
                LIMIT ?
            ");

            $stmt->execute([$campaignId, $availableSlots]);
            $numbers = $stmt->fetchAll();

            if (empty($numbers)) {
                // Check if campaign is complete
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as pending_count
                    FROM campaign_numbers
                    WHERE campaign_id = ?
                      AND status = 'pending'
                ");
                $stmt->execute([$campaignId]);
                $result = $stmt->fetch();

                if ($result->pending_count == 0 && $campaign['currentCalls'] == 0) {
                    $this->logger->info("Campaign $campaignId completed - marking as stopped");
                    $this->db->prepare("UPDATE campaigns SET status = 'completed' WHERE id = ?")->execute([$campaignId]);
                }

                return;
            }

            $this->logger->debug("Campaign $campaignId: Found " . count($numbers) . " numbers to dial");

            // Originate calls
            foreach ($numbers as $number) {
                $this->originateCall($campaign, $number);
            }

        } catch (PDOException $e) {
            $this->logger->error("Failed to process campaign $campaignId: " . $e->getMessage());
        }
    }

    /**
     * Originate a call for a campaign number
     */
    private function originateCall($campaign, $number) {
        $campaignId = $campaign['id'];
        $numberId = $number->id;
        $phoneNumber = $number->phone_number;

        // Build trunk endpoint
        $trunkType = strtoupper($campaign['trunk_type']);
        $trunkValue = $campaign['trunk_value'];
        $trunkEndpoint = "$trunkType/$trunkValue";

        try {
            // Update number status to 'calling'
            $stmt = $this->db->prepare("
                UPDATE campaign_numbers
                SET status = 'calling',
                    attempts = attempts + 1,
                    last_attempt = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$numberId]);

            // Note: CDR records will be created automatically by Asterisk
            // with accountcode set to campaign_id for filtering

            // Determine destination context and extension based on agent_dest_type
            $destContext = 'dialer_agent';
            $destExten = $campaign['agent_dest_value'] ?? 's';
            $channelType = 'SIP'; // Default channel type

            switch ($campaign['agent_dest_type']) {
                case 'ivr':
                    $destContext = "ivr-menu-{$campaign['ivr_menu_id']}";
                    $destExten = 's';
                    break;
                case 'exten':
                    $destContext = 'dialer_agent';
                    // Parse "SIP/103" or "PJSIP/103" into channel type and extension
                    $agentValue = $campaign['agent_dest_value'] ?? '';
                    if (strpos($agentValue, '/') !== false) {
                        list($channelType, $destExten) = explode('/', $agentValue, 2);
                        $channelType = strtoupper($channelType);
                    } else {
                        $destExten = $agentValue;
                    }
                    break;
                case 'queue':
                    $destContext = 'dialer_queue';
                    $destExten = $campaign['agent_dest_value'] ?? 's';
                    break;
                case 'custom':
                default:
                    $destContext = 'dialer_agent';
                    $destExten = $campaign['agent_dest_value'] ?? 's';
                    break;
            }

            // Get timeout settings from campaign (with defaults)
            $dialTimeout = $campaign['dial_timeout'] ?? 30;  // Default 30 seconds
            $callTimeout = $campaign['call_timeout'] ?? 600; // Default 10 minutes (600 seconds)

            // Originate via AMI - use LOCAL channel to go through dialer_out context
            $originateParams = [
                'Channel' => "LOCAL/$phoneNumber@dialer_out",
                'Context' => $destContext,
                'Exten' => $destExten,
                'Priority' => '1',
                'CallerID' => $campaign['callerid'],
                'Timeout' => ($dialTimeout * 1000), // Convert to milliseconds for AMI
                'Variable' => [
                    "CAMPAIGN_ID=$campaignId",
                    "NUMBER_ID=$numberId",
                    "TRUNK=$trunkEndpoint",
                    "CHANNEL_TYPE=$channelType",
                    "DIAL_TIMEOUT=$dialTimeout",
                    "CALL_TIMEOUT=$callTimeout"
                ],
                'Async' => 'true'
            ];

            // Build variable string for AMI
            $variables = '';
            foreach ($originateParams['Variable'] as $var) {
                if (!empty($variables)) {
                    $variables .= ',';
                }
                $variables .= $var;
            }

            $amiParams = [
                'Channel' => $originateParams['Channel'],
                'Context' => $originateParams['Context'],
                'Exten' => $originateParams['Exten'],
                'Priority' => $originateParams['Priority'],
                'CallerID' => $originateParams['CallerID'],
                'Timeout' => $originateParams['Timeout'],
                'Variable' => $variables,
                'Async' => $originateParams['Async']
            ];

            $this->logger->info("Preparing to originate call", [
                'campaign_id' => $campaignId,
                'number_id' => $numberId,
                'phone_number' => $phoneNumber,
                'channel' => $amiParams['Channel'],
                'context' => $amiParams['Context'],
                'callerid' => $amiParams['CallerID']
            ]);

            $response = $this->ami->originate($amiParams);

            // Log originate response details
            $this->logger->info("Originate response received", [
                'response' => $response['Response'] ?? 'N/A',
                'message' => $response['Message'] ?? 'N/A',
                'actionid' => $response['ActionID'] ?? 'N/A'
            ]);

            // Check if originate failed
            if (!isset($response['Response']) || strtolower($response['Response']) !== 'success') {
                $this->logger->error("Originate failed for number $numberId: " . ($response['Message'] ?? 'Unknown error'));

                // Mark as originate_failed
                $this->db->prepare("UPDATE campaign_numbers SET status = 'originate_failed' WHERE id = ?")->execute([$numberId]);
                return;
            }

            // Increment current calls counter
            $this->campaigns[$campaignId]['currentCalls']++;

            // Track in activeCalls
            if (!isset($this->activeCalls[$campaignId])) {
                $this->activeCalls[$campaignId] = [];
            }

            $this->activeCalls[$campaignId][] = [
                'number_id' => $numberId,
                'phone_number' => $phoneNumber,
                'started_at' => time()
            ];

            $this->logger->info("Originated call: Campaign $campaignId, Number $numberId ($phoneNumber)");

        } catch (Exception $e) {
            $this->logger->error("Failed to originate call for number $numberId: " . $e->getMessage());

            // Mark as originate_failed
            $this->db->prepare("UPDATE campaign_numbers SET status = 'originate_failed' WHERE id = ?")->execute([$numberId]);
        }
    }

    /**
     * Handle Hangup event
     */
    private function handleHangupEvent($event) {
        $uniqueid = $event['Uniqueid'] ?? null;
        $cause = $event['Cause'] ?? null;
        $causeText = $event['Cause-txt'] ?? null;

        if (!$uniqueid) {
            return;
        }

        // Try to extract campaign and number IDs from channel variables
        // In practice, we'll track by looking up the channel in our database
        // For now, update based on active calls tracking

        $this->logger->debug("Hangup event: UniqueID=$uniqueid, Cause=$cause ($causeText)");

        // Find the call in activeCalls by uniqueid
        // Note: Asterisk will handle CDR records automatically
        $campaignId = null;
        $numberId = null;

        // Find in our tracking
        foreach ($this->activeCalls as $cid => $calls) {
            foreach ($calls as $call) {
                // Match by phone number and recent timestamp (within last 2 minutes)
                if (isset($call['started_at']) && (time() - $call['started_at']) < 120) {
                    $campaignId = $cid;
                    $numberId = $call['number_id'];
                    break 2;
                }
            }
        }

        if (!$campaignId || !$numberId) {
            $this->logger->debug("Hangup event for unknown call: $uniqueid");
            return;
        }

        try {
            // Map disposition
            $disposition = $this->mapDisposition($cause);

            // Handle retry logic
            $this->handleRetryLogic($numberId, $disposition);

            // Decrement counter
            if (isset($this->campaigns[$campaignId])) {
                $this->campaigns[$campaignId]['currentCalls'] = max(0, $this->campaigns[$campaignId]['currentCalls'] - 1);
            }

            // Remove from active calls
            if (isset($this->activeCalls[$campaignId])) {
                $this->activeCalls[$campaignId] = array_filter(
                    $this->activeCalls[$campaignId],
                    function($call) use ($numberId) {
                        return $call['number_id'] != $numberId;
                    }
                );
            }

            $this->logger->info("Call ended: Campaign $campaignId, Number $numberId, Disposition: $disposition");

        } catch (PDOException $e) {
            $this->logger->error("Failed to handle hangup event: " . $e->getMessage());
        }
    }

    /**
     * Handle UserEvent (custom events from dialplan)
     */
    private function handleUserEvent($event) {
        $userEvent = $event['UserEvent'] ?? null;

        if (!$userEvent) {
            return;
        }

        $this->logger->debug("UserEvent: $userEvent", $event);

        try {
            switch ($userEvent) {
                case 'CallAnswered':
                    $campaignId = $event['Campaign'] ?? null;
                    $numberId = $event['Number'] ?? null;

                    if ($numberId) {
                        // Update number status
                        $stmt = $this->db->prepare("UPDATE campaign_numbers SET status = 'answered' WHERE id = ?");
                        $stmt->execute([$numberId]);

                        // Note: CDR is automatically handled by Asterisk
                        $this->logger->info("Call answered: Campaign $campaignId, Number $numberId");
                    }
                    break;

                case 'CallFailed':
                    $numberId = $event['Number'] ?? null;
                    $status = $event['Status'] ?? 'FAILED';

                    if ($numberId) {
                        $this->handleRetryLogic($numberId, $status);
                    }
                    break;

                case 'IVRAction':
                    $numberId = $event['Number'] ?? null;
                    $digit = $event['Digit'] ?? null;
                    $action = $event['Action'] ?? null;

                    $this->logger->info("IVR Action: Number $numberId pressed $digit (action: $action)");
                    break;
            }

        } catch (PDOException $e) {
            $this->logger->error("Failed to handle user event: " . $e->getMessage());
        }
    }

    /**
     * Handle Newchannel event
     */
    private function handleNewchannelEvent($event) {
        $uniqueid = $event['Uniqueid'] ?? null;
        $channel = $event['Channel'] ?? null;

        if (!$uniqueid || !$channel) {
            return;
        }

        $this->logger->debug("New channel: $channel ($uniqueid)");

        // Store uniqueid in CDR for tracking
        // Try to find CDR by campaign variables (if available in event)
        // This is a bit tricky with AMI - we'll update on hangup instead
    }

    /**
     * Map Asterisk hangup cause to database status enum
     * Hangup causes: https://wiki.asterisk.org/wiki/display/AST/Hangup+Cause+Mappings
     *
     * Database enum: pending, calling, answered, failed, no_answer, busy, cancel, chanunavail, congestion, originate_failed
     */
    private function mapDisposition($cause) {
        switch ((int)$cause) {
            // Answered/Success
            case 16: // Normal clearing - call was answered and ended normally
                return 'answered';

            // Busy
            case 17: // User busy
                return 'busy';

            // No answer
            case 18: // No user responding
            case 19: // No answer from user (user alerted, no answer)
            case 20: // Subscriber absent
                return 'no_answer';

            // Cancel (originator hung up)
            case 21: // Call rejected
            case 487: // Request terminated (SIP cancel)
                return 'cancel';

            // Channel unavailable
            case 1:  // Unallocated/unassigned number
            case 2:  // No route to specified transit network
            case 3:  // No route to destination
            case 27: // Destination out of order
            case 28: // Invalid number format
            case 29: // Facility rejected
            case 31: // Normal unspecified
                return 'chanunavail';

            // Congestion
            case 34: // No circuit/channel available
            case 38: // Network out of order
            case 41: // Temporary failure
            case 42: // Switching equipment congestion
            case 47: // Resource unavailable
            case 63: // Service or option not available
                return 'congestion';

            // Failed (other errors)
            default:
                return 'failed';
        }
    }

    /**
     * Handle retry logic for failed/unanswered calls
     */
    private function handleRetryLogic($numberId, $disposition) {
        try {
            // Get number and campaign info
            $stmt = $this->db->prepare("
                SELECT cn.*, c.retry_times, c.retry_delay
                FROM campaign_numbers cn
                JOIN campaigns c ON c.id = cn.campaign_id
                WHERE cn.id = ?
            ");
            $stmt->execute([$numberId]);
            $number = $stmt->fetch();

            if (!$number) {
                return;
            }

            // Statuses that should trigger retry
            $retryStatuses = ['no_answer', 'busy', 'failed', 'cancel', 'chanunavail', 'congestion'];
            $shouldRetry = in_array($disposition, $retryStatuses);

            if ($shouldRetry && $number->attempts < $number->retry_times) {
                // Schedule retry (just reset to pending - will be picked up in next cycle)
                $stmt = $this->db->prepare("
                    UPDATE campaign_numbers
                    SET status = 'pending'
                    WHERE id = ?
                ");
                $stmt->execute([$numberId]);

                $this->logger->info("Number $numberId scheduled for retry (attempt {$number->attempts}/{$number->retry_times})");
            } else {
                // Keep the real disposition status instead of generic 'completed'
                $stmt = $this->db->prepare("
                    UPDATE campaign_numbers
                    SET status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$disposition, $numberId]);

                $reason = !$shouldRetry ? $disposition : 'max attempts reached';
                $this->logger->info("Number $numberId marked as $disposition ($reason)");
            }

        } catch (PDOException $e) {
            $this->logger->error("Failed to handle retry logic for number $numberId: " . $e->getMessage());
        }
    }

    /**
     * Graceful shutdown
     */
    private function shutdown() {
        $this->logger->info("Shutting down daemon...");

        // Disconnect from AMI
        if ($this->ami) {
            $this->ami->disconnect();
        }

        // Close database connection
        $this->db = null;

        // Remove PID file
        $this->removePidFile();

        $this->logger->info("Daemon stopped");
    }
}

// Run daemon
try {
    $daemon = new ADialDaemon();
    $daemon->run();
} catch (Exception $e) {
    error_log("Fatal error: " . $e->getMessage());
    exit(1);
}
