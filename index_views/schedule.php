<?php
$parent_id = (int) ($_SESSION['parent_id'] ?? 0);
$st = $conn->prepare("SELECT c.class_code, c.title, c.schedule, c.location, t.full_name AS tutor_name FROM classes c 
                      JOIN class_matching m ON m.class_id=c.class_id AND m.status='matched' 
                      JOIN tutors t ON t.tutor_id=m.tutor_id WHERE c.parent_id=? AND c.status='matched'");
$st->execute([$parent_id]);
$schedules = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="section-title"><span>5. Lịch Học Cố Định Theo Tuần</span></div>
<table class="custom-table" style="width:100%; border-collapse:collapse;">
    <thead><tr style="background:#fdf2f4;"><th style="padding:10px;">Mã Lớp</th><th style="padding:10px;">Môn Học</th><th style="padding:10px;">Gia Sư</th><th style="padding:10px;">Lịch Học</th></tr></thead>
    <tbody>
        <?php if ($schedules): foreach ($schedules as $s): ?>
            <tr style="border-bottom:1px solid #eee;"><td style="padding:10px;"><strong><?= htmlspecialchars($s['class_code']) ?></strong></td><td style="padding:10px;"><?= htmlspecialchars($s['title']) ?></td><td style="padding:10px;"><?= htmlspecialchars($s['tutor_name']) ?></td><td style="padding:10px;"><?= htmlspecialchars($s['schedule']) ?></td></tr>
        <?php endforeach; else: ?><tr><td colspan="4" style="padding:15px; text-align:center;">Chưa có lịch học nào.</td></tr><?php endif; ?>
    </tbody>
</table>