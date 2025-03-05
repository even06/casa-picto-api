<?php
// api/auth/login.php

require_once '../../includes/database.php';
require_once '../../includes/auth.php';

handleCORS();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'METHOD_NOT_ALLOWED',
            'message' => 'Method not allowed'
        ]
    ]);
    exit();
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['username']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'INVALID_INPUT',
            'message' => 'Username and password are required'
        ]
    ]);
    exit();
}

$conn = getConnection();

// Get user from database
$stmt = $conn->prepare("
    SELECT u.*, p.name, p.id as professional_id 
    FROM users u 
    LEFT JOIN professionals p ON u.id = p.user_id 
    WHERE u.username = ?
");

$stmt->bind_param("s", $data['username']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($data['password'], $user['password'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'INVALID_CREDENTIALS',
            'message' => 'Invalid username or password'
        ]
    ]);
    exit();
}

// Generate token
$token = generateToken($user['id'], $user['role']);

// Remove sensitive data
unset($user['password']);

// Return success response
echo json_encode([
    'success' => true,
    'data' => [
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'name' => $user['name'],
            'professionalId' => $user['professional_id']
        ]
    ]
]);

$conn->close();