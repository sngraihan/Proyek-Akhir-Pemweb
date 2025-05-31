<?php
function validateRoom($data) {
    $errors = [];
    
    // Validasi nomor kamar
    if (empty($data['room_number'])) {
        $errors[] = "Nomor kamar harus diisi";
    } elseif (!preg_match('/^[A-Z]\d{2}$/', strtoupper($data['room_number']))) {
        $errors[] = "Format nomor kamar harus seperti A01, B02, dll (1 huruf kapital + 2 angka)";
    }
    
    // Validasi tipe kamar
    $valid_types = ['Standard', 'Premium', 'Deluxe'];
    if (empty($data['room_type'])) {
        $errors[] = "Tipe kamar harus dipilih";
    } elseif (!in_array($data['room_type'], $valid_types)) {
        $errors[] = "Tipe kamar tidak valid";
    }
    
    // Validasi harga
    if (!is_numeric($data['price']) || $data['price'] <= 0) {
        $errors[] = "Harga harus berupa angka positif";
    } elseif ($data['price'] < 100000) {
        $errors[] = "Harga minimal Rp 100.000";
    }
    
    // Validasi deskripsi
    if (strlen($data['description']) > 500) {
        $errors[] = "Deskripsi maksimal 500 karakter";
    }
    
    return $errors;
}

function validateUser($data) {
    $errors = [];
    
    // Validasi username
    if (empty($data['username'])) {
        $errors[] = "Username harus diisi";
    } elseif (strlen($data['username']) < 3) {
        $errors[] = "Username minimal 3 karakter";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
        $errors[] = "Username hanya boleh huruf, angka, dan underscore";
    }
    
    // Validasi password (hanya untuk user baru atau jika diisi)
    if (isset($data['password']) && !empty($data['password'])) {
        if (strlen($data['password']) < 6) {
            $errors[] = "Password minimal 6 karakter";
        }
    } elseif (isset($data['password']) && empty($data['password']) && !isset($data['id'])) {
        // Password wajib untuk user baru
        $errors[] = "Password harus diisi";
    }
    
    // Validasi nama lengkap
    if (empty($data['full_name'])) {
        $errors[] = "Nama lengkap harus diisi";
    } elseif (strlen($data['full_name']) < 3) {
        $errors[] = "Nama lengkap minimal 3 karakter";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $data['full_name'])) {
        $errors[] = "Nama lengkap hanya boleh huruf dan spasi";
    }
    
    return $errors;
}

function validatePayment($data) {
    $errors = [];
    
    if (empty($data['tenant_id'])) {
        $errors[] = "Penyewa harus dipilih";
    }
    
    if (empty($data['amount']) || $data['amount'] <= 0) {
        $errors[] = "Jumlah pembayaran harus lebih dari 0";
    }
    
    if (empty($data['payment_month'])) {
        $errors[] = "Bulan pembayaran harus diisi";
    }
    
    if (empty($data['payment_date'])) {
        $errors[] = "Tanggal pembayaran harus diisi";
    }
    
    // Validasi status
    $valid_status = ['paid', 'pending', 'overdue'];
    if (!in_array($data['status'], $valid_status)) {
        $errors[] = "Status pembayaran tidak valid";
    }
    
    return $errors;
}
?>