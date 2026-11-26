<?php
$parent_id = (int) ($_SESSION['parent_id'] ?? 0);

// Xử lý hủy hoặc sửa yêu cầu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $cid = (int)($_POST['class_id'] ?? 0);
    if ($act === 'cancel_request') {
        $conn->prepare("UPDATE classes SET status='cancelled' WHERE class_id=? AND parent_id=? AND status='finding'")->execute([$cid, $parent_id]);
        echo '<div class="alert alert-success">Đã hủy yêu cầu thành công.</div>';
    } elseif ($act === 'edit_request') {
        $subj = trim($_POST['subject'] ?? '');
        $grd  = trim($_POST['grade'] ?? '');
        $loc  = trim($_POST['location'] ?? '');
        $sch  = trim($_POST['schedule'] ?? '');
        $fee  = preg_replace('/[^\d]/', '', $_POST['tuition_fee'] ?? '0');
        $conn->prepare("UPDATE classes SET subject=?, grade=?, location=?, schedule=?, tuition_fee=? WHERE class_id=? AND parent_id=? AND status='finding'")->execute([$subj, $grd, $loc, $sch, $fee, $cid, $parent_id]);
        echo '<div class="alert alert-success">Đã cập nhật yêu cầu thành công.</div>';
    }
}

$stmt = $conn->prepare("SELECT * FROM classes WHERE parent_id = ? ORDER BY class_id DESC");
$stmt->execute([$parent_id]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="section-title"><span>2. Trang Yêu Cầu Tìm Gia Sư Của Tôi</span></div>
<table class="custom-table" style="width:100%; border-collapse:collapse; margin-top:10px;">
    <thead>
        <tr style="background:#fdf2f4; text-align:left;">
            <th style="padding:10px;">Mã Lớp</th>
            <th style="padding:10px;">Tiêu Đề / Môn</th>
            <th style="padding:10px;">Địa Điểm & Lịch</th>
            <th style="padding:10px;">Học Phí</th>
            <th style="padding:10px;">Trạng Thái</th>
            <th style="padding:10px;">Thao Tác</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($requests): foreach ($requests as $r): ?>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding:10px;"><strong><?= htmlspecialchars($r['class_code']) ?></strong></td>
                <td style="padding:10px;"><?= htmlspecialchars($r['title']) ?></td>
                <td style="padding:10px;"><?= htmlspecialchars($r['location']) ?><br><small><?= htmlspecialchars($r['schedule']) ?></small></td>
                <td style="padding:10px; color:var(--primary-red); font-weight:bold;"><?= number_format($r['tuition_fee'], 0, ',', '.') ?> đ</td>
                <td style="padding:10px;"><?php $nhanLop = ['pending' => 'Chờ duyệt', 'finding' => 'Đang tìm gia sư', 'matched' => 'Đã có gia sư', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy', 'rejected' => 'Bị từ chối']; ?>
                    <span class="badge badge-warning"><?= htmlspecialchars($nhanLop[$r['status']] ?? $r['status']) ?></span></td>
                <td style="padding:10px;">
                    <?php if ($r['status'] === 'finding'): ?>
                        <form method="POST" action="index.php?sub=my_requests" style="display:inline;" onsubmit="return confirm('Hủy yêu cầu này?');">
                            <input type="hidden" name="action" value="cancel_request">
                            <input type="hidden" name="class_id" value="<?= $r['class_id'] ?>">
                            <button type="submit" class="btn-search" style="background:#e74c3c; padding:5px 10px; font-size:12px;"><i class="fa-solid fa-ban"></i> Hủy</button>
                        </form>
                    <?php else: ?>
                        <small style="color:#888;">Đã khóa</small>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="6" style="padding:15px; text-align:center;">Bạn chưa có yêu cầu nào.</td></tr>
        <?php endif; ?>
    </tbody>
</table>