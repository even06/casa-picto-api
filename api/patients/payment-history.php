<?php
// api/patients/payment-history.php

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
    // Get patient details with insurance info
    $stmt = $conn->prepare("
        SELECT p.*, ic.name as insurance_company_name
        FROM patients p
        LEFT JOIN insurance_companies ic ON p.insurance_company_id = ic.id
        WHERE p.id = ?");
    
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $patientResult = $stmt->get_result();

    if ($patientResult->num_rows === 0) {
        throw new Exception('Patient not found');
    }

    $patient = $patientResult->fetch_assoc();

    // Get payment summary
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_sessions,
            SUM(CASE WHEN payment_type = 'INSURANCE' THEN 1 ELSE 0 END) as insurance_sessions,
            SUM(CASE WHEN payment_type IN ('CASH', 'TRANSFER') THEN 1 ELSE 0 END) as direct_payment_sessions,
            SUM(CASE 
                WHEN payment_type IN ('CASH', 'TRANSFER') 
                AND payment_status = 'PAID' THEN payment_amount 
                ELSE 0 
            END) as total_paid_amount,
            SUM(CASE 
                WHEN payment_type IN ('CASH', 'TRANSFER') 
                AND payment_status = 'PENDING' 
                AND status = 'COMPLETED' THEN 
                    COALESCE(payment_amount, 
                        (SELECT price 
                        FROM therapy_pricing 
                        WHERE specialty = pr.specialty 
                        AND valid_from <= si.session_date 
                        AND (valid_to IS NULL OR valid_to >= si.session_date)
                        ORDER BY valid_from DESC 
                        LIMIT 1)
                    )
                ELSE 0 
            END) as total_pending_amount
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        LEFT JOIN professionals pr ON pp.professional_id = pr.id
        WHERE pp.patient_id = ?
        AND si.session_date BETWEEN ? AND ?");
    
    $stmt->bind_param("iss", $patientId, $startDate, $endDate);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();

    // Get payment history by professional
    $stmt = $conn->prepare("
        SELECT 
            pr.id as professional_id,
            pr.name as professional_name,
            pr.specialty,
            COUNT(*) as total_sessions,
            SUM(CASE WHEN payment_type = 'INSURANCE' THEN 1 ELSE 0 END) as insurance_sessions,
            SUM(CASE WHEN payment_type IN ('CASH', 'TRANSFER') THEN 1 ELSE 0 END) as direct_payment_sessions,
            SUM(CASE 
                WHEN payment_type IN ('CASH', 'TRANSFER') 
                AND payment_status = 'PAID' THEN payment_amount 
                ELSE 0 
            END) as total_paid,
            SUM(CASE 
                WHEN payment_type IN ('CASH', 'TRANSFER') 
                AND payment_status = 'PENDING' 
                AND status = 'COMPLETED' THEN 
                    COALESCE(payment_amount, 
                        (SELECT price 
                        FROM therapy_pricing 
                        WHERE specialty = pr.specialty 
                        AND valid_from <= si.session_date 
                        AND (valid_to IS NULL OR valid_to >= si.session_date)
                        ORDER BY valid_from DESC 
                        LIMIT 1)
                    )
                ELSE 0 
            END) as total_pending
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

    // Get detailed payment history
    $stmt = $conn->prepare("
        SELECT 
            si.id as session_id,
            si.session_date,
            si.session_time,
            pr.name as professional_name,
            pr.specialty,
            si.status,
            si.payment_type,
            si.payment_status,
            si.payment_amount,
            si.payment_date,
            COALESCE(si.payment_amount, 
                (SELECT price 
                FROM therapy_pricing 
                WHERE specialty = pr.specialty 
                AND valid_from <= si.session_date 
                AND (valid_to IS NULL OR valid_to >= si.session_date)
                ORDER BY valid_from DESC 
                LIMIT 1)
            ) as expected_amount
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        LEFT JOIN professionals pr ON pp.professional_id = pr.id
        WHERE pp.patient_id = ?
        AND si.session_date BETWEEN ? AND ?
        ORDER BY si.session_date DESC, si.session_time");
    
    $stmt->bind_param("iss", $patientId, $startDate, $endDate);
    $stmt->execute();
    $paymentHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get pending payments details
    $stmt = $conn->prepare("
        SELECT 
            si.id as session_id,
            si.session_date,
            si.session_time,
            pr.name as professional_name,
            pr.specialty,
            COALESCE(si.payment_amount, 
                (SELECT price 
                FROM therapy_pricing 
                WHERE specialty = pr.specialty 
                AND valid_from <= si.session_date 
                AND (valid_to IS NULL OR valid_to >= si.session_date)
                ORDER BY valid_from DESC 
                LIMIT 1)
            ) as pending_amount
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        LEFT JOIN professionals pr ON pp.professional_id = pr.id
        WHERE pp.patient_id = ?
        AND si.session_date BETWEEN ? AND ?
        AND si.status = 'COMPLETED'
        AND si.payment_type IN ('CASH', 'TRANSFER')
        AND si.payment_status = 'PENDING'
        ORDER BY si.session_date");
    
    $stmt->bind_param("iss", $patientId, $startDate, $endDate);
    $stmt->execute();
    $pendingPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get payment patterns
    $monthlyPayments = [];
    foreach ($paymentHistory as $payment) {
        $month = date('Y-m', strtotime($payment['session_date']));
        if (!isset($monthlyPayments[$month])) {
            $monthlyPayments[$month] = [
                'month' => $month,
                'total_sessions' => 0,
                'paid_sessions' => 0,
                'total_amount' => 0
            ];
        }
        $monthlyPayments[$month]['total_sessions']++;
        if ($payment['payment_status'] === 'PAID') {
            $monthlyPayments[$month]['paid_sessions']++;
            $monthlyPayments[$month]['total_amount'] += $payment['payment_amount'];
        }
    }
    
    $monthlyPayments = array_values($monthlyPayments);

    echo json_encode([
        'success' => true,
        'data' => [
            'patient' => [
                'id' => $patientId,
                'name' => $patient['name'],
                'insurance_company' => $patient['insurance_company_id'] ? [
                    'id' => $patient['insurance_company_id'],
                    'name' => $patient['insurance_company_name']
                ] : null,
                'insurance_number' => $patient['insurance_number']
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'summary' => [
                'total_sessions' => $summary['total_sessions'],
                'insurance_sessions' => $summary['insurance_sessions'],
                'direct_payment_sessions' => $summary['direct_payment_sessions'],
                'total_paid' => $summary['total_paid_amount'],
                'total_pending' => $summary['total_pending_amount']
            ],
            'by_professional' => $byProfessional,
            'monthly_patterns' => $monthlyPayments,
            'pending_payments' => $pendingPayments,
            'payment_history' => $paymentHistory
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'PAYMENT_HISTORY_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();

