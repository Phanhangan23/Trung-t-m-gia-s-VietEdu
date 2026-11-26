<?php
$parent_id = (int) ($_SESSION['parent_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'respond_matching') {
    $match_id = (int)($_POST['match_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    if ($decision === 'accept') {
        $conn->beginTransaction();
        $conn->prepare("UPDATE class_matching SET status='matched' WHERE match_id=?")->execute([$match_id]);
        $st = $conn->prepare("SELECT class_id FROM class_matching WHERE match_id=?");
        $st->execute([$match_id]);
        if ($cid = $st->fetchColumn()) {
            $conn->prepare("UPDATE classes SET status='matched' WHERE class_id=?")->execute([$cid]);
            $conn->prepare("UPDATE class_matching SET status='rejected' WHERE class_id=? AND match_id<>?")->execute([$cid, $match_id]);
        }
        $conn->commit();
        echo '<div class="alert alert-success">Đã đồng ý nhận gia sư thành công!</div>';
    } else {
        $conn->prepare("UPDATE class_matching SET status='rejected' WHERE match_id=?")->execute([$match_id]);
        echo '<div class="alert alert-success">Đã từ chối gia sư đề xuất.</div>';
    }
}

$st = $conn->prepare("SELECT m.*, t.full_name, t.phone, t.university, t.major, t.rating, t.avatar, c.class_code, c.title AS class_title 
                      FROM class_matching m 
                      JOIN classes c ON c.class_id = m.class_id 
                      JOIN tutors t ON t.tutor_id = m.tutor_id 
                      WHERE c.parent_id = ? AND m.status IN ('applied', 'interviewing', 'matched') 
                      ORDER BY m.match_score DESC");
$st->execute([$parent_id]);
$matches = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="section-title"><span>3. Danh Sách Gia Sư Được Đề Xuất Phù Hợp</span></div>
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">
    <?php if ($matches): foreach ($matches as $m): ?>
        <div style="border:1px solid #ddd; padding:15px; border-radius:8px; background:#fff;">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:6px;">
                <?= ve_the_anh_gia_su($m['avatar'] ?? null, 52) ?>
                <h4 style="margin:0; color:var(--primary-red);"><?= htmlspecialchars($m['full_name']) ?></h4>
            </div>
            <p style="font-size:13px; margin:4px 0;"><i class="fa-solid fa-university"></i> <?= htmlspecialchars($m['university']) ?> - <?= htmlspecialchars($m['major']) ?></p>
            <p style="font-size:13px; margin:4px 0;"><strong>Lớp:</strong> <?= htmlspecialchars($m['class_code']) ?></p>
            <p style="font-size:13px; margin:4px 0;"><strong>Đánh giá:</strong> ⭐ <?= htmlspecialchars($m['rating'] ?? '5.0') ?>/5.0</p>
            <div style="margin-top:10px; display:flex; gap:5px;">
                <?php if ($m['status'] !== 'matched'): ?>
                    <form method="POST" action="index.php?sub=suggested_tutors">
                        <input type="hidden" name="action" value="respond_matching">
                        <input type="hidden" name="match_id" value="<?= $m['match_id'] ?>">
                        <input type="hidden" name="decision" value="accept">
                        <button type="submit" class="btn-search" style="background:#27ae60; padding:5px 10px; font-size:12px;">Đồng ý</button>
                    </form>
                    <form method="POST" action="index.php?sub=suggested_tutors">
                        <input type="hidden" name="action" value="respond_matching">
                        <input type="hidden" name="match_id" value="<?= $m['match_id'] ?>">
                        <input type="hidden" name="decision" value="reject">
                        <button type="submit" class="btn-search" style="background:#e74c3c; padding:5px 10px; font-size:12px;">Từ chối</button>
                    </form>
                <?php else: ?>
                    <span class="badge badge-success">Đã nhận lớp</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; else: ?>
        <p>Chưa có gia sư đề xuất nào.</p>
    <?php endif; ?>
</div>