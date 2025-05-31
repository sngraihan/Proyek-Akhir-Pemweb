<?php
session_start();
require_once 'config/database.php';

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: tenant/dashboard.php');
    }
    exit();
}

// Proses login
if ($_POST) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validasi input
    if (empty($username) || empty($password)) {
        $error = "Username dan password harus diisi!";
    } else {
        // Cek kredensial menggunakan prepared statement
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verifikasi password
            if (password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect berdasarkan role
                if ($user['role'] == 'admin') {
                    header('Location: admin/dashboard.php');
                } else {
                    header('Location: tenant/dashboard.php');
                }
                exit();
            } else {
                $error = "Password salah!";
            }
        } else {
            $error = "Username tidak ditemukan!";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Manajemen Kos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="text-center">Login Sistem Kos</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                        
                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                Demo: admin/password untuk Admin
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
// Validasi form login
document.querySelector('form').addEventListener('submit', function(e) {
    const username = document.getElementById('username');
    const password = document.getElementById('password');
    let isValid = true;
    
    // Reset validation
    [username, password].forEach(field => {
        field.classList.remove('is-invalid');
        // Remove any existing error messages
        const existingError = field.nextElementSibling;
        if (existingError && existingError.classList.contains('invalid-feedback')) {
            existingError.remove();
        }
    });
    
    // Validasi username
    if (username.value.length < 3) {
        username.classList.add('is-invalid');
        username.insertAdjacentHTML('afterend', '<div class="invalid-feedback">Username minimal 3 karakter</div>');
        isValid = false;
    }
    
    // Validasi password
    if (password.value.length < 6) {
        password.classList.add('is-invalid');
        password.insertAdjacentHTML('afterend', '<div class="invalid-feedback">Password minimal 6 karakter</div>');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        e.stopPropagation();
    }
});

// Real-time validation
document.getElementById('username').addEventListener('input', function() {
    if (this.value.length >= 3) {
        this.classList.remove('is-invalid');
        const error = this.nextElementSibling;
        if (error && error.classList.contains('invalid-feedback')) {
            error.remove();
        }
    }
});

document.getElementById('password').addEventListener('input', function() {
    if (this.value.length >= 6) {
        this.classList.remove('is-invalid');
        const error = this.nextElementSibling;
        if (error && error.classList.contains('invalid-feedback')) {
            error.remove();
        }
    }
});
</script>                       
</body>
</html>

