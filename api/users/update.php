<?php
// api/users/update.php

require_once '../../includes/database.php';
require_once '../../includes/auth.php';

handleCORS();

// Only allow PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
$currentUser = validateToken();
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'FORBIDDEN',
            'message' => 'Only administrators can update users'
        ]
    ]);
    exit();
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate user ID
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'MISSING_ID',
            'message' => 'User ID is required'
        ]
    ]);
    exit();
}

$userId = intval($_GET['id']);

$conn = getConnection();
$conn->begin_transaction();

try {
    // Check if user exists
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('User not found');
    }
    
    $userRole = $result->fetch_assoc()['role'];

    // Update password if provided
    if (isset($data['password']) && !empty($data['password'])) {
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $userId);
        $stmt->execute();
    }

    // Update professional info if applicable
    if ($userRole === 'professional') {
        $updateFields = [];
        $params = [];
        $types = "";

        if (isset($data['name'])) {
            $updateFields[] = "name = ?";
            $params[] = $data['name'];
            $types .= "s";
        }

        if (isset($data['specialty'])) {
            $updateFields[] = "specialty = ?";
            $params[] = $data['specialty'];
            $types .= "s";
        }

        if (isset($data['is_active'])) {
            $updateFields[] = "is_active = ?";
            $params[] = $data['is_active'];
            $types .= "i";
        }

        if (!empty($updateFields)) {
            $query = "UPDATE professionals SET " . implode(", ", $updateFields) . " WHERE user_id = ?";
            $types .= "i";
            $params[] = $userId;
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
        }
    }

    $conn->commit();

    // Get updated user data
    $query = "
        SELECT 
            u.id,
            u.username,
            u.role,
            p.id as professional_id,
            p.name,
            p.specialty,
            p.is_active
        FROM users u
        LEFT JOIN professionals p ON u.id = p.user_id
        WHERE u.id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $response = [
        'id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role']
    ];

    if ($user['role'] === 'professional') {
        $response['professional'] = [
            'id' => $user['professional_id'],
            'name' => $user['name'],
            'specialty' => $user['specialty'],
            'is_active' => (bool)$user['is_active']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $response
    ]);

} catch (Exception $e) {
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'UPDATE_USER_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();