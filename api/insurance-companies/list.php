<?php
// api/insurance-companies/list.php

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
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $isActive = isset($_GET['isActive']) ? filter_var($_GET['isActive'], FILTER_VALIDATE_BOOLEAN) : null;

    // Build query
    $whereClause = [];
    $params = [];
    $types = "";

    if ($search) {
        $whereClause[] = "name LIKE ?";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $types .= "s";
    }

    if ($isActive !== null) {
        $whereClause[] = "is_active = ?";
        $params[] = $isActive;
        $types .= "i";
    }

    $whereSQL = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM insurance_companies $whereSQL";
    
    $stmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $totalResult = $stmt->get_result();
    $total = $totalResult->fetch_assoc()['total'];

    // Get insurance companies
    $query = "
        SELECT 
            id,
            name,
            is_active,
            created_at,
            updated_at,
            (
                SELECT COUNT(*)
                FROM patients
                WHERE insurance_company_id = insurance_companies.id
                AND is_active = 1
            ) as active_patients
        FROM insurance_companies
        $whereSQL
        ORDER BY name ASC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $companies = [];
    while ($row = $result->fetch_assoc()) {
        $companies[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'is_active' => (bool)$row['is_active'],
            'active_patients' => $row['active_patients'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'companies' => $companies,
            'total' => $total
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'LIST_INSURANCE_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();