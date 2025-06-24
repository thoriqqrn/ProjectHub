<?php
session_start(); // Mulai sesi

// Periksa apakah pengguna sudah login dan user_id ada di sesi
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php?status=error&msg=Silakan%20login%20terlebih%20dulu");
    exit();
}

// Sertakan file koneksi database
require_once 'db.php'; // Pastikan path ini sesuai

$user_id = $_SESSION['user_id'];

// Periksa apakah request yang datang adalah metode POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Ambil data pengaturan dari $_POST
    $setting_id = $_POST['setting_id'] ?? null; // Akan ada jika edit, null jika baru
    $total_value = $_POST['total_value'] ?? '';
    $minimum_passing = $_POST['minimum_passing'] ?? '';
    $upper_bounds = $_POST['upper_bound'] ?? []; // Array dari input tabel
    $lower_bounds = $_POST['lower_bound'] ?? []; // Array dari input tabel
    $predicate_names = $_POST['predicate_name'] ?? []; // Array dari input tabel

    // Validasi dasar (Anda bisa menambahkan validasi yang lebih ketat)
    if ($total_value === '' || $minimum_passing === '' || !is_numeric($total_value) || !is_numeric($minimum_passing)) {
         $error_message = urlencode("Total Nilai dan Nilai Minimum Lulus harus diisi dengan angka.");
         header("Location: ../pages/settings.php?status=error&msg=" . $error_message);
         exit();
    }

    // Konversi ke integer
    $total_value = (int)$total_value;
    $minimum_passing = (int)$minimum_passing;

    // Lakukan validasi pada data predikat jika ada
    if (!empty($upper_bounds) || !empty($lower_bounds) || !empty($predicate_names)) {
        // Pastikan jumlah elemen di semua array predikat sama
        if (count($upper_bounds) !== count($lower_bounds) || count($upper_bounds) !== count($predicate_names)) {
            $error_message = urlencode("Data predikat tidak lengkap.");
            header("Location: ../pages/settings.php?status=error&msg=" . $error_message);
            exit();
        }

        // Validasi setiap baris predikat
        foreach ($upper_bounds as $index => $upper) {
            $lower = $lower_bounds[$index] ?? '';
            $name = $predicate_names[$index] ?? '';

            if ($upper === '' || $lower === '' || $name === '' || !is_numeric($upper) || !is_numeric($lower)) {
                $error_message = urlencode("Semua kolom predikat harus diisi dengan benar.");
                header("Location: ../pages/settings.php?status=error&msg=" . $error_message);
                 exit();
            }
             // Konversi ke integer dan sanitasi nama
             $upper_bounds[$index] = (int)$upper;
             $lower_bounds[$index] = (int)$lower;
             $predicate_names[$index] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

             // Anda bisa menambahkan validasi tambahan, misal batas atas > batas bawah, rentang tidak tumpang tindih, dll.
        }
    }


    // Mulai transaksi database
    mysqli_begin_transaction($conn);

    try {
        // 1. Simpan data ke tabel basdat_grade_settings (INSERT atau UPDATE)
        if ($setting_id) {
            // Jika setting_id ada, lakukan UPDATE
            $sql_update_settings = "UPDATE grade_settings SET total_value = ?, minimum_passing = ? WHERE setting_id = ? AND user_id = ?";
            $stmt_update_settings = mysqli_prepare($conn, $sql_update_settings);

            if ($stmt_update_settings === false) {
                 throw new Exception("Prepare failed for settings update: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt_update_settings, "iiii", $total_value, $minimum_passing, $setting_id, $user_id);

            if (!mysqli_stmt_execute($stmt_update_settings)) {
                 throw new Exception("Error executing settings update: " . mysqli_stmt_error($stmt_update_settings));
            }
            mysqli_stmt_close($stmt_update_settings);

            // 2. Hapus predikat lama untuk setting_id ini sebelum insert yang baru
            $sql_delete_predicates = "DELETE FROM grade_predicates WHERE setting_id = ?";
            $stmt_delete_predicates = mysqli_prepare($conn, $sql_delete_predicates);

            if ($stmt_delete_predicates === false) {
                 throw new Exception("Prepare failed for predicates delete: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt_delete_predicates, "i", $setting_id);

            if (!mysqli_stmt_execute($stmt_delete_predicates)) {
                throw new Exception("Error executing predicates delete: " . mysqli_stmt_error($stmt_delete_predicates));
            }
            mysqli_stmt_close($stmt_delete_predicates);

        } else {
            // Jika setting_id tidak ada, lakukan INSERT baru
            $sql_insert_settings = "INSERT INTO grade_settings (user_id, total_value, minimum_passing) VALUES (?, ?, ?)";
            $stmt_insert_settings = mysqli_prepare($conn, $sql_insert_settings);

             if ($stmt_insert_settings === false) {
                 throw new Exception("Prepare failed for settings insert: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt_insert_settings, "iii", $user_id, $total_value, $minimum_passing);

            if (!mysqli_stmt_execute($stmt_insert_settings)) {
                throw new Exception("Error executing settings insert: " . mysqli_stmt_error($stmt_insert_settings));
            }

            // Ambil setting_id yang baru saja di-generate
            $setting_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_insert_settings);
        }

        // 3. Insert predikat baru ke tabel basdat_grade_predicates
        if (!empty($upper_bounds)) {
            $sql_insert_predicate = "INSERT INTO grade_predicates (setting_id, upper_bound, lower_bound, predicate_name) VALUES (?, ?, ?, ?)";
            $stmt_insert_predicate = mysqli_prepare($conn, $sql_insert_predicate);

             if ($stmt_insert_predicate === false) {
                 throw new Exception("Prepare failed for predicate insert: " . mysqli_error($conn));
            }

            // Loop melalui data predikat yang sudah divalidasi dan disanitasi
            foreach ($upper_bounds as $index => $upper) {
                $lower = $lower_bounds[$index];
                $name = $predicate_names[$index]; // Gunakan data yang sudah disanitasi

                // Bind parameter untuk setiap baris predikat
                mysqli_stmt_bind_param($stmt_insert_predicate, "iiis", $setting_id, $upper, $lower, $name);

                // Eksekusi insert predikat
                if (!mysqli_stmt_execute($stmt_insert_predicate)) {
                     throw new Exception("Error executing predicate insert: " . mysqli_stmt_error($stmt_insert_predicate));
                }
            }
            mysqli_stmt_close($stmt_insert_predicate);
        }


        // Jika semua operasi database berhasil, commit transaksi
        mysqli_commit($conn);

        // Redirect kembali ke halaman settings dengan pesan sukses
        $success_message = urlencode("Pengaturan berhasil disimpan!");
        header("Location: ../pages/settings.php?status=success&msg=" . $success_message);
        exit();

    } catch (Exception $e) {
        // Jika terjadi error di mana pun dalam blok try, lakukan rollback transaksi
        mysqli_rollback($conn);

        // Log error detail di sisi server
        error_log("Settings Save Error: " . $e->getMessage()); // Catat error di log PHP

        // Redirect kembali ke halaman settings dengan pesan error umum
        $error_message = urlencode("Terjadi kesalahan saat menyimpan pengaturan. Silakan coba lagi."); // Pesan umum untuk pengguna
        header("Location: ../pages/settings.php?status=error&msg=" . $error_message);
        exit();
    }

    // Tutup koneksi database (opsional, akan ditutup otomatis di akhir skrip)
    mysqli_close($conn);

} else {
    // Jika diakses bukan dari POST, redirect kembali ke halaman settings
    header("Location: ../pages/settings.php");
    exit();
}
?>