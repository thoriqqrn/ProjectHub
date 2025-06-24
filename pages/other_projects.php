<?php
session_start(); // Mulai sesi

// Periksa apakah pengguna sudah login dan user_id ada di sesi
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php?status=error&msg=Silakan%20login%20terlebih%20dulu");
    exit();
}

// Sertakan file koneksi database
require_once '../backend/db.php'; // Pastikan path ini benar

$current_user_id = $_SESSION['user_id'];
$otherProjects = [];
$error_message = '';

// Ambil data proyek milik pengguna LAIN
$sql_other_projects = "SELECT
                        p.project_id,
                        p.project_name,
                        p.project_chapter,
                        p.last_submitted,
                        u.username AS created_by
                    FROM
                        projects p
                    JOIN
                        users u ON p.user_id = u.id
                    WHERE
                        p.user_id != ? -- Filter: user_id TIDAK SAMA dengan user_id yang sedang login
                    ORDER BY
                        p.last_submitted DESC"; // Urutkan berdasarkan waktu submit terakhir

$stmt_other_projects = mysqli_prepare($conn, $sql_other_projects);

if ($stmt_other_projects) {
    mysqli_stmt_bind_param($stmt_other_projects, "i", $current_user_id);
    mysqli_stmt_execute($stmt_other_projects);
    $result_other_projects = mysqli_stmt_get_result($stmt_other_projects);

    if ($result_other_projects) {
        while ($row = mysqli_fetch_assoc($result_other_projects)) {
            $otherProjects[] = $row;
        }
    } else {
        $error_message = "Error mengambil data proyek: " . mysqli_error($conn);
        error_log("Error getting result for other projects query: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt_other_projects);
} else {
    $error_message = "Tidak dapat menyiapkan query proyek pengguna lain: " . mysqli_error($conn);
    error_log("Error preparing other projects query: " . mysqli_error($conn));
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proyek Pengguna Lain - ProjectHub</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <!-- Font Awesome (Optional, for icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Custom CSS matching the dashboard -->
    <style>
        body {
            background-color: #f0f2f5;
            /* Match dashboard */
            color: #212529;
            padding-top: 60px;
            /* Room for fixed navbar on mobile */
            /* Remove padding-left for sidebar initially */
        }

        /* Navbar Styles (for mobile) */
        .navbar {
            display: none;
            /* Hide navbar desktop by default */
            background-color: #6f42c1;
            /* Match dashboard */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            /* Fixed top */
            width: 100%;
            top: 0;
            left: 0;
            z-index: 1030;
            height: 60px;
            /* Consistent height */
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


        /* Sidebar Styles */
        .sidebar {
            height: 100vh;
            position: fixed;
            width: 240px;
            background-color: #fff;
            /* Match dashboard */
            border-right: 1px solid #dee2e6;
            padding: 30px 20px;
            transition: transform 0.3s ease-in-out;
            /* Use transform for transition */
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
            /* Adjust padding slightly */
            border-radius: 8px;
            transition: all 0.2s ease-in-out;
            margin-bottom: 5px;
            /* Add spacing */
            display: flex;
            /* Align icon and text */
            align-items: center;
        }

        .sidebar .nav-link i {
            margin-right: 10px;
            /* Space between icon and text */
            width: 20px;
            /* Fixed width for icon alignment */
            text-align: center;
        }


        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            background-color: #6f42c1;
            /* Match dashboard */
            color: #fff;
        }

        .sidebar .nav-link.logout-link {
            /* Specific style for logout */
            color: #dc3545;
            /* Red color */
        }

        .sidebar .nav-link.logout-link:hover {
            background-color: #dc3545;
            color: #fff;
        }


        .sidebar .sidebar-header {
            text-align: center;
            margin-bottom: 1.5rem;
            /* More space below header */
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .sidebar .sidebar-header h4 {
            color: #6f42c1;
            /* Purple color for title */
            margin-bottom: 0;
        }

        .sidebar .nav-item:last-child {
            margin-top: auto;
            /* Push logout to bottom */
        }


        /* Main Content Styles */
        .main-content {
            padding: 30px;
            /* Adjust padding */
            transition: margin-left 0.3s ease-in-out;
            /* Animate margin shift */
            margin-left: 240px;
            /* Default margin for desktop */
        }

        .card {
            background-color: #fff;
            border-radius: 12px;
            /* Slightly less rounded */
            border: none;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.07);
            /* Refined shadow */
            padding: 25px;
            margin-bottom: 20px;
            /* Add margin below card */
        }

        .card-header.table-header {
            /* Specific style for table card header */
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1.5rem;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }


        .table th {
            background-color: transparent;
            /* Remove default header background */
            color: #495057;
            font-weight: 600;
            /* Slightly bolder */
            vertical-align: middle;
            border-bottom-width: 1px;
            /* Thinner border */
            text-transform: uppercase;
            /* Uppercase headers */
            font-size: 0.8rem;
            /* Smaller header font */
            letter-spacing: 0.5px;
        }

        .table td {
            vertical-align: middle;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
            /* Match border */
        }

        .table-hover>tbody>tr:hover>* {
            background-color: #f8f9fa;
            /* Subtle hover effect */
            color: #212529;
        }

        /* DataTables Adjustments */
        .dataTables_wrapper .dataTables_filter input {
            margin-left: 0.5em;
            display: inline-block;
            width: auto;
            border-radius: 0.25rem;
            border: 1px solid #ced4da;
            padding: 0.375rem 0.75rem;
        }

        .dataTables_wrapper .dataTables_length select {
            width: auto;
            display: inline-block;
            border-radius: 0.25rem;
            border: 1px solid #ced4da;
            padding: 0.375rem 1.75rem 0.375rem 0.75rem;
            background-position: right 0.75rem center;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.3em 0.8em;
            /* Adjust pagination button padding */
        }

        .dataTables_wrapper .dataTables_info {
            padding-top: 0.85em;
            /* Align info text */
        }


        /* Responsive styles */
        @media (max-width: 992px) {
            .navbar {
                display: flex;
                /* Show mobile navbar */
            }

            .sidebar {
                transform: translateX(-100%);
                /* Hide sidebar off-screen */
                /* Removed top: 60px, height: calc(...) as it caused issues with fixed */
                z-index: 1040;
                /* Ensure sidebar is above content but potentially below modal */
            }

            .sidebar.active {
                transform: translateX(0);
                /* Show sidebar */
            }

            .main-content {
                margin-left: 0;
                /* Full width main content */
            }

            .sidebar .sidebar-header h4 {
                display: none;
                /* Hide desktop header on mobile */
            }
        }

        @media (min-width: 993px) {

            /* Ensure body padding accounts for fixed navbar ONLY on mobile */
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

            h1 {
                font-size: 1.75rem;
            }

            /* Adjust heading size */
        }

        @media (max-width: 576px) {
            body {
                padding-top: 60px;
                /* Keep padding for mobile navbar */
            }

            .main-content {
                padding: 15px;
            }

            .card {
                padding: 15px;
            }

            .table th,
            .table td {
                font-size: 0.85rem;
                /* Smaller font in table */
            }

            h1 {
                font-size: 1.5rem;
            }

        }
    </style>
</head>

<body>

    <!-- Mobile Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">ProjectHub</a>
            <button class="navbar-toggler" type="button" id="mobileMenuToggle" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column" id="sidebar">
        <div class="sidebar-header">
            <h4 class="fw-bold">ProjectHub</h4>
            <!-- Mobile Toggle can be added here if needed -->
        </div>
        <ul class="nav flex-column flex-grow-1">
            <li class="nav-item">
                <a class="nav-link" href="index.php"> <!-- Use index.php or dashboard.php based on your file name -->
                    <i class="fas fa-home"></i>Home
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="form.php">
                    <i class="fas fa-edit"></i>Form
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="other_projects.php"> <!-- Active class here -->
                    <i class="fas fa-project-diagram"></i>Proyek Lain
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog"></i>Settings
                </a>
            </li>
        </ul>
        <!-- Logout Item at the bottom -->
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
        <h1 class="mb-4">Proyek Pengguna Lain</h1>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header table-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list-alt me-2"></i>Daftar Proyek</h5>
                <!-- Optional: Add buttons or filters here if needed -->
            </div>
            <div class="card-body">
                <?php if (!empty($otherProjects)): ?>
                    <div class="table-responsive">
                        <table id="otherProjectsTable" class="table table-hover dt-responsive nowrap" style="width:100%">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-file-alt me-1"></i>Nama Proyek</th>
                                    <th><i class="fas fa-book me-1"></i>Bab</th>
                                    <th><i class="fas fa-clock me-1"></i>Terakhir Diubah</th>
                                    <th><i class="fas fa-user me-1"></i>Dibuat Oleh</th>
                                    <!-- Remove Aksi column if not needed -->
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($otherProjects as $project): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($project['project_name']); ?></td>
                                        <td><?= htmlspecialchars($project['project_chapter']); ?></td>
                                        <td><?= $project['last_submitted'] ? date('d M Y, H:i', strtotime($project['last_submitted'])) : '<span class="text-muted">Belum ada</span>'; ?></td>
                                        <td><?= htmlspecialchars($project['created_by']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light text-center border" role="alert">
                        <i class="fas fa-info-circle me-2 text-primary"></i> Saat ini belum ada proyek dari pengguna lain.
                    </div>
                <?php endif; ?>
            </div> <!-- card-body -->
        </div> <!-- card -->

        <!-- Optional: Footer or additional elements -->

    </div> <!-- main-content -->

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#otherProjectsTable').DataTable({
                responsive: true,
                language: { // Simplified Indonesian translation
                    search: "_INPUT_",
                    searchPlaceholder: "Cari...",
                    lengthMenu: "Tampilkan _MENU_",
                    zeroRecords: "Tidak ada data ditemukan",
                    info: "Menampilkan _START_-_END_ dari _TOTAL_",
                    infoEmpty: "Tidak ada data",
                    infoFiltered: "(difilter dari _MAX_ total)",
                    paginate: {
                        first: "<<",
                        last: ">>",
                        next: ">",
                        previous: "<"
                    },
                    emptyTable: "Tidak ada data tersedia di tabel"
                },
                order: [
                    [2, 'desc']
                ], // Default sort by 'Terakhir Diubah' descending
                pageLength: 10,
                // Additional DataTables options can go here
                columnDefs: [
                    // Example: Disable sorting on the 'Dibuat Oleh' column if needed
                    // { targets: 3, orderable: false }
                ]
            });

            // Sidebar Toggle Logic (Matches your dashboard code)
            $('#mobileMenuToggle').click(function(e) {
                e.stopPropagation(); // Prevent click bubbling to document
                $('#sidebar').toggleClass('active');
            });

            // Close sidebar when clicking outside on mobile
            $(document).click(function(e) {
                // Check if screen is mobile (where navbar is visible) and sidebar is active
                if ($(window).width() <= 992 && $('#sidebar').hasClass('active')) {
                    // Check if the click is outside the sidebar and not on the toggle button
                    if (!$(e.target).closest('#sidebar').length && !$(e.target).closest('#mobileMenuToggle').length) {
                        $('#sidebar').removeClass('active');
                    }
                }
            });

            // Prevent sidebar from closing when clicking inside it
            $('#sidebar').click(function(e) {
                e.stopPropagation();
            });


        });
    </script>

</body>

</html>