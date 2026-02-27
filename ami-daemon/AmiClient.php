<?php
/**
 * Simple AMI (Asterisk Manager Interface) Client
 * Implements basic AMI protocol for originate and event listening
 */
class AmiClient {
    private $socket;
    private $host;
    private $port;
    private $username;
    private $password;
    private $connected = false;
    private $eventCallbacks = [];
    private $logger;
    private $actionIdCounter = 0;

    public function __construct($host, $port, $username, $password, $logger) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->logger = $logger;
    }

    /**
     * Connect to AMI and login
     */
    public function connect() {
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 10);

        if (!$this->socket) {
            throw new Exception("Failed to connect to AMI: $errstr ($errno)");
        }

        // Enable TCP keepalive to prevent firewall/NAT from dropping idle connections
        if (function_exists('socket_import_stream')) {
            $rawSocket = socket_import_stream($this->socket);
            if ($rawSocket) {
                socket_set_option($rawSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
            }
        }

        stream_set_blocking($this->socket, false);
        stream_set_timeout($this->socket, 0, 100000); // 100ms timeout

        // Read welcome message
        $this->readResponse();

        // Login
        $this->sendAction([
            'Action' => 'Login',
            'Username' => $this->username,
            'Secret' => $this->password
        ]);

        $response = $this->readResponse();

        if (stripos($response, 'Success') === false) {
            throw new Exception("AMI login failed: $response");
        }

        $this->connected = true;
        $this->logger->info("Connected to AMI successfully");
    }

    /**
     * Reconnect to AMI
     */
    public function reconnect() {
        $this->logger->warning("AMI connection lost, attempting reconnect...");

        // Close old socket
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
        $this->connected = false;

        try {
            $this->connect();
            $this->logger->info("AMI reconnected successfully");
            return true;
        } catch (Exception $e) {
            $this->logger->error("AMI reconnect failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if connected
     */
    public function isConnected() {
        if (!$this->connected || !$this->socket) {
            return false;
        }

        if (feof($this->socket)) {
            $this->connected = false;
            return false;
        }

        return true;
    }

    /**
     * Disconnect from AMI
     */
    public function disconnect() {
        if ($this->socket) {
            @$this->sendAction(['Action' => 'Logoff']);
            @fclose($this->socket);
            $this->socket = null;
            $this->connected = false;
            $this->logger->info("Disconnected from AMI");
        }
    }

    /**
     * Send an action to AMI
     * Returns false if write fails (broken pipe)
     */
    private function sendAction($params) {
        if (!$this->socket) {
            return false;
        }

        $message = '';
        foreach ($params as $key => $value) {
            $message .= "$key: $value\r\n";
        }
        $message .= "\r\n";

        $result = @fwrite($this->socket, $message);

        if ($result === false || $result === 0) {
            $this->connected = false;
            $this->logger->error("AMI write failed (broken pipe)");
            return false;
        }

        return true;
    }

    /**
     * Generate unique ActionID
     */
    private function generateActionId() {
        $this->actionIdCounter++;
        return 'adial-' . getmypid() . '-' . $this->actionIdCounter;
    }

    /**
     * Read response from AMI (blocks until response received or timeout)
     */
    private function readResponse() {
        $response = '';
        $startTime = microtime(true);
        $timeout = 5; // 5 seconds timeout

        while ((microtime(true) - $startTime) < $timeout) {
            $line = @fgets($this->socket);
            if ($line === false) {
                usleep(10000); // 10ms
                continue;
            }

            $response .= $line;

            // End of response is marked by double CRLF
            if (strpos($response, "\r\n\r\n") !== false) {
                break;
            }
        }

        return $response;
    }

    /**
     * Read response matching a specific ActionID, processing events along the way
     */
    private function readResponseForAction($actionId) {
        $startTime = microtime(true);
        $timeout = 5; // 5 seconds timeout
        $buffer = '';

        while ((microtime(true) - $startTime) < $timeout) {
            $line = @fgets($this->socket);
            if ($line === false) {
                usleep(10000); // 10ms
                continue;
            }

            $buffer .= $line;

            // Check for end of a message block
            if (trim($line) === '' || strpos($buffer, "\r\n\r\n") !== false) {
                $parsed = $this->parseEvent(trim($buffer));

                // Check if this is our response (matching ActionID)
                if (isset($parsed['ActionID']) && $parsed['ActionID'] === $actionId) {
                    return $parsed;
                }

                // If it's an event, process it
                if (isset($parsed['Event'])) {
                    $this->handleEvent($parsed);
                }

                // Reset buffer for next message
                $buffer = '';
            }
        }

        // Timeout - return empty result
        return [];
    }

    /**
     * Originate a call
     */
    public function originate($params) {
        $actionId = $this->generateActionId();
        $action = array_merge(['Action' => 'Originate', 'ActionID' => $actionId], $params);

        // Log the full Originate action being sent
        $this->logger->info("AMI Originate Action:", $action);

        if (!$this->sendAction($action)) {
            return ['Response' => 'Error', 'Message' => 'AMI connection lost'];
        }

        // Read response matching our ActionID, processing events along the way
        $result = $this->readResponseForAction($actionId);

        $this->logger->info("AMI Originate Response:", $result);

        return $result;
    }

    /**
     * Register an event callback
     */
    public function on($eventName, $callback) {
        if (!isset($this->eventCallbacks[$eventName])) {
            $this->eventCallbacks[$eventName] = [];
        }
        $this->eventCallbacks[$eventName][] = $callback;
    }

    /**
     * Process incoming events (non-blocking)
     */
    public function processEvents() {
        if (!$this->isConnected()) {
            return;
        }

        // Process multiple events per cycle to avoid falling behind
        $maxEvents = 50;
        $processed = 0;

        while ($processed < $maxEvents) {
            $event = $this->readEvent();

            if (!$event) {
                break;
            }

            $this->handleEvent($event);
            $processed++;
        }
    }

    /**
     * Read an event from AMI (non-blocking)
     */
    private function readEvent() {
        if (!$this->socket) {
            return null;
        }

        $event = '';
        $lines = 0;

        while (true) {
            $line = @fgets($this->socket);

            if ($line === false) {
                // No data available
                break;
            }

            $event .= $line;
            $lines++;

            // Events end with empty line
            if (trim($line) === '' || strpos($event, "\r\n\r\n") !== false) {
                break;
            }

            // Prevent infinite loop
            if ($lines > 100) {
                break;
            }
        }

        if (empty(trim($event))) {
            return null;
        }

        return $this->parseEvent($event);
    }

    /**
     * Parse event text into array
     */
    private function parseEvent($eventText) {
        $lines = explode("\n", $eventText);
        $event = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = explode(':', $line, 2);
            if (count($parts) == 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                $event[$key] = $value;
            }
        }

        return $event;
    }

    /**
     * Handle parsed event
     */
    private function handleEvent($event) {
        if (!isset($event['Event'])) {
            return;
        }

        $eventName = $event['Event'];

        // Log event
        if ($this->logger->getLevel() === 'debug') {
            $this->logger->debug("AMI Event: $eventName", $event);
        }

        // Call registered callbacks for this event
        if (isset($this->eventCallbacks[$eventName])) {
            foreach ($this->eventCallbacks[$eventName] as $callback) {
                call_user_func($callback, $event);
            }
        }

        // Also call wildcard callbacks
        if (isset($this->eventCallbacks['*'])) {
            foreach ($this->eventCallbacks['*'] as $callback) {
                call_user_func($callback, $event);
            }
        }
    }

    /**
     * Send ping to keep connection alive
     */
    public function ping() {
        if (!$this->sendAction(['Action' => 'Ping'])) {
            $this->connected = false;
        }
    }
}
