<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. TỰ ĐỘNG NHẬN DIỆN THƯ MỤC ĐỂ GỌI ĐÚNG FILE CSS & LINK
$isGiaSuFolder = strpos($_SERVER['PHP_SELF'], '/gia-su/') !== false;
$goc = $goc ?? ($isGiaSuFolder ? '../' : '');
$giaSu = $giaSu ?? ($isGiaSuFolder ? '' : 'gia-su/');
$trang = $trang ?? basename($_SERVER['PHP_SELF']);

// Trang thông tin tài khoản theo vai trò đăng nhập: gia sư / phụ huynh
$loaiUser = $_SESSION['user_type'] ?? '';
// Gia sư -> hồ sơ đầy đủ (ảnh, môn dạy, khu vực...) trong cổng gia sư; đổi mật khẩu có link từ trang đó
$trangTaiKhoan = ($loaiUser === 'tutor') ? 'gia-su/ho-so.php' : 'thong-tin-phu-huynh.php';

// 2. KHỞI TẠO HÀM $act ĐỂ CHỐNG LỖI SẬP TRANG
$act = $act ?? function($dk) { return $dk ? ' active' : ''; };

// 3. THÔNG BÁO CHO GIA SƯ: lớp đã được duyệt nhưng chưa thanh toán (chỉ gia sư mới có chuông)
$tbTutorId = 0;
if ($loaiUser !== 'parent') {
    if (!empty($_SESSION['tutor_id'])) {
        $tbTutorId = (int) $_SESSION['tutor_id'];
    } elseif ($loaiUser === 'tutor' && !empty($_SESSION['user_id'])) {
        $tbTutorId = (int) $_SESSION['user_id'];
    }
}
$dsThongBao = [];
$dsDaThanhToan = [];
if ($tbTutorId > 0) {
    require_once __DIR__ . '/thong-bao-gia-su.php';
    $dsThongBao = gs_tb_lop_can_thanh_toan($tbTutorId);
    $dsDaThanhToan = gs_tb_lop_vua_thanh_toan($tbTutorId);   // đã thanh toán xong -> báo SĐT phụ huynh
}
$soThongBao = count($dsThongBao) + count($dsDaThanhToan);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'VietEdu - Cổng Thông Tin & Quản Lý Gia Sư' ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription ?? 'Trung tâm gia sư VietEdu – kết nối phụ huynh với gia sư đã xác minh hồ sơ tại Hà Nội. Đăng yêu cầu tìm gia sư, theo dõi lớp học và thanh toán VietQR minh bạch.') ?>">
    <link rel="icon" href="<?= $goc ?>favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#800020">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Đường dẫn CSS linh hoạt, tự động lùi thư mục khi cần -->
    <link rel="stylesheet" href="<?= $goc ?>style.css">
    <style>
        .header-left { display: flex; align-items: center; gap: 14px; }
        .home-link { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: rgba(255, 255, 255, .12); color: #fff; font-size: 17px; text-decoration: none; transition: background .2s, color .2s; }
        .home-link:hover { background: rgba(255, 255, 255, .25); }
        .home-link.active { background: var(--accent-gold); color: var(--primary-dark); }
        .user-link { color: #fff; font-size: 14px; font-weight: 600; display: flex; align-items: center; text-decoration: none; padding: 8px 12px; border-radius: 6px; transition: background .2s; }
        .user-link i { margin-right: 6px; color: var(--accent-gold); }
        .user-link:hover, .user-link.active { background: rgba(255, 255, 255, .15); }

        /* Chuông thông báo (chỉ gia sư) */
        .tb-wrap { position: relative; align-self: center; }
        .tb-bell { position: relative; width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 50%; background: rgba(255, 255, 255, .12); color: #fff; font-size: 17px; cursor: pointer; transition: background .2s; }
        .tb-bell:hover, .tb-bell[aria-expanded="true"] { background: rgba(255, 255, 255, .25); }
        .tb-bell:focus-visible { outline: 2px solid var(--accent-gold); outline-offset: 2px; }
        .tb-count { position: absolute; top: -4px; right: -4px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: var(--accent-gold); color: var(--primary-dark); font-size: 11px; font-weight: 700; line-height: 18px; text-align: center; }
        .tb-panel { position: absolute; top: calc(100% + 10px); right: 0; width: 360px; max-width: calc(100vw - 24px); background: #fff; color: #2b2b2b; border-radius: 10px; box-shadow: 0 10px 30px rgba(0, 0, 0, .25); overflow: hidden; z-index: 1100; }
        .tb-panel[hidden] { display: none; }
        .tb-head { padding: 12px 16px; font-weight: 700; color: var(--primary-dark); border-bottom: 1px solid #eee; }
        .tb-list { max-height: 360px; overflow-y: auto; }
        .tb-item { display: flex; gap: 12px; padding: 12px 16px; color: #2b2b2b; text-decoration: none; border-left: 3px solid var(--accent-gold); border-bottom: 1px solid #f1f1f1; font-size: 13.5px; line-height: 1.5; }
        .tb-item:hover, .tb-item:focus-visible { background: #f8ecee; }
        .tb-item:focus-visible { outline: 2px solid var(--primary-red); outline-offset: -2px; }
        .tb-icon { flex: 0 0 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #fff4cc; color: var(--primary-red); }
        .tb-item strong { color: var(--primary-dark); }
        .tb-item small { display: block; margin-top: 2px; color: #6c757d; font-size: 12.5px; }
        .tb-item small.tb-warn { color: #b3261e; }
        .tb-item-ok { border-left-color: #2E7D32; }
        .tb-icon-ok { background: #E3F4E6; color: #2E7D32; }
        .tb-phone { color: #1B5E20 !important; font-size: 15px; letter-spacing: .3px; }
        .tb-empty { padding: 22px 16px; text-align: center; color: #6c757d; font-size: 14px; }
        .tb-foot { display: block; padding: 11px 16px; text-align: center; font-size: 13.5px; font-weight: 600; color: var(--primary-red); text-decoration: none; background: #faf6f6; }
        .tb-foot:hover { text-decoration: underline; }
        @media (max-width: 600px) {
            .tb-wrap { position: static; }
            .tb-panel { position: fixed; top: 68px; left: 12px; right: 12px; width: auto; max-width: none; }
        }
    </style>
</head>
<body>

    <header>
        <div class="header-container">
            <!-- Logo + nút Trang chủ (biểu tượng ngôi nhà) nằm cạnh nhau bên trái -->
            <div class="header-left">
                <a href="<?= $goc ?>index.php" class="logo">
                    <i class="fa-solid fa-graduation-cap"></i>
                    Viet<span>Edu</span>
                </a>
                <a href="<?= $goc ?>index.php" class="home-link<?= $act($trang === 'index.php' && ($_GET['sub'] ?? '') === '') ?>" title="Trang chủ" aria-label="Trang chủ">
                    <i class="fa-solid fa-house"></i>
                </a>
            </div>

            <div class="user-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($tbTutorId > 0): ?>
                    <!-- CHUÔNG THÔNG BÁO (chỉ gia sư): lớp đã được duyệt, cần thanh toán -->
                    <div class="tb-wrap">
                        <button type="button" class="tb-bell" id="tbBell" aria-expanded="false" aria-controls="tbPanel"
                                aria-label="Thông báo<?= $soThongBao ? ', ' . $soThongBao . ' lớp cần thanh toán' : '' ?>">
                            <i class="fa-solid fa-bell" aria-hidden="true"></i>
                            <?php if ($soThongBao > 0): ?><span class="tb-count" aria-hidden="true"><?= $soThongBao > 9 ? '9+' : $soThongBao ?></span><?php endif; ?>
                        </button>
                        <div class="tb-panel" id="tbPanel" hidden>
                            <div class="tb-head">Thông báo</div>
                            <?php if ($soThongBao === 0): ?>
                                <div class="tb-empty"><i class="fa-regular fa-circle-check"></i> Bạn không có khoản nào cần thanh toán.</div>
                            <?php else: ?>
                                <div class="tb-list">
                                <?php foreach ($dsDaThanhToan as $tb): ?>
                                    <a class="tb-item tb-item-ok" href="<?= $giaSu ?>thanh-toan.php#lien-he-<?= (int) $tb['class_id'] ?>">
                                        <span class="tb-icon tb-icon-ok"><i class="fa-solid fa-phone" aria-hidden="true"></i></span>
                                        <span>
                                            <strong>Thanh toán lớp <?= htmlspecialchars($tb['class_code']) ?> thành công!</strong>
                                            Liên hệ phụ huynh <?= htmlspecialchars($tb['parent_name'] ?? '') ?>:
                                            <strong class="tb-phone"><?= htmlspecialchars($tb['parent_phone'] ?? '—') ?></strong>
                                            <small>Bấm để xem đầy đủ thông tin liên hệ.</small>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                                <?php foreach ($dsThongBao as $tb): ?>
                                    <a class="tb-item" href="<?= $giaSu ?>thanh-toan.php">
                                        <span class="tb-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                                        <span>
                                            <strong>Lớp <?= htmlspecialchars($tb['class_code']) ?> – <?= htmlspecialchars($tb['title']) ?></strong>
                                            đã được duyệt. Vui lòng thanh toán phí nhận lớp để nhận thông tin chi tiết lớp.
                                            <?php if ($tb['het_han']): ?>
                                                <small class="tb-warn">Mã thanh toán trước đã hết hạn, hãy tạo mã mới.</small>
                                            <?php elseif ($tb['con_lai'] !== null): ?>
                                                <small>Còn <?= htmlspecialchars($tb['con_lai']) ?> để thanh toán (mã <?= htmlspecialchars($tb['tx_code']) ?>).</small>
                                            <?php else: ?>
                                                <small>Bấm để lấy mã thanh toán.</small>
                                            <?php endif; ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                                </div>
                                <a class="tb-foot" href="<?= $giaSu ?>thanh-toan.php">Đến trang thanh toán</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <script>
                    (function () {
                        var bell = document.getElementById('tbBell'), panel = document.getElementById('tbPanel');
                        if (!bell || !panel) { return; }
                        function dong() { panel.hidden = true; bell.setAttribute('aria-expanded', 'false'); }
                        bell.addEventListener('click', function (e) {
                            e.stopPropagation();
                            if (panel.hidden) { panel.hidden = false; bell.setAttribute('aria-expanded', 'true'); } else { dong(); }
                        });
                        document.addEventListener('click', function (e) { if (!panel.hidden && !panel.contains(e.target)) { dong(); } });
                        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { dong(); bell.focus(); } });
                    })();
                    </script>
                    <?php endif; ?>
                    <a href="<?= $goc . $trangTaiKhoan ?>" class="user-link<?= $act($trang === basename($trangTaiKhoan) || ($loaiUser === 'tutor' && $trang === 'thong-tin-gia-su.php')) ?>" title="Xem thông tin tài khoản">
                        <i class="fa-solid fa-user"></i>
                        <?= htmlspecialchars($_SESSION['user_name'] ?? 'Tài khoản') ?>
                    </a>
                    <a href="<?= $goc ?>dang-xuat-nguoi-dung.php" class="btn btn-outline" style="border-color: #ff4d4d; color: #ff4d4d; padding: 8px 16px; border-radius: 6px; text-decoration: none;">
                        <i class="fa-solid fa-right-from-bracket"></i> Đăng xuất
                    </a>
                <?php else: ?>
                    <a href="<?= $goc ?>dang-nhap-nguoi-dung.php" class="btn btn-outline">
                        <i class="fa-solid fa-right-to-bracket"></i> Đăng Nhập
                    </a>
                    <a href="<?= $goc ?>chon-vai-tro.php" class="btn btn-primary">
                        <i class="fa-solid fa-user-plus"></i> Đăng Ký
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>