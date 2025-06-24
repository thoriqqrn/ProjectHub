<?php
session_start(); // Pastikan ini di baris paling atas

// Pastikan path ini sesuai dengan struktur folder kamu.
// Jika db.php ada di folder backend bersama auth.php, path-nya sudah benar.
require 'db.php';

$mode = $_POST['mode'] ?? ''; // Gunakan null coalescing operator untuk default empty string
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if ($mode === 'register') {
    // Lakukan sanitasi dasar pada username sebelum query
    $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

    // Cek apakah username sudah ada
    // Menggunakan prepared statement untuk mencegah SQL Injection
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?"); // Ambil id juga
    if ($stmt === false) {
        // Handle error prepare statement
        error_log("Prepare failed for username check: " . $conn->error);
        header("Location: ../pages/login.php?status=error&msg=Terjadi%20kesalahan%20sistem");
        exit();
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt->close();
        header("Location: ../pages/login.php?status=error&msg=Username%20sudah%20terdaftar");
        exit();
    }
    $stmt->close();

    // Lakukan sanitasi dasar pada password jika diperlukan (meskipun password_hash sudah aman)
    // $password = htmlspecialchars($password, ENT_QUOTES, 'UTF-8'); // Biasanya tidak perlu sanitasi untuk password sebelum hash

    // Hash password sebelum disimpan
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    // Simpan user baru ke database
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
     if ($stmt === false) {
        // Handle error prepare statement
        error_log("Prepare failed for register insert: " . $conn->error);
        header("Location: ../pages/login.php?status=error&msg=Terjadi%20kesalahan%20sistem");
        exit();
    }
    $stmt->bind_param("ss", $username, $hashed); // "ss" karena keduanya string

    if ($stmt->execute()) {
        $stmt->close();
        // Redirect ke halaman login setelah berhasil register
        header("Location: ../pages/login.php?status=success&msg=Berhasil%20register.%20Silakan%20login.");
    } else {
        $stmt->close();
        // Handle error eksekusi
        error_log("Error executing register insert: " . $stmt->error);
        header("Location: ../pages/login.php?status=error&msg=Gagal%20register");
    }

} elseif ($mode === 'login') {
     // Lakukan sanitasi dasar pada username sebelum query
    $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

    // Cek apakah user ada berdasarkan username
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?"); // Ambil id, username, dan password
     if ($stmt === false) {
        // Handle error prepare statement
        error_log("Prepare failed for login select: " . $conn->error);
        header("Location: ../pages/login.php?status=error&msg=Terjadi%20kesalahan%20sistem");
        exit();
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc(); // Ambil data user sebagai array asosiatif
    $stmt->close(); // Tutup statement setelah mendapatkan hasil

    // Verifikasi password menggunakan password_verify
    // Pastikan $user ada (username ditemukan) dan password cocok
    if ($user && password_verify($password, $user['password'])) {
        // Login berhasil
        // SETEL VARIABEL SESI
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_id'] = $user['id']; // <--- BARIS PENTING INI YANG DITAMBAHKAN/DIPASTIKAN ADA

        // Redirect ke halaman utama atau halaman form
        // Menggunakan index.php sesuai kode Anda, atau bisa diubah ke form.php
        header("Location: ../pages/index.php"); // Bisa juga diarahkan ke ../pages/form.php
        exit(); // Penting setelah header redirect

    } else {
        // Login gagal (username tidak ditemukan atau password salah)
        $error_message = urlencode("Password atau username salah.");
        header("Location: ../pages/login.php?status=error&msg=" . $error_message);
        exit(); // Penting setelah header redirect
    }

} else {
    // Jika mode tidak 'register' atau 'login'
    $error_message = urlencode("Mode tidak valid.");
    header("Location: ../pages/login.php?status=error&msg=" . $error_message);
    exit(); // Penting setelah header redirect
}

// Tutup koneksi database jika belum ditutup di db.php (opsional, koneksi akan otomatis ditutup di akhir skrip)
// mysqli_close($conn);
?>