<?php
// api/professionals/availability/update.php

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
$required_fields = ['day_of_week', 'start_time', 'end_time', 'valid_from'];
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

    // Deactivate current availability for this day if exists
    $updateStmt = $conn->prepare("
        UPDATE professional_availability 
        SET valid_to = CURRENT_DATE
        WHERE professional_id = ? 
        AND day_of_week = ?
        AND valid_to IS NULL");
    $updateStmt->bind_param("is", $professionalId, $data['day_of_week']);
    $updateStmt->execute();

    // Insert new availability
    $stmt = $conn->prepare("
        INSERT INTO professional_availability 
        (professional_id, day_of_week, start_time, end_time, valid_from, valid_to, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)");
    
    $validTo = isset($data['valid_to']) ? $data['valid_to'] : null;
    $stmt->bind_param("isssss", 
        $professionalId,
        $data['day_of_week'],
        $data['start_time'],
        $data['end_time'],
        $data['valid_from'],
        $validTo
    );
    $stmt->execute();

    $newAvailabilityId = $conn->insert_id;

    // Get the inserted availability
    $stmt = $conn->prepare("
        SELECT 
            id,
            day_of_week,
            start_time,
            end_time,
            valid_from,
            valid_to,
            is_active
        FROM professional_availability
        WHERE id = ?");
    $stmt->bind_param("i", $newAvailabilityId);
    $stmt->execute();
    $result = $stmt->get_result();
    $availability = $result->fetch_assoc();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $availability['id'],
            'day_of_week' => $availability['day_of_week'],
            'start_time' => $availability['start_time'],
            'end_time' => $availability['end_time'],
            'valid_from' => $availability['valid_from'],
            'valid_to' => $availability['valid_to'],
            'is_active' => (bool)$availability['is_active']
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'UPDATE_AVAILABILITY_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();