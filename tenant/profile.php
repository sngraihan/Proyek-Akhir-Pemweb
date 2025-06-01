<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/validation.php';
checkTenant();

// Handle profile update
if (isset($_POST['update_profile'])) {
    $user_data = [
        'id' => $_SESSION['user_id'],
        'username' => trim($_POST['username']),
        'full_name' => trim($_POST['full_name']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone']),
        'emergency_contact' => trim($_POST['emergency_contact']),
        'password' => trim($_POST['new_password'])
    ];
    
    // Basic validation
    $errors = [];
    if (strlen($user_data['full_name']) < 3) {
        $errors[] = "Nama lengkap minimal 3 karakter";
    }
    
    if (!empty($user_data['email']) && !filter_var($user_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid";
    }
    
    if (!empty($user_data['phone']) && !preg_match('/^[0-9]{10,13}$/', $user_data['phone'])) {
        $errors[] = "Nomor telepon harus 10-13 digit";
    }
    
    // Check username duplication
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $user_data['username'], $user_data['id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Username sudah digunakan!";
    }
    $stmt->close();
    
    if (empty($errors)) {
        // First, check if additional_info exists
        $check_stmt = $conn->prepare("SELECT id FROM tenant_additional_info WHERE user_id = ?");
        $check_stmt->bind_param("i", $_SESSION['user_id']);
        $check_stmt->execute();
        $info_exists = $check_stmt->get_result()->num_rows > 0;
        $check_stmt->close();
        
        // Update users table
        if (!empty($user_data['password'])) {
            $password = password_hash($user_data['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username=?, password=?, full_name=? WHERE id=?");
            $stmt->bind_param("sssi", $user_data['username'], $password, $user_data['full_name'], $user_data['id']);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, full_name=? WHERE id=?");
            $stmt->bind_param("ssi", $user_data['username'], $user_data['full_name'], $user_data['id']);
        }
        
        if ($stmt->execute()) {
            // Update or insert additional info
            if ($info_exists) {
                $stmt2 = $conn->prepare("UPDATE tenant_additional_info SET email=?, phone=?, emergency_contact=? WHERE user_id=?");
                $stmt2->bind_param("sssi", $user_data['email'], $user_data['phone'], $user_data['emergency_contact'], $_SESSION['user_id']);
            } else {
                $stmt2 = $conn->prepare("INSERT INTO tenant_additional_info (user_id, email, phone, emergency_contact) VALUES (?, ?, ?, ?)");
                $stmt2->bind_param("isss", $_SESSION['user_id'], $user_data['email'], $user_data['phone'], $user_data['emergency_contact']);
            }
            $stmt2->execute();
            $stmt2->close();
            
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

// Get tenant data with additional info
$query = "SELECT u.*, tai.email, tai.phone, tai.emergency_contact, r.room_number, r.room_type, r.price 
          FROM users u 
          LEFT JOIN tenant_additional_info tai ON u.id = tai.user_id 
          LEFT JOIN rooms r ON u.id = r.tenant_id 
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$tenant_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get payment summary
$payment_summary = $conn->prepare("
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        MIN(payment_date) as first_payment,
        MAX(payment_date) as last_payment
    FROM payments 
    WHERE tenant_id = ?
");
$payment_summary->bind_param("i", $_SESSION['user_id']);
$payment_summary->execute();
$payment_stats = $payment_summary->get_result()->fetch_assoc();
$payment_summary->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Manajemen Kos</title>
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
                    <a class="nav-link" href="dashboard.php">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>
                    <a class="nav-link active" href="profile.php">
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
            <!-- Profile Information -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="bi bi-person-circle" style="font-size: 100px; color: #6c757d;"></i>
                        </div>
                        <h4><?= htmlspecialchars($tenant_data['full_name']) ?></h4>
                        <p class="text-muted">@<?= htmlspecialchars($tenant_data['username']) ?></p>
                        <?php if ($tenant_data['room_number']): ?>
                            <p class="mb-2">
                                <span class="badge bg-primary">Kamar <?= $tenant_data['room_number'] ?></span>
                                <span class="badge bg-secondary"><?= $tenant_data['room_type'] ?></span>
                            </p>
                        <?php endif; ?>
                        <hr>
                        <div class="text-start">
                            <p class="mb-2"><i class="bi bi-calendar"></i> Bergabung: <?= date('d M Y', strtotime($tenant_data['created_at'])) ?></p>
                            <?php if ($payment_stats['total_payments'] > 0): ?>
                                <p class="mb-2"><i class="bi bi-check-square"></i> Total Pembayaran: <?= $payment_stats['paid_count'] ?>/<?= $payment_stats['total_payments'] ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Form -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person-gear"></i> Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="profileForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="full_name" 
                                               value="<?= htmlspecialchars($tenant_data['full_name']) ?>" 
                                               minlength="3" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Username <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="username" 
                                               value="<?= htmlspecialchars($tenant_data['username']) ?>" 
                                               minlength="3" 
                                               pattern="[a-zA-Z0-9_]+" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?= htmlspecialchars($tenant_data['email'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">No. Telepon</label>
                                        <input type="tel" class="form-control" name="phone" 
                                               value="<?= htmlspecialchars($tenant_data['phone'] ?? '') ?>"
                                               pattern="[0-9]{10,13}">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Kontak Darurat</label>
                                <input type="text" class="form-control" name="emergency_contact" 
                                       value="<?= htmlspecialchars($tenant_data['emergency_contact'] ?? '') ?>"
                                       placeholder="Nama dan nomor kontak darurat">
                            </div>

                            <hr>

                            <div class="mb-3">
                                <label class="form-label">Password Baru</label>
                                <input type="password" class="form-control" name="new_password" minlength="6">
                                <small class="text-muted">Kosongkan jika tidak ingin mengubah password</small>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="reset" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Reset
                                </button>
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        const inputs = this.querySelectorAll('input');
        let isValid = true;
        
        inputs.forEach(input => {
            // Remove previous validation states
            input.classList.remove('is-invalid', 'is-valid');
            
            // Check validity
            if (input.hasAttribute('required') && !input.value) {
                input.classList.add('is-invalid');
                isValid = false;
            } else if (input.value && !input.checkValidity()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else if (input.value) {
                input.classList.add('is-valid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            e.stopPropagation();
        }
    });
    
    // Real-time validation
    document.querySelectorAll('#profileForm input').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid', 'is-valid');
            if (this.value && this.checkValidity()) {
                this.classList.add('is-valid');
            } else if (this.value) {
                this.classList.add('is-invalid');
            }
        });
    });
    </script>
</body>
</html>