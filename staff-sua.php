<?php
require_once 'db.php';
session_start();
require_once 'phan-quyen.php';

// Chỉ Admin mới được sửa nhân viên
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
$hoTen   = trim($_POST['full_name'] ?? '');
$roleId  = (int) ($_POST['role_id'] ?? 0);
$matKhau = $_POST['password'] ?? '';   // để trống = giữ mật khẩu cũ

if (mb_strlen($hoTen) < 2 || mb_strlen($hoTen) > 100) {
    quayVe('loi', 'Họ tên phải từ 2 đến 100 ký tự.');
}
// Nhân viên cần sửa phải tồn tại
$st = $conn->prepare('SELECT role_id, username FROM staffs WHERE staff_id = ?');
$st->execute([$staffId]);
$hienTai = $st->fetch(PDO::FETCH_ASSOC);
if (!$hienTai) {
    quayVe('loi', 'Không tìm thấy nhân viên này.');
}

if ($matKhau !== '' && ($loiMk = ve_kiem_tra_mat_khau($matKhau, [$hienTai['username']])) !== '') {
    quayVe('loi', $loiMk);
}

// Vai trò phải có thật
$st = $conn->prepare('SELECT COUNT(*) FROM roles WHERE role_id = ?');
$st->execute([$roleId]);
if (!$st->fetchColumn()) {
    quayVe('loi', 'Vai trò không hợp lệ.');
}

// Không cho tự đổi vai trò của chính mình (tránh tự khóa quyền Admin của mình)
$laChinhMinh = ($staffId === (int) $_SESSION['staff_id']);
if ($laChinhMinh && $roleId !== (int) $hienTai['role_id']) {
    quayVe('loi', 'Bạn không thể tự đổi vai trò của chính mình.');
}

try {
    if ($matKhau !== '') {
        $st = $conn->prepare('UPDATE staffs SET full_name = ?, role_id = ?, password_hash = ? WHERE staff_id = ?');
        $st->execute([$hoTen, $roleId, password_hash($matKhau, PASSWORD_DEFAULT), $staffId]);
    } else {
        $st = $conn->prepare('UPDATE staffs SET full_name = ?, role_id = ? WHERE staff_id = ?');
        $st->execute([$hoTen, $roleId, $staffId]);
    }

    if ($laChinhMinh) {
        $_SESSION['full_name'] = $hoTen;   // cập nhật tên hiện ở góc phải
    }
    quayVe('ok', 'Đã cập nhật nhân viên ' . $hoTen . '.');
} catch (PDOException $e) {
    error_log('Lỗi sửa nhân viên: ' . $e->getMessage());
    quayVe('loi', 'Không thể lưu lúc này, vui lòng thử lại sau.');
}