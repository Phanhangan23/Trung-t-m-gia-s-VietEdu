<?php
/** 4. TRANG CHI TIẾT LỚP HỌC – xem đầy đủ thông tin & ứng tuyển */
require_once __DIR__ . '/core.php';

$classId = (int) ($_GET['id'] ?? 0);
$tutor = gs_current_tutor();

$st = $conn->prepare('SELECT * FROM classes WHERE class_id = ?');
$st->execute([$classId]);
$class = $st->fetch(PDO::FETCH_ASSOC);

$myApp = null;
if ($class && $tutor) {
    $st = $conn->prepare('SELECT * FROM class_matching WHERE class_id = ? AND tutor_id = ? ORDER BY match_id DESC LIMIT 1');
    $st->execute([$classId, $tutor['tutor_id']]);
    $myApp = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
// Lớp chỉ công khai khi đang tìm gia sư; gia sư đã ứng tuyển vẫn xem lại được
$visible = $class && ($class['status'] === 'finding' || $myApp);
$self = 'lop-hoc-chi-tiet.php?id=' . $classId;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $visible) {
    gs_check_csrf();
    $tutor = gs_require_login();
    $act = $_POST['action'] ?? '';

    if ($act === 'apply') {
        if ($tutor['status'] !== 'active') {
            gs_flash('error', 'Tài khoản chưa được kích hoạt. Hãy hoàn thiện hồ sơ và xác minh hồ sơ trước khi ứng tuyển.');
        } elseif ($class['status'] !== 'finding') {
            gs_flash('error', 'Lớp này đã ngừng nhận hồ sơ.');
        } elseif ($myApp && $myApp['status'] !== 'rejected') {
            gs_flash('error', 'Bạn đã ứng tuyển lớp này rồi.');
        } elseif ($myApp) {
            gs_flash('error', 'Hồ sơ của bạn cho lớp này đã không được chọn trước đó.');
        } else {
            $conn->prepare("INSERT INTO class_matching (class_id, tutor_id, status) VALUES (?, ?, 'applied')")
                 ->execute([$classId, $tutor['tutor_id']]);
            gs_flash('ok', 'Ứng tuyển thành công lớp ' . $class['class_code'] . '! Trung tâm sẽ liên hệ khi có kết quả. Theo dõi tại mục "Lớp đã ứng tuyển".');
        }
    } elseif ($act === 'withdraw' && $myApp && $myApp['status'] === 'applied') {
        $conn->prepare("DELETE FROM class_matching WHERE match_id = ? AND tutor_id = ? AND status = 'applied'")
             ->execute([$myApp['match_id'], $tutor['tutor_id']]);
        gs_flash('ok', 'Đã rút hồ sơ ứng tuyển lớp ' . $class['class_code'] . '.');
    }
    gs_redirect($self);
}

$applicants = 0;
if ($visible) {
    $st = $conn->prepare("SELECT COUNT(*) FROM class_matching WHERE class_id = ? AND status IN ('applied','interviewing')");
    $st->execute([$classId]);
    $applicants = (int) $st->fetchColumn();
}
$sched = $visible ? gs_parse_schedule($class['schedule']) : null;
$dayNames = [1 => 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật'];

$pageTitle = ($visible ? $class['class_code'] . ' – ' . $class['title'] : 'Không tìm thấy lớp') . ' - VietEdu';
require __DIR__ . '/../header.php';
$flashes = $_SESSION['gs_flash'] ?? [];
unset($_SESSION['gs_flash']);
?>
<link rel="stylesheet" href="gia-su.css">
<div style="max-width:1000px;margin:30px auto;padding:0 20px">
    <p style="margin-bottom:14px"><a href="../index.php#bang-tin" style="color:var(--primary-red)"><i class="fa-solid fa-arrow-left"></i> Quay lại bảng tin</a></p>
    <?php foreach ($flashes as $f): ?>
        <div class="gs-alert gs-alert-<?= $f['type'] === 'error' ? 'error' : 'ok' ?>"><?= gs_e($f['msg']) ?></div>
    <?php endforeach; ?>

    <?php if (!$visible): ?>
        <div class="content-area"><p class="gs-empty">Lớp học không tồn tại hoặc đã ngừng tuyển gia sư.</p></div>
    <?php else: ?>
    <div class="content-area">
        <div class="section-header">
            <div>
                <div class="gs-muted">MÃ LỚP: <strong style="color:var(--primary-red)"><?= gs_e($class['class_code']) ?></strong> · Đăng ngày <?= gs_date($class['created_at']) ?></div>
                <h2 class="section-title"><?= gs_e($class['title']) ?></h2>
            </div>
            <?= gs_badge(GS_CLASS_LABELS, $class['status']) ?>
        </div>

        <div class="gs-info" style="margin-bottom:20px">
            <div><span>Môn học</span><strong><?= gs_e($class['subject']) ?></strong></div>
            <div><span>Khối lớp</span><strong><?= gs_e(gs_grade($class['grade'])) ?></strong></div>
            <div><span>Khu vực</span><strong><?= gs_e($class['location'] ?: 'Thỏa thuận') ?>, Hà Nội</strong></div>
            <div><span>Học phí</span><strong style="color:var(--primary-red)"><?= $class['tuition_fee'] !== null ? gs_money($class['tuition_fee']) . ' / tháng' : 'Thỏa thuận' ?></strong></div>
            <div><span>Phí nhận lớp (<?= GS_FEE_RATE * 100 ?>% tháng đầu)</span><strong><?= $class['tuition_fee'] !== null ? gs_money(round($class['tuition_fee'] * GS_FEE_RATE, -3)) : '—' ?></strong></div>
            <div><span>Số gia sư đang ứng tuyển</span><strong><?= $applicants ?></strong></div>
        </div>

        <div class="gs-card">
            <h3><i class="fa-solid fa-calendar-days"></i> Lịch học</h3>
            <p><?= gs_e($class['schedule'] ?: 'Thỏa thuận với phụ huynh') ?></p>
            <?php if ($sched['days']): ?>
                <p class="gs-muted" style="margin-top:6px">Các ngày: <?= gs_e(implode(', ', array_map(function ($d) use ($dayNames) { return $dayNames[$d]; }, $sched['days']))) ?> · Khung giờ: <?= gs_e($sched['time']) ?></p>
            <?php endif; ?>
        </div>
        <div class="gs-card">
            <h3><i class="fa-solid fa-list-check"></i> Yêu cầu đối với gia sư</h3>
            <p style="white-space:pre-line"><?= gs_e($class['requirements'] ?: 'Không có yêu cầu đặc biệt.') ?></p>
        </div>
        <?php $lienHe = $tutor ? (gs_lop_da_mo_lien_he($conn, (int) $tutor['tutor_id'], $classId)[$classId] ?? null) : null; ?>
        <?php if ($lienHe): ?>
            <div class="gs-card">
                <h3><i class="fa-solid fa-address-book"></i> Liên hệ phụ huynh</h3>
                <p class="gs-muted" style="margin-bottom:10px">Bạn đã thanh toán phí nhận lớp. Hãy liên hệ phụ huynh để sắp xếp buổi học đầu tiên.</p>
                <?= gs_the_lien_he($lienHe) ?>
            </div>
        <?php else: ?>
            <p class="gs-muted" style="margin-bottom:16px"><i class="fa-solid fa-lock"></i> Thông tin liên hệ phụ huynh được cung cấp sau khi gia sư được chọn và hoàn tất phí nhận lớp.</p>
        <?php endif; ?>

        <!-- KHỐI ỨNG TUYỂN -->
        <?php if (!$tutor): ?>
            <a class="gs-btn" href="dang-nhap.php?next=<?= rawurlencode($self) ?>"><i class="fa-solid fa-right-to-bracket"></i> Đăng nhập để ứng tuyển</a>
        <?php elseif ($myApp): ?>
            <div class="gs-alert gs-alert-info" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
                <span>Bạn đã ứng tuyển lúc <?= gs_date($myApp['apply_date'], 'H:i d/m/Y') ?> · Trạng thái: <?= gs_badge(GS_APPLY_LABELS, $myApp['status']) ?></span>
                <?php if ($myApp['status'] === 'applied'): ?>
                    <form method="post" onsubmit="return confirm('Rút hồ sơ ứng tuyển lớp này?')">
                        <?= gs_csrf_field() ?><input type="hidden" name="action" value="withdraw">
                        <button class="gs-btn outline sm" type="submit">Rút hồ sơ</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php elseif ($tutor['status'] !== 'active'): ?>
            <div class="gs-alert gs-alert-info">Tài khoản của bạn chưa được kích hoạt. <a href="kyc.php"><u>Xác minh hồ sơ</u></a> để có thể ứng tuyển.</div>
            <button class="gs-btn" disabled><i class="fa-solid fa-paper-plane"></i> Ứng tuyển lớp</button>
        <?php elseif ($class['status'] === 'finding'): ?>
            <form method="post" onsubmit="return confirm('Xác nhận ứng tuyển lớp <?= gs_e($class['class_code']) ?>?')">
                <?= gs_csrf_field() ?><input type="hidden" name="action" value="apply">
                <button class="gs-btn gold" type="submit"><i class="fa-solid fa-paper-plane"></i> Ứng tuyển lớp</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../footer.php'; ?>
