<?php
// api/patients/attendance-history.php

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
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-6 months'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$conn = getConnection();

try {
    // Get patient details
    $stmt = $conn->prepare("
        SELECT name, is_active
        FROM patients
        WHERE id = ?");
    
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $patientResult = $stmt->get_result();

    if ($patientResult->num_rows === 0) {
        throw new Exception('Patient not found');
    }

    $patient = $patientResult->fetch_assoc();

    // Get overall attendance statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_sessions,
            SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_sessions,
            SUM(CASE WHEN status = 'NO_SHOW' THEN 1 ELSE 0 END) as no_shows,
            SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled_sessions
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        WHERE pp.patient_id = ?
        AND si.session_date BETWEEN ? AND ?");
    
    $stmt->bind_param("iss", $patientId, $startDate, $endDate);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    // Get attendance by professional
    $stmt = $conn->prepare("
        SELECT 
            pr.id as professional_id,
            pr.name as professional_name,
            pr.specialty,
            COUNT(*) as total_sessions,
            SUM(CASE WHEN si.status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_sessions,
            SUM(CASE WHEN si.status = 'NO_SHOW' THEN 1 ELSE 0 END) as no_shows,
            SUM(CASE WHEN si.status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled_sessions
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        LEFT JOIN professionals pr ON pp.professional_id = pr.id
        WHERE pp.patient_id = ?
        AND si.session_date BETWEEN ? AND ?
        GROUP BY pr.id, pr.name, pr.specialty");
    
    $stmt->bind_param("iss", $patientId, $startDate, $endDate);
    $stmt->execute();
    $byProfessional = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get attendance by day of week
    $stmt = $conn->prepare("
        SELECT 
            DAYNAME(si.session_date) as day_of_week,
            COUNT(*) as total_sessions,
            SUM(CASE WHEN si.status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_sessions,
            SUM(CASE WHEN si.status = 'NO_SHOW' THEN 1 ELSE 0 END) as no_shows
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        WHERE pp.patient_id = ?
        AND si.session_date BETWEEN ? AND ?
        GROUP BY DAYNAME(si.session_date)
        ORDER BY DAYOFWEEK(si.session_date)");
    
    $stmt->bind_param("iss", $patientId, $startDate, $endDate);
    $stmt->execute();
    $byDayOfWeek = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get detailed session history
    $stmt = $conn->prepare("
        SELECT 
            si.session_date,
            si.session_time,
            si.status,
            pr.name as professional_name,
            pr.specialty,
            si.recurring_schedule_id IS NOT NULL as is_recurring
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        LEFT JOIN professionals pr ON pp.professional_id = pr.id
        WHERE pp.patient_id = ?
        AND si.session_date BETWEEN ? AND ?
        ORDER BY si.session_date DESC, si.session_time");
    
    $stmt->bind_param("iss", $patientId, $startDate, $endDate);
    $stmt->execute();
    $sessionHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Calculate attendance patterns
    $consecutiveNoShows = 0;
    $maxConsecutiveNoShows = 0;
    $previousStatus = null;
    $noShowDates = [];

    foreach ($sessionHistory as $session) {
        if ($session['status'] === 'NO_SHOW') {
            $consecutiveNoShows++;
            $noShowDates[] = $session['session_date'];
        } else {
            $maxConsecutiveNoShows = max($maxConsecutiveNoShows, $consecutiveNoShows);
            $consecutiveNoShows = 0;
        }
        $previousStatus = $session['status'];
    }

    // Final check for consecutive no-shows
    $maxConsecutiveNoShows = max($maxConsecutiveNoShows, $consecutiveNoShows);

    echo json_encode([
        'success' => true,
        'data' => [
            'patient' => [
                'id' => $patientId,
                'name' => $patient['name'],
                'is_active' => (bool)$patient['is_active']
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'overall_stats' => [
                'total_sessions' => $stats['total_sessions'],
                'completed_sessions' => $stats['completed_sessions'],
                'no_shows' => $stats['no_shows'],
                'cancelled_sessions' => $stats['cancelled_sessions'],
                'attendance_rate' => $stats['total_sessions'] > 0 
                    ? round(($stats['completed_sessions'] * 100) / $stats['total_sessions'], 2) 
                    : 0,
                'no_show_rate' => $stats['total_sessions'] > 0 
                    ? round(($stats['no_shows'] * 100) / $stats['total_sessions'], 2) 
                    : 0
            ],
            'by_professional' => $byProfessional,
            'by_day_of_week' => $byDayOfWeek,
            'patterns' => [
                'max_consecutive_no_shows' => $maxConsecutiveNoShows,
                'no_show_dates' => $noShowDates
            ],
            'session_history' => $sessionHistory
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'ATTENDANCE_HISTORY_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();