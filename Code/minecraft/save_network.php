<?php
header('Content-Type: application/json');

// Get the JSON data from the request
$jsonData = file_get_contents('php://input');

// Validate that we received data
if (empty($jsonData)) {
    http_response_code(400);
    echo json_encode(['error' => 'No data received']);
    exit;
}

// Validate JSON
$data = json_decode($jsonData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Save to network.json
$filename = 'network.json';
$result = file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));

if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

// Success
echo json_encode(['success' => true, 'message' => 'Network saved successfully']);
?>