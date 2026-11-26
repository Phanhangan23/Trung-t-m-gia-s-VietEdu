<?php
require_once __DIR__ . '/bao-mat.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Chỉ xóa session của user thường, giữ nguyên session admin (nếu có)
unset($_SESSION['user_type'], $_SESSION['user_id'], $_SESSION['user_name'],
      $_SESSION['tutor_id'], $_SESSION['parent_id']);
session_regenerate_id(true);

header('Location: index.php');
exit;