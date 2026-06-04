/**
 * HDSF Mushroom - Custom JavaScript
 * Tambahkan script kustom di sini jika diperlukan
 */

// Contoh: Auto-hide flash message setelah 5 detik
document.addEventListener('DOMContentLoaded', function () {
    const flash = document.getElementById('flash-msg');
    if (flash) {
        setTimeout(function () {
            flash.style.transition = 'opacity 0.5s ease';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 500);
        }, 5000);
    }
});
