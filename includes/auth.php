<?php
// includes/auth.php

// Generate a simple token
function generateToken($userId, $role) {
    $payload = [
        'user_id' => $userId,
        'role' => $role,
        'expires' => time() + (24 * 60 * 60) // 24 hours
    ];
    
    return base64_encode(json_encode($payload));
}

// Validate token
function validateToken() {
    $headers = getallheaders();
    
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'No authorization token provided'
            ]
        ]);
        exit();
    }
    
    $authHeader = $headers['Authorization'];
    $token = str_replace('Bearer ', '', $authHeader);
    
    try {
        $payload = json_decode(base64_decode($token), true);
        
        if ($payload['expires'] < time()) {
            throw new Exception('Token expired');
        }
        
        return $payload;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Invalid token'
            ]
        ]);
        exit();
    }
}

// Helper function for CORS
function handleCORS() {
    header("Access-Control-Allow-Origin: *"); // You might want to restrict this to your domain
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit(0);
    }
}

// Automatically call handleCORS() when this file is included
handleCORS();