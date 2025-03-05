<?php
// api/insurance-companies/create.php

require_once '../../includes/database.php';
require_once '../../includes/auth.php';

handleCORS();

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
$currentUser = validateToken();
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'FORBIDDEN',
            'message' => 'Only administrators can create insurance companies'
        ]
    ]);
    exit();
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
if (!isset($data['name']) || empty($data['name'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'INVALID_INPUT',
            'message' => 'Name is required'
        ]
    ]);
    exit();
}

$conn = getConnection();

try {
    // Check if name already exists
    $stmt = $conn->prepare("SELECT id FROM insurance_companies WHERE name = ?");
    $stmt->bind_param("s", $data['name']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => 'DUPLICATE_NAME',
                'message' => 'Insurance company with this name already exists'
            ]
        ]);
        exit();
    }

    // Insert insurance company
    $stmt = $conn->prepare("
        INSERT INTO insurance_companies (name, is_active) 
        VALUES (?, 1)
    ");
    
    $stmt->bind_param("s", $data['name']);
    $stmt->execute();
    $companyId = $conn->insert_id;

    // Get the created company
    $stmt = $conn->prepare("
        SELECT id, name, is_active, created_at, updated_at
        FROM insurance_companies
        WHERE id = ?
    ");
    
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $company = $result->fetch_assoc();

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $company['id'],
            'name' => $company['name'],
            'is_active' => (bool)$company['is_active'],
            'created_at' => $company['created_at'],
            'updated_at' => $company['updated_at']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'CREATE_INSURANCE_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();