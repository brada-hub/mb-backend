<?php
$host = getenv('DB_HOST') ?: 'monster-db';
$port = getenv('DB_PORT') ?: 5432;
echo "Testing connection to $host:$port...\n";

$fp = @fsockopen($host, $port, $errno, $errstr, 5);
if ($fp) {
    echo "✅ SUCCESS: Connectivity OK to $host:$port\n";
    fclose($fp);
} else {
    echo "❌ ERROR: Could not connect to $host:$port\n";
    echo "Details: $errstr ($errno)\n";

    // Try resolving IP
    $ip = gethostbyname($host);
    echo "DNS Resolution: $host -> $ip\n";
}
