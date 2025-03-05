<?php
// setup/create_admin.php

require_once '../includes/database.php';

// Configuration for first admin
$admin = [
    'username' => 'admin',
    'password' => 'Picto5124$%', // Change this!
    'role' => 'admin',
    'name' => 'System Administrator'
];

$conn = getConnection();

// Check if any users exist
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    die("Error: Users already exist in the system. This script is only for initial setup.\n");
}

// Create admin user
$hashed_password = password_hash($admin['password'], PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $admin['username'], $hashed_password, $admin['role']);
    $stmt->execute();

    echo "Admin user created successfully!\n";
    echo "Username: " . $admin['username'] . "\n";
    echo "Password: " . $admin['password'] . "\n";
    echo "\nPlease change the password after first login.\n";
    
} catch (Exception $e) {
    echo "Error creating admin user: " . $e->getMessage() . "\n";
}

$conn->close();