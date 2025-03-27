#!/usr/bin/php -q
<?php
file_put_contents("/tmp/whitelist.log", "AGI called with: " . implode(" ", $argv) . "\n", FILE_APPEND);

$callerid_raw = $argv[1] ?? '';
$callerid = preg_replace('/\D/', '', $callerid_raw); // Remove non-digits
$callerid = substr($callerid, -10); // Keep last 10 digits
$today = date('Y-m-d');
$limit = 5;

$mysqli = new mysqli("localhost", "root", "", "asterisk");
if ($mysqli->connect_error) {
    file_put_contents("/tmp/whitelist.log", "STEP 1: DB connection failed\n", FILE_APPEND);
    echo "EXEC VoiceMail(1001@default,u)\n";
    echo "EXEC Hangup\n";
    exit(1);
}

// STEP 2: Check if caller is in vicidial_list
$res = $mysqli->query("SELECT lead_id FROM vicidial_list WHERE phone_number = '$callerid' LIMIT 1");
if (!$res || !$res->num_rows) {
    file_put_contents("/tmp/whitelist.log", "STEP 2: BLOCKED - Not in whitelist\n", FILE_APPEND);
    echo "EXEC VoiceMail(1001@default,u)\n";
    echo "EXEC Hangup\n";
    exit(1);
}

$row = $res->fetch_assoc();
$lead_id = $row['lead_id'];
file_put_contents("/tmp/whitelist.log", "STEP 2: VALID - Lead ID $lead_id\n", FILE_APPEND);

// STEP 3: Check if daily limit is reached
$res = $mysqli->query("SELECT call_count FROM cli_call_limits WHERE caller_id = '$callerid' AND call_date = '$today'");
if ($res && $row = $res->fetch_assoc()) {
    if ($row['call_count'] >= $limit) {
        file_put_contents("/tmp/whitelist.log", "STEP 3: BLOCKED - Limit reached ({$row['call_count']})\n", FILE_APPEND);
        echo "EXEC VoiceMail(1001@default,u)\n";
        echo "EXEC Hangup\n";
        exit(1);
    } else {
        $mysqli->query("UPDATE cli_call_limits SET call_count = call_count + 1 WHERE caller_id = '$callerid' AND call_date = '$today'");
        file_put_contents("/tmp/whitelist.log", "STEP 3: ALLOWED - Incremented\n", FILE_APPEND);
    }
} else {
    $mysqli->query("INSERT INTO cli_call_limits (caller_id, call_date, call_count) VALUES ('$callerid', '$today', 1)");
    file_put_contents("/tmp/whitelist.log", "STEP 3: ALLOWED - First call today\n", FILE_APPEND);
}

// If all checks passed, allow the call to proceed normally
file_put_contents("/tmp/whitelist.log", "STEP 4: PASSED - Allow call to route\n", FILE_APPEND);
$mysqli->close();
exit(0);
?>
