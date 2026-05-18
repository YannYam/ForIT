<?php

/**
 * Generate a pseudo-ULID (Universally Unique Lexicographic ID)
 * Format: 26 chars, base32-like, time-ordered
 * Tidak memerlukan library eksternal
 */
function generate_ulid(): string
{
    $chars = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    $time = microtime(true) * 1000;
    $timePart = '';
    $t = (int)$time;

    for ($i = 9; $i >= 0; $i--) {
        $timePart = $chars[$t % 32] . $timePart;
        $t = (int)($t / 32);
    }

    $randomPart = '';
    for ($i = 0; $i < 16; $i++) {
        $randomPart .= $chars[random_int(0, 31)];
    }

    return $timePart . $randomPart;
}

/**
 * Sanitasi output HTML untuk mencegah XSS
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Redirect ke URL lain
 */
function redirect(string $url): never
{
    header("Location: $url");
    exit;
}

/**
 * Set flash message ke session
 */
function set_flash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Ambil dan hapus flash message dari session
 */
function get_flash(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Generate CSRF token dan simpan ke session
 */
function generate_csrf(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifikasi CSRF token dari form POST
 */
function verify_csrf(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = $_POST['csrf_token'] ?? '';
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Cek apakah user sudah login
 */
function is_logged_in(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

/**
 * Ambil data user dari session
 */
function auth_user(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) return null;
    return [
        'user_id'  => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'fullname' => $_SESSION['fullname'],
        'role'     => $_SESSION['role'],
        'status'   => $_SESSION['status'],
    ];
}

/**
 * Cek apakah user memiliki role tertentu
 */
function has_role(string ...$roles): bool
{
    $user = auth_user();
    if (!$user) return false;
    return in_array($user['role'], $roles);
}

/**
 * Format tanggal relatif (misal: "2 jam lalu")
 * Menggunakan timezone WIB (Asia/Jakarta) agar waktu selalu akurat
 */
function time_ago(string $datetime): string
{
    $tz   = new DateTimeZone('Asia/Jakarta');
    $now  = new DateTime('now', $tz);
    $past = new DateTime($datetime, $tz);
    $diff = $now->diff($past);

    if ($diff->y > 0) return $diff->y . ' tahun lalu';
    if ($diff->m > 0) return $diff->m . ' bulan lalu';
    if ($diff->d > 0) return $diff->d . ' hari lalu';
    if ($diff->h > 0) return $diff->h . ' jam lalu';
    if ($diff->i > 0) return $diff->i . ' menit lalu';
    return 'Baru saja';
}

/**
 * Truncate teks ke panjang tertentu
 */
function truncate(string $text, int $length = 150): string
{
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '...';
}
