<?php
/**
 * Server-side error logging endpoint for cost dashboard
 * Accepts JSON error reports from client-side JavaScript
 * Doctrine: DEBUGGING IS A FEATURE
 */

declare(strict_types=1);

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$errorData = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$required = ['timestamp', 'context', 'error'];
foreach ($required as $field) {
    if (!isset($errorData[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: {$field}"]);
        exit;
    }
}

// Log to file
$logDir = __DIR__ . '/../../storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/cost-dashboard-errors.log';
$logEntry = sprintf(
    "[%s] %s: %s | Stack: %s\n",
    $errorData['timestamp'],
    $errorData['context'],
    $errorData['error'],
    $errorData['stack'] ?? 'none'
);

file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Return success
echo json_encode(['status' => 'logged']);
