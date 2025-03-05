<?php
// api/professionals/get.php

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

$currentUser = validateToken();

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'MISSING_ID',
            'message' => 'Professional ID is required'
        ]
    ]);
    exit();
}

$professionalId = intval($_GET['id']);
$conn = getConnection();

try {
    // Get professional details
    $query = "
        SELECT 
            p.id,
            p.name,
            p.specialty,
            p.is_active,
            p.created_at,
            p.updated_at,
            u.username,
            (
                SELECT COUNT(*)
                FROM patient_professionals pp
                WHERE pp.professional_id = p.id
                AND pp.is_active = 1
            ) as active_patients
        FROM professionals p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $professionalId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Professional not found');
    }

    $professional = $result->fetch_assoc();

    // Get regular availability
    $availabilityQuery = "
        SELECT 
            id,
            day_of_week,
            start_time,
            end_time,
            valid_from,
            valid_to,
            is_active
        FROM professional_availability
        WHERE professional_id = ?
        AND (valid_to IS NULL OR valid_to >= CURRENT_DATE)
        ORDER BY day_of_week, start_time";

    $stmt = $conn->prepare($availabilityQuery);
    $stmt->bind_param("i", $professionalId);
    $stmt->execute();
    $availabilityResult = $stmt->get_result();

    $availability = [];
    while ($row = $availabilityResult->fetch_assoc()) {
        $availability[] = [
            'id' => $row['id'],
            'day_of_week' => $row['day_of_week'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'valid_from' => $row['valid_from'],
            'valid_to' => $row['valid_to'],
            'is_active' => (bool)$row['is_active']
        ];
    }

    // Get upcoming exceptions
    $exceptionsQuery = "
        SELECT 
            id,
            exception_date,
            start_time,
            end_time,
            is_available,
            reason
        FROM professional_availability_exceptions
        WHERE professional_id = ?
        AND exception_date >= CURRENT_DATE
        ORDER BY exception_date";

    $stmt = $conn->prepare($exceptionsQuery);
    $stmt->bind_param("i", $professionalId);
    $stmt->execute();
    $exceptionsResult = $stmt->get_result();

    $exceptions = [];
    while ($row = $exceptionsResult->fetch_assoc()) {
        $exceptions[] = [
            'id' => $row['id'],
            'date' => $row['exception_date'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'is_available' => (bool)$row['is_available'],
            'reason' => $row['reason']
        ];
    }

    // Get current patients
    $patientsQuery = "
        SELECT 
            p.id,
            p.name,
            pp.start_date
        FROM patients p
        JOIN patient_professionals pp ON p.id = pp.patient_id
        WHERE pp.professional_id = ?
        AND pp.is_active = 1
        ORDER BY p.name";

    $stmt = $conn->prepare($patientsQuery);
    $stmt->bind_param("i", $professionalId);
    $stmt->execute();
    $patientsResult = $stmt->get_result();

    $patients = [];
    while ($row = $patientsResult->fetch_assoc()) {
        $patients[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'start_date' => $row['start_date']
        ];
    }

    $response = [
        'id' => $professional['id'],
        'name' => $professional['name'],
        'username' => $professional['username'],
        'specialty' => $professional['specialty'],
        'is_active' => (bool)$professional['is_active'],
        'active_patients' => $professional['active_patients'],
        'created_at' => $professional['created_at'],
        'updated_at' => $professional['updated_at'],
        'availability' => $availability,
        'exceptions' => $exceptions,
        'patients' => $patients
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
            'code' => 'GET_PROFESSIONAL_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();