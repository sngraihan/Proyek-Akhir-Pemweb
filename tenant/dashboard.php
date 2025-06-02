<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/validation.php';
checkTenant();

// UPDATE Profile Tenant
if (isset($_POST['update_profile'])) {
    $user_data = [
        'id' => $_SESSION['user_id'],
        'username' => trim($_POST['username']),
        'full_name' => trim($_POST['full_name']),
        'password' => trim($_POST['new_password'])
    ];
    
    // Validasi
    $errors = validateUser($user_data);
    
    // Cek duplikasi username
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $user_data['username'], $user_data['id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Username sudah digunakan!";
    }
    $stmt->close();
    
    if (empty($errors)) {
        if (!empty($user_data['password'])) {
            $password = password_hash($user_data['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username=?, password=?, full_name=? WHERE id=?");
            $stmt->bind_param("sssi", $user_data['username'], $password, $user_data['full_name'], $user_data['id']);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, full_name=? WHERE id=?");
            $stmt->bind_param("ssi", $user_data['username'], $user_data['full_name'], $user_data['id']);
        }
        
        if ($stmt->execute()) {
            $_SESSION['username'] = $user_data['username'];
            $_SESSION['full_name'] = $user_data['full_name'];
            $success = "Profile berhasil diupdate!";
        } else {
            $error = "Gagal mengupdate profile!";
        }
        $stmt->close();
    } else {
        $error = implode("<br>", $errors);
    }
}

// Ambil data tenant
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$tenant_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Ambil data kamar tenant
$stmt = $conn->prepare("SELECT * FROM rooms WHERE tenant_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$my_room = $result->fetch_assoc();
$stmt->close();

// Filter pembayaran
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_year = isset($_GET['filter_year']) ? $_GET['filter_year'] : date('Y');

// Ambil data pembayaran tenant dengan filter
$where_conditions = ["tenant_id = ?"];
$params = [$_SESSION['user_id']];
$types = 'i';

if (!empty($filter_status)) {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if (!empty($filter_year)) {
    $where_conditions[] = "YEAR(payment_date) = ?";
    $params[] = $filter_year;
    $types .= 's';
}

$query = "SELECT * FROM payments WHERE " . implode(' AND ', $where_conditions) . " ORDER BY payment_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Statistik pembayaran
$stats_query = "SELECT 
    COUNT(*) as total_payments,
    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
    SUM(amount) as total_amount
    FROM payments 
    WHERE tenant_id = ? AND YEAR(payment_date) = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("is", $_SESSION['user_id'], $filter_year);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Penyewa - Manajemen Kos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Panel Penyewa</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>
                    <a class="nav-link" href="profile.php">
                        <i class="bi bi-person"></i> Profile
                    </a>
                    <a class="nav-link" href="payments.php">
                        <i class="bi bi-wallet2"></i> Pembayaran
                    </a>
                    <span class="navbar-text mx-3 text-white">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['full_name']) ?>
                    </span>
                    <a class="nav-link" href="../logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-circle"></i> <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <h2 class="mb-4">Dashboard Penyewa</h2>
            </div>
        </div>
        
        <?php if ($my_room): ?>
            <!-- Info Cards Row -->
            <div class="row mb-4">
                <!-- Room Info Card -->
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-house-door"></i> Informasi Kamar</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <p class="mb-2"><strong>Nomor Kamar:</strong></p>
                                    <p class="mb-2"><strong>Tipe:</strong></p>
                                    <p class="mb-2"><strong>Harga/Bulan:</strong></p>
                                    <p class="mb-0"><strong>Status:</strong></p>
                                </div>
                                <div class="col-6">
                                    <p class="mb-2"><?= htmlspecialchars($my_room['room_number']) ?></p>
                                    <p class="mb-2"><?= htmlspecialchars($my_room['room_type']) ?></p>
                                    <p class="mb-2">Rp <?= number_format($my_room['price'], 0, ',', '.') ?></p>
                                    <p class="mb-0">
                                        <span class="badge bg-success">Aktif</span>
                                    </p>
                                </div>
                            </div>
                            <?php if ($my_room['description']): ?>
                                <hr>
                                <p class="mb-0"><small><?= htmlspecialchars($my_room['description']) ?></small></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <!-- Payment Summary Card -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-wallet2"></i> Ringkasan Pembayaran <?= $filter_year ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <h3 class="text-success"><?= isset($stats['paid_count']) ? $stats['paid_count'] : 0 ?></h3>
                                <p class="mb-0">Lunas</p>
                            </div>
                            <div class="col-4">
                                <h3 class="text-warning"><?= isset($stats['pending_count']) ? $stats['pending_count'] : 0 ?></h3>
                                <p class="mb-0">Pending</p>
                            </div>
                            <div class="col-4">
                                <h3 class="text-danger"><?= isset($stats['overdue_count']) ? $stats['overdue_count'] : 0 ?></h3>
                                <p class="mb-0">Terlambat</p>
                            </div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <p class="mb-0"><strong>Total Pembayaran:</strong></p>
                            <?php
                            if (isset($stats['total_amount']) && $stats['total_amount'] !== null) {
                                echo "<h4>Rp " . number_format($stats['total_amount'], 0, ',', '.') . "</h4>";
                            } else {
                                echo "<h4>Rp 0</h4>"; // Jika kosong, tampilkan angka 0
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-lightning"></i> Menu Cepat</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                        <i class="bi bi-person-gear"></i> Edit Profile
                                    </button>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <a href="payments.php" class="btn btn-info w-100">
                                        <i class="bi bi-receipt"></i> Lihat Semua Pembayaran
                                    </a>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <button class="btn btn-success w-100" onclick="window.print()">
                                        <i class="bi bi-printer"></i> Cetak Dashboard
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment History with Filter -->
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Riwayat Pembayaran</h5>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" class="row g-2">
                                <div class="col-md-6">
                                    <select class="form-select form-select-sm" name="filter_status" onchange="this.form.submit()">
                                        <option value="">Semua Status</option>
                                        <option value="paid" <?= $filter_status == 'paid' ? 'selected' : '' ?>>Lunas</option>
                                        <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="overdue" <?= $filter_status == 'overdue' ? 'selected' : '' ?>>Terlambat</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <select class="form-select form-select-sm" name="filter_year" onchange="this.form.submit()">
                                        <?php for($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                                            <option value="<?= $y ?>" <?= $filter_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($payments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Bulan</th>
                                        <th>Jumlah</th>
                                        <th>Tanggal Bayar</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?= date('F Y', strtotime($payment['payment_month'] . '-01')) ?></td>
                                        <td>Rp <?= number_format($payment['amount'], 0, ',', '.') ?></td>
                                        <td><?= date('d/m/Y', strtotime($payment['payment_date'])) ?></td>
                                        <td>
                                            <?php if ($payment['status'] == 'paid'): ?>
                                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Lunas</span>
                                            <?php elseif ($payment['status'] == 'pending'): ?>
                                                <span class="badge bg-warning"><i class="bi bi-clock"></i> Pending</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class="bi bi-exclamation-circle"></i> Terlambat</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">Belum ada data pembayaran untuk filter yang dipilih</p>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- Jika belum memiliki kamar -->
            <div class="alert alert-info">
                <h5><i class="bi bi-info-circle"></i> Anda belum memiliki kamar!</h5>
                <p>Silakan hubungi admin untuk mendapatkan kamar.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Edit Profile -->
    <div class="modal fade" id="editProfileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-gear"></i> Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editProfileForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" name="full_name" 
                                   value="<?= htmlspecialchars($tenant_data['full_name']) ?>" 
                                   minlength="3" 
                                   pattern="[a-zA-Z\s]+" 
                                   required>
                            <div class="invalid-feedback">
                                Nama minimal 3 karakter, hanya huruf dan spasi
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" 
                                   value="<?= htmlspecialchars($tenant_data['username']) ?>" 
                                   minlength="3" 
                                   pattern="[a-zA-Z0-9_]+" 
                                   required>
                            <div class="invalid-feedback">
                                Username minimal 3 karakter, hanya huruf, angka, dan underscore
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Baru (kosongkan jika tidak ingin mengubah)</label>
                            <input type="password" class="form-control" name="new_password" minlength="6">
                            <div class="invalid-feedback">
                                Password minimal 6 karakter
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation
    document.getElementById('editProfileForm').addEventListener('submit', function(e) {
        let isValid = true;
        const inputs = this.querySelectorAll('input[required]');
        
        inputs.forEach(input => {
            if (!input.checkValidity()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        const password = this.querySelector('input[name="new_password"]');
        if (password.value && password.value.length < 6) {
            password.classList.add('is-invalid');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            e.stopPropagation();
        }
    });
    
    // Real-time validation
    document.querySelectorAll('#editProfileForm input').forEach(input => {
        input.addEventListener('input', function() {
            if (this.checkValidity()) {
                this.classList.remove('is-invalid');
            }
        });
    });
    </script>
</body>
</html>
