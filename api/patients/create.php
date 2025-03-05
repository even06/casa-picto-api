<?php
// api/patients/create.php

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
$required_fields = ['name', 'phone'];
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
    // Insert patient
    $stmt = $conn->prepare("
        INSERT INTO patients (
            name, 
            email, 
            phone, 
            emergency_contact_name,
            emergency_contact_phone,
            insurance_company_id,
            insurance_number,
            cud_type,
            has_cud,
            is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    $email = isset($data['email']) ? $data['email'] : null;
    $emergency_contact_name = isset($data['emergency_contact_name']) ? $data['emergency_contact_name'] : null;
    $emergency_contact_phone = isset($data['emergency_contact_phone']) ? $data['emergency_contact_phone'] : null;
    $insurance_company_id = isset($data['insurance_company_id']) ? $data['insurance_company_id'] : null;
    $insurance_number = isset($data['insurance_number']) ? $data['insurance_number'] : null;
    $cud_type = isset($data['cud_type']) ? $data['cud_type'] : null;
    $has_cud = isset($data['has_cud']) ? $data['has_cud'] : false;

    $stmt->bind_param("ssssssssi", 
        $data['name'],
        $email,
        $data['phone'],
        $emergency_contact_name,
        $emergency_contact_phone,
        $insurance_company_id,
        $insurance_number,
        $cud_type,
        $has_cud
    );

    $stmt->execute();
    $patientId = $conn->insert_id;

    // If professionals are provided, create patient-professional relationships
    if (isset($data['professionals']) && is_array($data['professionals'])) {
        $stmt = $conn->prepare("
            INSERT INTO patient_professionals (
                patient_id, 
                professional_id, 
                start_date,
                is_active
            ) VALUES (?, ?, CURRENT_DATE, 1)
        ");

        foreach ($data['professionals'] as $professionalId) {
            $stmt->bind_param("ii", $patientId, $professionalId);
            $stmt->execute();
        }
    }

    // Get the created patient with all relationships
    $query = "
        SELECT 
            p.*,
            ic.name as insurance_company_name,
            GROUP_CONCAT(
                JSON_OBJECT(
                    'id', pr.id,
                    'name', pr.name,
                    'specialty', pr.specialty
                )
            ) as professionals
        FROM patients p
        LEFT JOIN insurance_companies ic ON p.insurance_company_id = ic.id
        LEFT JOIN patient_professionals pp ON p.id = pp.patient_id
        LEFT JOIN professionals pr ON pp.professional_id = pr.id
        WHERE p.id = ?
        GROUP BY p.id";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result->fetch_assoc();

    $conn->commit();

    // Format the response
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
        'professionals' => $patient['professionals'] ? json_decode('[' . $patient['professionals'] . ']', true) : []
    ];

    echo json_encode([
        'success' => true,
        'data' => $response
    ]);

} catch (Exception $e) {
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'CREATE_PATIENT_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();