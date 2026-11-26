<?php
/** 10. TRANG HỖ TRỢ / KHIẾU NẠI – gửi yêu cầu & theo dõi trạng thái xử lý */
require_once __DIR__ . '/core.php';
$tutor = gs_require_login();
$tid = (int) $tutor['tutor_id'];

$categories = [
    'lop-hoc'   => 'Lớp học (đổi lịch, hủy lớp...)',
    'phu-huynh' => 'Phụ huynh / Học viên',
    'thanh-toan'=> 'Thanh toán / Học phí',
    'tai-khoan' => 'Tài khoản / Xác minh hồ sơ',
    'khac'      => 'Vấn đề khác',
];
$catShort = ['lop-hoc' => 'Lớp học', 'phu-huynh' => 'Phụ huynh', 'thanh-toan' => 'Thanh toán', 'tai-khoan' => 'Tài khoản', 'khac' => 'Khác'];

// Lớp gia sư đã ứng tuyển / nhận để gắn vào yêu cầu
$st = $conn->prepare('SELECT DISTINCT c.class_id, c.class_code, c.title FROM class_matching m JOIN classes c ON c.class_id = m.class_id WHERE m.tutor_id = ? ORDER BY c.class_code');
$st->execute([$tid]);
$myClasses = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) $myClasses[$c['class_id']] = $c;

$v = ['category' => '', 'class_id' => '', 'title' => '', 'content' => ''];
$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    gs_check_csrf();
    foreach ($v as $k => $_) $v[$k] = trim((string) ($_POST[$k] ?? ''));
    if (!isset($categories[$v['category']])) $err['category'] = 'Chọn loại yêu cầu.';
    if ($v['class_id'] !== '' && !isset($myClasses[(int) $v['class_id']])) $err['class_id'] = 'Lớp không hợp lệ.';
    $l = gs_len($v['title']);
    if ($l < 5 || $l > 100) $err['title'] = 'Tiêu đề từ 5 đến 100 ký tự.';
    $l = gs_len($v['content']);
    if ($l < 20 || $l > 5000) $err['content'] = 'Nội dung từ 20 đến 5000 ký tự.';

    if (!$err) {
        // Gắn loại & mã lớp vào tiêu đề để màn admin tickets.php hiển thị được mà không cần đổi CSDL
        $prefix = '[' . $catShort[$v['category']] . ']' . ($v['class_id'] !== '' ? ' ' . $myClasses[(int) $v['class_id']]['class_code'] . ':' : '');
        $title = mb_substr($prefix . ' ' . $v['title'], 0, 150, 'UTF-8');
        $conn->prepare("INSERT INTO support_tickets (sender_type, sender_id, issue_title, content, status) VALUES ('tutor', ?, ?, ?, 'open')")
             ->execute([$tid, $title, $v['content']]);
        gs_flash('ok', 'Đã gửi yêu cầu #' . $conn->lastInsertId() . '. Bộ phận chăm sóc khách hàng sẽ phản hồi trong 24 giờ làm việc.');
        gs_redirect('ho-tro.php#danh-sach');
    }
}

$filter = isset(GS_TICKET_LABELS[$_GET['tt'] ?? '']) ? $_GET['tt'] : '';
$st = $conn->prepare("SELECT * FROM support_tickets WHERE sender_type = 'tutor' AND sender_id = ? ORDER BY created_at DESC, ticket_id DESC");
$st->execute([$tid]);
$tickets = $st->fetchAll(PDO::FETCH_ASSOC);
$counts = array_count_values(array_column($tickets, 'status'));
$shown = $filter ? array_filter($tickets, function ($t) use ($filter) { return $t['status'] === $filter; }) : $tickets;

// Phản hồi của trung tâm cho từng phiếu hỗ trợ
$replies = [];
if ($tickets) {
    $ids = implode(',', array_map('intval', array_column($tickets, 'ticket_id')));
    foreach ($conn->query("SELECT ticket_id, content, created_at FROM ticket_replies WHERE ticket_id IN ($ids) ORDER BY created_at, reply_id") as $r) {
        $replies[$r['ticket_id']][] = $r;
    }
}

$fe = function ($k) use (&$err) {
    return isset($err[$k]) ? '<span class="gs-err">' . gs_e($err[$k]) . '</span>' : '';
};
gs_layout_start('Hỗ trợ / Khiếu nại', 'ho-tro', $tutor);
?>
<form method="post" class="gs-card" novalidate>
    <?= gs_csrf_field() ?>
    <h3><i class="fa-solid fa-headset"></i> Gửi yêu cầu hỗ trợ</h3>
    <div class="gs-form">
        <div class="gs-field"><label>Loại yêu cầu <span class="req">*</span></label>
            <select name="category">
                <option value="">-- Chọn --</option>
                <?php foreach ($categories as $k => $lb): ?>
                    <option value="<?= $k ?>" <?= $v['category'] === $k ? 'selected' : '' ?>><?= $lb ?></option>
                <?php endforeach; ?>
            </select><?= $fe('category') ?></div>
        <div class="gs-field"><label>Lớp liên quan</label>
            <select name="class_id">
                <option value="">-- Không gắn lớp --</option>
                <?php foreach ($myClasses as $c): ?>
                    <option value="<?= $c['class_id'] ?>" <?= $v['class_id'] === (string) $c['class_id'] ? 'selected' : '' ?>><?= gs_e($c['class_code'] . ' – ' . $c['title']) ?></option>
                <?php endforeach; ?>
            </select><?= $fe('class_id') ?></div>
        <div class="gs-field full"><label>Tiêu đề <span class="req">*</span></label>
            <input type="text" name="title" maxlength="100" value="<?= gs_e($v['title']) ?>" placeholder="VD: Phụ huynh muốn đổi lịch học sang cuối tuần"><?= $fe('title') ?></div>
        <div class="gs-field full"><label>Mô tả chi tiết <span class="req">*</span></label>
            <textarea name="content" maxlength="5000" placeholder="Mô tả sự việc, thời gian, mong muốn hỗ trợ..."><?= gs_e($v['content']) ?></textarea><?= $fe('content') ?></div>
    </div>
    <button type="submit" class="gs-btn" style="margin-top:16px"><i class="fa-solid fa-paper-plane"></i> Gửi yêu cầu</button>
</form>

<h3 id="danh-sach" style="color:var(--primary-dark);margin:24px 0 12px">Yêu cầu đã gửi</h3>
<div class="gs-tabs">
    <a href="?#danh-sach" class="<?= $filter === '' ? 'active' : '' ?>">Tất cả (<?= count($tickets) ?>)</a>
    <?php foreach (GS_TICKET_LABELS as $k => $lb): ?>
        <a href="?tt=<?= $k ?>#danh-sach" class="<?= $filter === $k ? 'active' : '' ?>"><?= $lb[0] ?> (<?= $counts[$k] ?? 0 ?>)</a>
    <?php endforeach; ?>
</div>
<div class="gs-table-wrap">
<table class="gs-table">
    <thead><tr><th>Mã</th><th>Tiêu đề</th><th>Nội dung</th><th>Ngày gửi</th><th>Trạng thái</th></tr></thead>
    <tbody>
    <?php if (!$shown): ?><tr><td colspan="5" class="gs-empty">Chưa có yêu cầu nào.</td></tr><?php endif; ?>
    <?php foreach ($shown as $t): ?>
        <tr>
            <td>#<?= (int) $t['ticket_id'] ?></td>
            <td><strong><?= gs_e($t['issue_title']) ?></strong></td>
            <td style="max-width:380px"><div style="white-space:pre-line"><?= gs_e($t['content']) ?></div>
                <?php foreach ($replies[$t['ticket_id']] ?? [] as $r): ?>
                    <div style="margin-top:8px;padding:8px 10px;background:#F4F8FB;border-left:3px solid #3498db;border-radius:4px;font-size:13px">
                        <strong>VietEdu phản hồi</strong> <small style="color:#888">(<?= gs_date($r['created_at'], 'd/m/Y H:i') ?>)</small>
                        <div style="white-space:pre-line"><?= gs_e($r['content']) ?></div>
                    </div>
                <?php endforeach; ?>
            </td>
            <td><?= gs_date($t['created_at'], 'H:i d/m/Y') ?></td>
            <td><?= gs_badge(GS_TICKET_LABELS, $t['status']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php gs_layout_end(); ?>
