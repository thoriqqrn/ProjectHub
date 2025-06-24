<?php
session_start(); // Mulai sesi

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php?status=error&msg=Silakan%20login%20terlebih%20dulu");
    exit();
}

// Logika edit dihapus untuk sementara
$edit_mode = false; // Set ke false secara default
$error_message_edit = ''; // Kosongkan pesan error edit

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Penilaian Baru - ProjectHub</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- === CSS UI KONSISTEN (dari halaman lain) === -->
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

        .card-header.form-header {
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

        .parameter-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 1.25rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .btn-add-parameter {
            border-radius: 0.375rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
        }

        .btn-add-sub-aspek {
            border-radius: 0.375rem;
            font-weight: 500;
            padding: 0.375rem 0.75rem;
        }

        .btn-remove {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            line-height: 1;
        }

        .btn-submit-form {
            background-color: #198754;
            color: white;
            border-radius: 0.375rem;
            font-weight: 500;
            padding: 0.75rem 1.25rem;
            border: none;
        }

        .btn-submit-form:hover {
            background-color: #157347;
        }

        .btn-cancel {
            margin-left: 10px;
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

            .btn-add-parameter {
                font-size: 0.9rem;
                padding: 0.4rem 0.8rem;
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

    <!-- Sidebar (Identik, link Form aktif) -->
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
                <a class="nav-link active" href="form.php"> <!-- ** ACTIVE DI SINI ** -->
                    <i class="fas fa-edit"></i>Form
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="other_projects.php">
                    <i class="fas fa-project-diagram"></i>Proyek Lain
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
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
        <h4 class="mb-4 fw-bold page-title">Buat Proyek Baru</h4>

        <?php if (!empty($error_message_edit)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message_edit); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Card untuk form -->
        <div class="card shadow-sm">
            <div class="card-header form-header">
                <h5 class="mb-0"><i class="fas fa-pencil-alt me-2"></i>Detail Proyek</h5>
            </div>
            <div class="card-body">
                <!-- Action tetap ke process_form.php -->
                <form id="evaluationForm" action="../backend/process_form.php" method="POST">

                    <!-- Hapus hidden input project_id -->

                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label" for="project_name">Nama Project <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="project_name" name="project_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="project_chapter">Chapter / Versi <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="project_chapter" name="project_chapter" required>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold m-0">Parameter Penilaian</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-add-parameter" onclick="addParameter()">
                            <i class="fas fa-plus me-1"></i> Tambah Parameter
                        </button>
                    </div>

                    <!-- Kontainer untuk parameter dinamis (akan diisi oleh JS) -->
                    <div id="parameterContainer">
                        <!-- Blok parameter dari PHP dihapus -->
                    </div> <!-- akhir parameterContainer -->

                    <hr class="my-4">

                    <div class="mb-3">
                        <label class="form-label" for="examiner_notes">Catatan Penguji</label>
                        <textarea class="form-control" id="examiner_notes" name="examiner_notes" rows="4"></textarea>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-submit-form">
                            <i class="fas fa-save me-1"></i> Submit Penilaian
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-cancel">Batal</a>
                    </div>

                </form>
            </div> <!-- akhir card-body -->
        </div> <!-- akhir card -->
    </div> <!-- akhir main-content -->

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Fungsi JS untuk form dinamis (tidak berubah)
        function addParameter(paramName = '', subAspects = []) {
            let parameterContainer = document.getElementById("parameterContainer");
            let parameterDiv = document.createElement("div");
            parameterDiv.classList.add("card", "parameter-card", "mb-3");
            let nextIndex = parameterContainer.children.length;
            let subAspectsHTML = '';
            if (subAspects.length > 0) {
                subAspects.forEach((sub, subIndex) => {
                    subAspectsHTML += createSubAspectHTML(nextIndex, subIndex, sub.sub_aspect_name, sub.error_count);
                });
            } else {
                subAspectsHTML = createSubAspectHTML(nextIndex, 0); // Buat satu sub aspek kosong
            }
            parameterDiv.innerHTML = createParameterHTML(nextIndex, paramName, subAspectsHTML);
            parameterContainer.appendChild(parameterDiv);
            updateNames();
        }

        function createParameterHTML(index, name, subAspectsHTML) {
            return `
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <input type="text" class="form-control form-control-sm me-2" placeholder="Nama Parameter" name="parameter_name[${index}]" required value="${escapeHTML(name)}">
                    <button type="button" class="btn btn-danger btn-sm btn-remove" onclick="removeParameter(this)" title="Hapus Parameter Ini"><i class="fas fa-trash-alt"></i></button>
                </div>
                <label class="form-label fw-bold small mb-2">Sub Aspek</label>
                <div class="subAspekContainer mb-2">
                    ${subAspectsHTML}
                </div>
                <button type="button" class="btn btn-secondary btn-sm w-100 btn-add-sub-aspek" onclick="addSubAspek(this)">
                     <i class="fas fa-plus me-1"></i> Tambah Sub Aspek
                </button>
            `;
        }

        function createSubAspectHTML(paramIndex, subIndex, name = '', errorCount = '') {
            return `
                 <div class="d-flex mb-2 align-items-center">
                     <input type="text" class="form-control form-control-sm me-2" placeholder="Nama Sub Aspek" name="sub_aspect_name[${paramIndex}][${subIndex}]" required value="${escapeHTML(name)}">
                     <input type="number" class="form-control form-control-sm" style="width: 100px;" placeholder="Error" name="error_count[${paramIndex}][${subIndex}]" required min="0" value="${escapeHTML(errorCount)}">
                     <button type="button" class="btn btn-outline-danger btn-sm btn-remove ms-2" onclick="removeSubAspek(this)" title="Hapus Sub Aspek Ini">X</button>
                 </div>
             `;
        }


        function removeParameter(button) {
            button.closest('.parameter-card').remove();
            updateNames();
        }

        function addSubAspek(button) {
            let subAspekContainer = button.closest('.parameter-card').querySelector('.subAspekContainer');
            let parameterIndex = getParameterIndex(button.closest('.parameter-card'));
            let nextSubIndex = subAspekContainer.children.length;

            // 1. Hasilkan HTML string untuk baris sub-aspek baru menggunakan helper function
            const newSubAspectHTML = createSubAspectHTML(parameterIndex, nextSubIndex);

            // 2. Langsung tambahkan HTML string ini ke dalam container
            //    Menggunakan insertAdjacentHTML lebih efisien daripada membuat div sementara
            subAspekContainer.insertAdjacentHTML('beforeend', newSubAspectHTML);

            // Kita TIDAK perlu membuat div baru di sini dan menambahkan class padanya,
            // karena createSubAspectHTML sudah menghasilkan div dengan class yang benar.
        }

        function removeSubAspek(button) {
            let parameterCard = button.closest('.parameter-card');
            button.closest('.d-flex').remove();
            updateSubAspectNames(parameterCard);
        }

        function getParameterIndex(parameterCard) {
            let container = document.getElementById("parameterContainer");
            return Array.from(container.children).indexOf(parameterCard);
        }

        function updateSubAspectNames(parameterCard) {
            let parameterIndex = getParameterIndex(parameterCard);
            if (parameterIndex < 0) return;
            let subAspectDivs = parameterCard.querySelectorAll('.subAspekContainer > .d-flex');
            subAspectDivs.forEach((subDiv, subIndex) => {
                let nameInput = subDiv.querySelector('input[placeholder="Nama Sub Aspek"]');
                let countInput = subDiv.querySelector('input[placeholder="Error"]');
                if (nameInput) nameInput.name = `sub_aspect_name[${parameterIndex}][${subIndex}]`;
                if (countInput) countInput.name = `error_count[${parameterIndex}][${subIndex}]`;
            });
        }

        function updateNames() {
            let parameterDivs = document.querySelectorAll("#parameterContainer > .parameter-card");
            parameterDivs.forEach((parameterDiv, parameterIndex) => {
                let paramNameInput = parameterDiv.querySelector('input[placeholder="Nama Parameter"]');
                if (paramNameInput) paramNameInput.name = `parameter_name[${parameterIndex}]`;
                updateSubAspectNames(parameterDiv);
            });
        }

        function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str.toString()));
            return div.innerHTML;
        }


        // === JAVASCRIPT SIDEBAR (Sama) + SWEETALERT ===
        $(document).ready(function() {

            // Selalu tambahkan satu parameter awal untuk form baru
            addParameter();

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
            $('#evaluationForm').on('submit', function(event) {
                event.preventDefault(); // Hentikan submit default
                const form = this; // Simpan referensi ke form

                Swal.fire({
                    title: 'Konfirmasi Penyimpanan',
                    text: "Apakah Anda yakin ingin menyimpan data proyek ini?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6', // Biru
                    cancelButtonColor: '#d33', // Merah
                    confirmButtonText: 'Ya, Simpan!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Jika dikonfirmasi, submit form secara manual
                        form.submit();
                    }
                    // Jika tidak dikonfirmasi (klik Batal atau close), tidak terjadi apa-apa
                });
            });

        });
        // === AKHIR JAVASCRIPT SIDEBAR + SWEETALERT ===
    </script>

</body>

</html>