<?php
session_start(); // Mulai sesi

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) { // Pastikan user_id juga ada di sesi
    // Jika belum login, redirect ke halaman login
    header("Location: ../pages/login.php?status=error&msg=Silakan%20login%20terlebih%20dulu");
    exit();
}

// Sertakan file koneksi database
// Pastikan path ini benar sesuai lokasi file db.php
require_once 'db.php';

// Periksa apakah request yang datang adalah POST (dari pengiriman form)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Ambil user_id dari sesi
    $user_id = $_SESSION['user_id'];

    // Ambil data proyek dari $_POST dan lakukan sanitasi dasar
    $project_name = htmlspecialchars($_POST['project_name'] ?? ''); // Gunakan ?? '' untuk menangani jika field tidak ada
    $project_chapter = htmlspecialchars($_POST['project_chapter'] ?? '');
    $examiner_notes = htmlspecialchars($_POST['examiner_notes'] ?? '');

    // Validasi dasar
    if (empty($project_name) || empty($project_chapter)) {
        // Redirect kembali ke form dengan pesan error jika validasi gagal
        header("Location: ../pages/form.php?status=error&msg=Nama%20Proyek%20dan%20Chapter%20tidak%20boleh%20kosong");
        exit();
    }

    // Mulai transaksi database
    // Ini penting untuk memastikan data terkait proyek, parameter, dan sub-aspek
    // semuanya tersimpan atau tidak sama sekali (transaksi atomik)
    mysqli_begin_transaction($conn);

    try {
        // 1. Insert data ke tabel basdat_projects
        $sql_project = "INSERT INTO projects (user_id, project_name, project_chapter, examiner_notes, created_at, last_submitted)
                        VALUES (?, ?, ?, ?, NOW(), NOW())";
        $stmt_project = mysqli_prepare($conn, $sql_project);
        // "isss" artinya integer, string, string, string
        mysqli_stmt_bind_param($stmt_project, "isss", $user_id, $project_name, $project_chapter, $examiner_notes);

        if (!mysqli_stmt_execute($stmt_project)) {
            // Jika gagal, lempar exception untuk memicu rollback
            throw new Exception("Error inserting project: " . mysqli_error($conn));
        }

        // Ambil project_id yang baru saja di-generate
        $project_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_project); // Tutup statement setelah selesai

        // 2. Insert data parameter dan sub-aspek
        // Periksa apakah ada data parameter yang dikirim
        if (isset($_POST['parameter_name']) && is_array($_POST['parameter_name'])) {
            $parameter_names = $_POST['parameter_name'];
            $sub_aspect_names_by_param = $_POST['sub_aspect_name']; // Ini akan menjadi array 2 dimensi
            $error_counts_by_param = $_POST['error_count']; // Ini akan menjadi array 2 dimensi

            // Loop melalui setiap parameter yang dikirim
            foreach ($parameter_names as $param_index => $parameter_name) {
                $parameter_name = htmlspecialchars($parameter_name);

                // Insert ke tabel basdat_evaluation_parameters
                $sql_parameter = "INSERT INTO evaluation_parameters (project_id, parameter_name) VALUES (?, ?)";
                $stmt_parameter = mysqli_prepare($conn, $sql_parameter);
                // "is" artinya integer, string
                mysqli_stmt_bind_param($stmt_parameter, "is", $project_id, $parameter_name);

                if (!mysqli_stmt_execute($stmt_parameter)) {
                    throw new Exception("Error inserting parameter: " . mysqli_error($conn));
                }

                // Ambil parameter_id yang baru saja di-generate
                $parameter_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt_parameter); // Tutup statement

                // 3. Insert data sub-aspek untuk parameter ini
                // Periksa apakah ada sub-aspek untuk parameter dengan indeks saat ini
                if (isset($sub_aspect_names_by_param[$param_index]) && is_array($sub_aspect_names_by_param[$param_index])) {
                    $current_sub_aspect_names = $sub_aspect_names_by_param[$param_index];
                    $current_error_counts = $error_counts_by_param[$param_index];

                    // Statement untuk insert sub-aspek (siapkan sekali di luar loop sub-aspek)
                    $sql_sub_aspect = "INSERT INTO sub_aspects (parameter_id, aspect_name, error_count) VALUES (?, ?, ?)";
                    $stmt_sub_aspect = mysqli_prepare($conn, $sql_sub_aspect);

                    // Loop melalui setiap sub-aspek untuk parameter saat ini
                    foreach ($current_sub_aspect_names as $sub_index => $sub_aspect_name) {
                        $sub_aspect_name = htmlspecialchars($sub_aspect_name);
                        // Konversi error_count ke integer, gunakan 0 jika tidak valid atau kosong
                        $error_count = isset($current_error_counts[$sub_index]) ? (int)$current_error_counts[$sub_index] : 0;
                         // Pastikan error_count tidak negatif
                         if ($error_count < 0) {
                             $error_count = 0;
                         }


                        // Bind parameter dan eksekusi statement
                        // "isi" artinya integer, string, integer
                        mysqli_stmt_bind_param($stmt_sub_aspect, "isi", $parameter_id, $sub_aspect_name, $error_count);

                        if (!mysqli_stmt_execute($stmt_sub_aspect)) {
                             throw new Exception("Error inserting sub-aspect: " . mysqli_error($conn));
                        }
                    }
                    mysqli_stmt_close($stmt_sub_aspect); // Tutup statement sub-aspek setelah selesai
                }
            }
        }

        // Jika semua operasi database berhasil, commit transaksi
        mysqli_commit($conn);

        // Redirect ke halaman sukses atau halaman hasil, mungkin dengan project_id
        // untuk menampilkan detail proyek yang baru disimpan
        header("Location: ../pages/result.php?status=success&project_id=" . $project_id);
        exit();

    } catch (Exception $e) {
        // Jika terjadi error di mana pun dalam blok try, lakukan rollback transaksi
        mysqli_rollback($conn);

        // --- START KODE DEBUGGING SEMENTARA ---

        // Catat error detail di log server (seharusnya tetap berjalan jika logging aktif)
        error_log("Form Submission Error: " . $e->getMessage());

        // Tampilkan pesan error detail langsung di browser (sementara)
        echo "<h1>Terjadi Kesalahan Database</h1>";
        echo "<p>Detail kesalahan saat menyimpan data:</p>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>"; // Tampilkan pesan exception
        // Anda juga bisa menampilkan pesan error MySQL yang lebih spesifik jika $e->getMessage() kurang detail
        // if (mysqli_error($conn)) {
        //      echo "<p><strong>MySQL Error:</strong> " . htmlspecialchars(mysqli_error($conn)) . "</p>";
        // }
        echo "<p>Catat pesan error di atas dan gunakan untuk debugging.</p>";
        echo "<p><strong>INGAT:</strong> Halaman ini menampilkan error detail untuk tujuan debugging. Harap kembalikan kode ke versi sebelumnya setelah selesai.</p>";

        // HENTIKAN EKSEKUSI agar tidak ada redirect
        exit(); // Gunakan exit() di sini untuk menghentikan skrip setelah menampilkan error

        // --- END KODE DEBUGGING SEMENTARA ---


        // --- KODE ASLI (YANG AKAN DIKEMBALIKAN NANTI) ---
        /*
        // Redirect kembali ke form dengan pesan error yang lebih umum
        $error_message = urlencode("Terjadi kesalahan saat menyimpan data. Silakan coba lagi. (Error code: " . substr(md5($e->getMessage()), 0, 6) . ")"); // Contoh pesan error umum
        header("Location: ../pages/form.php?status=error&msg=" . $error_message);
        exit();
        */
    }

    // Tutup koneksi database setelah selesai
    mysqli_close($conn);

} else {
    // Jika halaman ini diakses langsung tanpa melalui method POST,
    // redirect kembali ke halaman form
    header("Location: ../pages/form.php");
    exit();
}
?>