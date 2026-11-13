<?php 
include 'db.php'; 

// Lấy tham số view từ URL (mặc định là dashboard nếu chưa chọn)
$current_view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

// Truy vấn sẵn dữ liệu tổng quan cho dashboard
try {
    $stmtRev = $conn->query("SELECT SUM(amount) AS total_revenue FROM transactions WHERE status = 'success'");
    $revenue = $stmtRev->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?: 0;
    $formatted_revenue = number_format($revenue, 0, ',', '.') . ' VNĐ';

    $stmtClasses = $conn->query("SELECT COUNT(*) FROM classes");
    $total_classes = $stmtClasses->fetchColumn();

    $stmtKyc = $conn->query("SELECT COUNT(*) FROM kyc_profiles WHERE status = 'pending'");
    $pending_kyc = $stmtKyc->fetchColumn();

    $stmtTx = $conn->query("SELECT t.*, c.class_code, tu.full_name 
                            FROM transactions t 
                            JOIN classes c ON t.class_id = c.class_id 
                            JOIN tutors tu ON t.tutor_id = tu.tutor_id 
                            ORDER BY t.tx_time DESC LIMIT 5");
    $transactions = $stmtTx->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_msg = "Lỗi truy vấn: " . $e->getMessage();
}
?>
<?php include 'admin_header.php'; ?>

<main class="admin-main">
    <?php
    // Danh sách các view hợp lệ trong thư mục admin_views/
    $allowed_views = ['dashboard', 'staff', 'tickets', 'tutors', 'kyc', 'classes', 'matching', 'reports', 'parents', 'payments'];
    
    if (in_array($current_view, $allowed_views)) {
        $file_to_include = "admin_views/" . $current_view . ".php";
        if (file_exists($file_to_include)) {
            include $file_to_include;
        } else {
            include "admin_views/dashboard.php";
        }
    } else {
        echo "<h2>Trang quản trị không tồn tại.</h2>";
    }
    ?>
</main>

<?php include 'admin_footer.php'; ?>