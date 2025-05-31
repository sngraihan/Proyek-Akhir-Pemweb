<?php
// Konfigurasi database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'kos_management';

// Koneksi menggunakan mysqli
$conn = new mysqli($host, $username, $password, $database);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Set charset ke utf8
$conn->set_charset("utf8");
?>