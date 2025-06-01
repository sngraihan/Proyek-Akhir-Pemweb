<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
checkAdmin();

// Proses tambah tenant baru
if (isset($_POST['add_tenant'])) {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = trim($_POST['full_name']);
    $room_id = intval($_POST['room_id']);
    
    // Mulai transaction
    $conn->begin_transaction();
    
    try {
        // Insert user baru
        $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'tenant')");
        $stmt->bind_param("sss", $username, $password, $full_name);
        $stmt->execute();
        $tenant_id = $conn->insert_id;
        
        // Update room dengan tenant_id dan status
        $stmt = $conn->prepare("UPDATE rooms SET tenant_id=?, status='occupied' WHERE id=?");
        $stmt->bind_param("ii", $tenant_id, $room_id);
        $stmt->execute();
        
        $conn->commit();
        $success = "Penyewa berhasil ditambahkan!";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Gagal menambahkan penyewa: " . $e->getMessage();
    }
}

// Proses UPDATE tenant
if (isset($_POST['edit_tenant'])) {
    $id = intval($_POST['id']);
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $new_password = trim($_POST['new_password']);
    $new_room_id = isset($_POST['new_room_id']) ? intval($_POST['new_room_id']) : null;
    
    $conn->begin_transaction();
    try {
        // Update user data
        if (!empty($new_password)) {
            $password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username=?, password=?, full_name=? WHERE id=?");
            $stmt->bind_param("sssi", $username, $password, $full_name, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, full_name=? WHERE id=?");
            $stmt->bind_param("ssi", $username, $full_name, $id);
        }
        $stmt->execute();
        
        // Update room assignment if changed
        if ($new_room_id !== null) {
            // Free up old room
            $stmt = $conn->prepare("UPDATE rooms SET tenant_id=NULL, status='available' WHERE tenant_id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            // Assign new room
            $stmt = $conn->prepare("UPDATE rooms SET tenant_id=?, status='occupied' WHERE id=?");
            $stmt->bind_param("ii", $id, $new_room_id);
            $stmt->execute();
        }
        
        $conn->commit();
        $success = "Data penyewa berhasil diupdate!";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Gagal mengupdate data: " . $e->getMessage();
    }
}

// Proses DELETE tenant
if (isset($_GET['delete_tenant'])) {
    $id = intval($_GET['delete_tenant']);
    
    $conn->begin_transaction();
    try {
        // Update room status jika tenant dihapus
        $stmt = $conn->prepare("UPDATE rooms SET tenant_id=NULL, status='available' WHERE tenant_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        // Delete related payments
        $stmt = $conn->prepare("DELETE FROM payments WHERE tenant_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        // Hapus user
        $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND role='tenant'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $conn->commit();
        $success = "Penyewa berhasil dihapus!";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Gagal menghapus penyewa: " . $e->getMessage();
    }
}

// Ambil data penyewa dengan info kamar
$query = "SELECT u.*, r.room_number, r.price, r.id as room_id 
          FROM users u 
          LEFT JOIN rooms r ON u.id = r.tenant_id 
          WHERE u.role = 'tenant' 
          ORDER BY u.full_name";
$tenants = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Ambil kamar yang tersedia untuk dropdown
$available_rooms = $conn->query("SELECT * FROM rooms WHERE status='available' ORDER BY room_number")->fetch_all(MYSQLI_ASSOC);

// Ambil semua kamar untuk dropdown edit
$all_rooms = $conn->query("SELECT * FROM rooms ORDER BY room_number")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Penyewa - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Admin Panel</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="rooms.php">Kelola Kamar</a>
                <a class="nav-link active" href="tenants.php">Data Penyewa</a>
                <a class="nav-link" href="payments.php">Pembayaran</a>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Kelola Data Penyewa</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form Tambah Penyewa -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Tambah Penyewa Baru</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addTenantForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" minlength="3" required>
                                <div class="invalid-feedback">Nama lengkap minimal 3 karakter</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" pattern="[a-zA-Z0-9_]{3,}" minlength="3" required>
                                <div class="invalid-feedback">Username minimal 3 karakter, hanya huruf, angka, dan underscore</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                                <div class="invalid-feedback">Password minimal 6 karakter</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="room_id" class="form-label">Pilih Kamar</label>
                                <select class="form-control" id="room_id" name="room_id" required>
                                    <option value="">Pilih Kamar</option>
                                    <?php foreach ($available_rooms as $room): ?>
                                        <option value="<?= $room['id'] ?>">
                                            <?= $room['room_number'] ?> - <?= $room['room_type'] ?> (Rp <?= number_format($room['price'], 0, ',', '.') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Pilih kamar untuk penyewa</div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_tenant" class="btn btn-primary">Tambah Penyewa</button>
                </form>
            </div>
        </div>

        <!-- Tabel Daftar Penyewa -->
        <div class="card">
            <div class="card-header">
                <h5>Daftar Penyewa</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama Lengkap</th>
                                <th>Username</th>
                                <th>Kamar</th>
                                <th>Harga Sewa</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($tenants as $tenant): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($tenant['full_name']) ?></td>
                                <td><?= htmlspecialchars($tenant['username']) ?></td>
                                <td><?= $tenant['room_number'] ? $tenant['room_number'] : '<span class="text-muted">Belum ada kamar</span>' ?></td>
                                <td><?= $tenant['price'] ? 'Rp ' . number_format($tenant['price'], 0, ',', '.') : '-' ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $tenant['id'] ?>">Edit</button>
                                    <a href="tenants.php?delete_tenant=<?= $tenant['id'] ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Yakin ingin menghapus penyewa ini? Semua data pembayaran akan ikut terhapus.')">Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Edit untuk setiap tenant -->
    <?php foreach ($tenants as $tenant): ?>
    <div class="modal fade" id="editModal<?= $tenant['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Data Penyewa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="editTenantForm">
                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?= $tenant['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($tenant['full_name']) ?>" minlength="3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($tenant['username']) ?>" pattern="[a-zA-Z0-9_]{3,}" minlength="3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Baru (kosongkan jika tidak ingin mengubah)</label>
                            <input type="password" class="form-control" name="new_password" minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pindah Kamar (opsional)</label>
                            <select class="form-control" name="new_room_id">
                                <option value="">Tidak pindah kamar</option>
                                <?php foreach ($all_rooms as $room): ?>
                                    <?php if ($room['status'] == 'available' || $room['id'] == $tenant['room_id']): ?>
                                        <option value="<?= $room['id'] ?>" <?= $room['id'] == $tenant['room_id'] ? 'selected' : '' ?>>
                                            <?= $room['room_number'] ?> - <?= $room['room_type'] ?> 
                                            <?= $room['id'] == $tenant['room_id'] ? '(Kamar saat ini)' : '' ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="edit_tenant" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Validasi form tambah tenant
    document.getElementById('addTenantForm').addEventListener('submit', function(e) {
        let isValid = true;
        const inputs = this.querySelectorAll('input[required], select[required]');
        
        inputs.forEach(input => {
            if (!input.checkValidity()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            e.stopPropagation();
        }
    });

    // Real-time validation
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') || this.value) {
                if (!this.checkValidity()) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            }
        });
    });
    </script>
</body>
</html>