<?php
session_start(); // Mulai sesi

// Periksa apakah pengguna sudah login dan user_id ada di sesi
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    // Jika belum login, redirect ke halaman login
    header("Location: login.php?status=error&msg=Silakan%20login%20terlebih%20dulu");
    exit();
}

// Sertakan file koneksi database
require_once '../backend/db.php'; // Sesuaikan path jika perlu

$user_id = $_SESSION['user_id'];
$current_settings = null;
$current_predicates = [];
$error_message_load = ''; // Pesan error untuk proses load

// Ambil data pengaturan yang ada untuk pengguna yang login
$sql_settings = "SELECT setting_id, total_value, minimum_passing FROM grade_settings WHERE user_id = ?";
$stmt_settings = mysqli_prepare($conn, $sql_settings);

if ($stmt_settings) {
    mysqli_stmt_bind_param($stmt_settings, "i", $user_id);
    mysqli_stmt_execute($stmt_settings);
    $result_settings = mysqli_stmt_get_result($stmt_settings);

    if ($result_settings && mysqli_num_rows($result_settings) > 0) {
        $current_settings = mysqli_fetch_assoc($result_settings);
        $setting_id = $current_settings['setting_id'];

        // Ambil predikat untuk setting_id ini
        $sql_predicates = "SELECT upper_bound, lower_bound, predicate_name FROM grade_predicates WHERE setting_id = ? ORDER BY upper_bound DESC";
        $stmt_predicates = mysqli_prepare($conn, $sql_predicates);

        if ($stmt_predicates) {
            mysqli_stmt_bind_param($stmt_predicates, "i", $setting_id);
            mysqli_stmt_execute($stmt_predicates);
            $result_predicates = mysqli_stmt_get_result($stmt_predicates);

            while ($row = mysqli_fetch_assoc($result_predicates)) {
                $current_predicates[] = $row;
            }
            mysqli_stmt_close($stmt_predicates);
        } else {
            $error_message_load = "Gagal mengambil data predikat: " . mysqli_error($conn);
            error_log("Error preparing to fetch predicates: " . mysqli_error($conn));
        }
    } // Tidak perlu else di sini, $current_settings akan tetap null jika tidak ada data
    mysqli_stmt_close($stmt_settings);
} else {
    $error_message_load = "Gagal mengambil data pengaturan: " . mysqli_error($conn);
    error_log("Error preparing to fetch settings: " . mysqli_error($conn));
}

mysqli_close($conn);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Nilai - ProjectHub</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- === COPY CSS DARI HALAMAN LAIN (index.php, form.php, dll.) === -->
    <style>
        body {
            background-color: #f0f2f5;
            color: #212529;
            padding-top: 60px;
        }

        .navbar {
            display: none;
            background-color: #6f42c1;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            z-index: 1030;
            height: 60px;
        }

        .navbar-brand {
            color: white;
            font-weight: bold;
        }

        .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.1);
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.55%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        .sidebar {
            height: 100vh;
            position: fixed;
            width: 240px;
            background-color: #fff;
            border-right: 1px solid #dee2e6;
            padding: 30px 20px;
            transition: transform 0.3s ease-in-out;
            z-index: 1000;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar .nav-link {
            color: #6c757d;
            font-weight: 500;
            padding: 12px 15px;
            border-radius: 8px;
            transition: all 0.2s ease-in-out;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }

        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            background-color: #6f42c1;
            color: #fff;
        }

        .sidebar .nav-link.logout-link {
            color: #dc3545;
        }

        .sidebar .nav-link.logout-link:hover {
            background-color: #dc3545;
            color: #fff;
        }

        .sidebar .sidebar-header {
            text-align: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .sidebar .sidebar-header h4 {
            color: #6f42c1;
            margin-bottom: 0;
        }

        .sidebar .nav-item:last-child {
            margin-top: auto;
        }

        .main-content {
            padding: 30px;
            transition: margin-left 0.3s ease-in-out;
            margin-left: 240px;
        }

        .card {
            background-color: #fff;
            border-radius: 12px;
            border: none;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.07);
            padding: 25px;
            margin-bottom: 20px;
        }

        .card-header.form-header,
        .card-header.table-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1.5rem;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #495057;
        }

        .form-control {
            border-radius: 0.375rem;
            border: 1px solid #ced4da;
        }

        .form-control:focus {
            border-color: #a384d1;
            box-shadow: 0 0 0 0.25rem rgba(111, 66, 193, 0.25);
        }

        .input-group-text {
            background-color: #e9ecef;
            border: 1px solid #ced4da;
        }

        .table th {
            background-color: transparent;
            color: #495057;
            font-weight: 600;
            vertical-align: middle;
            border-bottom-width: 1px;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            text-align: center;
        }

        .table td {
            vertical-align: middle;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
        }

        .table-hover>tbody>tr:hover>* {
            background-color: #f8f9fa;
            color: #212529;
        }

        .btn-add-predicate {
            padding: 0.375rem 0.75rem;
        }

        .btn-remove-predicate {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            line-height: 1;
        }

        .btn-save-settings {
            background-color: #198754;
            color: white;
            border-radius: 0.375rem;
            font-weight: 500;
            padding: 0.75rem 1.25rem;
            border: none;
        }

        .btn-save-settings:hover {
            background-color: #157347;
        }

        @media (max-width: 992px) {
            .navbar {
                display: flex;
            }

            .sidebar {
                transform: translateX(-100%);
                z-index: 1040;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar .sidebar-header h4 {
                display: none;
            }
        }

        @media (min-width: 993px) {
            body {
                padding-top: 0;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }

            .card {
                padding: 20px;
            }

            h4.page-title {
                font-size: 1.5rem;
            }

            /* Make inputs slightly wider on medium screens */
            .form-control.setting-input {
                max-width: 150px;
            }
        }

        @media (max-width: 576px) {
            body {
                padding-top: 60px;
            }

            .main-content {
                padding: 15px;
            }

            .card {
                padding: 15px;
            }

            h4.page-title {
                font-size: 1.3rem;
            }

            /* Full width inputs on small screens */
            .form-control.setting-input {
                max-width: 100%;
            }
        }
    </style>
    <!-- === AKHIR CSS === -->
</head>

<body>

    <!-- Mobile Navbar (Identik) -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">ProjectHub</a>
            <button class="navbar-toggler" type="button" id="mobileMenuToggle" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </nav>

    <!-- Sidebar (Identik, sesuaikan link aktif) -->
    <div class="sidebar d-flex flex-column" id="sidebar">
        <div class="sidebar-header">
            <h4 class="fw-bold">ProjectHub</h4>
        </div>
        <ul class="nav flex-column flex-grow-1">
            <li class="nav-item">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home"></i>Home
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="form.php">
                    <i class="fas fa-edit"></i>Form
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="other_projects.php">
                    <i class="fas fa-project-diagram"></i>Proyek Lain
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="settings.php"> <!-- ** ACTIVE DI SINI ** -->
                    <i class="fas fa-cog"></i>Settings
                </a>
            </li>
        </ul>
        <!-- Logout Item -->
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link logout-link" href="../backend/logout.php">
                    <i class="fas fa-sign-out-alt"></i>Logout (<?= htmlspecialchars($_SESSION['username']); ?>)
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content" id="mainContent">
        <h4 class="mb-4 fw-bold page-title">Pengaturan Nilai</h4>

        <?php if (!empty($error_message_load)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message_load); ?> <small>(Pengaturan default akan digunakan jika data tidak ditemukan).</small>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Card utama untuk form -->
        <div class="card shadow-sm">
            <div class="card-header form-header">
                <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Konfigurasi Penilaian</h5>
            </div>
            <div class="card-body">
                <!-- Form untuk menyimpan pengaturan -->
                <form id="settingsForm" action="../backend/process_settings.php" method="POST">
                    <!-- Hidden input untuk setting_id jika data sudah ada (mode update) -->
                    <?php if ($current_settings): ?>
                        <input type="hidden" name="setting_id" value="<?= $current_settings['setting_id']; ?>">
                    <?php endif; ?>

                    <!-- Pengaturan Dasar -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label" for="total_value">Total Nilai Awal</label>
                            <div class="input-group">
                                <input type="number" class="form-control setting-input" id="total_value" name="total_value" value="<?= $current_settings ? htmlspecialchars($current_settings['total_value']) : '100'; ?>" required min="0" step="any" aria-describedby="total-help">
                                <span class="input-group-text" id="total-help">- Total Kesalahan</span>
                            </div>
                            <div id="totalHelp" class="form-text">Nilai maksimal sebelum pengurangan error.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="minimum_passing">Nilai Minimum Lulus</label>
                            <input type="number" class="form-control setting-input" id="minimum_passing" name="minimum_passing" value="<?= $current_settings ? htmlspecialchars($current_settings['minimum_passing']) : '70'; ?>" required min="0" step="any">
                            <div id="minHelp" class="form-text">Batas nilai terendah untuk dianggap lulus.</div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Pengaturan Predikat -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold m-0">Rentang Predikat Nilai</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-add-predicate" onclick="addRow()">
                            <i class="fas fa-plus me-1"></i> Tambah Predikat
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="predikatTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Batas Atas (<) </th>
                                    <th>Batas Bawah (>=)</th>
                                    <th>Nama Predikat</th>
                                    <th>Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($current_predicates)): ?>
                                    <!-- Baris default jika tidak ada data -->
                                    <tr>
                                        <td><input type="number" class="form-control form-control-sm" name="upper_bound[]" placeholder="e.g., 101" required min="0" step="any"></td>
                                        <td><input type="number" class="form-control form-control-sm" name="lower_bound[]" placeholder="e.g., 90" required min="0" step="any"></td>
                                        <td><input type="text" class="form-control form-control-sm" name="predicate_name[]" placeholder="e.g., Sangat Baik" required></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm btn-remove-predicate" onclick="deleteRow(this)" title="Hapus Baris"><i class="fas fa-times"></i></button>
                                        </td>
                                    </tr>
                                    <tr> <!-- Contoh baris kedua -->
                                        <td><input type="number" class="form-control form-control-sm" name="upper_bound[]" placeholder="e.g., 90" required min="0" step="any"></td>
                                        <td><input type="number" class="form-control form-control-sm" name="lower_bound[]" placeholder="e.g., 80" required min="0" step="any"></td>
                                        <td><input type="text" class="form-control form-control-sm" name="predicate_name[]" placeholder="e.g., Baik" required></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm btn-remove-predicate" onclick="deleteRow(this)" title="Hapus Baris"><i class="fas fa-times"></i></button>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <!-- Isi tabel dengan data yang ada -->
                                    <?php foreach ($current_predicates as $predicate): ?>
                                        <tr>
                                            <td><input type="number" class="form-control form-control-sm" name="upper_bound[]" value="<?= htmlspecialchars($predicate['upper_bound']); ?>" placeholder="e.g., 101" required min="0" step="any"></td>
                                            <td><input type="number" class="form-control form-control-sm" name="lower_bound[]" value="<?= htmlspecialchars($predicate['lower_bound']); ?>" placeholder="e.g., 90" required min="0" step="any"></td>
                                            <td><input type="text" class="form-control form-control-sm" name="predicate_name[]" value="<?= htmlspecialchars($predicate['predicate_name']); ?>" placeholder="e.g., Sangat Baik" required></td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-outline-danger btn-sm btn-remove-predicate" onclick="deleteRow(this)" title="Hapus Baris"><i class="fas fa-times"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="form-text mt-2">Pastikan rentang nilai tidak tumpang tindih dan mencakup semua kemungkinan nilai. Urutan tidak berpengaruh saat disimpan.</div>


                    <!-- Tombol Simpan -->
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-save-settings">
                            <i class="fas fa-save me-1"></i> Simpan Pengaturan
                        </button>
                    </div>

                </form> <!-- Akhir form -->
            </div> <!-- Akhir card-body -->
        </div> <!-- Akhir card -->
    </div> <!-- Akhir main-content -->

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Fungsi tambah baris predikat (sama seperti sebelumnya)
        function addRow() {
            const table = document.getElementById("predikatTable").getElementsByTagName('tbody')[0];
            const newRow = table.insertRow();
            newRow.innerHTML = `
                <td><input type="number" class="form-control form-control-sm" name="upper_bound[]" placeholder="e.g., 80" required min="0" step="any"></td>
                <td><input type="number" class="form-control form-control-sm" name="lower_bound[]" placeholder="e.g., 70" required min="0" step="any"></td>
                <td><input type="text" class="form-control form-control-sm" name="predicate_name[]" placeholder="e.g., Cukup" required></td>
                <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove-predicate" onclick="deleteRow(this)" title="Hapus Baris"><i class="fas fa-times"></i></button>
                </td>
            `;
        }

        // Fungsi hapus baris predikat (sama seperti sebelumnya)
        function deleteRow(button) {
            const row = button.closest('tr');
            // Tambahkan konfirmasi sebelum menghapus jika ada lebih dari 1 baris
            const tbody = row.parentNode;
            if (tbody.rows.length > 1) {
                row.remove();
            } else {
                // Beri tahu pengguna bahwa setidaknya satu baris diperlukan
                Swal.fire({
                    icon: 'warning',
                    title: 'Oops...',
                    text: 'Setidaknya harus ada satu baris predikat.',
                });
            }
        }

        // === JAVASCRIPT SIDEBAR + SWEETALERT ===
        $(document).ready(function() {

            // Sidebar Toggle Logic
            $('#mobileMenuToggle').click(function(e) {
                e.stopPropagation();
                $('#sidebar').toggleClass('active');
            });

            // Close sidebar when clicking outside on mobile
            $(document).click(function(e) {
                if ($(window).width() <= 992 && $('#sidebar').hasClass('active')) {
                    if (!$(e.target).closest('#sidebar').length && !$(e.target).closest('#mobileMenuToggle').length) {
                        $('#sidebar').removeClass('active');
                    }
                }
            });

            // Prevent sidebar closing on internal click
            $('#sidebar').click(function(e) {
                e.stopPropagation();
            });

            // --- SWEETALERT CONFIRMATION ---
            $('#settingsForm').on('submit', function(event) {
                event.preventDefault(); // Hentikan submit default
                const form = this;

                // Optional: Lakukan validasi sederhana di sini sebelum menampilkan SweetAlert
                // Misalnya, cek apakah ada baris predikat
                const predicateRows = form.querySelectorAll('#predikatTable tbody tr');
                if (predicateRows.length === 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Input Tidak Lengkap',
                        text: 'Harap tambahkan setidaknya satu baris predikat nilai.',
                    });
                    return; // Hentikan proses jika tidak valid
                }
                // Tambahkan validasi lain jika perlu (misal range overlap, dll.)

                Swal.fire({
                    title: 'Konfirmasi Perubahan',
                    text: "Apakah Anda yakin ingin merubah peraturan nilai ini?",
                    icon: 'warning', // Gunakan ikon warning untuk perubahan
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Ya, Simpan Perubahan!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Jika dikonfirmasi, submit form
                        form.submit();
                    }
                });
            });

        });
        // === AKHIR JAVASCRIPT SIDEBAR + SWEETALERT ===
    </script>

</body>

</html>