<?php
require_once __DIR__ . '/../config/database.php';
unset($_SESSION['2fa_pending_staff_id']);
session_unset();
session_destroy();
header('Location: ' . BASE_URL . '/admin/login.php');
exit;

