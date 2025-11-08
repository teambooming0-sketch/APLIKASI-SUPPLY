<?php
/**
 * GSP Supply Management System
 * Configuration File
 * 
 * Database connection, security settings, and helper functions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'gsp_supply');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'GSP Supply Management');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/gsp_supply');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_SIZE', 5242880); // 5MB

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Database Connection using PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

/**
 * Security Functions
 */

// Sanitize input
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Require login
function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

// Check if user is admin
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Helper Functions
 */

// Format currency (Indonesian Rupiah)
function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Format date (Indonesian)
function format_tanggal($tanggal, $format = 'd F Y') {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $hari = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];
    
    $timestamp = strtotime($tanggal);
    $nama_hari = $hari[date('l', $timestamp)];
    $tanggal_format = date('j', $timestamp);
    $nama_bulan = $bulan[date('n', $timestamp)];
    $tahun = date('Y', $timestamp);
    
    if ($format === 'full') {
        return $nama_hari . ', ' . $tanggal_format . ' ' . $nama_bulan . ' ' . $tahun;
    }
    return $tanggal_format . ' ' . $nama_bulan . ' ' . $tahun;
}

// Convert number to Indonesian words (Terbilang)
function terbilang($angka) {
    $angka = abs($angka);
    $huruf = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
    
    if ($angka < 12) {
        return $huruf[$angka];
    } elseif ($angka < 20) {
        return $huruf[$angka - 10] . " Belas";
    } elseif ($angka < 100) {
        return $huruf[floor($angka / 10)] . " Puluh " . $huruf[$angka % 10];
    } elseif ($angka < 200) {
        return "Seratus " . terbilang($angka - 100);
    } elseif ($angka < 1000) {
        return $huruf[floor($angka / 100)] . " Ratus " . terbilang($angka % 100);
    } elseif ($angka < 2000) {
        return "Seribu " . terbilang($angka - 1000);
    } elseif ($angka < 1000000) {
        return terbilang(floor($angka / 1000)) . " Ribu " . terbilang($angka % 1000);
    } elseif ($angka < 1000000000) {
        return terbilang(floor($angka / 1000000)) . " Juta " . terbilang($angka % 1000000);
    } elseif ($angka < 1000000000000) {
        return terbilang(floor($angka / 1000000000)) . " Miliar " . terbilang($angka % 1000000000);
    } else {
        return terbilang(floor($angka / 1000000000000)) . " Triliun " . terbilang($angka % 1000000000000);
    }
}

// Generate nomor nota otomatis
function generate_nomor_nota($supplier_id, $pdo) {
    $prefix = ($supplier_id == 1) ? 'GMB' : 'RDV';
    $tahun = date('Y');
    
    // Get last number for this supplier and year
    $stmt = $pdo->prepare("SELECT nomor_nota FROM nota WHERE supplier_id = ? AND YEAR(tanggal_pengiriman) = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$supplier_id, $tahun]);
    $last = $stmt->fetch();
    
    if ($last) {
        // Extract number from format: NOTA/GMB/2024/001
        $parts = explode('/', $last['nomor_nota']);
        $last_number = isset($parts[3]) ? intval($parts[3]) : 0;
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    return sprintf("NOTA/%s/%s/%03d", $prefix, $tahun, $new_number);
}

// Get periode based on date (starting from Sept 21, 6 days per period)
function get_periode($tanggal) {
    $start_date = strtotime('2024-09-21');
    $current_date = strtotime($tanggal);
    
    if ($current_date < $start_date) {
        return 0;
    }
    
    $diff_days = floor(($current_date - $start_date) / (60 * 60 * 24));
    $periode = floor($diff_days / 6) + 1;
    
    return $periode;
}

// Get periode date range
function get_periode_range($periode) {
    $start_date = strtotime('2024-09-21');
    $periode_start = date('Y-m-d', strtotime('+' . (($periode - 1) * 6) . ' days', $start_date));
    $periode_end = date('Y-m-d', strtotime('+' . (($periode * 6) - 1) . ' days', $start_date));
    
    return [
        'start' => $periode_start,
        'end' => $periode_end,
        'label' => 'Periode ' . $periode . ' (' . format_tanggal($periode_start) . ' - ' . format_tanggal($periode_end) . ')'
    ];
}

// Flash message functions
function set_flash($type, $message) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
}

function get_flash() {
    if (isset($_SESSION['flash_message'])) {
        $flash = [
            'type' => $_SESSION['flash_type'],
            'message' => $_SESSION['flash_message']
        ];
        unset($_SESSION['flash_type']);
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

// Log activity
function log_activity($user_id, $action, $description, $pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $action, $description]);
    } catch (PDOException $e) {
        // Silent fail for logging
        error_log("Log activity failed: " . $e->getMessage());
    }
}

?>
