<?php
// api/auth/logout.php

require_once '../../includes/auth.php';

handleCORS();

// Validate token
validateToken();

// In a real application, you might want to blacklist the token
// For now, we'll just return success as the client will remove the token

echo json_encode([
    'success' => true,
    'data' => [
        'message' => 'Successfully logged out'
    ]
]);