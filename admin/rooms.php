<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/validation.php';
checkAdmin();

// Proses CREATE (Tambah Kamar)
if (isset($_POST['add_room'])) {
    $room_data = [
        'room_number' => strtoupper(trim($_POST['room_number'])),
        'room_type' => trim($_POST['room_type']),
        'price' => floatval($_POST['price']),
        'description' => trim($_POST['description'])
    ];
    
    // Validasi server-side
    $errors = validateRoom($room_data);
    
    // Cek duplikasi nomor kamar
    $stmt = $conn->prepare("SELECT id FROM rooms WHERE room_number = ?");
    $stmt->bind_param("s", $room_data['room_number']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Nomor kamar sudah ada!";
    }
    $stmt->close();
    
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO rooms (room_number, room_type, price, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $room_data['room_number'], $room_data['room_type'], $room_data['price'], $room_data['description']);
        
        if ($stmt->execute()) {
            $success = "Kamar berhasil ditambahkan!";
        } else {
            $error = "Gagal menambahkan kamar: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = implode("<br>", $errors);
    }
}

// Proses UPDATE (Edit Kamar)
if (isset($_POST['edit_room'])) {
    $id = intval($_POST['id']);
    $room_data = [
        'room_number' => strtoupper(trim($_POST['room_number'])),
        'room_type' => trim($_POST['room_type']),
        'price' => floatval($_POST['price']),
        'description' => trim($_POST['description'])
    ];
    
    // Validasi server-side
    $errors = validateRoom($room_data);
    
    // Cek duplikasi nomor kamar (kecuali untuk kamar yang sedang diedit)
    $stmt = $conn->prepare("SELECT id FROM rooms WHERE room_number = ? AND id != ?");
    $stmt->bind_param("si", $room_data['room_number'], $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Nomor kamar sudah ada!";
    }
    $stmt->close();
    
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE rooms SET room_number=?, room_type=?, price=?, description=? WHERE id=?");
        $stmt->bind_param("ssdsi", $room_data['room_number'], $room_data['room_type'], $room_data['price'], $room_data['description'], $id);
        
        if ($stmt->execute()) {
            $success = "Kamar berhasil diupdate!";
        } else {
            $error = "Gagal mengupdate kamar: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = implode("<br>", $errors);
    }
}

// Proses DELETE (Hapus Kamar)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Cek apakah kamar sedang ditempati
    $stmt = $conn->prepare("SELECT tenant_id FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    
    if ($room && $room['tenant_id']) {
        $error = "Tidak dapat menghapus kamar yang sedang ditempati!";
    } else {
        $stmt = $conn->prepare("DELETE FROM rooms WHERE id=?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = "Kamar berhasil dihapus!";
        } else {
            $error = "Gagal menghapus kamar: " . $conn->error;
        }
    }
    $stmt->close();
}

// Proses pencarian dan filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

// Query dengan filter
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(room_number LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

if (!empty($filter_type)) {
    $where_conditions[] = "room_type = ?";
    $params[] = $filter_type;
    $types .= 's';
}

if (!empty($filter_status)) {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$sql = "SELECT * FROM rooms";
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(' AND ', $where_conditions);
}
$sql .= " ORDER BY room_number";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
$rooms = $result->fetch_all(MYSQLI_ASSOC);

// Ambil data untuk edit (jika ada parameter edit)
$edit_room = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM rooms WHERE id=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_room = $result->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kamar - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .invalid-feedback {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Admin Panel</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link active" href="rooms.php">Kelola Kamar</a>
                <a class="nav-link" href="tenants.php">Data Penyewa</a>
                <a class="nav-link" href="payments.php">Pembayaran</a>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Kelola Data Kamar</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form Tambah/Edit Kamar -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><?= $edit_room ? 'Edit Kamar' : 'Tambah Kamar Baru' ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" id="roomForm">
                    <?php if ($edit_room): ?>
                        <input type="hidden" name="id" value="<?= $edit_room['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="room_number" class="form-label">Nomor Kamar</label>
                                <input type="text" class="form-control" id="room_number" name="room_number" 
                                       value="<?= $edit_room ? $edit_room['room_number'] : '' ?>" 
                                       pattern="[A-Z]\d{2}" 
                                       title="Format: 1 huruf kapital diikuti 2 angka (contoh: A01)"
                                       maxlength="3" 
                                       placeholder="Contoh: A01, B02" 
                                       required>
                                <div class="invalid-feedback">
                                    Format nomor kamar harus seperti A01, B02 (1 huruf + 2 angka)
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="room_type" class="form-label">Tipe Kamar</label>
                                <select class="form-control" id="room_type" name="room_type" required>
                                    <option value="">Pilih Tipe</option>
                                    <option value="Standard" <?= ($edit_room && $edit_room['room_type'] == 'Standard') ? 'selected' : '' ?>>Standard</option>
                                    <option value="Premium" <?= ($edit_room && $edit_room['room_type'] == 'Premium') ? 'selected' : '' ?>>Premium</option>
                                    <option value="Deluxe" <?= ($edit_room && $edit_room['room_type'] == 'Deluxe') ? 'selected' : '' ?>>Deluxe</option>
                                </select>
                                <div class="invalid-feedback">
                                    Pilih tipe kamar
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="price" class="form-label">Harga per Bulan (Rp)</label>
                                <input type="number" class="form-control" id="price" name="price" 
                                       value="<?= $edit_room ? $edit_room['price'] : '' ?>" 
                                       min="100000" 
                                       step="1000" 
                                       placeholder="Minimal 100000" 
                                       required>
                                <div class="invalid-feedback">
                                    Harga harus minimal Rp 100.000
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  maxlength="500" 
                                  placeholder="Deskripsi kamar (maksimal 500 karakter)"><?= $edit_room ? htmlspecialchars($edit_room['description']) : '' ?></textarea>
                        <small class="text-muted">
                            <span id="charCount">0</span>/500 karakter
                        </small>
                    </div>
                    
                    <button type="submit" name="<?= $edit_room ? 'edit_room' : 'add_room' ?>" class="btn btn-primary">
                        <?= $edit_room ? 'Update Kamar' : 'Tambah Kamar' ?>
                    </button>
                    
                    <?php if ($edit_room): ?>
                        <a href="rooms.php" class="btn btn-secondary">Batal</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Filter -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Cari nomor kamar atau deskripsi..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" name="filter_type">
                            <option value="">Semua Tipe</option>
                            <option value="Standard" <?= $filter_type == 'Standard' ? 'selected' : '' ?>>Standard</option>
                            <option value="Premium" <?= $filter_type == 'Premium' ? 'selected' : '' ?>>Premium</option>
                            <option value="Deluxe" <?= $filter_type == 'Deluxe' ? 'selected' : '' ?>>Deluxe</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" name="filter_status">
                            <option value="">Semua Status</option>
                            <option value="available" <?= $filter_status == 'available' ? 'selected' : '' ?>>Available</option>
                            <option value="occupied" <?= $filter_status == 'occupied' ? 'selected' : '' ?>>Occupied</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Cari</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Daftar Kamar -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Daftar Kamar</h5>
                <span class="badge bg-secondary">Total: <?= count($rooms) ?> kamar</span>
            </div>
            <div class="card-body">
                <?php if (empty($rooms)): ?>
                    <p class="text-center">Tidak ada data kamar</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nomor Kamar</th>
                                <th>Tipe</th>
                                <th>Harga</th>
                                <th>Status</th>
                                <th>Deskripsi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($rooms as $room): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($room['room_number']) ?></strong></td>
                                <td><?= htmlspecialchars($room['room_type']) ?></td>
                                <td>Rp <?= number_format($room['price'], 0, ',', '.') ?></td>
                                <td>
                                    <span class="badge <?= $room['status'] == 'available' ? 'bg-success' : 'bg-warning' ?>">
                                        <?= $room['status'] == 'available' ? 'Tersedia' : 'Terisi' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($room['description']) ?></td>
                                <td>
                                    <a href="rooms.php?edit=<?= $room['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <?php if ($room['status'] == 'available'): ?>
                                        <a href="rooms.php?delete=<?= $room['id'] ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Yakin ingin menghapus kamar ini?')">Hapus</a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-danger" disabled title="Kamar sedang ditempati">Hapus</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Character counter untuk deskripsi
    const description = document.getElementById('description');
    const charCount = document.getElementById('charCount');
    
    function updateCharCount() {
        charCount.textContent = description.value.length;
    }
    
    description.addEventListener('input', updateCharCount);
    updateCharCount(); // Initial count
    
    // Validasi form
    document.getElementById('roomForm').addEventListener('submit', function(e) {
        let isValid = true;
        const roomNumber = document.getElementById('room_number');
        const roomType = document.getElementById('room_type');
        const price = document.getElementById('price');
        
        // Reset validation states
        [roomNumber, roomType, price].forEach(field => {
            field.classList.remove('is-invalid');
        });
        
        // Validasi nomor kamar
        const roomNumberPattern = /^[A-Z]\d{2}$/;
        if (!roomNumberPattern.test(roomNumber.value.toUpperCase())) {
            roomNumber.classList.add('is-invalid');
            isValid = false;
        }
        
        // Validasi tipe kamar
        if (!roomType.value) {
            roomType.classList.add('is-invalid');
            isValid = false;
        }
        
        // Validasi harga
        if (isNaN(price.value) || price.value < 100000) {
            price.classList.add('is-invalid');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            e.stopPropagation();
        }
    });
    
    // Real-time validation untuk nomor kamar
    document.getElementById('room_number').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
        const pattern = /^[A-Z]\d{0,2}$/;
        
        if (this.value && !pattern.test(this.value)) {
            this.classList.add('is-invalid');
        } else {~
            this.classList.remove('is-invalid');
        }
    });
    
    // Real-time validation untuk harga
    document.getElementById('price').addEventListener('input', function() {
        if (this.value < 0) this.value = 0;
        
        if (this.value && this.value < 100000) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
    
    // Real-time validation untuk tipe kamar
    document.getElementById('room_type').addEventListener('change', function() {
        if (this.value) {
            this.classList.remove('is-invalid');
        }
    });
    </script>
</body>
</html>