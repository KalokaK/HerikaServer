#!/usr/bin/env php
<?php

/**
 * Simple test script for network IP detection and database storage
 * Usage: php test_network_detection.php
 */

// Include the necessary files
$enginePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR;
require_once($enginePath . "conf" . DIRECTORY_SEPARATOR . "conf.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "{$GLOBALS["DBDRIVER"]}.class.php");
require_once($enginePath . "lib" . DIRECTORY_SEPARATOR . "network_utils.php");

echo "CHIM Network IP Detection & Database Storage Test\n";
echo str_repeat('=', 50) . "\n";

// Create database connection
$db = new sql();
$GLOBALS["db"] = $db;

echo "Database connection: OK\n\n";

// Test IP detection
echo "Testing IP Detection:\n";
echo str_repeat('-', 30) . "\n";

$wslIP = NetworkUtils::getWSL2IP();
$hostIP = NetworkUtils::getHostIP();

printf("%-15s: %s\n", "WSL2 IP", $wslIP ?: 'NOT DETECTED');
printf("%-15s: %s\n", "Host IP", $hostIP ?: 'NOT DETECTED');

echo "\nStoring IPs in database...\n";

// Store IPs in database
NetworkUtils::detectAndStoreIPs();

echo "\nRetrieving stored IPs from database:\n";
echo str_repeat('-', 35) . "\n";

$storedWSL = NetworkUtils::getStoredWSL2IP();
$storedHost = NetworkUtils::getStoredHostIP();
$timestamp = NetworkUtils::getDetectionTimestamp();

printf("%-20s: %s\n", "Stored WSL2 IP", $storedWSL ?: 'NOT FOUND');
printf("%-20s: %s\n", "Stored Host IP", $storedHost ?: 'NOT FOUND');
printf("%-20s: %s\n", "Detection Time", $timestamp ? date('Y-m-d H:i:s', $timestamp) : 'NOT FOUND');

// Direct database query to verify
echo "\nDirect database verification:\n";
echo str_repeat('-', 30) . "\n";

$results = $db->fetchAll("SELECT id, value FROM conf_opts WHERE id LIKE 'network_%' ORDER BY id");

if (empty($results)) {
    echo "No network entries found in conf_opts table\n";
} else {
    foreach ($results as $row) {
        printf("%-25s: %s\n", $row['id'], $row['value']);
    }
}

// Test bash script equivalent output
echo "\nBash Script Equivalent Output:\n";
echo str_repeat('-', 35) . "\n";
echo "wsl_ip=$wslIP\n";
echo "host_ip=$hostIP\n";
echo "=======================================\n";
echo "Network Information:\n";
echo "Windows Host IP: $hostIP\n";
echo "WSL2 Internal IP: $wslIP\n";
echo "=======================================\n";

// Summary
echo "\nSummary:\n";
echo str_repeat('-', 15) . "\n";

$detectedCount = 0;
if ($wslIP) $detectedCount++;
if ($hostIP) $detectedCount++;

$storedCount = 0;
if ($storedWSL) $storedCount++;
if ($storedHost) $storedCount++;

echo "IPs detected: $detectedCount/2\n";
echo "IPs stored: $storedCount/2\n";

if ($detectedCount === 0) {
    echo "Status: ERROR - No IPs detected\n";
    exit(1);
} elseif ($storedCount !== $detectedCount) {
    echo "Status: WARNING - Storage mismatch\n";
    exit(2);
} else {
    echo "Status: SUCCESS - All working correctly\n";
    exit(0);
}

?> 