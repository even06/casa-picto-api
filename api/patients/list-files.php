<?php
// api/patients/list-files.php

require_once '../../includes/database.php';
require_once '../../includes/auth.php';

handleCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

// Validate token
$currentUser = validateToken();

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'MISSING_ID',
            'message' => 'Patient ID is required'
        ]
    ]);
    exit();
}

$patientId = intval($_GET['id']);
$conn = getConnection();

try {
    // Check if patient exists
    $stmt = $conn->prepare("SELECT id FROM patients WHERE id = ?");
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception('Patient not found');
    }

    // Get files
    $stmt = $conn->prepare("
        SELECT 
            id,
            file_name,
            file_path,
            file_type,
            description,
            uploaded_at
        FROM patient_files
        WHERE patient_id = ?
        ORDER BY uploaded_at DESC");
    
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $result = $stmt->get_result();

    $files = [];
    while ($row = $result->fetch_assoc()) {
        $files[] = [
            'id' => $row['id'],
            'file_name' => $row['file_name'],
            'file_path' => $row['file_path'],
            'file_type' => $row['file_type'],
            'description' => $row['description'],
            'uploaded_at' => $row['uploaded_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'files' => $files
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'LIST_FILES_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();