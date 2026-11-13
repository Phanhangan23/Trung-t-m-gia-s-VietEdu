<?php
$host = 'localhost';
$dbname = 'vietedu';
$username = 'root'; // Tài khoản mặc định của XAMPP/WAMP
$password = '';     // Mật khẩu mặc định để trống

try {
    // Khởi tạo kết nối PDO
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Thiết lập chế độ báo lỗi để dễ dàng debug
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch(PDOException $e) {
    // Bắt lỗi nếu kết nối thất bại
    echo "Kết nối thất bại: " . $e->getMessage();
    die();
}

// Thêm hàm db() để các file khác có thể gọi
if (!function_exists('db')) {
    function db() {
        global $conn;
        return $conn;
    }
}
?>