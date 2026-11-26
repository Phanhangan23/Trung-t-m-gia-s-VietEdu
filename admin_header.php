<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VietEdu - Hệ Thống Quản Trị Trung Tâm</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin-style.css?v=<?= filemtime(__DIR__ . '/admin-style.css') ?>">
</head>
<body>

<?php
$ahE = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
// Menu quản trị: [view, icon, nhãn] theo từng phân hệ; chỉ hiện mục mà người đăng nhập có quyền xem
$ahMenu = [
    'PHÂN HỆ 1: TÀI KHOẢN & CSKH' => [
        ['dashboard', 'fa-chart-line', 'Tổng Quan & Doanh Thu'],
        ['staff', 'fa-user-gear', 'QL & Phân Quyền Nhân Viên'],
        ['tickets', 'fa-headset', 'Xử Lý Khiếu Nại & Hỗ Trợ'],
    ],
    'PHÂN HỆ 2: GIA SƯ & XÁC MINH' => [
        ['tutors', 'fa-user-graduate', 'QL Hồ Sơ Gia Sư'],
        ['kyc', 'fa-id-card', 'Soi & Duyệt Hồ Sơ Xác Minh'],
    ],
    'PHÂN HỆ 3: LỚP HỌC & GHÉP LỚP' => [
        ['classes', 'fa-chalkboard-user', 'Duyệt & Công Bố Lớp Học'],
        ['matching', 'fa-diagram-project', 'Ghép Lớp Gia Sư'],
    ],
    'PHÂN HỆ 4: PHỤ HUYNH & VIETQR' => [
        ['parents', 'fa-users', 'Yêu Cầu Từ Phụ Huynh'],
        ['payments', 'fa-receipt', 'Giao Dịch & VietQR 24h'],
    ],
];
?>

    <!-- 1. HEADER ADMIN -->
    <header class="admin-header">
        <div class="header-left">
            <a href="admin.php" class="admin-logo">
                <i class="fa-solid fa-graduation-cap"></i> Viet<span>Edu Quản Trị</span>
            </a>
            <button class="btn-toggle-sidebar" id="btnToggleSidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>

        <div class="header-right">
            <div class="admin-profile">
                <i class="fa-solid fa-circle-user avatar-icon"></i>
                <div class="profile-info">
                    <span class="admin-name"><?= $ahE($_SESSION['full_name'] ?? 'Nhân viên') ?></span>
                    <span class="admin-role"><?= $ahE($_SESSION['role_name'] ?? 'Chưa phân quyền') ?></span>
                </div>
            </div>
            <a href="dang-xuat-admin.php" class="btn-logout" title="Đăng xuất"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
        </div>
    </header>

    <div class="admin-body">
        <!-- 2. SIDEBAR ADMIN -->
        <aside class="admin-sidebar" id="adminSidebar">
            <?php foreach ($ahMenu as $ahNhom => $ahMuc): ?>
                <?php $ahMuc = array_filter($ahMuc, fn($m) => coQuyen($m[0])); ?>
                <?php if (!$ahMuc) continue; ?>
                <div class="menu-category"><?= $ahE($ahNhom) ?></div>
                <ul class="sidebar-nav">
                    <?php foreach ($ahMuc as [$ahView, $ahIcon, $ahNhan]): ?>
                        <li><a href="admin.php?view=<?= $ahView ?>" class="nav-item <?= ($current_view === $ahView) ? 'active' : '' ?>"><i class="fa-solid <?= $ahIcon ?>"></i> <?= $ahE($ahNhan) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </aside>
