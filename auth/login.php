<?php
session_start();
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ../admin/dashboard.php');
    exit;
}
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Login — HDSF Mushroom</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              "primary": "#154212", "on-primary": "#ffffff",
              "primary-container": "#2d5a27", "on-primary-container": "#9dd090",
              "primary-fixed": "#bcf0ae", "on-primary-fixed-variant": "#23501e",
              "surface": "#fbf9f8", "surface-container-lowest": "#ffffff",
              "surface-container": "#f0eded", "surface-container-low": "#f6f3f2",
              "on-surface": "#1b1c1c", "on-surface-variant": "#42493e",
              "outline": "#72796e", "outline-variant": "#c2c9bb",
              "secondary": "#5d5f5b", "error": "#ba1a1a",
              "error-container": "#ffdad6", "on-error-container": "#93000a",
            },
            spacing: {
              "xs": "4px", "sm": "12px", "md": "24px", "lg": "48px",
              "margin": "32px", "base": "8px"
            }
          }
        }
      }
    </script>
    <style>
        body { background-color: #F5F5F0; font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .input-focus:focus { outline: none; border-color: #154212; box-shadow: 0 0 0 2px rgba(21,66,18,0.15); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-margin">
<main class="w-full max-w-[420px]">
    <!-- Card -->
    <div class="bg-surface-container-lowest rounded-xl p-lg shadow-[0_8px_32px_rgba(21,66,18,0.10)] border border-outline-variant/20">

        <!-- Branding -->
        <div class="flex flex-col items-center mb-lg">
            <div class="w-20 h-20 bg-primary rounded-2xl flex items-center justify-center mb-md shadow-md">
                <span class="material-symbols-outlined text-white" style="font-size:40px;font-variation-settings:'FILL' 1">eco</span>
            </div>
            <h1 class="text-[28px] font-bold text-primary tracking-tight">HDSF Mushroom</h1>
            <p class="text-[13px] text-on-surface-variant mt-xs">Inventory Management System</p>
        </div>

        <!-- Error Alert -->
        <?php if ($error): ?>
        <div class="mb-md p-sm rounded-lg bg-error-container text-on-error-container flex items-center gap-sm text-[13px] border border-error/20">
            <span class="material-symbols-outlined text-[18px]">error</span>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="proses_login.php" method="POST" class="space-y-md">
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs" for="username">Username</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">person</span>
                    <input type="text" id="username" name="username" required
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           placeholder="Masukkan username"
                           class="w-full bg-surface-container-low border border-outline/20 pl-[44px] pr-sm py-sm rounded-lg text-[15px] input-focus transition-all placeholder:text-outline-variant"/>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs" for="password">Password</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">lock</span>
                    <input type="password" id="password" name="password" required
                           placeholder="Masukkan password"
                           class="w-full bg-surface-container-low border border-outline/20 pl-[44px] pr-[44px] py-sm rounded-lg text-[15px] input-focus transition-all placeholder:text-outline-variant"/>
                    <button type="button" id="toggle-pass" class="absolute right-sm top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-primary transition-colors">
                        <span class="material-symbols-outlined text-[20px]" id="eye-icon">visibility_off</span>
                    </button>
                </div>
            </div>

            <button type="submit"
                    class="w-full bg-primary text-on-primary font-semibold py-sm px-md rounded-lg hover:opacity-90 active:scale-[0.98] transition-all flex items-center justify-center gap-xs shadow-md text-[15px] mt-base">
                Masuk
                <span class="material-symbols-outlined text-[20px]">login</span>
            </button>
        </form>

        <div class="mt-lg pt-md border-t border-outline-variant/10 text-center">
            <p class="text-[12px] text-on-surface-variant/60 italic">Khusus Untuk Admin Saja</p>
        </div>
    </div>

    <div class="mt-md text-center">
        <p class="text-[10px] text-on-surface-variant/40 tracking-[0.1em] uppercase">
            V.2.1.0 © <?= date('Y') ?> HDSF Organic Precision
        </p>
    </div>
</main>

<script>
const toggleBtn = document.getElementById('toggle-pass');
const passInput = document.getElementById('password');
const eyeIcon   = document.getElementById('eye-icon');
toggleBtn.addEventListener('click', () => {
    const isHidden = passInput.type === 'password';
    passInput.type = isHidden ? 'text' : 'password';
    eyeIcon.textContent = isHidden ? 'visibility' : 'visibility_off';
});
</script>
</body>
</html>
