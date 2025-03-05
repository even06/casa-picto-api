<?php
// api/schedules/conflicts.php

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
$duration = isset($_GET['duration']) ? intval($_GET['duration']) : 40; // default session duration

if (!$professionalId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'MISSING_PROFESSIONAL',
            'message' => 'Professional ID is required'
        ]
    ]);
    exit();
}

$conn = getConnection();

try {
    $dayOfWeek = strtoupper(date('l', strtotime($date)));

    // Get professional's regular availability for the day
    $stmt = $conn->prepare("
        SELECT start_time, end_time
        FROM professional_availability
        WHERE professional_id = ?
        AND day_of_week = ?
        AND (valid_to IS NULL OR valid_to >= ?)
        AND is_active = 1
        ORDER BY valid_from DESC
        LIMIT 1");

    $stmt->bind_param("iss", $professionalId, $dayOfWeek, $date);
    $stmt->execute();
    $availabilityResult = $stmt->get_result();
    
    if ($availabilityResult->num_rows === 0) {
        echo json_encode([
            'success' => true,
            'data' => [
                'is_available' => false,
                'reason' => 'Professional does not work on this day',
                'available_slots' => []
            ]
        ]);
        exit();
    }

    $availability = $availabilityResult->fetch_assoc();

    // Check for exceptions
    $stmt = $conn->prepare("
        SELECT is_available, start_time, end_time, reason
        FROM professional_availability_exceptions
        WHERE professional_id = ?
        AND exception_date = ?");

    $stmt->bind_param("is", $professionalId, $date);
    $stmt->execute();
    $exceptionResult = $stmt->get_result();

    if ($exceptionResult->num_rows > 0) {
        $exception = $exceptionResult->fetch_assoc();
        if (!$exception['is_available']) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'is_available' => false,
                    'reason' => $exception['reason'],
                    'available_slots' => []
                ]
            ]);
            exit();
        }
        // If available but with different hours, override regular availability
        if ($exception['start_time'] && $exception['end_time']) {
            $availability['start_time'] = $exception['start_time'];
            $availability['end_time'] = $exception['end_time'];
        }
    }

    // Get existing sessions
    $stmt = $conn->prepare("
        SELECT 
            si.session_time,
            si.duration,
            p.name as patient_name
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        LEFT JOIN patients p ON pp.patient_id = p.id
        WHERE pp.professional_id = ?
        AND si.session_date = ?
        AND si.status != 'CANCELLED'
        ORDER BY si.session_time");

    $stmt->bind_param("is", $professionalId, $date);
    $stmt->execute();
    $existingSessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Generate available time slots
    $availableSlots = [];
    $occupiedRanges = [];

    // Convert existing sessions to occupied time ranges
    foreach ($existingSessions as $session) {
        $startTime = strtotime($session['session_time']);
        $endTime = $startTime + ($session['duration'] * 60);
        $occupiedRanges[] = [
            'start' => $startTime,
            'end' => $endTime,
            'patient' => $session['patient_name']
        ];
    }

    // Sort occupied ranges by start time
    usort($occupiedRanges, function($a, $b) {
        return $a['start'] - $b['start'];
    });

    // Generate available slots
    $currentTime = strtotime($availability['start_time']);
    $endTime = strtotime($availability['end_time']);
    $slotDuration = $duration * 60; // convert to seconds

    while ($currentTime + $slotDuration <= $endTime) {
        $slotEnd = $currentTime + $slotDuration;
        $isAvailable = true;
        $conflictingSession = null;

        // Check for conflicts with existing sessions
        foreach ($occupiedRanges as $range) {
            if ($currentTime < $range['end'] && $slotEnd > $range['start']) {
                $isAvailable = false;
                $conflictingSession = $range;
                break;
            }
        }

        if ($isAvailable) {
            $availableSlots[] = [
                'time' => date('H:i:s', $currentTime),
                'end_time' => date('H:i:s', $slotEnd)
            ];
        }

        $currentTime += 1800; // Move forward by 30 minutes
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'date' => $date,
            'is_available' => true,
            'working_hours' => [
                'start' => $availability['start_time'],
                'end' => $availability['end_time']
            ],
            'existing_sessions' => $existingSessions,
            'available_slots' => $availableSlots
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'CONFLICTS_CHECK_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();