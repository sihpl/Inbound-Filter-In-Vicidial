#!/usr/bin/php -q
<?php
error_reporting(E_ALL);
set_time_limit(30);
ob_implicit_flush(false);

$log_file = "/tmp/whitelist.log";

// Get CLI argument
$argv = $_SERVER['argv'];
$caller_id_raw = isset($argv[1]) ? $argv[1] : '';
$caller_id = preg_replace('/[^0-9]/', '', $caller_id_raw);

// Reject if CLI is not valid (empty or too short)
if (empty($caller_id) || strlen($caller_id) < 7) {
    echo "EXEC Wait 1\n";
    echo "HANGUP\n";
    exit;
}

// Normalize to both 10-digit and 11-digit formats
$caller_id_10 = $caller_id;
$caller_id_11 = $caller_id;

if (strlen($caller_id) == 11 && substr($caller_id, 0, 1) == "1") {
    $caller_id_10 = substr($caller_id, 1);
} elseif (strlen($caller_id) == 10) {
    $caller_id_11 = "1" . $caller_id;
}

file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] === START 
===\n", FILE_APPEND);
file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] CLI Raw: 
$caller_id_raw | 10-digit: $caller_id_10 | 11-digit: $caller_id_11\n", 
FILE_APPEND);

// DB connection
$conn = mysqli_connect("localhost", "root", "", "asterisk");
if (!$conn) {
    echo "EXEC Wait 1\n";
    echo "HANGUP\n";
    exit;
}

// Date/time
$date = date("Y-m-d");
$time = date("Y-m-d H:i:s");

// Check whitelist match (10 or 11 digit)
$res = mysqli_query($conn, "
    SELECT lead_id FROM vicidial_list 
    WHERE phone_number IN ('$caller_id_10', '$caller_id_11') 
    LIMIT 1
");

if (!$res || mysqli_num_rows($res) == 0) {
    // Not in whitelist - log and play audio
    mysqli_query($conn, "INSERT INTO cli_call_logs_all (caller_id, 
call_date, call_time, call_status) VALUES ('$caller_id', '$date', 
'$time', 'BLOCKED_WHITELIST')");
    file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] BLOCKED 
- Not in whitelist\n", FILE_APPEND);

    echo "ANSWER\n";
    echo "STREAM FILE reveal \"\"\n";
    echo "WAIT FOR DIGIT 1000\n";
    echo "HANGUP\n";
    exit;
}

// Lead found
$row = mysqli_fetch_assoc($res);
$lead_id = $row['lead_id'];

// Limit check (max 5 per day)
$max_calls = 5;
$chk = mysqli_query($conn, "SELECT call_count FROM cli_call_limits WHERE 
caller_id = '$caller_id' AND call_date = '$date'");
$call_limit = 0;
if ($chk && mysqli_num_rows($chk) > 0) {
    $limit_row = mysqli_fetch_assoc($chk);
    $call_limit = (int)$limit_row['call_count'];
}

// Block if limit reached
if ($call_limit >= $max_calls) {
    mysqli_query($conn, "INSERT INTO cli_call_logs_all (caller_id, 
call_date, call_time, call_status, lead_id) VALUES ('$caller_id', 
'$date', '$time', 'BLOCKED_LIMIT', '$lead_id')");
    file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] BLOCKED 
- Limit reached\n", FILE_APPEND);

    echo "ANSWER\n";
    echo "STREAM FILE reveal \"\"\n";
    echo "WAIT FOR DIGIT 1000\n";
    echo "HANGUP\n";
    exit;
}

// Allow call, update count
if ($call_limit == 0) {
    mysqli_query($conn, "INSERT INTO cli_call_limits (caller_id, 
call_date, call_count) VALUES ('$caller_id', '$date', 1)");
} else {
    mysqli_query($conn, "UPDATE cli_call_limits SET call_count = 
call_count + 1 WHERE caller_id = '$caller_id' AND call_date = '$date'");
}

// Log allowed call
mysqli_query($conn, "INSERT INTO cli_call_logs_all (caller_id, 
call_date, call_time, call_status, lead_id) VALUES ('$caller_id', 
'$date', '$time', 'ALLOWED', '$lead_id')");
file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] ALLOWED - 
Lead ID: $lead_id\n", FILE_APPEND);

// Final AGI response
echo "EXEC Wait 1\n";
exit;
?>

