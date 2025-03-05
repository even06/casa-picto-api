<?php
// includes/database.php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Speokerbo5124$%');
define('DB_NAME', 'casa_picto_v2');

function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        returnError('DATABASE_ERROR', 'Connection failed: ' . $conn->connect_error);
        exit();
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Helper function to return errors
function returnError($code, $message) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => $code,
            'message' => $message
        ]
    ]);
    exit();
}