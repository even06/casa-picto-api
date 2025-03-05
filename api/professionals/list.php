<?php
// api/professionals/list.php

require_once '../../includes/database.php';
require_once '../../includes/auth.php';

handleCORS();

// Only allow GET requests
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
    $specialty = isset($_GET['specialty']) ? $_GET['specialty'] : '';
    $isActive = isset($_GET['isActive']) ? filter_var($_GET['isActive'], FILTER_VALIDATE_BOOLEAN) : null;

    // Build query
    $whereClause = [];
    $params = [];
    $types = "";

    if ($search) {
        $whereClause[] = "(p.name LIKE ? OR p.specialty LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "ss";
    }

    if ($specialty) {
        $whereClause[] = "p.specialty = ?";
        $params[] = $specialty;
        $types .= "s";
    }

    if ($isActive !== null) {
        $whereClause[] = "p.is_active = ?";
        $params[] = $isActive;
        $types .= "i";
    }

    $whereSQL = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM professionals p $whereSQL";
    
    $stmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $totalResult = $stmt->get_result();
    $total = $totalResult->fetch_assoc()['total'];

    // Get professionals
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
        $whereSQL
        ORDER BY p.name ASC
        LIMIT ? OFFSET ?";

    $types .= "ii";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $professionals = [];
    while ($row = $result->fetch_assoc()) {
        $professionals[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'username' => $row['username'],
            'specialty' => $row['specialty'],
            'is_active' => (bool)$row['is_active'],
            'active_patients' => $row['active_patients'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    // Get unique specialties for filtering
    $specialtiesQuery = "SELECT DISTINCT specialty FROM professionals ORDER BY specialty";
    $specialtiesResult = $conn->query($specialtiesQuery);
    $specialties = [];
    while ($row = $specialtiesResult->fetch_assoc()) {
        $specialties[] = $row['specialty'];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'professionals' => $professionals,
            'specialties' => $specialties,
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
            'code' => 'LIST_PROFESSIONALS_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();