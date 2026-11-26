<?php
require_once __DIR__ . '/bao-mat.php';   // cấu hình phiên an toàn + hàm bảo mật dùng chung
require_once __DIR__ . '/cau-hinh.php';  // tài khoản ngân hàng, nội dung chuyển khoản, VietQR

$host = 'localhost';
$port = 3308; // MySQL của XAMPP trên máy này chạy cổng 3308 (cổng 3306 bị MySQL khác chiếm)
$dbname = 'vietedu';
$username = 'root'; // Tài khoản mặc định của XAMPP/WAMP
$password = '';     // Mật khẩu mặc định để trống

try {
    // Khởi tạo kết nối PDO
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);

    // Thiết lập chế độ báo lỗi để dễ dàng debug
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // Ghi chi tiết lỗi vào log, không hiển thị thông tin kỹ thuật cho người dùng
    error_log('Kết nối CSDL thất bại: ' . $e->getMessage());
    http_response_code(503);
    echo 'Hệ thống đang bảo trì, vui lòng thử lại sau.';
    die();
}

// Thêm hàm db() để các file khác có thể gọi
if (!function_exists('db')) {
    function db() {
        global $conn;
        return $conn;
    }
}
