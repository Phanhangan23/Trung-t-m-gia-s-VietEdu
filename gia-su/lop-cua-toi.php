<?php
/** 6. TRANG LỚP HỌC CỦA TÔI – các lớp gia sư đã được nhận */
require_once __DIR__ . '/core.php';
$tutor = gs_require_login();
$tid = (int) $tutor['tutor_id'];

$st = $conn->prepare("SELECT c.*, m.apply_date, p.full_name AS parent_name, p.phone AS parent_phone, p.address AS parent_address, p.email AS parent_email,
                             (SELECT COUNT(*) FROM transactions x WHERE x.class_id = c.class_id AND x.tutor_id = m.tutor_id AND x.status = 'success') AS paid
                      FROM class_matching m
                      JOIN classes c ON c.class_id = m.class_id
                      LEFT JOIN parents p ON p.parent_id = c.parent_id
                      WHERE m.tutor_id = ? AND m.status = 'matched'
                      ORDER BY FIELD(c.status, 'matched', 'completed', 'cancelled'), m.apply_date DESC");
$st->execute([$tid]);
$classes = $st->fetchAll(PDO::FETCH_ASSOC);
$active = count(array_filter($classes, function ($c) { return $c['status'] === 'matched'; }));

gs_layout_start('Lớp học của tôi', 'lop-cua-toi', $tutor);
?>
<div class="gs-stats">
    <div class="gs-stat"><span>Lớp đang dạy</span><b><?= $active ?></b></div>
    <div class="gs-stat"><span>Tổng số lớp đã nhận</span><b><?= count($classes) ?></b></div>
</div>

<?php if (!$classes): ?>
    <p class="gs-empty">Bạn chưa nhận lớp nào. <a href="../index.php#bang-tin" style="color:var(--primary-red)"><u>Xem bảng tin lớp trống</u></a></p>
<?php endif; ?>

<?php foreach ($classes as $c): $unlocked = $c['paid'] > 0; ?>
    <div class="gs-card">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px">
            <h3 style="margin:0"><?= gs_e($c['class_code']) ?> – <?= gs_e($c['title']) ?></h3>
            <?= gs_badge(GS_CLASS_LABELS, $c['status']) ?>
        </div>
        <div class="gs-info">
            <div><span>Học viên</span><?= gs_e($c['title']) ?> (<?= gs_e(gs_grade($c['grade'])) ?>)</div>
            <div><span>Môn học</span><?= gs_e($c['subject']) ?></div>
            <div><span>Địa điểm</span><?= gs_e($c['location'] ?: '—') ?></div>
            <div><span>Học phí</span><?= gs_money($c['tuition_fee']) ?> / tháng</div>
            <div><span>Lịch học</span><?= gs_e($c['schedule'] ?: '—') ?></div>
            <div><span>Nhận lớp từ</span><?= gs_date($c['apply_date']) ?></div>
            <?php if (!$unlocked): ?>
                <div><span>Phụ huynh / Liên hệ</span><span class="gs-muted"><i class="fa-solid fa-lock"></i> Hiển thị sau khi thanh toán phí nhận lớp</span></div>
            <?php endif; ?>
        </div>
        <?php if ($unlocked): ?>
            <div style="margin-top:12px"><?= gs_the_lien_he($c) ?></div>
        <?php endif; ?>
        <?php if (!$unlocked): ?>
            <div class="gs-actions" style="margin-top:14px">
                <a class="gs-btn gold sm" href="thanh-toan.php"><i class="fa-solid fa-qrcode"></i> Thanh toán phí nhận lớp</a>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php gs_layout_end(); ?>
