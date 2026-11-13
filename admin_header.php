<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VietEdu Admin - Hệ Thống Quản Trị Trung Tâm</title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin-style.css">
</head>
<body>

    <!-- 1. HEADER ADMIN -->
    <header class="admin-header">
        <div class="header-left">
            <a href="#" class="admin-logo">
                <i class="fa-solid fa-graduation-cap"></i> Viet<span>Edu Admin</span>
            </a>
            <button class="btn-toggle-sidebar" id="btnToggleSidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>

        <div class="header-right">
            <div class="admin-profile">
                <i class="fa-solid fa-circle-user avatar-icon"></i>
                <div class="profile-info">
                    <span class="admin-name">Nguyễn Văn A</span>
                    <span class="admin-role">Quản trị viên (Admin)</span>
                </div>
            </div>
            <a href="index.html" class="btn-logout" title="Đăng xuất"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
        </div>
    </header>

    <div class="admin-body">
        <!-- 2. SIDEBAR ADMIN (ĐÃ PHÂN MỤC CHO 4 THÀNH VIÊN) -->
        <aside class="admin-sidebar" id="adminSidebar">
            
            <!-- Dashboard Tổng Quan (Thành viên 4) -->
            <div class="menu-category">TỔNG QUAN</div>
            <ul class="sidebar-nav">
                <li><a href="#" class="nav-item active" data-target="view-dashboard"><i class="fa-solid fa-chart-line"></i> Dashboard & Doanh Thu</a></li>
            </ul>

            <!-- PHÂN HỆ 1: AUTH & CSKH -->
<div class="menu-category">PHÂN HỆ 1: AUTH & CSKH</div>
<ul class="sidebar-nav">
    <li><a href="admin.php?view=dashboard" class="nav-item <?= ($current_view == 'dashboard') ? 'active' : '' ?>"><i class="fa-solid fa-chart-line"></i> Dashboard & Doanh Thu</a></li>
    <li><a href="admin.php?view=staff" class="nav-item <?= ($current_view == 'staff') ? 'active' : '' ?>"><i class="fa-solid fa-user-gear"></i> QL & Phân Quyền Nhân Viên</a></li>
    <li><a href="admin.php?view=tickets" class="nav-item <?= ($current_view == 'tickets') ? 'active' : '' ?>"><i class="fa-solid fa-headset"></i> Xử Lý Ticket Khiếu Nại</a></li>
</ul>

<!-- PHÂN HỆ 2: GIA SƯ & KYC -->
<div class="menu-category">PHÂN HỆ 2: GIA SƯ & KYC</div>
<ul class="sidebar-nav">
    <li><a href="admin.php?view=tutors" class="nav-item <?= ($current_view == 'tutors') ? 'active' : '' ?>"><i class="fa-solid fa-user-graduate"></i> QL Hồ Sơ Gia Sư</a></li>
    <li><a href="admin.php?view=kyc" class="nav-item <?= ($current_view == 'kyc') ? 'active' : '' ?>"><i class="fa-solid fa-id-card"></i> Soi & Duyệt Hồ Sơ KYC</a></li>
</ul>

<!-- PHÂN HỆ 3: LỚP HỌC & MATCHING -->
<div class="menu-category">PHÂN HỆ 3: LỚP HỌC & MATCHING</div>
<ul class="sidebar-nav">
    <li><a href="admin.php?view=classes" class="nav-item <?= ($current_view == 'classes') ? 'active' : '' ?>"><i class="fa-solid fa-chalkboard-user"></i> Duyệt & Công Bố Lớp Học</a></li>
    <li><a href="admin.php?view=matching" class="nav-item <?= ($current_view == 'matching') ? 'active' : '' ?>"><i class="fa-solid fa-diagram-project"></i> Matching Ghép Lớp</a></li>
    <li><a href="admin.php?view=reports" class="nav-item <?= ($current_view == 'reports') ? 'active' : '' ?>"><i class="fa-solid fa-file-circle-check"></i> QL Báo Cáo Buổi Học</a></li>
</ul>

<!-- PHÂN HỆ 4: PHỤ HUYNH & VIETQR -->
<div class="menu-category">PHÂN HỆ 4: PHỤ HUYNH & VIETQR</div>
<ul class="sidebar-nav">
    <li><a href="admin.php?view=parents" class="nav-item <?= ($current_view == 'parents') ? 'active' : '' ?>"><i class="fa-solid fa-users"></i> Yêu Cầu Từ Phụ Huynh</a></li>
    <li><a href="admin.php?view=payments" class="nav-item <?= ($current_view == 'payments') ? 'active' : '' ?>"><i class="fa-solid fa-receipt"></i> Giao Dịch & VietQR 24h</a></li>
</ul>

        </aside>