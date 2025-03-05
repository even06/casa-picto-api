<?php
// api/reports/professional-summary.php

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
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month

// If user is a professional and no professional_id is specified, use their ID
if ($currentUser['role'] === 'professional' && !$professionalId) {
    $professionalId = $currentUser['professionalId'];
}

// If user is a professional, they can only view their own summary
if ($currentUser['role'] === 'professional' && $professionalId !== $currentUser['professionalId']) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'FORBIDDEN',
            'message' => 'You can only view your own summary'
        ]
    ]);
    exit();
}

$conn = getConnection();

try {
    // Get professional details first
    $stmt = $conn->prepare("
        SELECT name, specialty
        FROM professionals
        WHERE id = ?");
    
    $stmt->bind_param("i", $professionalId);
    $stmt->execute();
    $professional = $stmt->get_result()->fetch_assoc();

    // Get sessions summary
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_sessions,
            SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_sessions,
            SUM(CASE WHEN status = 'NO_SHOW' THEN 1 ELSE 0 END) as no_shows,
            SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) as cancelled_sessions,
            SUM(CASE WHEN payment_type = 'CASH' AND payment_status = 'PAID' THEN payment_amount ELSE 0 END) as cash_payments,
            SUM(CASE WHEN payment_type = 'TRANSFER' AND payment_status = 'PAID' THEN payment_amount ELSE 0 END) as transfer_payments,
            COUNT(DISTINCT CASE WHEN rs.id IS NOT NULL THEN pp.patient_id END) as recurring_patients,
            COUNT(DISTINCT pp.patient_id) as total_patients
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        WHERE pp.professional_id = ?
        AND si.session_date BETWEEN ? AND ?");
    
    $stmt->bind_param("iss", $professionalId, $startDate, $endDate);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();

    // Get attendance by day of week
    $stmt = $conn->prepare("
        SELECT 
            DAYNAME(session_date) as day_name,
            COUNT(*) as total_sessions,
            SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_sessions,
            SUM(CASE WHEN status = 'NO_SHOW' THEN 1 ELSE 0 END) as no_shows
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        WHERE pp.professional_id = ?
        AND si.session_date BETWEEN ? AND ?
        GROUP BY DAYNAME(session_date)
        ORDER BY DAYOFWEEK(session_date)");
    
    $stmt->bind_param("iss", $professionalId, $startDate, $endDate);
    $stmt->execute();
    $attendanceByDay = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get patients with most no-shows
    $stmt = $conn->prepare("
        SELECT 
            p.id as patient_id,
            p.name as patient_name,
            COUNT(*) as total_sessions,
            SUM(CASE WHEN si.status = 'NO_SHOW' THEN 1 ELSE 0 END) as no_shows,
            (SUM(CASE WHEN si.status = 'NO_SHOW' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) as no_show_rate
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        LEFT JOIN patients p ON pp.patient_id = p.id
        WHERE pp.professional_id = ?
        AND si.session_date BETWEEN ? AND ?
        GROUP BY p.id, p.name
        HAVING no_shows > 0
        ORDER BY no_show_rate DESC
        LIMIT 5");
    
    $stmt->bind_param("iss", $professionalId, $startDate, $endDate);
    $stmt->execute();
    $noShowPatients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get pending payments
    $stmt = $conn->prepare("
        SELECT 
            p.id as patient_id,
            p.name as patient_name,
            COUNT(*) as pending_sessions,
            SUM(CASE 
                WHEN si.payment_type IN ('CASH', 'TRANSFER') THEN 
                    COALESCE(si.payment_amount, 
                        (SELECT price 
                        FROM therapy_pricing 
                        WHERE specialty = pr.specialty 
                        AND valid_from <= si.session_date 
                        AND (valid_to IS NULL OR valid_to >= si.session_date)
                        ORDER BY valid_from DESC 
                        LIMIT 1)
                    )
                ELSE 0 
            END) as pending_amount
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        LEFT JOIN patients p ON pp.patient_id = p.id
        LEFT JOIN professionals pr ON pp.professional_id = pr.id
        WHERE pp.professional_id = ?
        AND si.session_date BETWEEN ? AND ?
        AND si.status = 'COMPLETED'
        AND si.payment_type IN ('CASH', 'TRANSFER')
        AND si.payment_status = 'PENDING'
        GROUP BY p.id, p.name
        ORDER BY pending_amount DESC");
    
    $stmt->bind_param("iss", $professionalId, $startDate, $endDate);
    $stmt->execute();
    $pendingPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Calculate rates
    $attendanceRate = $summary['total_sessions'] > 0 
        ? ($summary['completed_sessions'] * 100 / $summary['total_sessions']) 
        : 0;
    
    $noShowRate = $summary['total_sessions'] > 0 
        ? ($summary['no_shows'] * 100 / $summary['total_sessions']) 
        : 0;

    echo json_encode([
        'success' => true,
        'data' => [
            'professional' => $professional,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'summary' => [
                'total_sessions' => $summary['total_sessions'],
                'completed_sessions' => $summary['completed_sessions'],
                'no_shows' => $summary['no_shows'],
                'cancelled_sessions' => $summary['cancelled_sessions'],
                'attendance_rate' => round($attendanceRate, 2),
                'no_show_rate' => round($noShowRate, 2),
                'total_patients' => $summary['total_patients'],
                'recurring_patients' => $summary['recurring_patients']
            ],
            'payments' => [
                'cash_total' => $summary['cash_payments'],
                'transfer_total' => $summary['transfer_payments'],
                'total_collected' => $summary['cash_payments'] + $summary['transfer_payments']
            ],
            'attendance_by_day' => $attendanceByDay,
            'no_show_patients' => $noShowPatients,
            'pending_payments' => $pendingPayments
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'REPORT_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();