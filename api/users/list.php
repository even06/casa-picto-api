<?php
// api/users/list.php

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

// Validate token and check if admin
$user = validateToken();
if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => [
            'code' => 'FORBIDDEN',
            'message' => 'Only administrators can list users'
        ]
    ]);
    exit();
}

$conn = getConnection();

try {
    // Get query parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $role = isset($_GET['role']) ? $_GET['role'] : '';

    // Build query
    $whereClause = [];
    $params = [];
    $types = "";

    if ($search) {
        $whereClause[] = "(u.username LIKE ? OR p.name LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "ss";
    }

    if ($role) {
        $whereClause[] = "u.role = ?";
        $params[] = $role;
        $types .= "s";
    }

    $whereSQL = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";

    // Get total count
    $countQuery = "
        SELECT COUNT(DISTINCT u.id) as total 
        FROM users u 
        LEFT JOIN professionals p ON u.id = p.user_id 
        $whereSQL";

    $stmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $totalResult = $stmt->get_result();
    $total = $totalResult->fetch_assoc()['total'];

    // Get users
    $query = "
        SELECT 
            u.id,
            u.username,
            u.role,
            u.created_at,
            p.id as professional_id,
            p.name,
            p.specialty,
            p.is_active
        FROM users u
        LEFT JOIN professionals p ON u.id = p.user_id
        $whereSQL
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($query);
    $types .= "ii";
    $params[] = $limit;
    $params[] = $offset;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $user = [
            'id' => $row['id'],
            'username' => $row['username'],
            'role' => $row['role'],
            'created_at' => $row['created_at']
        ];

        if ($row['role'] === 'professional') {
            $user['professional'] = [
                'id' => $row['professional_id'],
                'name' => $row['name'],
                'specialty' => $row['specialty'],
                'is_active' => (bool)$row['is_active']
            ];
        }

        $users[] = $user;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'users' => $users,
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
            'code' => 'LIST_USERS_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
}

$conn->close();