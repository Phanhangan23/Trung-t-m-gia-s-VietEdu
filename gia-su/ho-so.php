<?php
/** 1. TRANG HỒ SƠ GIA SƯ – cập nhật thông tin cá nhân & năng lực giảng dạy */
require_once __DIR__ . '/core.php';
$tutor = gs_require_login();
$tid = (int) $tutor['tutor_id'];

$split = function ($s) {
    return array_values(array_filter(array_map('trim', explode(',', (string) $s)), 'strlen'));
};
$err = [];
$v = $tutor;
$v['subjects_arr'] = $split($tutor['subjects']);
$v['areas_arr'] = $split($tutor['teaching_areas']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    gs_check_csrf();

    // Ảnh đại diện được lưu ngay khi hợp lệ, KHÔNG phụ thuộc các ô thông tin khác
    // (trước đây ảnh bị bỏ qua âm thầm nếu còn ô bắt buộc chưa điền).
    list($avatar, $upErr) = gs_upload('avatar', 'avatars', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 2);
    if ($upErr) {
        $err['avatar'] = $upErr;
    } elseif ($avatar) {
        $conn->prepare('UPDATE tutors SET avatar = ? WHERE tutor_id = ?')->execute([$avatar, $tid]);
        if (!empty($tutor['avatar']) && strpos($tutor['avatar'], 'uploads/avatars/') === 0) {
            @unlink(__DIR__ . '/' . $tutor['avatar']);
        }
        $tutor['avatar'] = $avatar;
    }

    foreach (['full_name', 'phone', 'email', 'birth_date', 'gender', 'university', 'major', 'grade_min', 'grade_max',
              'experience_years', 'experience_note', 'expected_fee'] as $k) {
        $v[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    // Môn học: chọn sẵn + nhập thêm
    $subjects = array_intersect((array) ($_POST['subjects'] ?? []), GS_SUBJECTS);
    $subjects = array_merge($subjects, $split($_POST['subjects_other'] ?? ''));
    $v['subjects_arr'] = array_values(array_unique($subjects));
    $v['areas_arr'] = array_values(array_intersect((array) ($_POST['areas'] ?? []), GS_AREAS));
    $v['phone'] = str_replace([' ', '.', '-'], '', $v['phone']);
    $v['expected_fee'] = preg_replace('/\D/', '', $v['expected_fee']);

    $l = gs_len($v['full_name']);
    if ($l < 2 || $l > 100) $err['full_name'] = 'Họ tên phải từ 2 đến 100 ký tự.';
    if (!preg_match('/^0(3|5|7|8|9)\d{8}$/', $v['phone'])) $err['phone'] = 'Số điện thoại không hợp lệ.';
    if ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $err['email'] = 'Email không hợp lệ.';
    if ($v['birth_date'] !== '') {
        if (!gs_valid_date($v['birth_date'])) $err['birth_date'] = 'Ngày sinh không hợp lệ.';
        else {
            $age = (int) date_diff(date_create($v['birth_date']), date_create('today'))->y;
            if ($age < 16 || $age > 80) $err['birth_date'] = 'Tuổi phải từ 16 đến 80.';
        }
    }
    if ($v['gender'] !== '' && !in_array($v['gender'], ['male', 'female', 'other'], true)) $err['gender'] = 'Giới tính không hợp lệ.';
    if (gs_len($v['university']) > 150) $err['university'] = 'Tối đa 150 ký tự.';
    if (gs_len($v['major']) > 100) $err['major'] = 'Tối đa 100 ký tự.';
    $subStr = implode(', ', $v['subjects_arr']);
    if (!$v['subjects_arr']) $err['subjects'] = 'Chọn ít nhất 1 môn có thể dạy.';
    elseif (strlen($subStr) > 255) $err['subjects'] = 'Danh sách môn quá dài.';
    $gMin = (int) $v['grade_min'];
    $gMax = (int) $v['grade_max'];
    if ($gMin < 1 || $gMax > 12 || $gMin > $gMax) $err['grade'] = 'Khoảng khối lớp không hợp lệ.';
    if ($v['experience_years'] !== '' && (!ctype_digit($v['experience_years']) || (int) $v['experience_years'] > 50)) $err['experience_years'] = 'Số năm không hợp lệ.';
    if (gs_len($v['experience_note']) > 2000) $err['experience_note'] = 'Tối đa 2000 ký tự.';
    if (!$v['areas_arr']) $err['areas'] = 'Chọn ít nhất 1 khu vực.';
    if ($v['expected_fee'] !== '' && (int) $v['expected_fee'] > 99999999) $err['expected_fee'] = 'Mức học phí không hợp lệ.';

    if (!$err) {
        $st = $conn->prepare('SELECT phone, email FROM tutors WHERE tutor_id <> ? AND (phone = ? OR (email IS NOT NULL AND email = ?))');
        $st->execute([$tid, $v['phone'], $v['email']]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $d) {
            if ($d['phone'] === $v['phone']) $err['phone'] = 'Số điện thoại đã được tài khoản khác sử dụng.';
            if ($v['email'] !== '' && $d['email'] === $v['email']) $err['email'] = 'Email đã được tài khoản khác sử dụng.';
        }
    }

    if (!$err) {
        $conn->prepare('UPDATE tutors SET full_name=?, phone=?, email=?, birth_date=?, gender=?, university=?, major=?, subjects=?,
                        grade_min=?, grade_max=?, experience_years=?, experience_note=?, teaching_areas=?, expected_fee=?
                        WHERE tutor_id=?')
             ->execute([
                 $v['full_name'], $v['phone'], $v['email'] ?: null, $v['birth_date'] ?: null, $v['gender'] ?: null,
                 $v['university'] ?: null, $v['major'] ?: null, $subStr, $gMin, $gMax,
                 $v['experience_years'] === '' ? null : (int) $v['experience_years'], $v['experience_note'] ?: null,
                 implode(', ', $v['areas_arr']), $v['expected_fee'] === '' ? null : (int) $v['expected_fee'],
                 $tid,
             ]);
        gs_flash('ok', $avatar ? 'Đã cập nhật hồ sơ và ảnh đại diện.' : 'Đã cập nhật hồ sơ.');
        gs_redirect('ho-so.php');
    }

    // Còn lỗi: báo rõ ở đầu trang thay vì chỉ hiện chữ đỏ nhỏ cạnh từng ô
    if ($avatar) {
        gs_flash('ok', 'Đã lưu ảnh đại diện mới.');
    }
    $soLoi = count(array_diff_key($err, ['avatar' => 1]));
    if ($soLoi) {
        gs_flash('error', "Thông tin hồ sơ chưa được lưu: còn {$soLoi} ô chưa hợp lệ, vui lòng kiểm tra các dòng báo đỏ bên dưới.");
    }
}

// Mức độ hoàn thiện hồ sơ
$checks = [!empty($tutor['avatar']), !empty($tutor['birth_date']), !empty($tutor['gender']), !empty($tutor['email']),
           !empty($tutor['university']), !empty($tutor['major']), !empty($tutor['subjects']), !empty($tutor['grade_min']),
           $tutor['experience_years'] !== null, !empty($tutor['teaching_areas']), !empty($tutor['expected_fee'])];
$percent = (int) round(array_sum($checks) / count($checks) * 100);
$kyc = gs_kyc($conn, $tid);
$kycKey = $kyc ? $kyc['status'] : 'none';

$fe = function ($k) use (&$err) {
    return isset($err[$k]) ? '<span class="gs-err">' . gs_e($err[$k]) . '</span>' : '';
};
gs_layout_start('Hồ sơ gia sư', 'ho-so', $tutor);
?>
<div class="gs-stats">
    <div class="gs-stat"><span>Hoàn thiện hồ sơ</span><b><?= $percent ?>%</b><div class="gs-progress"><span style="width:<?= $percent ?>%"></span></div></div>
    <div class="gs-stat"><span>Xác minh hồ sơ</span><b style="font-size:15px;margin-top:6px"><?= gs_badge(GS_KYC_LABELS, $kycKey) ?></b></div>
    <div class="gs-stat"><span>Tài khoản</span><b style="font-size:15px;margin-top:6px"><?= $tutor['status'] === 'active' ? '<span class="gs-badge gs-b-ok">Đã kích hoạt</span>' : '<span class="gs-badge gs-b-warn">Chờ kích hoạt</span>' ?></b></div>
    <div class="gs-stat"><span>Đánh giá</span><b>⭐ <?= gs_e($tutor['rating']) ?></b></div>
</div>
<?php if ($tutor['status'] !== 'active'): ?>
    <div class="gs-alert gs-alert-info">Tài khoản chưa được kích hoạt nên chưa thể ứng tuyển. Hãy hoàn thiện hồ sơ và <a href="kyc.php"><u>gửi xác minh hồ sơ</u></a>.</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate>
    <?= gs_csrf_field() ?>
    <div class="gs-form">
        <div class="gs-field full" style="flex-direction:row;align-items:center;gap:16px">
            <span id="avatarPreview"><?= ve_the_anh_gia_su($tutor['avatar'] ?? null, 80) ?></span>
            <div style="flex:1">
                <label for="avatarInput" style="font-weight:600;font-size:14px">Ảnh đại diện <span class="gs-muted">(JPG/PNG/WEBP, tối đa 2MB)</span></label>
                <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp" style="display:block;margin-top:6px">
                <span class="gs-muted" id="avatarHint" style="font-size:13px"></span>
                <?= $fe('avatar') ?>
            </div>
        </div>

        <div class="gs-field"><label>Họ và tên <span class="req">*</span></label>
            <input type="text" name="full_name" maxlength="100" value="<?= gs_e($v['full_name']) ?>"><?= $fe('full_name') ?></div>
        <div class="gs-field"><label>Ngày sinh</label>
            <input type="date" name="birth_date" value="<?= gs_e($v['birth_date']) ?>"><?= $fe('birth_date') ?></div>
        <div class="gs-field"><label>Giới tính</label>
            <select name="gender">
                <option value="">-- Chọn --</option>
                <?php foreach (['male' => 'Nam', 'female' => 'Nữ', 'other' => 'Khác'] as $k => $lb): ?>
                    <option value="<?= $k ?>" <?= ($v['gender'] ?? '') === $k ? 'selected' : '' ?>><?= $lb ?></option>
                <?php endforeach; ?>
            </select><?= $fe('gender') ?></div>
        <div class="gs-field"><label>Số điện thoại <span class="req">*</span></label>
            <input type="tel" name="phone" maxlength="15" value="<?= gs_e($v['phone']) ?>"><?= $fe('phone') ?></div>
        <div class="gs-field"><label>Email</label>
            <input type="email" name="email" maxlength="100" value="<?= gs_e($v['email']) ?>"><?= $fe('email') ?></div>
        <div class="gs-field"><label>Trường đại học</label>
            <input type="text" name="university" maxlength="150" value="<?= gs_e($v['university']) ?>"><?= $fe('university') ?></div>
        <div class="gs-field full"><label>Chuyên ngành</label>
            <input type="text" name="major" maxlength="100" value="<?= gs_e($v['major']) ?>"><?= $fe('major') ?></div>

        <div class="gs-field full"><label>Môn học có thể dạy <span class="req">*</span></label>
            <div class="gs-checks">
                <?php foreach (GS_SUBJECTS as $s): ?>
                    <label><input type="checkbox" name="subjects[]" value="<?= gs_e($s) ?>" <?= in_array($s, $v['subjects_arr'], true) ? 'checked' : '' ?>><span><?= gs_e($s) ?></span></label>
                <?php endforeach; ?>
            </div>
            <input type="text" name="subjects_other" placeholder="Môn khác (cách nhau bởi dấu phẩy)"
                   value="<?= gs_e(implode(', ', array_diff($v['subjects_arr'], GS_SUBJECTS))) ?>">
            <?= $fe('subjects') ?></div>

        <div class="gs-field"><label>Khối lớp có thể dạy <span class="req">*</span></label>
            <div style="display:flex;gap:8px;align-items:center">
                <select name="grade_min"><?php for ($g = 1; $g <= 12; $g++): ?><option value="<?= $g ?>" <?= (int) ($v['grade_min'] ?: 1) === $g ? 'selected' : '' ?>>Lớp <?= $g ?></option><?php endfor; ?></select>
                <span>đến</span>
                <select name="grade_max"><?php for ($g = 1; $g <= 12; $g++): ?><option value="<?= $g ?>" <?= (int) ($v['grade_max'] ?: 12) === $g ? 'selected' : '' ?>>Lớp <?= $g ?></option><?php endfor; ?></select>
            </div><?= $fe('grade') ?></div>
        <div class="gs-field"><label>Mức học phí mong muốn (VNĐ/buổi)</label>
            <input type="text" inputmode="numeric" name="expected_fee" placeholder="VD: 200000"
                   value="<?= $v['expected_fee'] !== null && $v['expected_fee'] !== '' ? gs_e((int) $v['expected_fee']) : '' ?>"><?= $fe('expected_fee') ?></div>

        <div class="gs-field"><label>Số năm kinh nghiệm</label>
            <input type="number" name="experience_years" min="0" max="50" value="<?= gs_e($v['experience_years']) ?>"><?= $fe('experience_years') ?></div>
        <div class="gs-field full"><label>Kinh nghiệm giảng dạy</label>
            <textarea name="experience_note" maxlength="2000" placeholder="Các lớp đã dạy, thành tích học sinh, chứng chỉ..."><?= gs_e($v['experience_note']) ?></textarea><?= $fe('experience_note') ?></div>

        <div class="gs-field full"><label>Khu vực có thể nhận lớp <span class="req">*</span></label>
            <div class="gs-checks">
                <?php foreach (GS_AREAS as $a): ?>
                    <label><input type="checkbox" name="areas[]" value="<?= gs_e($a) ?>" <?= in_array($a, $v['areas_arr'], true) ? 'checked' : '' ?>><span><?= gs_e($a) ?></span></label>
                <?php endforeach; ?>
            </div><?= $fe('areas') ?></div>
    </div>
    <div class="gs-actions" style="margin-top:20px">
        <button type="submit" class="gs-btn"><i class="fa-solid fa-floppy-disk"></i> Lưu hồ sơ</button>
        <a class="gs-btn outline" href="../thong-tin-gia-su.php"><i class="fa-solid fa-key"></i> Đổi mật khẩu</a>
    </div>
</form>
<script>
// Xem trước ảnh ngay khi chọn file, kiểm tra nhanh định dạng và dung lượng
document.getElementById('avatarInput').addEventListener('change', function () {
    var f = this.files[0], hint = document.getElementById('avatarHint'), box = document.getElementById('avatarPreview');
    hint.textContent = '';
    if (!f) return;
    if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) < 0) { hint.textContent = 'Chỉ nhận ảnh JPG, PNG hoặc WEBP.'; this.value = ''; return; }
    if (f.size > 2 * 1024 * 1024) { hint.textContent = 'Ảnh vượt quá 2MB, vui lòng chọn ảnh nhỏ hơn.'; this.value = ''; return; }
    var img = document.createElement('img');
    img.src = URL.createObjectURL(f);
    img.alt = 'Xem trước ảnh đại diện';
    img.style.cssText = 'width:80px;height:80px;border-radius:50%;object-fit:cover;border:1px solid #e3e3e3';
    box.innerHTML = '';
    box.appendChild(img);
    hint.textContent = 'Bấm "Lưu hồ sơ" để lưu ảnh mới.';
});
</script>
<?php gs_layout_end(); ?>
