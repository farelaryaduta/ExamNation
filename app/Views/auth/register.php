<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    
    <!-- Font Google -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS eksternal -->
    <link rel="stylesheet" href="<?= base_url('assets/css/register_style.css'); ?>">
</head>
<body class="light-mode">
    <div class="container">
        <div class="register-card">
            <div class="theme-toggle">
                <button id="theme-button" aria-label="Toggle dark mode">
                    <span class="moon">🌙</span>
                    <span class="sun">☀️</span>
                </button>
            </div>
            
            <div class="register-header">
                <h1>Create Account</h1>
                <p>Get started with your new account</p>
            </div>
            
            <div class="register-form">
                <form action="<?= base_url('/register/store') ?>" method="post">
                    <div class="form-group">
                        <label for="name">Nama</label>
                        <input type="text" id="name" name="name" placeholder="Masukkan nama lengkap" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="Masukkan email anda" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Buat password" required>
                        <div class="password-toggle">
                            <button type="button" id="toggle-password" aria-label="Show password">
                                <span class="show-password">👁️</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Pilih Peran</label>
                        <div class="select-wrapper">
                            <select id="role" name="role">
                                <option value="peserta">Peserta</option>
                                <!-- <option value="admin">Admin</option> -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="terms-privacy">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">Saya setuju dengan <a href="#">Syarat & Ketentuan</a> dan <a href="#">Kebijakan Privasi</a></label>
                    </div>
                    
                    <button type="submit" class="register-button">Daftar Sekarang</button>
                </form>
            </div>
            
            <div class="register-footer">
                <p>Sudah punya akun? <a href="<?= base_url('/login') ?>">Login</a></p>
            </div>
        </div>
    </div>
    
    <script src="<?= base_url('assets/js/register_script.js'); ?>"></script>
</body>
</html>