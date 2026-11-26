<?php
/**
 * TRANG NỘI DUNG CHO CÁC MỤC Ở CHÂN TRANG
 * Địa chỉ:  trang.php?p=gioi-thieu | quy-dinh | hoan-phi | bao-mat
 *           | huong-dan-nhan-lop | thanh-toan-vietqr | xac-minh-ho-so
 * Nội dung nằm trong trang_noi_dung.php; file này chỉ lo bố cục.
 */
$duLieu   = require __DIR__ . '/trang_noi_dung.php';
$cacNhom  = $duLieu['nhom'];
$cacTrang = $duLieu['trang'];

$trangChu = 'index.php';   // đổi nếu trang chủ của nhóm có tên khác

$e = static function ($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
};

$slug  = is_string($_GET['p'] ?? null) ? $_GET['p'] : '';
$trangNoiDung = $cacTrang[$slug] ?? null;

// Phải đặt mã trạng thái trước khi header.php in HTML
if ($trangNoiDung === null) {
    http_response_code(404);
}

$tieuDeTrang = $trangNoiDung ? $trangNoiDung['tieu_de'] . ' - VietEdu' : 'Không tìm thấy trang - VietEdu';

include __DIR__ . '/header.php';
?>
<script>document.title = <?= json_encode($tieuDeTrang, JSON_UNESCAPED_UNICODE) ?>;</script>

<div class="main-layout info-page">

    <!-- SIDEBAR: các trang ở chân trang, nhóm theo cột -->
    <aside class="sidebar" aria-label="Mục lục thông tin">
        <?php foreach ($cacNhom as $nhomKhoa => $nhom): ?>
            <div class="sidebar-title"><i class="fa-solid <?= $e($nhom['icon']) ?>"></i> <?= $e($nhom['ten']) ?></div>
            <ul class="sidebar-menu">
                <?php foreach ($cacTrang as $khoa => $t): ?>
                    <?php if ($t['nhom'] !== $nhomKhoa) { continue; } ?>
                    <li>
                        <a href="trang.php?p=<?= urlencode($khoa) ?>"
                           class="<?= $khoa === $slug ? 'active' : '' ?>"
                           <?= $khoa === $slug ? 'aria-current="page"' : '' ?>>
                            <i class="fa-solid <?= $e($t['icon']) ?>"></i> <?= $e($t['ten_menu']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </aside>

    <article class="content-area info-article">
    <?php if ($trangNoiDung === null): /* ---------- 404 ---------- */ ?>

        <h1 class="info-title">Không tìm thấy trang</h1>
        <p class="info-lead">Trang bạn mở không tồn tại hoặc đã được chuyển đi. Hãy chọn một mục trong danh sách bên dưới.</p>
        <div class="info-related">
            <?php foreach ($cacTrang as $khoa => $t): ?>
                <a href="trang.php?p=<?= urlencode($khoa) ?>"><i class="fa-solid <?= $e($t['icon']) ?>"></i> <?= $e($t['ten_menu']) ?></a>
            <?php endforeach; ?>
        </div>

    <?php else: /* ---------- NỘI DUNG ---------- */ ?>

        <nav class="info-breadcrumb" aria-label="Đường dẫn">
            <a href="<?= $e($trangChu) ?>">Trang chủ</a>
            <span aria-hidden="true">/</span>
            <span><?= $e($cacNhom[$trangNoiDung['nhom']]['ten']) ?></span>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?= $e($trangNoiDung['ten_menu']) ?></span>
        </nav>

        <h1 class="info-title"><?= $e($trangNoiDung['tieu_de']) ?></h1>
        <p class="info-lead"><?= $e($trangNoiDung['mo_ta']) ?></p>

        <?php foreach ($trangNoiDung['khoi'] as $khoi): ?>
            <?php switch ($khoi['loai']):
                case 'heading': ?>
                    <h2><?= $e($khoi['text']) ?></h2>
                    <?php break;
                case 'text': ?>
                    <p><?= $e($khoi['text']) ?></p>
                    <?php break;
                case 'list': ?>
                    <ul class="info-list">
                        <?php foreach ($khoi['items'] as $muc): ?>
                            <li><?= $e($muc) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php break;
                case 'steps': ?>
                    <ol class="info-steps">
                        <?php foreach ($khoi['items'] as $buoc): ?>
                            <li>
                                <strong><?= $e($buoc[0]) ?></strong>
                                <p><?= $e($buoc[1]) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <?php break;
                case 'note': ?>
                    <div class="info-note" role="note">
                        <strong><?= $e($khoi['tieu_de']) ?></strong>
                        <p><?= $e($khoi['text']) ?></p>
                    </div>
                    <?php break;
            endswitch; ?>
        <?php endforeach; ?>

        <?php if (!empty($trangNoiDung['lien_quan'])): ?>
            <h2>Có thể bạn quan tâm</h2>
            <div class="info-related">
                <?php foreach ($trangNoiDung['lien_quan'] as $khoa): ?>
                    <?php if (!isset($cacTrang[$khoa])) { continue; } ?>
                    <a href="trang.php?p=<?= urlencode($khoa) ?>">
                        <i class="fa-solid <?= $e($cacTrang[$khoa]['icon']) ?>"></i> <?= $e($cacTrang[$khoa]['ten_menu']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="info-help">
            <i class="fa-solid fa-headset" aria-hidden="true"></i>
            <div>
                <strong>Cần hỗ trợ thêm?</strong>
                <p>Liên hệ nhân viên tư vấn qua đường dây nóng hoặc email ở chân trang.</p>
            </div>
        </div>

    <?php endif; ?>
    </article>
</div>

<?php include __DIR__ . '/footer.php'; ?>
