<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'VietEdu - Cổng Thông Tin & Quản Lý Gia Sư Uy Tín' ?></title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- File CSS riêng -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- HEADER & THANH ĐIỀU HƯỚNG CHÍNH -->
    <header>
        <div class="header-container">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-graduation-cap"></i>
                Viet<span>Edu</span>
            </a>
            <?php $loaiUser = $_SESSION['user_type'] ?? ''; ?>
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link active">Trang Chủ</a></li>
            <?php if ($loaiUser === 'parent'): ?>
                <li><a href="index.php?sub=home" class="nav-link active">Trang Chủ</a></li>
                <li><a href="index.php?sub=lop_trong" class="nav-link">Lớp Học Trống</a></li>
                <li><a href="index.php?sub=gia_su" class="nav-link">Dành Cho Gia Sư</a></li>
                <li><a href="index.php?sub=phu_huynh" class="nav-link">Dành Cho Phụ Huynh</a></li>
                <li><a href="index.php?sub=faq" class="nav-link">Tin Tức / FAQ</a></li>
            <?php elseif ($loaiUser === 'tutor'): ?>
                    <!-- Menu dành riêng cho Gia Sư -->
                    <li><a href="lop-hoc-trong.php" class="nav-link">Tìm Lớp Mới</a></li>
                    <li><a href="#" class="nav-link">Lớp Đang Dạy</a></li>
                    <li><a href="thong-tin-gia-su.php" class="nav-link">Hồ Sơ & KYC</a></li>

                <?php else: ?>
                    <!-- Menu cho Khách vãng lai (Chưa đăng nhập) -->
                    <li><a href="lop-hoc-trong.php" class="nav-link">Lớp Học Trống</a></li>
                    <li><a href="#" class="nav-link">Dành Cho Gia Sư</a></li>
                    <li><a href="#" class="nav-link">Dành Cho Phụ Huynh</a></li>
                <?php endif; ?>
                
                <li><a href="#" class="nav-link">Tin Tức / FAQ</a></li>
            </ul>

            <div class="user-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Nếu đã đăng nhập, hiển thị tên và nút Đăng xuất -->
                    <span style="color: #fff; font-size: 14px; display: flex; align-items: center; font-weight: 600;">
                        <i class="fa-solid fa-user" style="margin-right: 6px; color: var(--accent-gold);"></i> 
                        <?= htmlspecialchars($_SESSION['user_name'] ?? 'Tài khoản') ?>
                    </span>
                    <a href="dang-xuat-nguoi-dung.php" class="btn btn-outline" style="border-color: #ff4d4d; color: #ff4d4d;">
                        <i class="fa-solid fa-right-from-bracket"></i> Đăng xuất
                    </a>
                <?php else: ?>
                    <!-- Nếu chưa đăng nhập, hiển thị nút Đăng nhập và Đăng ký -->
                    <a href="dang-nhap-nguoi-dung.php" class="btn btn-outline">
                        <i class="fa-solid fa-right-to-bracket"></i> Đăng Nhập
                    </a>
                    <a href="chon-vai-tro.php" class="btn btn-primary">
                        <i class="fa-solid fa-user-plus"></i> Đăng Ký
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>
<?php 
