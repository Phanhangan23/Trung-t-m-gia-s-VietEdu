<?php
/** 2. TRANG XÁC MINH HỒ SƠ (KYC) – tải giấy tờ & theo dõi trạng thái xác minh */
require_once __DIR__ . '/core.php';
$tutor = gs_require_login();
$tid = (int) $tutor['tutor_id'];

$docs = [
    'id_card_front' => ['CCCD mặt trước', true],
    'id_card_back'  => ['CCCD mặt sau', true],
    'student_card'  => ['Thẻ sinh viên', false],
    'degree_cert'   => ['Bằng cấp / Chứng chỉ', false],
];
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
$kyc = gs_kyc($conn, $tid);

// Thư mục giấy tờ không cho truy cập trực tiếp qua URL
$kycDir = GS_UPLOAD_DIR . '/kyc';
if (!is_dir($kycDir)) mkdir($kycDir, 0755, true);
if (!is_file($kycDir . '/.htaccess')) file_put_contents($kycDir . '/.htaccess', "Require all denied\n");

// Xem lại giấy tờ của chính mình
if (isset($_GET['xem'], $docs[$_GET['xem']]) && $kyc && !empty($kyc[$_GET['xem']])) {
    $path = realpath(__DIR__ . '/' . $kyc[$_GET['xem']]);
    if ($path && strpos($path, realpath($kycDir)) === 0 && is_file($path)) {
        header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($path));
        header('Content-Disposition: inline');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
    http_response_code(404);
    exit('Không tìm thấy file.');
}

$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    gs_check_csrf();
    if ($kyc && $kyc['status'] === 'approved') {
        gs_flash('error', 'Hồ sơ đã được xác minh, không cần gửi lại.');
        gs_redirect('kyc.php');
    }

    // Kiểm tra bắt buộc trước khi lưu file
    foreach ($docs as $f => $d) {
        $has = isset($_FILES[$f]) && $_FILES[$f]['error'] !== UPLOAD_ERR_NO_FILE;
        if ($d[1] && !$has && empty($kyc[$f])) $err[$f] = 'Vui lòng tải lên ' . $d[0] . '.';
    }
    $hasStudentOrDegree = !empty($kyc['student_card']) || !empty($kyc['degree_cert'])
        || (isset($_FILES['student_card']) && $_FILES['student_card']['error'] !== UPLOAD_ERR_NO_FILE)
        || (isset($_FILES['degree_cert']) && $_FILES['degree_cert']['error'] !== UPLOAD_ERR_NO_FILE);
    if (!$hasStudentOrDegree) $err['student_card'] = 'Cần ít nhất thẻ sinh viên hoặc bằng cấp.';

    $saved = [];
    if (!$err) {
        foreach ($docs as $f => $d) {
            list($p, $e) = gs_upload($f, 'kyc', $allowed, 5);
            if ($e) $err[$f] = $e;
            elseif ($p) $saved[$f] = $p;
        }
    }

    if ($err) {
        foreach ($saved as $p) @unlink(__DIR__ . '/' . $p); // lỗi ở file khác -> bỏ các file vừa lưu
    } else {
        $data = [];
        foreach ($docs as $f => $_) $data[$f] = $saved[$f] ?? ($kyc[$f] ?? null);
        if ($kyc) {
            $conn->prepare("UPDATE kyc_profiles SET id_card_front=?, id_card_back=?, student_card=?, degree_cert=?,
                            status='pending', review_note=NULL, reviewed_by=NULL, submitted_at=NOW() WHERE kyc_id=?")
                 ->execute([$data['id_card_front'], $data['id_card_back'], $data['student_card'], $data['degree_cert'], $kyc['kyc_id']]);
            foreach ($saved as $f => $_) {
                if (!empty($kyc[$f])) @unlink(__DIR__ . '/' . $kyc[$f]); // xóa file cũ đã được thay
            }
        } else {
            $conn->prepare("INSERT INTO kyc_profiles (tutor_id, id_card_front, id_card_back, student_card, degree_cert, status) VALUES (?, ?, ?, ?, ?, 'pending')")
                 ->execute([$tid, $data['id_card_front'], $data['id_card_back'], $data['student_card'], $data['degree_cert']]);
        }
        gs_flash('ok', 'Đã gửi hồ sơ xác minh. Trung tâm sẽ kiểm tra trong 1–2 ngày làm việc.');
        gs_redirect('kyc.php');
    }
}

$status = $kyc ? $kyc['status'] : 'none';
$steps = ['none' => 0, 'pending' => 1, 'rejected' => 1, 'approved' => 2];
gs_layout_start('Xác minh hồ sơ', 'kyc', $tutor);
?>
<div class="gs-card">
    <h3>Trạng thái xác minh: <?= gs_badge(GS_KYC_LABELS, $status) ?></h3>
    <?php if ($status === 'none'): ?>
        <p class="gs-muted">Bạn chưa gửi giấy tờ xác minh. Gia sư cần xác minh danh tính để được kích hoạt và ứng tuyển lớp.</p>
    <?php elseif ($status === 'pending'): ?>
        <p class="gs-muted">Đã gửi lúc <?= gs_date($kyc['submitted_at'], 'H:i d/m/Y') ?>. Trung tâm đang kiểm tra, bạn vẫn có thể thay ảnh nếu cần.</p>
    <?php elseif ($status === 'rejected'): ?>
        <div class="gs-alert gs-alert-error" style="margin:8px 0 0">
            <strong>Cần bổ sung thông tin:</strong> <?= gs_e($kyc['review_note'] ?? '') ?: 'Giấy tờ chưa rõ hoặc chưa hợp lệ. Vui lòng tải lại ảnh rõ nét, đủ 4 góc.' ?>
        </div>
    <?php else: ?>
        <p style="color:#2E7D32"><i class="fa-solid fa-circle-check"></i> Danh tính của bạn đã được xác minh. Hồ sơ sẽ hiển thị huy hiệu "Đã xác minh danh tính".</p>
    <?php endif; ?>
    <div class="gs-progress" style="margin-top:12px"><span style="width:<?= [0 => 5, 1 => 55, 2 => 100][$steps[$status]] ?>%"></span></div>
    <div class="gs-muted" style="display:flex;justify-content:space-between;margin-top:4px"><span>Chưa gửi</span><span>Chờ xác minh</span><span>Đã xác minh</span></div>
</div>

<?php if ($status !== 'approved'): ?>
<form method="post" enctype="multipart/form-data" class="gs-card" novalidate>
    <?= gs_csrf_field() ?>
    <h3><?= $kyc ? 'Cập nhật giấy tờ' : 'Tải lên giấy tờ xác minh' ?></h3>
    <p class="gs-muted" style="margin-bottom:14px">Chấp nhận JPG, PNG, WEBP hoặc PDF, tối đa 5MB mỗi tệp. Ảnh cần rõ nét, không bị che. Thông tin chỉ dùng để xác minh và được bảo mật.</p>
    <div class="gs-form">
        <?php foreach ($docs as $f => $d): ?>
            <div class="gs-field">
                <label><?= $d[0] ?> <?= $d[1] ? '<span class="req">*</span>' : '' ?></label>
                <?php if (!empty($kyc[$f])): ?>
                    <span class="gs-muted"><i class="fa-solid fa-paperclip"></i> Đã tải lên – <a href="?xem=<?= $f ?>" target="_blank"><u>xem lại</u></a> (chọn file mới để thay)</span>
                <?php endif; ?>
                <input type="file" name="<?= $f ?>" accept="image/jpeg,image/png,image/webp,application/pdf">
                <?= isset($err[$f]) ? '<span class="gs-err">' . gs_e($err[$f]) . '</span>' : '' ?>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="submit" class="gs-btn" style="margin-top:18px"><i class="fa-solid fa-upload"></i> <?= $kyc ? 'Gửi lại để xác minh' : 'Gửi xác minh' ?></button>
</form>
<?php else: ?>
    <div class="gs-card">
        <h3>Giấy tờ đã gửi</h3>
        <div class="gs-actions">
            <?php foreach ($docs as $f => $d): if (!empty($kyc[$f])): ?>
                <a class="gs-btn outline sm" href="?xem=<?= $f ?>" target="_blank"><i class="fa-solid fa-file"></i> <?= $d[0] ?></a>
            <?php endif; endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<?php gs_layout_end(); ?>
