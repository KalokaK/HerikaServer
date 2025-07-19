<?php

/**
 * Simple Network IP Detection and Replacement
 * Detects WSL2 and Host IPs on startup and replaces localhost addresses automatically
 * Only affects services that run on the Windows host (not WSL2 services)
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
     * Replace localhost/127.0.0.1 addresses in a URL with the host IP
     * @param string $url The original URL
     * @param string $hostIP The host IP to use for replacement
     * @return string The URL with replaced IP
     */
    public static function replaceLocalhostInURL($url, $hostIP) {
        if (empty($url) || empty($hostIP)) {
            return $url;
        }
        
        // Replace localhost and 127.0.0.1 with host IP
        return preg_replace('/(?:localhost|127\.0\.0\.1)/', $hostIP, $url);
    }
    
    /**
     * Apply IP replacement to specific connector configurations on startup
     * Only affects services that run on the Windows host, not WSL2 services
     */
    public static function replaceLocalhostOnStartup() {
        // Detect IPs
        $wslIP = self::getWSL2IP();
        $hostIP = self::getHostIP();
        
        // Log detected IPs
        error_log("Network Detection - WSL2 IP: " . ($wslIP ?: 'N/A') . ", Host IP: " . ($hostIP ?: 'N/A'));
        
        // Use host IP for Windows-based services
        if (!$hostIP) {
            error_log("Network Detection: No host IP found for Windows services replacement");
            return;
        }
        
        $replacementCount = 0;
        
        // Define which services run on Windows host (not WSL2)
        $windowsHostServices = [
            'CONNECTOR' => ['player2json', 'player2local', 'player2'],  // Player2 runs on Windows
            'TTS' => ['XVASYNTH'],  // xVASynth runs on Windows
            'STT' => [],  // Add any STT services that run on Windows here
            'ITT' => []   // Add any ITT services that run on Windows here
        ];
        
        // Replace in specific CONNECTOR configurations
        if (isset($GLOBALS['CONNECTOR']) && is_array($GLOBALS['CONNECTOR'])) {
            foreach ($windowsHostServices['CONNECTOR'] as $connectorName) {
                if (isset($GLOBALS['CONNECTOR'][$connectorName]) && is_array($GLOBALS['CONNECTOR'][$connectorName])) {
                    $connectorConfig = &$GLOBALS['CONNECTOR'][$connectorName];
                    $urlFields = ['url', 'endpoint', 'URL', 'HOST'];
                    
                    foreach ($urlFields as $field) {
                        if (isset($connectorConfig[$field])) {
                            $originalURL = $connectorConfig[$field];
                            $newURL = self::replaceLocalhostInURL($originalURL, $hostIP);
                            if ($newURL !== $originalURL) {
                                $connectorConfig[$field] = $newURL;
                                error_log("Network: Replaced $connectorName.$field: $originalURL -> $newURL");
                                $replacementCount++;
                            }
                        }
                    }
                }
            }
        }
        
        // Replace in specific TTS configurations
        if (isset($GLOBALS['TTS']) && is_array($GLOBALS['TTS'])) {
            foreach ($windowsHostServices['TTS'] as $ttsName) {
                if (isset($GLOBALS['TTS'][$ttsName]) && is_array($GLOBALS['TTS'][$ttsName])) {
                    $ttsConfig = &$GLOBALS['TTS'][$ttsName];
                    $urlFields = ['url', 'endpoint', 'URL'];
                    
                    foreach ($urlFields as $field) {
                        if (isset($ttsConfig[$field])) {
                            $originalURL = $ttsConfig[$field];
                            $newURL = self::replaceLocalhostInURL($originalURL, $hostIP);
                            if ($newURL !== $originalURL) {
                                $ttsConfig[$field] = $newURL;
                                error_log("Network: Replaced TTS.$ttsName.$field: $originalURL -> $newURL");
                                $replacementCount++;
                            }
                        }
                    }
                }
            }
        }
        
        // Replace in specific STT configurations (if any Windows-based ones are added)
        if (isset($GLOBALS['STT']) && is_array($GLOBALS['STT'])) {
            foreach ($windowsHostServices['STT'] as $sttName) {
                if (isset($GLOBALS['STT'][$sttName]) && is_array($GLOBALS['STT'][$sttName])) {
                    $sttConfig = &$GLOBALS['STT'][$sttName];
                    $urlFields = ['url', 'endpoint', 'URL'];
                    
                    foreach ($urlFields as $field) {
                        if (isset($sttConfig[$field])) {
                            $originalURL = $sttConfig[$field];
                            $newURL = self::replaceLocalhostInURL($originalURL, $hostIP);
                            if ($newURL !== $originalURL) {
                                $sttConfig[$field] = $newURL;
                                error_log("Network: Replaced STT.$sttName.$field: $originalURL -> $newURL");
                                $replacementCount++;
                            }
                        }
                    }
                }
            }
        }
        
        if ($replacementCount > 0) {
            error_log("Network: Completed $replacementCount Windows host service replacements using IP: $hostIP");
            error_log("Network: WSL2 services (KoboldCPP, MeloTTS, etc.) remain on localhost as intended");
        } else {
            error_log("Network: No Windows host services found to replace");
        }
    }
}

?> 