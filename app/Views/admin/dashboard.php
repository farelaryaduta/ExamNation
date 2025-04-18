<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Ujian</title>
    <!-- CSS Style -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/admin_dashboard.css'); ?>">
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h5 class="text-center">Admin Menu</h5>
        <a href="<?= base_url('admin/manage-exam'); ?>">Kelola Ujian</a>
        <a href="<?= base_url('admin/manage-users'); ?>">Kelola Pengguna</a>
        <a href="<?= base_url('admin/add-exam'); ?>">Tambah Ujian</a>
        <a href="#statistik">Statistik Ujian</a>
        <button onclick="toggleDarkMode()" class="btn btn-sm btn-light mt-4 mx-3">🌙 Mode Gelap</button>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm mb-4">
            <div class="container-fluid">
                <span class="navbar-brand">Dashboard Ujian</span>
            </div>
        </nav>

        <div class="exam-header">
            <h1>Selamat Datang, Admin!</h1>
            <p class="lead">Kelola ujian, pengguna, dan lihat statistik dengan mudah.</p>
        </div>

        <!-- Cards -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card text-center shadow">
                    <div class="card-body">
                        <h5 class="card-title">Kelola Ujian</h5>
                        <p class="card-text">Edit atau hapus ujian yang tersedia.</p>
                        <a href="<?= base_url('admin/manage-exam'); ?>" class="btn btn-primary">Kelola</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card text-center shadow">
                    <div class="card-body">
                        <h5 class="card-title">Kelola Pengguna</h5>
                        <p class="card-text">Atur pengguna yang terdaftar.</p>
                        <a href="<?= base_url('admin/manage-users'); ?>" class="btn btn-warning">Kelola</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card text-center shadow">
                    <div class="card-body">
                        <h5 class="card-title">Tambah Ujian</h5>
                        <p class="card-text">Buat ujian baru dan sesuaikan pengaturannya.</p>
                        <a href="<?= base_url('admin/add-exam'); ?>" class="btn btn-success">Tambah</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="card shadow" id="statistik">
            <div class="card-body">
                <h4 class="card-title">📊 Statistik Ujian Mingguan</h4>
                <canvas id="ujianChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <!-- Script JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= base_url('assets/js/chart.js'); ?>"></script>
    <!-- Chart JS -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>

</html>