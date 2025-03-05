<?php
// api/patients/update.php

require_once '../../includes/database.php';
require_once '../../includes/auth.php';

handleCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$patientId = intval($_GET['id']);
$conn = getConnection();
$conn->begin_transaction();

try {
    // Check if patient exists
    $stmt = $conn->prepare("SELECT id FROM patients WHERE id = ?");
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception('Patient not found');
    }

    // Build update query dynamically
    $updateFields = [];
    $params = [];
    $types = "";

    $fields = [
        'name' => 's',
        'email' => 's',
        'phone' => 's',
        'emergency_contact_name' => 's',
        'emergency_contact_phone' => 's',
        'insurance_company_id' => 'i',
        'insurance_number' => 's',
        'cud_type' => 's',
        'has_cud' => 'i',
        'is_active' => 'i'
    ];

    foreach ($fields as $field => $type) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = $data[$field];
            $types .= $type;
        }
    }

    if (!empty($updateFields)) {
        $query = "UPDATE patients SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $types .= "i";
        $params[] = $patientId;
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    }

    // Update professional assignments if provided
    if (isset($data['professionals'])) {
        // Deactivate all current assignments
        $stmt = $conn->prepare("
            UPDATE patient_professionals 
            SET is_active = 0, updated_at = CURRENT_TIMESTAMP 
            WHERE patient_id = ? AND is_active = 1");
        $stmt->bind_param("i", $patientId);
        $stmt->execute();

        // Add new assignments
        if (!empty($data['professionals'])) {
            $stmt = $conn->prepare("
                INSERT INTO patient_professionals 
                (patient_id, professional_id, start_date, is_active) 
                VALUES (?, ?, CURRENT_DATE, 1)");

            foreach ($data['professionals'] as $professionalId) {
                $stmt->bind_param("ii", $patientId, $professionalId);
                $stmt->execute();
            }
        }
    }

    // Get updated patient data
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
    $patient = $result->fetch_assoc();

    $conn->commit();

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
        'updated_at' => $patient['updated_at'],
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
            'code' => 'UPDATE_PATIENT_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();