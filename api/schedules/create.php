<?php
// api/schedules/create.php

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

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
$required_fields = ['patient_id', 'professional_id', 'duration', 'payment_type'];

if ($data['type'] === 'recurring') {
    $required_fields[] = 'day_of_week';
    $required_fields[] = 'session_time';
} else {
    $required_fields[] = 'session_date';
    $required_fields[] = 'session_time';
}

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

$conn = getConnection();
$conn->begin_transaction();

try {
    // First check if patient and professional exist and are active
    $stmt = $conn->prepare("
        SELECT pp.id as patient_professional_id, p.insurance_company_id, pa.start_time, pa.end_time
        FROM patient_professionals pp
        JOIN patients p ON pp.patient_id = p.id
        JOIN professionals pr ON pp.professional_id = pr.id
        LEFT JOIN professional_availability pa ON pr.id = pa.professional_id 
            AND pa.day_of_week = ? 
            AND (pa.valid_to IS NULL OR pa.valid_to >= CURRENT_DATE)
        WHERE pp.patient_id = ? 
        AND pp.professional_id = ?
        AND pp.is_active = 1
        AND pr.is_active = 1");

    $dayOfWeek = $data['type'] === 'recurring' ? 
        $data['day_of_week'] : 
        strtoupper(date('l', strtotime($data['session_date'])));

    $stmt->bind_param("sii", $dayOfWeek, $data['patient_id'], $data['professional_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Invalid patient-professional combination or inactive relationship');
    }

    $row = $result->fetch_assoc();
    $patientProfessionalId = $row['patient_professional_id'];
    
    // Check professional availability
    if (!$row['start_time'] || !$row['end_time']) {
        throw new Exception('Professional is not available on this day');
    }

    $sessionTime = $data['session_time'];
    if ($sessionTime < $row['start_time'] || $sessionTime > $row['end_time']) {
        throw new Exception('Session time is outside professional\'s working hours');
    }

    // Set default payment type based on insurance
    $defaultPaymentType = $row['insurance_company_id'] ? 'INSURANCE' : 'CASH';
    $paymentType = $data['payment_type'] ?? $defaultPaymentType;

    if ($data['type'] === 'recurring') {
        // Create recurring schedule
        $stmt = $conn->prepare("
            INSERT INTO recurring_schedules 
            (patient_professional_id, day_of_week, session_time, duration, payment_type, is_active)
            VALUES (?, ?, ?, ?, ?, 1)");
        
        $stmt->bind_param("issss", 
            $patientProfessionalId,
            $data['day_of_week'],
            $data['session_time'],
            $data['duration'],
            $paymentType
        );
        $stmt->execute();
        $recurringId = $conn->insert_id;

        // Create first instance
        $firstDate = date('Y-m-d', strtotime("next " . $data['day_of_week']));
        $stmt = $conn->prepare("
            INSERT INTO session_instances 
            (recurring_schedule_id, session_date, session_time, duration, payment_type)
            VALUES (?, ?, ?, ?, ?)");
        
        $stmt->bind_param("issis",
            $recurringId,
            $firstDate,
            $data['session_time'],
            $data['duration'],
            $paymentType
        );
        $stmt->execute();
        
    } else {
        // Create one-time session
        $stmt = $conn->prepare("
            INSERT INTO session_instances 
            (session_date, session_time, duration, payment_type)
            VALUES (?, ?, ?, ?)");
        
        $stmt->bind_param("ssis",
            $data['session_date'],
            $data['session_time'],
            $data['duration'],
            $paymentType
        );
        $stmt->execute();
    }

    $sessionId = $conn->insert_id;

    // Get created session details
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
    $session = $stmt->get_result()->fetch_assoc();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $session['id'],
            'session_date' => $session['session_date'],
            'session_time' => $session['session_time'],
            'duration' => $session['duration'],
            'status' => $session['status'],
            'payment_type' => $session['payment_type'],
            'payment_status' => $session['payment_status'],
            'is_recurring' => !is_null($session['recurring_schedule_id']),
            'day_of_week' => $session['day_of_week'],
            'patient_name' => $session['patient_name'],
            'professional_name' => $session['professional_name']
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'CREATE_SESSION_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();