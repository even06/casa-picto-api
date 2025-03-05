<?php
// api/patients/upload-file.php

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

// Check if file was uploaded
if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'NO_FILE',
            'message' => 'No file was uploaded'
        ]
    ]);
    exit();
}

$file = $_FILES['file'];
$fileName = basename($file['name']);
$fileType = $file['type'];
$description = isset($_POST['description']) ? $_POST['description'] : null;

// Create upload directory if it doesn't exist
$uploadDir = "../../uploads/patients/$patientId";
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate unique filename
$extension = pathinfo($fileName, PATHINFO_EXTENSION);
$uniqueName = uniqid() . '.' . $extension;
$filePath = "$uploadDir/$uniqueName";

$conn = getConnection();
$conn->begin_transaction();

try {
    // Check if patient exists
    $stmt = $conn->prepare("SELECT id FROM patients WHERE id = ?");
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception('Patient not found');
    }

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to save file');
    }

    // Save file record in database
    $stmt = $conn->prepare("
        INSERT INTO patient_files 
        (patient_id, file_name, file_path, file_type, description) 
        VALUES (?, ?, ?, ?, ?)
    ");

    $relativePath = "uploads/patients/$patientId/$uniqueName";
    $stmt->bind_param("issss", $patientId, $fileName, $relativePath, $fileType, $description);
    $stmt->execute();
    $fileId = $conn->insert_id;

    $conn->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $fileId,
            'file_name' => $fileName,
            'file_path' => $relativePath,
            'file_type' => $fileType,
            'description' => $description,
            'uploaded_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    
    // Remove uploaded file if it exists
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'UPLOAD_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();