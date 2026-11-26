<?php
/** 5. TRANG LỚP ĐÃ ỨNG TUYỂN */
require_once __DIR__ . '/core.php';
$tutor = gs_require_login();
$tid = (int) $tutor['tutor_id'];

// Nhóm trạng thái theo cách hiểu của gia sư
$groups = [
    ''         => ['Tất cả', null],
    'cho'      => ['Chờ phản hồi', ['applied', 'interviewing']],
    'duoc-chon'=> ['Được chọn', ['matched']],
    'khong'    => ['Không được chọn', ['rejected']],
];
$g = (string) ($_GET['loc'] ?? '');
if (!isset($groups[$g])) $g = '';

$st = $conn->prepare('SELECT m.match_id, m.apply_date, m.status, c.class_id, c.class_code, c.title, c.subject, c.grade,
                             c.location, c.schedule, c.tuition_fee, c.status AS class_status
                      FROM class_matching m JOIN classes c ON c.class_id = m.class_id
                      WHERE m.tutor_id = ? ORDER BY m.apply_date DESC, m.match_id DESC');
$st->execute([$tid]);
$all = $st->fetchAll(PDO::FETCH_ASSOC);

$counts = array_fill_keys(array_keys($groups), 0);
$rows = [];
foreach ($all as $r) {
    $counts['']++;
    foreach ($groups as $k => $gr) {
        if ($gr[1] && in_array($r['status'], $gr[1], true)) $counts[$k]++;
    }
    if (!$groups[$g][1] || in_array($r['status'], $groups[$g][1], true)) $rows[] = $r;
}

gs_layout_start('Lớp đã ứng tuyển', 'ung-tuyen', $tutor);
?>
<div class="gs-tabs">
    <?php foreach ($groups as $k => $gr): ?>
        <a href="?loc=<?= $k ?>" class="<?= $g === $k ? 'active' : '' ?>"><?= $gr[0] ?> (<?= $counts[$k] ?>)</a>
    <?php endforeach; ?>
</div>
<div class="gs-table-wrap">
<table class="gs-table">
    <thead><tr><th>Mã lớp</th><th>Lớp học</th><th>Khu vực / Lịch</th><th>Học phí</th><th>Ngày ứng tuyển</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="7" class="gs-empty">Chưa có hồ sơ nào. <a href="../index.php#bang-tin" style="color:var(--primary-red)"><u>Tìm lớp phù hợp</u></a></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><strong><?= gs_e($r['class_code']) ?></strong></td>
            <td><?= gs_e($r['title']) ?><div class="gs-muted"><?= gs_e($r['subject']) ?> · <?= gs_e(gs_grade($r['grade'])) ?></div></td>
            <td><?= gs_e($r['location'] ?: '—') ?><div class="gs-muted"><?= gs_e($r['schedule'] ?: '—') ?></div></td>
            <td><?= gs_money($r['tuition_fee']) ?></td>
            <td><?= gs_date($r['apply_date'], 'H:i d/m/Y') ?></td>
            <td><?= gs_badge(GS_APPLY_LABELS, $r['status']) ?>
                <?php if (in_array($r['status'], ['applied', 'interviewing'], true) && $r['class_status'] !== 'finding'): ?>
                    <div class="gs-muted">Lớp đã ngừng tuyển</div>
                <?php endif; ?></td>
            <td><div class="gs-actions">
                <a class="gs-btn outline sm" href="lop-hoc-chi-tiet.php?id=<?= (int) $r['class_id'] ?>">Xem lớp</a>
                <?php if ($r['status'] === 'matched'): ?>
                    <a class="gs-btn sm" href="lop-cua-toi.php">Lớp của tôi</a>
                <?php endif; ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php gs_layout_end(); ?>
