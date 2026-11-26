<?php
require_once __DIR__ . '/bao-mat.php';
session_start();

// Chỉ xóa phần đăng nhập nhân viên, giữ phiên người dùng thường (nếu có)
unset($_SESSION['staff_id'], $_SESSION['full_name'], $_SESSION['role_id'], $_SESSION['role_name'], $_SESSION['csrf']);
session_regenerate_id(true);

header('Location: dang-nhap-admin.php');
exit;
