<?php
require_once 'db.php';
session_start();
require_once 'phan-quyen.php';

// Chỉ Admin mới được xóa nhân viên
if (!coQuyen('staff')) {
    http_response_code(403);
    exit('Bạn không có quyền thực hiện thao tác này.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php?view=staff');
    exit;
}

function quayVe(string $loai, string $noiDung): void
{
    $_SESSION['flash'] = ['loai' => $loai, 'nd' => $noiDung];
    header('Location: admin.php?view=staff');
    exit;
}

if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    quayVe('loi', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
}

$staffId = (int) ($_POST['staff_id'] ?? 0);

// Không cho tự xóa chính mình
if ($staffId === (int) $_SESSION['staff_id']) {
    quayVe('loi', 'Bạn không thể xóa chính tài khoản đang đăng nhập.');
}

try {
    $st = $conn->prepare('SELECT full_name FROM staffs WHERE staff_id = ?');
    $st->execute([$staffId]);
    $ten = $st->fetchColumn();
    if ($ten === false) {
        quayVe('loi', 'Không tìm thấy nhân viên này.');
    }

    $conn->prepare('DELETE FROM staffs WHERE staff_id = ?')->execute([$staffId]);
    quayVe('ok', 'Đã xóa nhân viên ' . $ten . '.');
} catch (PDOException $e) {
    error_log('Lỗi xóa nhân viên: ' . $e->getMessage());
    quayVe('loi', 'Không thể xóa lúc này, vui lòng thử lại sau.');
}