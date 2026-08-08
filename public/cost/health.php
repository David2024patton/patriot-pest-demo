<?php
/**
 * Health check endpoint for cost dashboard
 * Returns JSON status for monitoring and debugging
 * Doctrine: DEBUGGING IS A FEATURE
 */

declare(strict_types=1);

header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'service' => 'cost-dashboard',
    'timestamp' => date('c'),
    'checks' => []
];

// Check if data file exists and is readable
$dataFile = __DIR__ . '/data/pricing.json';
if (file_exists($dataFile) && is_readable($dataFile)) {
    $health['checks']['data_file'] = [
        'status' => 'ok',
        'path' => $dataFile,
        'size' => filesize($dataFile) . ' bytes'
    ];
    
    // Validate JSON structure
    $jsonContent = file_get_contents($dataFile);
    $jsonData = json_decode($jsonContent, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        $health['checks']['data_json'] = [
            'status' => 'ok',
            'agency_range' => $jsonData['agency_range'] ?? null,
            'breakdown_count' => count($jsonData['breakdown'] ?? [])
        ];
    } else {
        $health['checks']['data_json'] = [
            'status' => 'error',
            'error' => json_last_error_msg()
        ];
        $health['status'] = 'degraded';
    }
} else {
    $health['checks']['data_file'] = [
        'status' => 'error',
        'error' => 'File not found or not readable'
    ];
    $health['status'] = 'error';
}

// Check CSS assets
$cssFile = __DIR__ . '/assets/css/cost.css';
if (file_exists($cssFile) && is_readable($cssFile)) {
    $health['checks']['css_asset'] = [
        'status' => 'ok',
        'path' => $cssFile,
        'size' => filesize($cssFile) . ' bytes'
    ];
} else {
    $health['checks']['css_asset'] = [
        'status' => 'error',
        'error' => 'CSS file not found or not readable'
    ];
    $health['status'] = 'degraded';
}

// Check JS assets
$jsFile = __DIR__ . '/assets/js/cost.js';
if (file_exists($jsFile) && is_readable($jsFile)) {
    $health['checks']['js_asset'] = [
        'status' => 'ok',
        'path' => $jsFile,
        'size' => filesize($jsFile) . ' bytes'
    ];
} else {
    $health['checks']['js_asset'] = [
        'status' => 'error',
        'error' => 'JS file not found or not readable'
    ];
    $health['status'] = 'degraded';
}

// Check if feature toggle is enabled (will be added to .env)
$featureEnabled = getenv('COST_DASHBOARD_ENABLED');
$health['checks']['feature_toggle'] = [
    'status' => $featureEnabled === 'false' ? 'disabled' : 'enabled',
    'value' => $featureEnabled ?? 'not_set'
];

// Return appropriate HTTP status code
$httpStatus = match($health['status']) {
    'ok' => 200,
    'degraded' => 200, // Still serving, but with issues
    'error' => 503,
    default => 200
};

http_response_code($httpStatus);
echo json_encode($health, JSON_PRETTY_PRINT);
