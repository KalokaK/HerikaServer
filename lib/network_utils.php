<?php

/**
 * Simple Network IP Detection
 * Detects WSL2 and Host IPs on startup and stores them in the database
 */

class NetworkUtils {
    
    /**
     * Get the WSL2 internal IP address
     * @return string|null The WSL2 IP address or null if not found
     */
    public static function getWSL2IP() {
        try {
            // Method 1: Try using WSL command (if running on Windows)
            if (PHP_OS === 'WINNT') {
                $output = shell_exec('wsl -d DwemerAI4Skyrim3 hostname -I 2>nul');
                if ($output) {
                    $ips = explode(' ', trim($output));
                    $wslIP = trim($ips[0]);
                    if (filter_var($wslIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        return $wslIP;
                    }
                }
            }
            
            // Method 2: If running inside WSL, get our IP
            if (file_exists('/etc/resolv.conf')) {
                $output = shell_exec('hostname -I 2>/dev/null');
                if ($output) {
                    $ips = explode(' ', trim($output));
                    $wslIP = trim($ips[0]);
                    if (filter_var($wslIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        return $wslIP;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("WSL2 IP detection failed: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Get the Windows host IP address
     * @return string|null The host IP address or null if not found
     */
    public static function getHostIP() {
        try {
            // Method 1: From WSL, get the nameserver (which is the Windows host)
            if (file_exists('/etc/resolv.conf')) {
                $resolv = file_get_contents('/etc/resolv.conf');
                if (preg_match('/nameserver\s+(\d+\.\d+\.\d+\.\d+)/', $resolv, $matches)) {
                    return $matches[1];
                }
            }
            
            // Method 2: If running on Windows directly
            if (PHP_OS === 'WINNT') {
                $output = shell_exec('ipconfig | findstr /R /C:"IPv4.*:" | findstr /V "127.0.0.1" | findstr /V "169.254"');
                if ($output && preg_match('/IPv4.*?:\s*(\d+\.\d+\.\d+\.\d+)/', $output, $matches)) {
                    return $matches[1];
                }
            }
        } catch (Exception $e) {
            error_log("Host IP detection failed: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Detect and store network IPs in the database on startup
     */
    public static function detectAndStoreIPs() {
        // Make sure we have database access
        if (!isset($GLOBALS['db'])) {
            error_log("Network Detection: Database not available");
            return;
        }
        
        $db = $GLOBALS['db'];
        
        // Detect IPs
        $wslIP = self::getWSL2IP();
        $hostIP = self::getHostIP();
        
        // Log detected IPs
        error_log("Network Detection - WSL2 IP: " . ($wslIP ?: 'N/A') . ", Host IP: " . ($hostIP ?: 'N/A'));
        
        try {
            // Store WSL2 IP in database
            if ($wslIP) {
                $db->upsertRowOnConflict('conf_opts', array(
                    'id' => 'network_wsl2_ip', 
                    'value' => $wslIP
                ), 'id');
                error_log("Network: Stored WSL2 IP in database: $wslIP");
            } else {
                // Remove WSL2 IP if not detected
                $db->delete("conf_opts", "id='network_wsl2_ip'");
                error_log("Network: WSL2 IP not detected, removed from database");
            }
            
            // Store Host IP in database
            if ($hostIP) {
                $db->upsertRowOnConflict('conf_opts', array(
                    'id' => 'network_host_ip', 
                    'value' => $hostIP
                ), 'id');
                error_log("Network: Stored Host IP in database: $hostIP");
            } else {
                // Remove Host IP if not detected
                $db->delete("conf_opts", "id='network_host_ip'");
                error_log("Network: Host IP not detected, removed from database");
            }
            
            // Store detection timestamp
            $db->upsertRowOnConflict('conf_opts', array(
                'id' => 'network_detection_timestamp', 
                'value' => time()
            ), 'id');
            
        } catch (Exception $e) {
            error_log("Network Detection: Database error - " . $e->getMessage());
        }
    }
    
    /**
     * Get stored WSL2 IP from database
     * @return string|null The WSL2 IP or null if not found
     */
    public static function getStoredWSL2IP() {
        if (!isset($GLOBALS['db'])) {
            return null;
        }
        
        $result = $GLOBALS['db']->fetchOne("SELECT value FROM conf_opts WHERE id='network_wsl2_ip'");
        return $result ?: null;
    }
    
    /**
     * Get stored Host IP from database
     * @return string|null The Host IP or null if not found
     */
    public static function getStoredHostIP() {
        if (!isset($GLOBALS['db'])) {
            return null;
        }
        
        $result = $GLOBALS['db']->fetchOne("SELECT value FROM conf_opts WHERE id='network_host_ip'");
        return $result ?: null;
    }
    
    /**
     * Get detection timestamp from database
     * @return int|null The timestamp or null if not found
     */
    public static function getDetectionTimestamp() {
        if (!isset($GLOBALS['db'])) {
            return null;
        }
        
        $result = $GLOBALS['db']->fetchOne("SELECT value FROM conf_opts WHERE id='network_detection_timestamp'");
        return $result ? (int)$result : null;
    }
}

?> 