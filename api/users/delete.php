<?php
// api/users/delete.php

require_once '../../includes/database.php';
require_once '../../includes/auth.php';

handleCORS();

// Only allow DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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
            'message' => 'Only administrators can delete users'
        ]
    ]);
    exit();
}

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

// Prevent deleting self
if ($userId === $currentUser['id']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'CANNOT_DELETE_SELF',
            'message' => 'Cannot delete your own user account'
        ]
    ]);
    exit();
}

$conn = getConnection();
$conn->begin_transaction();

try {
    // Check if user exists and get their role
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('User not found');
    }
    
    $userRole = $result->fetch_assoc()['role'];

    // Delete professional record if exists
    if ($userRole === 'professional') {
        $stmt = $conn->prepare("DELETE FROM professionals WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }

    // Delete user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('Failed to delete user');
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'message' => 'User deleted successfully'
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'DELETE_USER_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();