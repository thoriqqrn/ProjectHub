<?php
session_start(); // Mulai sesi

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    // Jika belum login, redirect ke halaman login (sesuaikan path jika login.php tidak di folder pages/)
    header("Location: login.php?status=error&msg=Silakan%20login%20terlebih%20dulu");
    exit();
}

// Sertakan file koneksi database
// Sesuaikan path ini agar sesuai dengan lokasi db.php Anda
require_once '../backend/db.php'; // Contoh: db.php ada di folder backend, satu level di atas pages

$user_id = $_SESSION['user_id']; // Ambil user_id dari sesi

// Ambil project_id dari parameter URL
$project_id = $_GET['project_id'] ?? null;

// Validasi project_id
if (!isset($project_id) || !is_numeric($project_id)) {
    // Jika project_id tidak ada atau tidak valid, redirect atau tampilkan pesan error
    header("Location: dashboard.php?status=error&msg=ID%20Proyek%20tidak%20valid%20atau%20tidak%20ditemukan");
    exit();
}

$project_id = (int)$project_id; // Pastikan ini adalah integer

// --- Ambil Data Proyek ---
// Penting: Ambil hanya proyek milik user yang sedang login
$sql_project = "SELECT * FROM projects WHERE project_id = ? AND user_id = ?";
$stmt_project = mysqli_prepare($conn, $sql_project);

$project = null; // Inisialisasi $project

if ($stmt_project) {
    mysqli_stmt_bind_param($stmt_project, "ii", $project_id, $user_id);
    mysqli_stmt_execute($stmt_project);
    $result_project = mysqli_stmt_get_result($stmt_project);
    $project = mysqli_fetch_assoc($result_project);
    mysqli_stmt_close($stmt_project);

    // Periksa apakah proyek ditemukan dan milik pengguna yang login
    if (!$project) {
        // Proyek tidak ditemukan atau bukan milik pengguna ini
        header("Location: dashboard.php?status=error&msg=Proyek%20tidak%20ditemukan%20atau%20Anda%20tidak%20memiliki%20izin%20untuk%20melihatnya");
        exit();
    }

    // --- Ambil Data Parameter Evaluasi ---
    $sql_parameters = "SELECT * FROM evaluation_parameters WHERE project_id = ?";
    $stmt_parameters = mysqli_prepare($conn, $sql_parameters);

    $parameters = [];
    $parameter_ids = [];

    if ($stmt_parameters) {
        mysqli_stmt_bind_param($stmt_parameters, "i", $project_id);
        mysqli_stmt_execute($stmt_parameters);
        $result_parameters = mysqli_stmt_get_result($stmt_parameters);

        while ($row = mysqli_fetch_assoc($result_parameters)) {
            $parameters[$row['parameter_id']] = $row; // Simpan parameter dengan parameter_id sebagai kunci
            $parameters[$row['parameter_id']]['sub_aspects'] = []; // Tambahkan array kosong untuk sub-aspek
            $parameters[$row['parameter_id']]['total_errors'] = 0; // Tambahkan field untuk total error per parameter
            $parameter_ids[] = $row['parameter_id']; // Kumpulkan semua parameter_id
        }
        mysqli_stmt_close($stmt_parameters);

        // --- Ambil Data Sub-Aspek ---
        if (!empty($parameter_ids)) {
            // Buat placeholder untuk IN clause (misal: ?, ?, ?)
            $placeholders = implode(',', array_fill(0, count($parameter_ids), '?'));
            $sql_sub_aspects = "SELECT * FROM sub_aspects WHERE parameter_id IN ($placeholders) ORDER BY aspect_name";
            $stmt_sub_aspects = mysqli_prepare($conn, $sql_sub_aspects);

            if ($stmt_sub_aspects) {
                // Buat string tipe parameter untuk bind_param (misal: "iii" jika ada 3 parameter_id)
                $types = str_repeat('i', count($parameter_ids));
                // Bind parameter_ids ke statement
                mysqli_stmt_bind_param($stmt_sub_aspects, $types, ...$parameter_ids);

                mysqli_stmt_execute($stmt_sub_aspects);
                $result_sub_aspects = mysqli_stmt_get_result($stmt_sub_aspects);

                while ($row = mysqli_fetch_assoc($result_sub_aspects)) {
                    // Kelompokkan sub-aspek di bawah parameter_id yang sesuai
                    if (isset($parameters[$row['parameter_id']])) { // Pastikan parameter_id ada
                        $parameters[$row['parameter_id']]['sub_aspects'][] = $row;
                        // --- Hitung total error per parameter saat mengelompokkan sub-aspek ---
                        $parameters[$row['parameter_id']]['total_errors'] += $row['error_count'];
                    }
                }
                mysqli_stmt_close($stmt_sub_aspects);
            } else {
                error_log("Error preparing sub_aspects query: " . mysqli_error($conn));
            }
        }
    } else {
        error_log("Error preparing parameters query: " . mysqli_error($conn));
    }
} else {
    error_log("Error preparing project query: " . mysqli_error($conn));
    header("Location: dashboard.php?status=error&msg=Terjadi%20kesalahan%20sistem");
    exit();
}

// --- Hitung Total Keseluruhan Error ---
$overall_total_errors = 0;
foreach ($parameters as $param) {
    $overall_total_errors += $param['total_errors']; // Jumlahkan total error dari setiap parameter
}

// --- Ambil Pengaturan Nilai dan Predikat dari Database ---
$settings = null;
$predicates = [];
$settings_found = false;

$sql_settings = "SELECT * FROM grade_settings WHERE user_id = ?";
$stmt_settings = mysqli_prepare($conn, $sql_settings);

if ($stmt_settings) {
    mysqli_stmt_bind_param($stmt_settings, "i", $user_id);
    mysqli_stmt_execute($stmt_settings);
    $result_settings = mysqli_stmt_get_result($stmt_settings);
    $settings = mysqli_fetch_assoc($result_settings);
    mysqli_stmt_close($stmt_settings);

    if ($settings) {
        $settings_found = true;
        $setting_id = $settings['setting_id'];

        // Ambil predikat terkait pengaturan ini
        $sql_predicates = "SELECT * FROM grade_predicates WHERE setting_id = ? ORDER BY lower_bound DESC"; // Urutkan dari batas bawah terbesar
        $stmt_predicates = mysqli_prepare($conn, $sql_predicates);

        if ($stmt_predicates) {
            mysqli_stmt_bind_param($stmt_predicates, "i", $setting_id);
            mysqli_stmt_execute($stmt_predicates);
            $result_predicates = mysqli_stmt_get_result($stmt_predicates);
            while ($row = mysqli_fetch_assoc($result_predicates)) {
                $predicates[] = $row;
            }
            mysqli_stmt_close($stmt_predicates);
        } else {
            error_log("Error preparing predicates query: " . mysqli_error($conn));
            // Jika query predikat gagal, anggap settings tidak lengkap dan gunakan default
            $settings_found = false;
        }
    }
} else {
    error_log("Error preparing settings query: " . mysqli_error($conn));
    // Jika query settings gagal, gunakan default
    $settings_found = false;
}


// Tutup koneksi database setelah selesai (jika belum ditutup)
mysqli_close($conn);


// --- Hitung Total Nilai, Predikat, dan Status Berdasarkan Pengaturan atau Default ---

$total_score = 0;
$predicate = 'Tidak Diketahui';
$status = 'Tidak Diketahui';
$settings_message = '';

if ($settings_found) {
    // Gunakan pengaturan dari database
    $max_score = $settings['total_value'];
    $minimum_passing = $settings['minimum_passing'];
    // Asumsi 1 error mengurangi 1 poin dari total_value
    $points_per_error = 1;

    // Hitung Total Nilai
    $total_score = $max_score - ($overall_total_errors * $points_per_error);
    $total_score = max(0, $total_score); // Pastikan nilai tidak negatif

    // Tentukan Predikat berdasarkan rentang dari database
    $predicate_found = false;
    foreach ($predicates as $p) {
        // Cek apakah total_score berada dalam rentang (inklusi batas bawah dan atas)
        if ($total_score >= $p['lower_bound'] && $total_score <= $p['upper_bound']) {
            $predicate = $p['predicate_name'];
            $predicate_found = true;
            break; // Ambil predikat pertama yang cocok (pentingnya ORDER BY lower_bound DESC)
        }
    }
    if (!$predicate_found && !empty($predicates)) {
        $predicate = 'Di Luar Rentang Predikat'; // Jika nilai tidak masuk rentang manapun
    } elseif (!$predicate_found && empty($predicates)) {
        $predicate = 'Predikat Tidak Ditetapkan'; // Jika tabel predikat kosong
    }


    // Tentukan Status berdasarkan nilai minimum lulus dari database
    $status = ($total_score >= $minimum_passing) ? 'Lulus' : 'Tidak Lulus';

    $settings_message = '<p class="text-success">Pengaturan penilaian diambil dari database.</p>';
} else {
    // Gunakan aturan Default (Hardcoded) jika pengaturan tidak ditemukan
    $settings_message = '<p class="text-warning">Pengaturan penilaian tidak ditemukan. Menggunakan nilai default.</p>';

    // Aturan Penilaian Default (Sama seperti sebelumnya)
    $max_score = 100;
    $points_per_error = 1;
    $minimum_passing = 60; // Default nilai minimum lulus

    // Hitung Total Nilai Default
    $total_score = $max_score - ($overall_total_errors * $points_per_error);
    $total_score = max(0, $total_score); // Pastikan nilai tidak negatif

    // Tentukan Predikat Default
    $predicate = 'Sangat Kurang';
    if ($total_score >= 90) {
        $predicate = 'Sangat Baik';
    } elseif ($total_score >= 80) {
        $predicate = 'Baik';
    } elseif ($total_score >= 60) {
        $predicate = 'Cukup';
    } elseif ($total_score >= 40) {
        $predicate = 'Kurang';
    }

    // Tentukan Status Default
    $status = ($total_score >= $minimum_passing) ? 'Lulus' : 'Tidak Lulus';
}

// --- Akhir Perhitungan ---

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Proyek: <?php echo htmlspecialchars($project['project_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Tambahkan atau sesuaikan CSS yang spesifik untuk laporan di sini */
        /* Ini akan menimpa style dari style.css jika selectornya sama */
        body {
            font-family: sans-serif;
            line-height: 1.6;
            margin: 0;
            /* Reset margin default */
            padding-top: 60px;
            /* Sesuaikan dengan tinggi mobile navbar */
            background-color: #f0f2f5;
            /* Pastikan background sesuai */
            color: #212529;
        }

        .navbar {
            display: none;
            /* Sembunyikan default */
            background-color: #6f42c1;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            color: white;
            font-weight: bold;
        }

        /* Sidebar Styles */
        .sidebar {
            height: 100vh;
            position: fixed;
            width: 240px;
            background-color: #fff;
            border-right: 1px solid #dee2e6;
            padding: 30px 20px;
            transition: all 0.3s;
            z-index: 1000;
            top: 0;
            left: 0;
            display: flex;
            /* Gunakan flexbox */
            flex-direction: column;
            /* Atur arah ke kolom */
        }

        .sidebar .nav-link {
            color: #6c757d;
            font-weight: 500;
            padding: 12px 20px;
            border-radius: 8px;
            transition: all 0.2s ease-in-out;
        }

        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            background-color: #6f42c1;
            color: #fff;
        }


        /* Main Content Styles */
        .main-content {
            margin-left: 260px;
            /* Sesuaikan dengan lebar sidebar + padding */
            padding: 40px 30px;
            transition: all 0.3s;
        }

        .container {
            max-width: 900px;
            margin: 20px auto;
            /* Center the container */
            background: #fff;
            padding: 30px;
            /* Adjust padding */
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
            /* Adjusted shadow */
            border-radius: 16px;
            /* Adjusted border-radius */
        }

        h1,
        h2,
        h3 {
            color: #6f42c1;
            /* Warna ungu */
            border-bottom: 2px solid #e9ecef;
            /* Garis bawah */
            padding-bottom: 10px;
            margin-top: 20px;
            margin-bottom: 20px;
        }

        h1 {
            text-align: center;
            color: #495057;
            /* Warna abu-abu tua */
        }

        .project-info p {
            margin: 8px 0;
            font-size: 1.1em;
        }

        .project-info strong {
            color: #495057;
        }

        .parameter-section {
            margin-bottom: 30px;
            padding: 20px;
            /* Adjusted padding */
            border: 1px solid #dee2e6;
            /* Border */
            border-radius: 8px;
            background-color: #f8f9fa;
            /* Light background */
        }

        .parameter-section h3 {
            border-bottom: 1px solid #ced4da;
            /* Lighter border */
            padding-bottom: 10px;
            margin-top: 0;
            margin-bottom: 15px;
            color: #495057;
            /* Warna abu-abu tua */
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            border: 1px solid #dee2e6;
            /* Match parameter border */
            padding: 12px;
            text-align: left;
        }

        th {
            background-color: #6f42c1;
            /* Warna ungu */
            color: white;
            font-weight: 600;
        }

        tr:nth-child(even) {
            background-color: #e9ecef;
            /* Light gray */
        }

        .parameter-total-row td {
            font-weight: bold;
            background-color: #e2e6ea;
            /* Slightly darker gray */
        }

        .overall-summary {
            margin-top: 40px;
            padding: 25px;
            /* Adjusted padding */
            background-color: #e9ecef;
            /* Light gray */
            border: 1px solid #ced4da;
            /* Match border */
            border-radius: 8px;
        }

        .overall-summary h2 {
            color: #495057;
            border-bottom: 2px solid #ced4da;
            margin-bottom: 20px;
        }

        .overall-summary p {
            margin: 10px 0;
            font-size: 1.2em;
        }

        .overall-summary strong {
            color: #495057;
        }

        .overall-summary .status-lulus {
            color: #28a745;
            /* Green */
            font-weight: bold;
        }

        .overall-summary .status-tidak-lulus {
            color: #dc3545;
            /* Red */
            font-weight: bold;
        }

        .text-warning {
            /* Style for settings warning message */
            color: #ffc107 !important;
        }

        .text-success {
            /* Style for settings success message */
            color: #28a745 !important;
        }


        .back-link {
            display: inline-block;
            margin-top: 30px;
            /* Adjusted margin */
            padding: 12px 20px;
            /* Adjusted padding */
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            /* Adjusted border-radius */
            font-weight: 500;
            transition: background-color 0.2s ease-in-out;
        }

        .back-link:hover {
            background-color: #0056b3;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .navbar {
                display: flex;
                /* Tampilkan navbar di mobile */
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1030;
            }

            .sidebar {
                transform: translateX(-100%);
                /* Sembunyikan sidebar default */
                top: 56px;
                /* Sesuaikan dengan tinggi mobile navbar */
                height: calc(100vh - 56px);
                /* Sesuaikan tinggi sidebar */
                padding-top: 20px;
                /* Kurangi padding atas */
                padding-bottom: 20px;
                /* Tambahkan padding bawah */
                justify-content: space-between;
                /* Atur spacing untuk logout link */
            }

            .sidebar.active {
                transform: translateX(0);
                /* Tampilkan sidebar saat aktif */
            }

            .sidebar .nav {
                flex-grow: 1;
                /* Biarkan nav memenuhi ruang */
            }

            .sidebar .nav-item:last-child {
                margin-top: auto;
                /* Dorong item logout ke bawah */
            }


            .main-content {
                margin-left: 0;
                /* Reset margin kiri */
                padding-top: 20px;
                /* Tambahkan padding atas */
            }

            .container {
                padding: 20px;
                /* Reduce container padding */
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }

            .container {
                padding: 15px;
                /* Reduce container padding further */
            }

            th,
            td {
                padding: 8px;
                /* Reduce table cell padding */
                font-size: 0.9em;
            }

            .overall-summary p {
                font-size: 1.1em;
            }
        }

        @media (max-width: 576px) {
            body {
                padding-top: 56px;
                /* Sesuaikan padding body */
            }

            .navbar-brand {
                font-size: 1.2rem;
            }

            .container {
                padding: 10px;
                /* Further reduce container padding */
            }

            h1 {
                font-size: 1.8em;
            }

            h2 {
                font-size: 1.4em;
            }

            h3 {
                font-size: 1.2em;
            }

            .project-info p {
                font-size: 1em;
            }

            .overall-summary p {
                font-size: 1em;
            }
        }
    </style>
</head>

<body>
        <div class="container">
            <h1>Laporan Evaluasi Proyek</h1>

            <div class="project-info">
                <h2>Informasi Proyek</h2>
                <p><strong>Nama Proyek:</strong> <?php echo htmlspecialchars($project['project_name']); ?></p>
                <p><strong>Chapter:</strong> <?php echo htmlspecialchars($project['project_chapter']); ?></p>
                <p><strong>Catatan Pemeriksa:</strong> <?php echo nl2br(htmlspecialchars($project['examiner_notes'])); ?></p>
                <p><strong>Tanggal Disubmit:</strong> <?php echo htmlspecialchars($project['last_submitted']); ?></p>
            </div>

            <h2>Detail Penilaian per Parameter</h2>

            <?php if (!empty($parameters)): ?>
                <?php foreach ($parameters as $param): ?>
                    <div class="parameter-section">
                        <h3><?php echo htmlspecialchars($param['parameter_name']); ?></h3>
                        <?php if (!empty($param['sub_aspects'])): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nama Sub-Aspek</th>
                                        <th>Jumlah Error</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($param['sub_aspects'] as $sub): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sub['aspect_name']); ?></td>
                                            <td><?php echo htmlspecialchars($sub['error_count']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="parameter-total-row">
                                        <td><strong>Total Error Parameter Ini:</strong></td>
                                        <td><strong><?php echo htmlspecialchars($param['total_errors']); ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>Tidak ada sub-aspek untuk parameter ini. (Total Error: 0)</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Tidak ada data parameter evaluasi untuk proyek ini.</p>
            <?php endif; ?>

            <div class="overall-summary">
                <h2>Ringkasan Keseluruhan</h2>
                <?php echo $settings_message; ?>
                <p><strong>Total Keseluruhan Error:</strong> <?php echo $overall_total_errors; ?></p>
                <p><strong>Total Nilai:</strong> <?php echo $total_score; ?></p>
                <p><strong>Predikat:</strong> <?php echo htmlspecialchars($predicate); ?></p>
                <p><strong>Status:</strong> <span class="status-<?php echo ($status == 'Lulus') ? 'lulus' : 'tidak-lulus'; ?>"><?php echo htmlspecialchars($status); ?></span></p>
            </div>


            <a href="index.php" class="back-link">Kembali ke Dashboard</a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


</body>

</html>