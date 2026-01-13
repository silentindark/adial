#!/usr/bin/env php
<?php
/**
 * Scan SIP and PJSIP Endpoints
 * Utility to discover available SIP peers and PJSIP endpoints in Asterisk
 */

// Prevent running as web script
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line\n");
}

// Load configuration
$config = require __DIR__ . '/ami-daemon/config.php';

// Simple AMI client for scanning
class EndpointScanner {
    private $socket;
    private $host;
    private $port;
    private $username;
    private $password;

    public function __construct($host, $port, $username, $password) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Connect to AMI
     */
    public function connect() {
        echo "Connecting to AMI at {$this->host}:{$this->port}...\n";

        $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, 10);

        if (!$this->socket) {
            throw new Exception("Failed to connect to AMI: $errstr ($errno)");
        }

        stream_set_blocking($this->socket, true);
        stream_set_timeout($this->socket, 10);

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
            throw new Exception("AMI login failed");
        }

        echo "Connected successfully!\n\n";
    }

    /**
     * Send action to AMI
     */
    private function sendAction($params) {
        $message = '';
        foreach ($params as $key => $value) {
            $message .= "$key: $value\r\n";
        }
        $message .= "\r\n";

        fwrite($this->socket, $message);
    }

    /**
     * Read response from AMI
     * For Command actions, reads until timeout to get all output
     */
    private function readResponse($isCommand = false) {
        $response = '';
        $timeout = time() + 10;
        $emptyLines = 0;

        while (time() < $timeout) {
            $line = fgets($this->socket);
            if ($line === false) {
                usleep(10000); // 10ms
                continue;
            }

            $response .= $line;

            // For commands, wait for multiple consecutive empty lines or timeout
            if ($isCommand) {
                if (trim($line) === '') {
                    $emptyLines++;
                    if ($emptyLines >= 3) {
                        break; // Got enough empty lines, assume end of output
                    }
                } else {
                    $emptyLines = 0;
                }
            } else {
                // For regular responses, end at double CRLF
                if (strpos($response, "\r\n\r\n") !== false) {
                    break;
                }
            }
        }

        return $response;
    }

    /**
     * Scan for SIP peers (chan_sip)
     */
    public function scanSipPeers() {
        echo "====================================\n";
        echo "Scanning SIP Peers (chan_sip)...\n";
        echo "====================================\n\n";

        $this->sendAction([
            'Action' => 'Command',
            'Command' => 'sip show peers'
        ]);

        $response = $this->readResponse(true);

        // Parse the output
        $lines = explode("\n", $response);
        $peers = [];
        $inOutput = false;

        foreach ($lines as $line) {
            // Skip header and separator lines
            if (strpos($line, 'Name/username') !== false ||
                strpos($line, '===') !== false ||
                preg_match('/^\d+ sip peers/', $line)) {
                continue;
            }

            // Match SIP peer lines that start with "Output: " followed by peer name
            // Format: "Output: peername/username       192.168.1.1    D  N      A  5060     OK (1 ms)"
            if (preg_match('/^Output:\s+(\S+)/', $line, $matches)) {
                $peer = $matches[1];

                // Remove /username part if present
                $peer = preg_replace('/\/.*$/', '', $peer);

                // Skip empty and header entries
                if (empty($peer) || $peer === 'Name' || $peer === 'Asterisk' ||
                    strpos($peer, '(Unspecified)') !== false ||
                    strpos($line, 'sip show peers') !== false) {
                    continue;
                }

                if (!in_array($peer, $peers)) {
                    $peers[] = $peer;

                    // Detect status
                    $status = 'Unknown';
                    if (preg_match('/OK\s*\(/', $line)) {
                        $status = 'Online';
                    } elseif (preg_match('/UNREACHABLE|Unmonitored/', $line)) {
                        $status = 'Offline';
                    }

                    echo "  - SIP/$peer ($status)\n";
                }
            }
        }

        if (empty($peers)) {
            echo "  No SIP peers found (chan_sip may not be loaded)\n";
        }

        echo "\n";
        return $peers;
    }

    /**
     * Scan for PJSIP endpoints
     */
    public function scanPjsipEndpoints() {
        echo "====================================\n";
        echo "Scanning PJSIP Endpoints...\n";
        echo "====================================\n\n";

        $this->sendAction([
            'Action' => 'Command',
            'Command' => 'pjsip show endpoints'
        ]);

        $response = $this->readResponse(true);

        // Parse the output
        $lines = explode("\n", $response);
        $endpoints = [];
        $inOutput = false;

        foreach ($lines as $line) {
            // Match lines that start with "Output:  Endpoint:  <name>"
            // Format: "Output:  Endpoint:  100/100                                              Not in use    0 of inf"
            if (preg_match('/^Output:\s+Endpoint:\s+(\S+?)(?:\/|\s|$)/', $line, $matches)) {
                $endpoint = $matches[1];

                // Skip header lines with < > placeholders
                if (strpos($endpoint, '<') !== false) {
                    continue;
                }

                if (!empty($endpoint) && !in_array($endpoint, $endpoints)) {
                    $endpoints[] = $endpoint;

                    // Detect status
                    $status = 'Unknown';
                    if (strpos($line, 'Not in use') !== false) {
                        $status = 'Available';
                    } elseif (strpos($line, 'Unavailable') !== false) {
                        $status = 'Unavailable';
                    } elseif (strpos($line, 'In use') !== false) {
                        $status = 'In use';
                    }

                    echo "  - PJSIP/$endpoint ($status)\n";
                }
            }
        }

        if (empty($endpoints)) {
            echo "  No PJSIP endpoints found (PJSIP may not be loaded)\n";
        }

        echo "\n";
        return $endpoints;
    }

    /**
     * Get Asterisk version
     */
    public function getVersion() {
        $this->sendAction([
            'Action' => 'Command',
            'Command' => 'core show version'
        ]);

        $response = $this->readResponse(true);

        if (preg_match('/Asterisk\s+([^\s]+)/', $response, $matches)) {
            return $matches[1];
        }

        return 'Unknown';
    }

    /**
     * Disconnect from AMI
     */
    public function disconnect() {
        if ($this->socket) {
            $this->sendAction(['Action' => 'Logoff']);
            fclose($this->socket);
            echo "Disconnected from AMI\n";
        }
    }
}

// Main execution
try {
    $scanner = new EndpointScanner(
        $config['ami']['host'],
        $config['ami']['port'],
        $config['ami']['username'],
        $config['ami']['password']
    );

    $scanner->connect();

    // Get Asterisk version
    $version = $scanner->getVersion();
    echo "Asterisk Version: $version\n\n";

    // Scan SIP peers
    $sipPeers = $scanner->scanSipPeers();

    // Scan PJSIP endpoints
    $pjsipEndpoints = $scanner->scanPjsipEndpoints();

    // Summary
    echo "====================================\n";
    echo "Summary\n";
    echo "====================================\n";
    echo "SIP Peers found: " . count($sipPeers) . "\n";
    echo "PJSIP Endpoints found: " . count($pjsipEndpoints) . "\n";
    echo "\n";

    // Usage examples
    if (!empty($sipPeers) || !empty($pjsipEndpoints)) {
        echo "====================================\n";
        echo "Usage Examples for Campaigns\n";
        echo "====================================\n\n";

        if (!empty($sipPeers)) {
            echo "SIP Trunk Examples:\n";
            $example = $sipPeers[0];
            echo "  Trunk Type: sip\n";
            echo "  Trunk Value: $example\n";
            echo "  Channel Format: SIP/{$example}/\${NUMBER}\n";
            echo "\n";
        }

        if (!empty($pjsipEndpoints)) {
            echo "PJSIP Trunk Examples:\n";
            $example = $pjsipEndpoints[0];
            echo "  Trunk Type: pjsip\n";
            echo "  Trunk Value: $example\n";
            echo "  Channel Format: PJSIP/\${NUMBER}@{$example}\n";
            echo "\n";
        }

        echo "Note: Replace \${NUMBER} with the actual phone number when dialing\n";
        echo "\n";
    }

    $scanner->disconnect();

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
