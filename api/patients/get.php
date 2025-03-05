<?php
// api/patients/get.php

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
$conn = getConnection();

try {
    // Get patient details with insurance and professionals
    $query = "
        SELECT 
            p.*,
            ic.name as insurance_company_name,
            GROUP_CONCAT(
                DISTINCT
                JSON_OBJECT(
                    'id', pr.id,
                    'name', pr.name,
                    'specialty', pr.specialty,
                    'start_date', pp.start_date
                )
            ) as professionals
        FROM patients p
        LEFT JOIN insurance_companies ic ON p.insurance_company_id = ic.id
        LEFT JOIN patient_professionals pp ON p.id = pp.patient_id AND pp.is_active = 1
        LEFT JOIN professionals pr ON pp.professional_id = pr.id
        WHERE p.id = ?
        GROUP BY p.id";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => 'PATIENT_NOT_FOUND',
                'message' => 'Patient not found'
            ]
        ]);
        exit();
    }

    $patient = $result->fetch_assoc();

    // Get recent sessions
    $sessionsQuery = "
        SELECT 
            si.*,
            rs.day_of_week,
            p.name as professional_name,
            p.specialty as professional_specialty
        FROM session_instances si
        LEFT JOIN recurring_schedules rs ON si.recurring_schedule_id = rs.id
        LEFT JOIN patient_professionals pp ON rs.patient_professional_id = pp.id
        LEFT JOIN professionals p ON pp.professional_id = p.id
        WHERE pp.patient_id = ?
        ORDER BY si.session_date DESC, si.session_time DESC
        LIMIT 10";

    $stmt = $conn->prepare($sessionsQuery);
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $sessionsResult = $stmt->get_result();

    $sessions = [];
    while ($row = $sessionsResult->fetch_assoc()) {
        $sessions[] = [
            'id' => $row['id'],
            'session_date' => $row['session_date'],
            'session_time' => $row['session_time'],
            'duration' => $row['duration'],
            'status' => $row['status'],
            'payment_status' => $row['payment_status'],
            'payment_type' => $row['payment_type'],
            'professional_name' => $row['professional_name'],
            'professional_specialty' => $row['professional_specialty'],
            'is_recurring' => !is_null($row['recurring_schedule_id']),
            'day_of_week' => $row['day_of_week']
        ];
    }

    // Format response
    $response = [
        'id' => $patient['id'],
        'name' => $patient['name'],
        'email' => $patient['email'],
        'phone' => $patient['phone'],
        'emergency_contact_name' => $patient['emergency_contact_name'],
        'emergency_contact_phone' => $patient['emergency_contact_phone'],
        'insurance_company' => $patient['insurance_company_id'] ? [
            'id' => $patient['insurance_company_id'],
            'name' => $patient['insurance_company_name']
        ] : null,
        'insurance_number' => $patient['insurance_number'],
        'cud_type' => $patient['cud_type'],
        'has_cud' => (bool)$patient['has_cud'],
        'is_active' => (bool)$patient['is_active'],
        'created_at' => $patient['created_at'],
        'updated_at' => $patient['updated_at'],
        'professionals' => $patient['professionals'] ? json_decode('[' . $patient['professionals'] . ']', true) : [],
        'recent_sessions' => $sessions
    ];

    echo json_encode([
        'success' => true,
        'data' => $response
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'GET_PATIENT_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();