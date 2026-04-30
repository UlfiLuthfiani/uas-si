<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'db_medklik';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Mulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>