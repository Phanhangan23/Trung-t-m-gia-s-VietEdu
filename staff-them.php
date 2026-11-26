<?php
require_once 'db.php';
session_start();
require_once 'phan-quyen.php';

// Chỉ người có quyền xem mục "staff" (tức Admin) mới được thêm nhân viên
if (!coQuyen('staff')) {
    http_response_code(403);
    exit('Bạn không có quyền thực hiện thao tác này.');
}

// Chỉ nhận dữ liệu gửi từ form (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php?view=staff');
    exit;
}

// Hàm nhỏ: lưu thông báo rồi quay về trang danh sách
function quayVe(string $loai, string $noiDung): void
{
    $_SESSION['flash'] = ['loai' => $loai, 'nd' => $noiDung];
    header('Location: admin.php?view=staff');
    exit;
}

// Chặn form giả mạo từ trang khác
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    quayVe('loi', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
}

$hoTen    = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$matKhau  = $_POST['password'] ?? '';
$roleId   = (int) ($_POST['role_id'] ?? 0);

// Kiểm tra dữ liệu
if (mb_strlen($hoTen) < 2 || mb_strlen($hoTen) > 100) {
    quayVe('loi', 'Họ tên phải từ 2 đến 100 ký tự.');
}
if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
    quayVe('loi', 'Tên đăng nhập 3-50 ký tự, chỉ gồm chữ không dấu, số và dấu gạch dưới.');
}
if (($loiMk = ve_kiem_tra_mat_khau($matKhau, [$username])) !== '') {
    quayVe('loi', $loiMk);
}

// Vai trò phải có thật trong bảng roles
$st = $conn->prepare('SELECT COUNT(*) FROM roles WHERE role_id = ?');
$st->execute([$roleId]);
if (!$st->fetchColumn()) {
    quayVe('loi', 'Vai trò không hợp lệ.');
}

// Lưu vào database (mật khẩu được mã hóa)
try {
    $st = $conn->prepare('INSERT INTO staffs (full_name, username, password_hash, role_id) VALUES (?, ?, ?, ?)');
    $st->execute([$hoTen, $username, password_hash($matKhau, PASSWORD_DEFAULT), $roleId]);
    quayVe('ok', 'Đã thêm nhân viên ' . $hoTen . '.');
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {          // mã lỗi "trùng dữ liệu"
        quayVe('loi', 'Tên đăng nhập "' . $username . '" đã tồn tại.');
    }
    error_log('Lỗi thêm nhân viên: ' . $e->getMessage());
    quayVe('loi', 'Không thể lưu lúc này, vui lòng thử lại sau.');
}