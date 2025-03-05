<?php
// api/patients/list.php

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

$conn = getConnection();

try {
    // Get query parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $isActive = isset($_GET['isActive']) ? filter_var($_GET['isActive'], FILTER_VALIDATE_BOOLEAN) : null;
    $insuranceId = isset($_GET['insuranceId']) ? intval($_GET['insuranceId']) : null;
    $professionalId = isset($_GET['professionalId']) ? intval($_GET['professionalId']) : null;

    // Build query
    $whereClause = [];
    $params = [];
    $types = "";

    if ($search) {
        $whereClause[] = "(p.name LIKE ? OR p.phone LIKE ? OR p.email LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sss";
    }

    if ($isActive !== null) {
        $whereClause[] = "p.is_active = ?";
        $params[] = $isActive;
        $types .= "i";
    }

    if ($insuranceId) {
        $whereClause[] = "p.insurance_company_id = ?";
        $params[] = $insuranceId;
        $types .= "i";
    }

    $joinClause = "";
    if ($professionalId) {
        $joinClause = "JOIN patient_professionals pp ON p.id = pp.patient_id";
        $whereClause[] = "pp.professional_id = ? AND pp.is_active = 1";
        $params[] = $professionalId;
        $types .= "i";
    }

    $whereSQL = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";

    // Get total count
    $countQuery = "
        SELECT COUNT(DISTINCT p.id) as total 
        FROM patients p 
        $joinClause
        $whereSQL";

    $stmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $totalResult = $stmt->get_result();
    $total = $totalResult->fetch_assoc()['total'];

    // Get patients
    $query = "
        SELECT DISTINCT
            p.*,
            ic.name as insurance_company_name,
            GROUP_CONCAT(
                DISTINCT
                JSON_OBJECT(
                    'id', pr.id,
                    'name', pr.name,
                    'specialty', pr.specialty
                )
            ) as professionals
        FROM patients p
        $joinClause
        LEFT JOIN insurance_companies ic ON p.insurance_company_id = ic.id
        LEFT JOIN patient_professionals pp2 ON p.id = pp2.patient_id AND pp2.is_active = 1
        LEFT JOIN professionals pr ON pp2.professional_id = pr.id
        $whereSQL
        GROUP BY p.id
        ORDER BY p.name
        LIMIT ? OFFSET ?";

    $types .= "ii";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $patients = [];
    while ($row = $result->fetch_assoc()) {
        $patients[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'emergency_contact_name' => $row['emergency_contact_name'],
            'emergency_contact_phone' => $row['emergency_contact_phone'],
            'insurance_company' => $row['insurance_company_id'] ? [
                'id' => $row['insurance_company_id'],
                'name' => $row['insurance_company_name']
            ] : null,
            'insurance_number' => $row['insurance_number'],
            'cud_type' => $row['cud_type'],
            'has_cud' => (bool)$row['has_cud'],
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at'],
            'professionals' => $row['professionals'] ? json_decode('[' . $row['professionals'] . ']', true) : []
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'patients' => $patients,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'LIST_PATIENTS_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();