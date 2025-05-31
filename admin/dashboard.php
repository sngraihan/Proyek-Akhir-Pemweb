<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAdmin();

// Statistik untuk dashboard
$total_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms")->fetch_assoc()['count'];
$occupied_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE status='occupied'")->fetch_assoc()['count'];
$total_tenants = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='tenant'")->fetch_assoc()['count'];
$pending_payments = $conn->query("SELECT COUNT(*) as count FROM payments WHERE status='pending'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Manajemen Kos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Admin Panel</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="rooms.php">Kelola Kamar</a>
                <a class="nav-link" href="tenants.php">Data Penyewa</a>
                <a class="nav-link" href="payments.php">Pembayaran</a>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2>Dashboard Admin</h2>
                <p>Selamat datang, <?= $_SESSION['full_name'] ?>!</p>
            </div>
        </div>

        <!-- Statistik Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Total Kamar</h5>
                        <h2><?= $total_rooms ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Kamar Terisi</h5>
                        <h2><?= $occupied_rooms ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Total Penyewa</h5>
                        <h2><?= $total_tenants ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Pembayaran Pending</h5>
                        <h2><?= $pending_payments ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Menu Utama</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <a href="rooms.php" class="btn btn-primary btn-lg w-100 mb-3">
                                    Kelola Data Kamar
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="tenants.php" class="btn btn-success btn-lg w-100 mb-3">
                                    Kelola Data Penyewa
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="payments.php" class="btn btn-info btn-lg w-100 mb-3">
                                    Kelola Pembayaran
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>