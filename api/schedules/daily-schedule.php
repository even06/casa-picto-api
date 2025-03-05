<?php
// api/schedules/daily-schedule.php

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

// Get query parameters
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$professionalId = isset($_GET['professional_id']) ? intval($_GET['professional_id']) : null;

// If user is a professional and no professional_id is specified, use their ID
if ($currentUser['role'] === 'professional' && !$professionalId) {
    $professionalId = $currentUser['professionalId'];
}

$conn = getConnection();

try {
    $dayOfWeek = strtoupper(date('l', strtotime($date))); // Get day name in uppercase

    // Base query
    $query = "
        SELECT 
            si.id as session_id,
            si.session_date,
            si.session_time,
            si.duration,
            si.status,
            si.payment_status,
            si.payment_type,
            rs.id as recurring_schedule_id,
            p.id as patient_id,
            p.name as patient_name,
            p.phone as patient_phone,
            pr.id as professional_id,
            pr.name as professional_name,
            pr.specialty,
            CASE 
                WHEN rs.id IS NOT NULL THEN true 
                ELSE false 
            END as is_recurring
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        LEFT JOIN patients p ON pp.patient_id = p.id
        LEFT JOIN professionals pr ON pp.professional_id = pr.id
        WHERE si.session_date = ?";

    $params = [$date];
    $types = "s";

    // Add professional filter if needed
    if ($professionalId) {
        $query .= " AND pr.id = ?";
        $params[] = $professionalId;
        $types .= "i";
    }

    $query .= " ORDER BY si.session_time, pr.name";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // Get availability exceptions for the day
    $exceptionsQuery = "
        SELECT 
            professional_id,
            is_available,
            start_time,
            end_time,
            reason
        FROM professional_availability_exceptions
        WHERE exception_date = ?";
    
    if ($professionalId) {
        $exceptionsQuery .= " AND professional_id = ?";
    }

    $stmt = $conn->prepare($exceptionsQuery);
    if ($professionalId) {
        $stmt->bind_param("si", $date, $professionalId);
    } else {
        $stmt->bind_param("s", $date);
    }
    $stmt->execute();
    $exceptionsResult = $stmt->get_result();

    $exceptions = [];
    while ($row = $exceptionsResult->fetch_assoc()) {
        $exceptions[] = [
            'professional_id' => $row['professional_id'],
            'is_available' => (bool)$row['is_available'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'reason' => $row['reason']
        ];
    }

    // Get regular availability for the day
    $availabilityQuery = "
        SELECT 
            professional_id,
            start_time,
            end_time
        FROM professional_availability
        WHERE day_of_week = ?
        AND (valid_to IS NULL OR valid_to >= CURRENT_DATE)";

    if ($professionalId) {
        $availabilityQuery .= " AND professional_id = ?";
    }

    $stmt = $conn->prepare($availabilityQuery);
    if ($professionalId) {
        $stmt->bind_param("si", $dayOfWeek, $professionalId);
    } else {
        $stmt->bind_param("s", $dayOfWeek);
    }
    $stmt->execute();
    $availabilityResult = $stmt->get_result();

    $availability = [];
    while ($row = $availabilityResult->fetch_assoc()) {
        $availability[] = [
            'professional_id' => $row['professional_id'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time']
        ];
    }

    // Process sessions into schedule
    $schedule = [];
    while ($row = $result->fetch_assoc()) {
        $session = [
            'id' => $row['session_id'],
            'time' => $row['session_time'],
            'duration' => $row['duration'],
            'status' => $row['status'],
            'payment_status' => $row['payment_status'],
            'payment_type' => $row['payment_type'],
            'is_recurring' => (bool)$row['is_recurring'],
            'patient' => [
                'id' => $row['patient_id'],
                'name' => $row['patient_name'],
                'phone' => $row['patient_phone']
            ],
            'professional' => [
                'id' => $row['professional_id'],
                'name' => $row['professional_name'],
                'specialty' => $row['specialty']
            ]
        ];

        $schedule[] = $session;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'date' => $date,
            'day_of_week' => $dayOfWeek,
            'schedule' => $schedule,
            'exceptions' => $exceptions,
            'availability' => $availability
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'DAILY_SCHEDULE_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();