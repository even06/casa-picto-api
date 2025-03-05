<?php
// api/schedules/delete.php

require_once '../../includes/database.php';
require_once '../../includes/auth.php';

handleCORS();

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
$deleteType = $_GET['type'] ?? 'single'; // 'single' or 'recurring'
$fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : null;

$conn = getConnection();
$conn->begin_transaction();

try {
    // Get session details first
    $stmt = $conn->prepare("
        SELECT si.*, rs.id as recurring_schedule_id
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

    if ($deleteType === 'recurring' && !$session['recurring_schedule_id']) {
        throw new Exception('Cannot delete as recurring - this is not a recurring session');
    }

    if ($deleteType === 'recurring') {
        if ($fromDate) {
            // Delete all future instances of this recurring schedule
            $stmt = $conn->prepare("
                DELETE FROM session_instances 
                WHERE recurring_schedule_id = ? 
                AND session_date >= ?");
            $stmt->bind_param("is", $session['recurring_schedule_id'], $fromDate);
            $stmt->execute();

            // Update the recurring schedule's active status if deleting all future sessions
            $stmt = $conn->prepare("
                UPDATE recurring_schedules 
                SET is_active = 0 
                WHERE id = ?");
            $stmt->bind_param("i", $session['recurring_schedule_id']);
            $stmt->execute();
        } else {
            // Delete all instances and the recurring schedule
            $stmt = $conn->prepare("
                DELETE FROM session_instances 
                WHERE recurring_schedule_id = ?");
            $stmt->bind_param("i", $session['recurring_schedule_id']);
            $stmt->execute();

            $stmt = $conn->prepare("
                DELETE FROM recurring_schedules 
                WHERE id = ?");
            $stmt->bind_param("i", $session['recurring_schedule_id']);
            $stmt->execute();
        }
    } else {
        // Delete single session
        $stmt = $conn->prepare("
            DELETE FROM session_instances 
            WHERE id = ?");
        $stmt->bind_param("i", $sessionId);
        $stmt->execute();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'message' => $deleteType === 'recurring' 
                ? ($fromDate 
                    ? 'All future recurring sessions deleted' 
                    : 'All recurring sessions deleted')
                : 'Session deleted'
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'DELETE_SESSION_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();