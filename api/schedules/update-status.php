<?php
// api/schedules/update-status.php

require_once '../../includes/database.php';
require_once '../../includes/auth.php';

handleCORS();

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

// Validate token
$currentUser = validateToken();

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'MISSING_ID',
            'message' => 'Session ID is required'
        ]
    ]);
    exit();
}

$sessionId = intval($_GET['id']);
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$conn = getConnection();
$conn->begin_transaction();

try {
    // Get session details first
    $stmt = $conn->prepare("
        SELECT si.*, rs.patient_professional_id
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        WHERE si.id = ?");
    
    $stmt->bind_param("i", $sessionId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Session not found');
    }

    $session = $result->fetch_assoc();
    $updateFields = [];
    $params = [];
    $types = "";

    // Update attendance status if provided
    if (isset($data['status'])) {
        $allowedStatuses = ['COMPLETED', 'CANCELLED', 'NO_SHOW', 'SCHEDULED'];
        if (!in_array($data['status'], $allowedStatuses)) {
            throw new Exception('Invalid status value');
        }
        $updateFields[] = "status = ?";
        $params[] = $data['status'];
        $types .= "s";
    }

    // Update payment information if provided
    if (isset($data['payment_status'])) {
        $updateFields[] = "payment_status = ?";
        $params[] = $data['payment_status'];
        $types .= "s";

        if ($data['payment_status'] === 'PAID') {
            if (!isset($data['payment_type']) || !isset($data['payment_amount'])) {
                throw new Exception('Payment type and amount required when marking as paid');
            }

            $updateFields[] = "payment_type = ?";
            $updateFields[] = "payment_amount = ?";
            $updateFields[] = "payment_date = CURRENT_TIMESTAMP";
            $params[] = $data['payment_type'];
            $params[] = $data['payment_amount'];
            $types .= "sd";
        }
    }

    if (!empty($updateFields)) {
        $query = "UPDATE session_instances SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $types .= "i";
        $params[] = $sessionId;

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    }

    // Get updated session
    $stmt = $conn->prepare("
        SELECT 
            si.*,
            p.name as patient_name,
            pr.name as professional_name,
            rs.day_of_week
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        LEFT JOIN patients p ON pp.patient_id = p.id
        LEFT JOIN professionals pr ON pp.professional_id = pr.id
        WHERE si.id = ?");
    
    $stmt->bind_param("i", $sessionId);
    $stmt->execute();
    $updatedSession = $stmt->get_result()->fetch_assoc();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $updatedSession['id'],
            'session_date' => $updatedSession['session_date'],
            'session_time' => $updatedSession['session_time'],
            'duration' => $updatedSession['duration'],
            'status' => $updatedSession['status'],
            'payment_type' => $updatedSession['payment_type'],
            'payment_status' => $updatedSession['payment_status'],
            'payment_amount' => $updatedSession['payment_amount'],
            'payment_date' => $updatedSession['payment_date'],
            'is_recurring' => !is_null($updatedSession['recurring_schedule_id']),
            'day_of_week' => $updatedSession['day_of_week'],
            'patient_name' => $updatedSession['patient_name'],
            'professional_name' => $updatedSession['professional_name']
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'UPDATE_SESSION_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();