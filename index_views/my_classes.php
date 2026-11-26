<?php
$parent_id = (int) ($_SESSION['parent_id'] ?? 0);
$st = $conn->prepare("SELECT c.*, t.full_name AS tutor_name, t.phone AS tutor_phone FROM classes c 
                      JOIN class_matching m ON m.class_id=c.class_id AND m.status='matched' 
                      JOIN tutors t ON t.tutor_id=m.tutor_id WHERE c.parent_id=?");
$st->execute([$parent_id]);
$classes = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="section-title"><span>4. Lớp Học Đã Kết Nối Với Gia Sư</span></div>
<table class="custom-table" style="width:100%; border-collapse:collapse;">
    <thead><tr style="background:#fdf2f4;"><th style="padding:10px;">Mã Lớp</th><th style="padding:10px;">Môn & Tiêu Đề</th><th style="padding:10px;">Gia Sư Phụ Trách</th><th style="padding:10px;">Học Phí</th></tr></thead>
    <tbody>
        <?php if ($classes): foreach ($classes as $c): ?>
            <tr style="border-bottom:1px solid #eee;"><td style="padding:10px;"><strong><?= htmlspecialchars($c['class_code']) ?></strong></td><td style="padding:10px;"><?= htmlspecialchars($c['title']) ?></td><td style="padding:10px;"><?= htmlspecialchars($c['tutor_name']) ?> (<?= htmlspecialchars($c['tutor_phone']) ?>)</td><td style="padding:10px;"><?= number_format($c['tuition_fee'], 0, ',', '.') ?> đ</td></tr>
        <?php endforeach; else: ?><tr><td colspan="4" style="padding:15px; text-align:center;">Chưa có lớp học nào.</td></tr><?php endif; ?>
    </tbody>
</table>