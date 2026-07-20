<?php
/**
 * AUREUM HOTEL PLATFORM — Database Configuration
 * ------------------------------------------------
 * Update the values below to match your MySQL setup.
 * This file uses PDO so it works the same on localhost (XAMPP/WAMP/MAMP)
 * and on most shared hosting (cPanel, etc).
 */

// ---- EDIT THESE 4 LINES -------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'hotel_website');
define('DB_USER', 'root');        // your MySQL username
define('DB_PASS', '');            // your MySQL password
// ---------------------------------------------------------------

// Dynamic Base URL calculation for assets and routing
$projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$scriptPath = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
$relativePath = str_replace($projectRoot, '', $scriptPath); // e.g. "/admin/index.php"

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$baseUrl = '';
if ($relativePath && substr($scriptName, -strlen($relativePath)) === $relativePath) {
    $baseUrl = substr($scriptName, 0, -strlen($relativePath));
} else {
    // Fallback if script filename/name mismatch or running via CLI
    $baseUrl = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if (preg_replace('#/(admin|public|guest|api|includes|config)$#i', '', $baseUrl) !== $baseUrl) {
        $baseUrl = preg_replace('#/(admin|public|guest|api|includes|config)$#i', '', $baseUrl);
    }
}
$baseUrl = rtrim($baseUrl, '/');
define('BASE_URL', $baseUrl);

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            // In production, log this instead of echoing it.
            die('Database connection failed. Please check config/database.php — (' . $e->getMessage() . ')');
        }
    }
    return $pdo;
}

// Session must start before any output, on every page that needs auth.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
