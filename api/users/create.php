<?php
// api/users/create.php

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

// Validate token and check if admin
$user = validateToken();
if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'FORBIDDEN',
            'message' => 'Only administrators can create users'
        ]
    ]);
    exit();
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
$required_fields = ['username', 'password', 'role', 'name'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => 'INVALID_INPUT',
                'message' => "Field '$field' is required"
            ]
        ]);
        exit();
    }
}

// Validate role
$allowed_roles = ['admin', 'professional'];
if (!in_array($data['role'], $allowed_roles)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'INVALID_ROLE',
            'message' => 'Role must be either admin or professional'
        ]
    ]);
    exit();
}

$conn = getConnection();

// Check if username already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $data['username']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'USERNAME_EXISTS',
            'message' => 'Username already exists'
        ]
    ]);
    $conn->close();
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Insert user
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $data['username'], $hashed_password, $data['role']);
    $stmt->execute();
    $user_id = $conn->insert_id;

    // If role is professional, create professional record
    if ($data['role'] === 'professional') {
        if (!isset($data['specialty'])) {
            throw new Exception('Specialty is required for professionals');
        }

        $stmt = $conn->prepare("INSERT INTO professionals (user_id, name, specialty) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $data['name'], $data['specialty']);
        $stmt->execute();
        $professional_id = $conn->insert_id;
    }

    // Commit transaction
    $conn->commit();

    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'id' => $user_id,
            'username' => $data['username'],
            'role' => $data['role'],
            'name' => $data['name']
        ]
    ];

    if ($data['role'] === 'professional') {
        $response['data']['professional_id'] = $professional_id;
        $response['data']['specialty'] = $data['specialty'];
    }

    echo json_encode($response);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'CREATE_USER_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();