<?php
// Mỗi màn hình admin cho phép những role_id nào xem
// role_id: 1 = Admin, 2 = Tư vấn viên (theo bảng roles)
// Mỗi lần tải trang: đọc lại thông tin nhân viên từ database
// (để đổi vai trò / xóa tài khoản có hiệu lực ngay, không phải chờ đăng xuất)
if (isset($_SESSION['staff_id'])) {
    $stCheck = $conn->prepare(
        'SELECT s.full_name, s.role_id, r.role_name
         FROM staffs s
         LEFT JOIN roles r ON s.role_id = r.role_id
         WHERE s.staff_id = ?'
    );
    $stCheck->execute([$_SESSION['staff_id']]);
    $nvHienTai = $stCheck->fetch(PDO::FETCH_ASSOC);

    if (!$nvHienTai) {
        // Tài khoản đã bị xóa -> đăng xuất ngay
        session_destroy();
        header('Location: dang-nhap-admin.php');
        exit;
    }

    // Cập nhật lại session theo dữ liệu mới nhất
    $_SESSION['full_name'] = $nvHienTai['full_name'];
    $_SESSION['role_id']   = $nvHienTai['role_id'];
    $_SESSION['role_name'] = $nvHienTai['role_name'];
}
const QUYEN_VIEW = [
    'dashboard' => [1],
    'staff'     => [1],
    'payments'  => [1],
    'tickets'   => [1, 2],
    'tutors'    => [1, 2],
    'kyc'       => [1, 2],
    'classes'   => [1, 2],
    'matching'  => [1, 2],
    'parents'   => [1, 2],
];

// Người đang đăng nhập có được xem màn hình này không?
function coQuyen(string $view): bool
{
    $roleId = (int) ($_SESSION['role_id'] ?? 0);
    return in_array($roleId, QUYEN_VIEW[$view] ?? [], true);
}

// Màn hình đầu tiên mà người này được xem (dùng làm trang mặc định)
function viewDauTien(): string
{
    foreach (QUYEN_VIEW as $view => $_) {
        if (coQuyen($view)) {
            return $view;
        }
    }
    return 'dashboard';
}