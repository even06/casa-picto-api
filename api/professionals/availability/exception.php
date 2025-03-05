<?php
// api/professionals/availability/exception.php

require_once '../../../includes/database.php';
require_once '../../../includes/auth.php';

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

$currentUser = validateToken();

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'MISSING_ID',
            'message' => 'Professional ID is required'
        ]
    ]);
    exit();
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input
if (!isset($data['exception_date']) || empty($data['exception_date'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'INVALID_INPUT',
            'message' => "Exception date is required"
        ]
    ]);
    exit();
}

$professionalId = intval($_GET['id']);
$conn = getConnection();
$conn->begin_transaction();

try {
    // Check if professional exists
    $stmt = $conn->prepare("SELECT id FROM professionals WHERE id = ?");
    $stmt->bind_param("i", $professionalId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception('Professional not found');
    }

    // Check if exception already exists for this date
    $stmt = $conn->prepare("
        SELECT id 
        FROM professional_availability_exceptions 
        WHERE professional_id = ? AND exception_date = ?");
    $stmt->bind_param("is", $professionalId, $data['exception_date']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Exception already exists for this date');
    }

    // Insert exception
    $stmt = $conn->prepare("
        INSERT INTO professional_availability_exceptions 
        (professional_id, exception_date, start_time, end_time, is_available, reason)
        VALUES (?, ?, ?, ?, ?, ?)");
    
    $isAvailable = isset($data['is_available']) ? $data['is_available'] : false;
    $startTime = isset($data['start_time']) ? $data['start_time'] : null;
    $endTime = isset($data['end_time']) ? $data['end_time'] : null;
    $reason = isset($data['reason']) ? $data['reason'] : null;

    $stmt->bind_param("issssi", 
        $professionalId,
        $data['exception_date'],
        $startTime,
        $endTime,
        $reason,
        $isAvailable
    );
    $stmt->execute();

    $newExceptionId = $conn->insert_id;

    // Get the inserted exception
    $stmt = $conn->prepare("
        SELECT 
            id,
            exception_date,
            start_time,
            end_time,
            is_available,
            reason
        FROM professional_availability_exceptions
        WHERE id = ?");
    $stmt->bind_param("i", $newExceptionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exception = $result->fetch_assoc();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $exception['id'],
            'exception_date' => $exception['exception_date'],
            'start_time' => $exception['start_time'],
            'end_time' => $exception['end_time'],
            'is_available' => (bool)$exception['is_available'],
            'reason' => $exception['reason']
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'ADD_EXCEPTION_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();