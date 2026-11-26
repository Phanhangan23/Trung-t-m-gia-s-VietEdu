<?php
require_once 'db.php';
session_start();
require_once 'phan-quyen.php';
require_once 'phan-trang.php';

// Bắt buộc đăng nhập nhân viên
if (empty($_SESSION['staff_id'])) {
    header('Location: dang-nhap-admin.php');
    exit;
}

/* ---------- CHỐNG GIẢ MẠO FORM (CSRF) CHO TOÀN BỘ CÁC TRANG QUẢN TRỊ ----------
 * Mọi form POST trong admin_views/ được tự chèn mã csrf (xem ob_start bên dưới).
 * Yêu cầu POST thiếu hoặc sai mã -> bỏ toàn bộ dữ liệu gửi lên TRƯỚC khi view xử lý,
 * nên không thao tác nào (thêm / sửa / xóa / duyệt) được thực hiện, và báo cho người dùng. */
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$csrf_loi = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
    $_POST  = [];
    $_FILES = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';   // các view kiểm tra REQUEST_METHOD === 'POST' nên sẽ bỏ qua phần xử lý
    $csrf_loi = 'Phiên làm việc đã hết hạn hoặc yêu cầu không hợp lệ nên thao tác CHƯA được thực hiện. Vui lòng thử lại.';
}

// Lấy tham số view từ URL (mặc định là màn hình đầu tiên người này được xem)
$current_view = isset($_GET['view']) ? (string) $_GET['view'] : viewDauTien();

// Xuất Excel trang Tổng quan: phải chạy TRƯỚC khi in bất kỳ HTML nào (file tải về không được lẫn giao diện)
if ($current_view === 'dashboard' && ($_GET['export'] ?? '') === 'xlsx') {
    if (!coQuyen('dashboard')) {
        http_response_code(403);
        exit('Bạn không có quyền xuất báo cáo này.');
    }
    require_once 'xuat-excel.php';
    $XUAT_EXCEL = true;
    include __DIR__ . '/admin_views/dashboard.php';   // tự gửi file .xlsx rồi dừng
    exit;
}

// (Dữ liệu tổng quan do admin_views/dashboard.php tự truy vấn theo bộ lọc – không truy vấn sẵn ở đây nữa)
?>
<?php
// Tự chèn mã csrf vào ngay sau mỗi thẻ <form method="post"> của trang (không phụ thuộc JavaScript)
$csrf_the = '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') . '">';
ob_start(function ($html) use ($csrf_the) {
    return preg_replace('/<form\b[^>]*\bmethod\s*=\s*["\']?post\b[^>]*>/i', '$0' . $csrf_the, $html);
});
include 'admin_header.php';
?>

<main class="admin-main">
    <?php if ($csrf_loi !== ''): ?>
        <div class="db-alert" role="alert"><i class="fa-solid fa-shield-halved"></i> <?= htmlspecialchars($csrf_loi) ?></div>
    <?php endif; ?>
    <?php
    // Danh sách các view hợp lệ trong thư mục admin_views/
    $allowed_views = ['dashboard', 'staff', 'tickets', 'tutors', 'kyc', 'classes', 'matching', 'parents', 'payments'];
    
    if (in_array($current_view, $allowed_views, true) && !coQuyen($current_view)) {
        echo '<h2>Bạn không có quyền xem mục này.</h2>';
    } elseif (in_array($current_view, $allowed_views, true)) {
        $file_to_include = __DIR__ . "/admin_views/" . $current_view . ".php";
        if (file_exists($file_to_include)) {
            include $file_to_include;
        } else {
            include __DIR__ . "/admin_views/dashboard.php";
        }
    } else {
        echo "<h2>Trang quản trị không tồn tại.</h2>";
    }
    ?>
</main>

<?php include 'admin_footer.php'; ?>