<?php
// api/schedules/weekly-view.php

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

// Get parameters
$professionalId = isset($_GET['professional_id']) ? intval($_GET['professional_id']) : null;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// If user is a professional and no professional_id is specified, use their ID
if ($currentUser['role'] === 'professional' && !$professionalId) {
    $professionalId = $currentUser['professionalId'];
}

$conn = getConnection();

try {
    // Calculate week start and end dates
    $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($date)));
    $weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($date)));

    // Base query for sessions
    $baseQuery = "
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
            pr.specialty
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        LEFT JOIN patients p ON pp.patient_id = p.id
        LEFT JOIN professionals pr ON pp.professional_id = pr.id
        WHERE si.session_date BETWEEN ? AND ?";

    $params = [$weekStart, $weekEnd];
    $types = "ss";

    if ($professionalId) {
        $baseQuery .= " AND pp.professional_id = ?";
        $params[] = $professionalId;
        $types .= "i";
    }

    $baseQuery .= " ORDER BY si.session_date, si.session_time, pr.name";

    $stmt = $conn->prepare($baseQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // Process sessions by day
    $weekSchedule = [];
    $currentDate = $weekStart;

    while ($currentDate <= $weekEnd) {
        $weekSchedule[$currentDate] = [
            'date' => $currentDate,
            'day_of_week' => strtoupper(date('l', strtotime($currentDate))),
            'sessions' => []
        ];
        $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
    }

    while ($row = $result->fetch_assoc()) {
        $session = [
            'id' => $row['session_id'],
            'time' => $row['session_time'],
            'duration' => $row['duration'],
            'status' => $row['status'],
            'payment_status' => $row['payment_status'],
            'payment_type' => $row['payment_type'],
            'is_recurring' => !is_null($row['recurring_schedule_id']),
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

        $weekSchedule[$row['session_date']]['sessions'][] = $session;
    }

    // Get availability exceptions for the week
    $stmt = $conn->prepare("
        SELECT 
            exception_date,
            professional_id,
            is_available,
            start_time,
            end_time,
            reason
        FROM professional_availability_exceptions
        WHERE exception_date BETWEEN ? AND ?
        " . ($professionalId ? "AND professional_id = ?" : ""));

    if ($professionalId) {
        $stmt->bind_param("ssi", $weekStart, $weekEnd, $professionalId);
    } else {
        $stmt->bind_param("ss", $weekStart, $weekEnd);
    }
    $stmt->execute();
    $exceptionsResult = $stmt->get_result();

    while ($row = $exceptionsResult->fetch_assoc()) {
        if (isset($weekSchedule[$row['exception_date']])) {
            $weekSchedule[$row['exception_date']]['exceptions'][] = [
                'professional_id' => $row['professional_id'],
                'is_available' => (bool)$row['is_available'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'reason' => $row['reason']
            ];
        }
    }

    // Get regular availability
    $stmt = $conn->prepare("
        SELECT 
            professional_id,
            day_of_week,
            start_time,
            end_time
        FROM professional_availability
        WHERE is_active = 1
        AND (valid_to IS NULL OR valid_to >= CURRENT_DATE)
        " . ($professionalId ? "AND professional_id = ?" : ""));

    if ($professionalId) {
        $stmt->bind_param("i", $professionalId);
    }
    $stmt->execute();
    $availabilityResult = $stmt->get_result();

    $availability = [];
    while ($row = $availabilityResult->fetch_assoc()) {
        if (!isset($availability[$row['day_of_week']])) {
            $availability[$row['day_of_week']] = [];
        }
        $availability[$row['day_of_week']][] = [
            'professional_id' => $row['professional_id'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time']
        ];
    }

    // Add availability to each day
    foreach ($weekSchedule as $date => &$dayData) {
        $dayOfWeek = $dayData['day_of_week'];
        $dayData['availability'] = isset($availability[$dayOfWeek]) ? $availability[$dayOfWeek] : [];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'schedule' => array_values($weekSchedule)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'WEEKLY_VIEW_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();