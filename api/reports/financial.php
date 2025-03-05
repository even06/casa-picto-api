<?php
// api/reports/financial.php

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

// Validate token and check if admin
$currentUser = validateToken();
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'FORBIDDEN',
            'message' => 'Only administrators can access financial reports'
        ]
    ]);
    exit();
}

// Get parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$professionalId = isset($_GET['professional_id']) ? intval($_GET['professional_id']) : null;

$conn = getConnection();

try {
    // Build base query parts
    $whereClause = "si.session_date BETWEEN ? AND ?";
    $params = [$startDate, $endDate];
    $types = "ss";

    if ($professionalId) {
        $whereClause .= " AND pp.professional_id = ?";
        $params[] = $professionalId;
        $types .= "i";
    }

    // Get overall summary
    $query = "
        SELECT 
            COUNT(*) as total_sessions,
            SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_sessions,
            SUM(CASE WHEN payment_type = 'INSURANCE' THEN 1 ELSE 0 END) as insurance_sessions,
            SUM(CASE WHEN payment_type IN ('CASH', 'TRANSFER') THEN 1 ELSE 0 END) as direct_payment_sessions,
            SUM(CASE 
                WHEN payment_type = 'CASH' AND payment_status = 'PAID' THEN payment_amount 
                ELSE 0 
            END) as cash_payments,
            SUM(CASE 
                WHEN payment_type = 'TRANSFER' AND payment_status = 'PAID' THEN payment_amount 
                ELSE 0 
            END) as transfer_payments,
            COUNT(DISTINCT pp.patient_id) as total_patients,
            COUNT(DISTINCT pp.professional_id) as total_professionals
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        WHERE $whereClause";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();

    // Get payments by professional
    $query = "
        SELECT 
            pr.id as professional_id,
            pr.name as professional_name,
            pr.specialty,
            COUNT(DISTINCT pp.patient_id) as total_patients,
            COUNT(*) as total_sessions,
            SUM(CASE WHEN si.payment_type = 'INSURANCE' THEN 1 ELSE 0 END) as insurance_sessions,
            SUM(CASE WHEN si.payment_type IN ('CASH', 'TRANSFER') THEN 1 ELSE 0 END) as direct_payment_sessions,
            SUM(CASE 
                WHEN si.payment_type IN ('CASH', 'TRANSFER') 
                AND si.payment_status = 'PAID' THEN si.payment_amount 
                ELSE 0 
            END) as total_collected,
            SUM(CASE 
                WHEN si.payment_type IN ('CASH', 'TRANSFER') 
                AND si.status = 'COMPLETED' 
                AND si.payment_status = 'PENDING' THEN 
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
        FROM professionals pr
        LEFT JOIN patient_professionals pp ON pr.id = pp.professional_id
        LEFT JOIN recurring_schedules rs ON pp.id = rs.patient_professional_id
        LEFT JOIN session_instances si ON rs.id = si.recurring_schedule_id
        WHERE $whereClause
        GROUP BY pr.id, pr.name, pr.specialty
        ORDER BY total_collected DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $professionalSummary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get payments by day
    $query = "
        SELECT 
            DATE(si.session_date) as date,
            COUNT(*) as total_sessions,
            SUM(CASE WHEN si.payment_type = 'INSURANCE' THEN 1 ELSE 0 END) as insurance_sessions,
            SUM(CASE WHEN si.payment_type = 'CASH' THEN 1 ELSE 0 END) as cash_sessions,
            SUM(CASE WHEN si.payment_type = 'TRANSFER' THEN 1 ELSE 0 END) as transfer_sessions,
            SUM(CASE 
                WHEN si.payment_type = 'CASH' 
                AND si.payment_status = 'PAID' THEN si.payment_amount 
                ELSE 0 
            END) as cash_amount,
            SUM(CASE 
                WHEN si.payment_type = 'TRANSFER' 
                AND si.payment_status = 'PAID' THEN si.payment_amount 
                ELSE 0 
            END) as transfer_amount
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        WHERE $whereClause
        GROUP BY DATE(si.session_date)
        ORDER BY date ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $dailyPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get pending payments by insurance company
    $query = "
        SELECT 
            ic.id as insurance_company_id,
            ic.name as insurance_company_name,
            COUNT(DISTINCT pp.patient_id) as total_patients,
            COUNT(*) as total_sessions,
            SUM(CASE WHEN si.status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_sessions
        FROM insurance_companies ic
        JOIN patients p ON ic.id = p.insurance_company_id
        JOIN patient_professionals pp ON p.id = pp.patient_id
        JOIN recurring_schedules rs ON pp.id = rs.patient_professional_id
        JOIN session_instances si ON rs.id = si.recurring_schedule_id
        WHERE $whereClause
        AND si.payment_type = 'INSURANCE'
        GROUP BY ic.id, ic.name
        ORDER BY total_sessions DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $insuranceCompanySummary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get pending payments (non-insurance)
    $query = "
        SELECT 
            p.id as patient_id,
            p.name as patient_name,
            pr.id as professional_id,
            pr.name as professional_name,
            pr.specialty,
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
        WHERE $whereClause
        AND si.status = 'COMPLETED'
        AND si.payment_type IN ('CASH', 'TRANSFER')
        AND si.payment_status = 'PENDING'
        GROUP BY p.id, p.name, pr.id, pr.name, pr.specialty
        ORDER BY pending_amount DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $pendingPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'summary' => [
                'total_sessions' => $summary['total_sessions'],
                'completed_sessions' => $summary['completed_sessions'],
                'insurance_sessions' => $summary['insurance_sessions'],
                'direct_payment_sessions' => $summary['direct_payment_sessions'],
                'total_patients' => $summary['total_patients'],
                'total_professionals' => $summary['total_professionals'],
                'payments' => [
                    'cash' => $summary['cash_payments'],
                    'transfer' => $summary['transfer_payments'],
                    'total' => $summary['cash_payments'] + $summary['transfer_payments']
                ]
            ],
            'professional_summary' => $professionalSummary,
            'daily_payments' => $dailyPayments,
            'insurance_summary' => $insuranceCompanySummary,
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