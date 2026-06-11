<?php
// ============================================================
//  PARTIAL: HEADER + SIDEBAR
//  Dipanggil di semua halaman admin
//  Variabel yang harus diset sebelum include:
//    $page_title  = judul di TopAppBar
//    $active_menu = 'dashboard' | 'pemasok' | 'pedagang' | 'stock' | 'barang_masuk' | 'barang_keluar'
//    $base_path   = path relatif ke folder admin (misal '../' atau '')
// ============================================================
if (!isset($base_path)) $base_path = '';
if (!isset($active_menu)) $active_menu = '';
if (!isset($page_title)) $page_title = 'HDSF Mushroom';

$nav_items = [
    ['key' => 'dashboard',     'icon' => 'dashboard',       'label' => 'Dashboard',     'href' => $base_path . 'dashboard.php'],
    ['key' => 'pemasok',       'icon' => 'local_shipping',  'label' => 'Pemasok',       'href' => $base_path . 'pemasok/index.php'],
    ['key' => 'pedagang',      'icon' => 'storefront',      'label' => 'Pedagang',      'href' => $base_path . 'pedagang/index.php'],
    ['key' => 'barang_masuk',  'icon' => 'move_to_inbox',   'label' => 'Barang Masuk',  'href' => $base_path . 'barang_masuk/index.php'],
    ['key' => 'barang_keluar', 'icon' => 'outbox',          'label' => 'Barang Keluar', 'href' => $base_path . 'barang_keluar/index.php'],
    ['key' => 'stock',         'icon' => 'inventory_2',     'label' => 'Stok',          'href' => $base_path . 'stock.php'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($page_title) ?> — HDSF Mushroom</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              "surface-container-lowest": "#ffffff",
              "inverse-primary": "#a1d494",
              "outline": "#72796e",
              "primary-container": "#2d5a27",
              "surface-variant": "#e4e2e1",
              "on-background": "#1b1c1c",
              "on-error-container": "#93000a",
              "on-error": "#ffffff",
              "surface-container": "#f0eded",
              "primary": "#154212",
              "surface-dim": "#dcd9d9",
              "on-surface": "#1b1c1c",
              "surface-tint": "#3b6934",
              "surface": "#fbf9f8",
              "on-tertiary": "#ffffff",
              "on-secondary-fixed": "#1a1c19",
              "primary-fixed": "#bcf0ae",
              "on-tertiary-container": "#c3c3b9",
              "on-secondary-container": "#62635f",
              "error": "#ba1a1a",
              "error-container": "#ffdad6",
              "on-tertiary-fixed-variant": "#46483f",
              "surface-container-low": "#f6f3f2",
              "surface-container-high": "#eae8e7",
              "surface-bright": "#fbf9f8",
              "secondary": "#5d5f5b",
              "on-primary-container": "#9dd090",
              "outline-variant": "#c2c9bb",
              "on-primary-fixed-variant": "#23501e",
              "tertiary": "#383a32",
              "on-secondary-fixed-variant": "#454744",
              "inverse-surface": "#303030",
              "tertiary-fixed": "#e3e3d8",
              "secondary-container": "#e0e0db",
              "on-tertiary-fixed": "#1b1c16",
              "tertiary-container": "#4f5149",
              "background": "#fbf9f8",
              "on-primary-fixed": "#002201",
              "on-primary": "#ffffff",
              "secondary-fixed": "#e3e3de",
              "surface-container-highest": "#e4e2e1",
              "primary-fixed-dim": "#a1d494",
              "secondary-fixed-dim": "#c6c7c2",
              "on-surface-variant": "#42493e",
              "tertiary-fixed-dim": "#c7c7bd",
              "on-secondary": "#ffffff",
              "inverse-on-surface": "#f3f0f0"
            },
            borderRadius: {
              "DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem"
            },
            spacing: {
              "xs": "4px", "xl": "80px", "md": "24px", "lg": "48px",
              "gutter": "24px", "margin": "32px", "base": "8px", "sm": "12px"
            },
            fontFamily: { "sans": ["Inter", "sans-serif"] },
            fontSize: {
              "h2": ["32px", {"lineHeight": "1.2", "letterSpacing": "-0.01em", "fontWeight": "600"}],
              "label-caps": ["12px", {"lineHeight": "1.2", "letterSpacing": "0.05em", "fontWeight": "700"}],
              "h1": ["40px", {"lineHeight": "1.2", "letterSpacing": "-0.02em", "fontWeight": "600"}],
              "body-md": ["16px", {"lineHeight": "1.6", "fontWeight": "400"}],
              "body-lg": ["18px", {"lineHeight": "1.6", "fontWeight": "400"}],
              "h3": ["24px", {"lineHeight": "1.3", "fontWeight": "500"}],
              "body-sm": ["14px", {"lineHeight": "1.5", "fontWeight": "400"}]
            }
          }
        }
      }
    </script>
    <style>
        body { background-color: #F5F5F0; font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .card-shadow { box-shadow: 0 4px 20px -2px rgba(27,28,28,0.06); }
        .nav-active { background-color: #2d5a27; color: #9dd090; }
        .nav-active .material-symbols-outlined { color: #9dd090; }
    </style>
</head>
<body class="text-on-surface">

<!-- ===== SIDEBAR OVERLAY (mobile) ===== -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-40 hidden md:hidden" onclick="closeSidebar()"></div>

<!-- ===== SIDEBAR ===== -->
<aside id="sidebar" class="fixed h-screen w-64 left-0 top-0 overflow-y-auto bg-surface-container-low border-r border-outline-variant/20 shadow-sm flex flex-col py-lg -translate-x-full md:translate-x-0 transition-transform duration-300 z-50">
    <div class="px-md mb-lg">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-sm">
                <img src="<?= $base_path ?>../image/logo.png" alt="HDSF Mushroom Logo" class="w-10 h-10 object-contain rounded-lg" />
                <div>
                    <h1 class="text-[18px] font-semibold text-primary leading-tight">HDSF Mushroom</h1>
                    <p class="text-[12px] text-on-surface-variant opacity-70">Inventory System</p>
                </div>
            </div>
            <button class="md:hidden p-1 rounded-lg hover:bg-surface-variant/50 transition-colors" onclick="closeSidebar()" aria-label="Tutup menu">
                <span class="material-symbols-outlined text-on-surface-variant text-[22px]">close</span>
            </button>
        </div>
    </div>

    <nav class="flex-1 px-sm space-y-xs">
        <?php foreach ($nav_items as $item): ?>
            <?php $is_active = ($active_menu === $item['key']); ?>
            <a href="<?= $item['href'] ?>"
               class="<?= $is_active ? 'nav-active' : 'text-on-surface-variant hover:bg-surface-variant/50' ?> rounded-full px-md py-sm flex items-center gap-sm transition-all duration-200">
                <span class="material-symbols-outlined text-[20px]"><?= $item['icon'] ?></span>
                <span class="text-[14px] font-medium"><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="px-sm mt-auto pt-md border-t border-outline-variant/10">
        <div class="px-md mb-sm">
            <p class="text-[12px] font-semibold text-on-surface">👤 <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></p>
            <p class="text-[11px] text-on-surface-variant opacity-70">Administrator</p>
        </div>
        <a href="<?= $base_path ?>../auth/logout.php"
           class="w-full bg-error/10 text-error py-sm rounded-xl font-medium hover:bg-error/20 transition-all flex items-center justify-center gap-xs text-[14px]">
            <span class="material-symbols-outlined text-[18px]">logout</span>
            Keluar
        </a>
    </div>
</aside>

<!-- ===== TOP APP BAR ===== -->
<header class="fixed top-0 right-0 left-0 md:left-64 h-16 bg-surface border-b border-outline-variant/10 z-30 flex items-center px-4 md:px-margin justify-between">
    <div class="flex items-center gap-sm">
        <button class="md:hidden p-1 mr-1 rounded-lg hover:bg-surface-container transition-colors" onclick="openSidebar()" aria-label="Buka menu">
            <span class="material-symbols-outlined text-primary text-[26px]">menu</span>
        </button>
        <span class="material-symbols-outlined text-primary text-[22px]">
            <?php
            $icon_map = [
                'dashboard'     => 'dashboard',
                'pemasok'       => 'local_shipping',
                'pedagang'      => 'storefront',
                'barang_masuk'  => 'move_to_inbox',
                'barang_keluar' => 'outbox',
                'stock'         => 'inventory_2',
            ];
            echo $icon_map[$active_menu] ?? 'apps';
            ?>
        </span>
        <h2 class="text-[20px] font-bold text-primary"><?= htmlspecialchars($page_title) ?></h2>
    </div>
    <div class="flex items-center gap-md">
        <span class="text-[12px] text-on-surface-variant hidden md:block">
            <?= date('l, d F Y') ?>
        </span>
    </div>
</header>

<!-- ===== MAIN CONTENT WRAPPER ===== -->
<main class="ml-0 md:ml-64 mt-16 p-4 md:p-margin min-h-screen">
<?php
// Flash message display
if (isset($_SESSION['flash_message'])):
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    $flash_msg  = $_SESSION['flash_message'];
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    $alert_class = $flash_type === 'error'
        ? 'bg-error-container text-on-error-container border-error/30'
        : 'bg-primary-fixed text-on-primary-fixed-variant border-primary/30';
    $alert_icon = $flash_type === 'error' ? 'error' : 'check_circle';
?>
<div class="mb-md p-md rounded-lg border <?= $alert_class ?> flex items-center gap-sm card-shadow" id="flash-msg">
    <span class="material-symbols-outlined"><?= $alert_icon ?></span>
    <span class="text-[14px] font-medium"><?= htmlspecialchars($flash_msg) ?></span>
    <button onclick="document.getElementById('flash-msg').remove()" class="ml-auto opacity-60 hover:opacity-100">
        <span class="material-symbols-outlined text-[18px]">close</span>
    </button>
</div>
<?php endif; ?>
<script>
function openSidebar() {
    document.getElementById('sidebar').classList.remove('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.remove('hidden');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.add('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.add('hidden');
}
</script>
