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

file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] === START ===\n", FILE_APPEND);
file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] CLI: $caller_id\n", FILE_APPEND);

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

// Check if CLI is whitelisted
$res = mysqli_query($conn, "SELECT lead_id FROM vicidial_list WHERE phone_number = '$caller_id' LIMIT 1");
if (!$res || mysqli_num_rows($res) == 0) {
    mysqli_query($conn, "INSERT INTO cli_call_logs_all (caller_id, call_date, call_time, call_status) VALUES ('$caller_id', '$date', '$time', 'BLOCKED_WHITELIST')");
    file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] BLOCKED - Not in whitelist\n", FILE_APPEND);
    echo "EXEC Wait 1\n";
    echo "HANGUP\n";
    exit;
}

// Get lead ID
$row = mysqli_fetch_assoc($res);
$lead_id = $row['lead_id'];

// Limit check (max 5 per day)
$max_calls = 5;
$chk = mysqli_query($conn, "SELECT call_count FROM cli_call_limits WHERE caller_id = '$caller_id' AND call_date = '$date'");
$call_limit = 0;
if ($chk && mysqli_num_rows($chk) > 0) {
    $limit_row = mysqli_fetch_assoc($chk);
    $call_limit = (int)$limit_row['call_count'];
}

// If limit reached
if ($call_limit >= $max_calls) {
    mysqli_query($conn, "INSERT INTO cli_call_logs_all (caller_id, call_date, call_time, call_status, lead_id) VALUES ('$caller_id', '$date', '$time', 'BLOCKED_LIMIT', '$lead_id')");
    file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] BLOCKED - Limit reached\n", FILE_APPEND);
    echo "EXEC Wait 1\n";
    echo "HANGUP\n";
    exit;
}

// Allow and update count
if ($call_limit == 0) {
    mysqli_query($conn, "INSERT INTO cli_call_limits (caller_id, call_date, call_count) VALUES ('$caller_id', '$date', 1)");
} else {
    mysqli_query($conn, "UPDATE cli_call_limits SET call_count = call_count + 1 WHERE caller_id = '$caller_id' AND call_date = '$date'");
}

mysqli_query($conn, "INSERT INTO cli_call_logs_all (caller_id, call_date, call_time, call_status, lead_id) VALUES ('$caller_id', '$date', '$time', 'ALLOWED', '$lead_id')");
file_put_contents($log_file, "[" . date("Y-m-d H:i:s") . "] ALLOWED - Lead ID: $lead_id\n", FILE_APPEND);

echo "EXEC Wait 1\n";
exit;
?>
