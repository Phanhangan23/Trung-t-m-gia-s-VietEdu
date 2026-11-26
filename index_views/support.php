<?php
$parent_id = (int) ($_SESSION['parent_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_ticket') {
    $title = trim($_POST['issue_title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = trim($_POST['issue_type'] ?? 'Chung');
    if ($title && $content) {
        $conn->prepare("INSERT INTO support_tickets (sender_type, sender_id, issue_title, content, status) VALUES ('parent', ?, ?, ?, 'open')")
             ->execute([$parent_id, "[$type] $title", $content]);
        echo '<div class="alert alert-success">Đã gửi khiếu nại thành công!</div>';
    }
}
$st = $conn->prepare("SELECT * FROM support_tickets WHERE sender_type='parent' AND sender_id=? ORDER BY ticket_id DESC");
$st->execute([$parent_id]);
$tickets = $st->fetchAll(PDO::FETCH_ASSOC);

// Phản hồi của trung tâm cho từng phiếu hỗ trợ
$phanHoiTheoTicket = [];
if ($tickets) {
    $ids = array_map('intval', array_column($tickets, 'ticket_id'));
    $rs = $conn->query('SELECT ticket_id, content, created_at FROM ticket_replies WHERE ticket_id IN (' . implode(',', $ids) . ') ORDER BY created_at, reply_id');
    foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $phanHoiTheoTicket[$r['ticket_id']][] = $r;
    }
}
$nhanTrangThai = VE_NHAN_TICKET;
?>
<div class="section-title"><span>7. Gửi Yêu Cầu Hỗ Trợ / Khiếu Nại</span></div>
<form method="POST" action="index.php?sub=support" style="margin-bottom:20px;">
    <input type="hidden" name="action" value="submit_ticket">
    <div style="margin-bottom:10px;"><label style="font-weight:600; font-size:13px;">Tiêu đề</label><input type="text" name="issue_title" class="search-input" style="width:100%; padding:8px;" required></div>
    <div style="margin-bottom:10px;"><label style="font-weight:600; font-size:13px;">Nội dung</label><textarea name="content" class="search-input" style="width:100%; padding:8px;" rows="3" required></textarea></div>
    <button type="submit" class="btn-search" style="padding:8px 15px;">Gửi Yêu Cầu</button>
</form>
<table class="custom-table" style="width:100%; border-collapse:collapse;">
    <thead><tr style="background:#fdf2f4;"><th style="padding:10px;">Mã</th><th style="padding:10px;">Tiêu Đề</th><th style="padding:10px;">Nội Dung</th><th style="padding:10px;">Trạng Thái</th></tr></thead>
    <tbody>
        <?php if ($tickets): foreach ($tickets as $tk): ?>
            <tr style="border-bottom:1px solid #eee;"><td style="padding:10px;">#<?= $tk['ticket_id'] ?></td><td style="padding:10px;"><?= htmlspecialchars($tk['issue_title']) ?></td><td style="padding:10px;"><?= nl2br(htmlspecialchars($tk['content'])) ?>
                <?php foreach ($phanHoiTheoTicket[$tk['ticket_id']] ?? [] as $ph): ?>
                    <div style="margin-top:8px; padding:8px 10px; background:#F4F8FB; border-left:3px solid #3498db; border-radius:4px; font-size:13px;">
                        <strong>VietEdu phản hồi</strong> <small style="color:#888;">(<?= date('d/m/Y H:i', strtotime($ph['created_at'])) ?>)</small><br>
                        <?= nl2br(htmlspecialchars($ph['content'])) ?>
                    </div>
                <?php endforeach; ?>
            </td><td style="padding:10px;"><?= htmlspecialchars($nhanTrangThai[$tk['status']] ?? $tk['status']) ?></td></tr>
        <?php endforeach; else: ?><tr><td colspan="4" style="padding:15px; text-align:center;">Chưa có khiếu nại nào.</td></tr><?php endif; ?>
    </tbody>
</table>