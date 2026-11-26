<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Bắt buộc phải là Gia sư đã đăng nhập
if (($_SESSION['user_type'] ?? '') !== 'tutor' || empty($_SESSION['user_id'])) {
    header('Location: dang-nhap-nguoi-dung.php');
    exit;
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$tutorId = (int)$_SESSION['user_id'];
$loi = '';
$thongBao = '';

$pdo = db();

// Lấy thông tin hiện tại của gia sư
$stmt = $pdo->prepare("SELECT full_name, phone, email, university, major, password_hash, rating, status FROM tutors WHERE tutor_id = ?");
$stmt->execute([$tutorId]);
$tutor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tutor) {
    session_destroy();
    header('Location: dang-nhap-nguoi-dung.php');
    exit;
}

// Xử lý khi Gia sư bấm Cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName   = trim($_POST['full_name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $university = trim($_POST['university'] ?? '');
    $major      = trim($_POST['major'] ?? '');
    
    $oldPass  = $_POST['old_password'] ?? '';
    $newPass  = $_POST['new_password'] ?? '';
    $newPass2 = $_POST['new_password_confirm'] ?? '';

    // Chuẩn hóa SĐT
    $phone = str_replace([' ', '.', '-'], '', $phone);
    if (strpos($phone, '+84') === 0) $phone = '0' . substr($phone, 3);

    if (!ve_csrf_hop_le()) {
        $loi = 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.';
    } elseif (mb_strlen($fullName) < 2) {
        $loi = 'Họ tên phải từ 2 ký tự trở lên.';
    } elseif (!preg_match('/^(0)(3|5|7|8|9)\d{8}$/', $phone)) {
        $loi = 'Số điện thoại không hợp lệ.';
    } elseif ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $loi = 'Vui lòng nhập email hợp lệ.';
    } else {
        try {
            // Câu lệnh cập nhật cơ bản
            $updateSql = "UPDATE tutors SET full_name = ?, phone = ?, email = ?, university = ?, major = ? WHERE tutor_id = ?";
            $params = [$fullName, $phone, $email, $university !== '' ? $university : null, $major !== '' ? $major : null, $tutorId];
            
            // Xử lý đổi mật khẩu
            if ($oldPass !== '') {
                if (!password_verify($oldPass, $tutor['password_hash'])) {
                    $loi = 'Mật khẩu cũ không chính xác.';
                } elseif (($loiMk = ve_kiem_tra_mat_khau($newPass, [$phone, $email])) !== '') {
                    $loi = $loiMk;
                } elseif (hash_equals($oldPass, $newPass)) {
                    $loi = 'Mật khẩu mới phải khác mật khẩu cũ.';
                } elseif ($newPass !== $newPass2) {
                    $loi = 'Xác nhận mật khẩu mới không khớp.';
                } else {
                    $updateSql = "UPDATE tutors SET full_name = ?, phone = ?, email = ?, university = ?, major = ?, password_hash = ? WHERE tutor_id = ?";
                    $params = [$fullName, $phone, $email, $university !== '' ? $university : null, $major !== '' ? $major : null, password_hash($newPass, PASSWORD_DEFAULT), $tutorId];
                }
            }

            if (!$loi) {
                // Kiểm tra xem SĐT hoặc Email có bị trùng với Phụ huynh không (tránh xung đột hệ thống)
                $check = $pdo->prepare("SELECT COUNT(*) FROM parents WHERE phone = ? OR email = ?");
                $check->execute([$phone, $email]);
                if ($check->fetchColumn() > 0) {
                    $loi = 'Số điện thoại hoặc Email này đã được sử dụng ở tài khoản Phụ huynh.';
                } else {
                    $pdo->prepare($updateSql)->execute($params);
                    
                    // Cập nhật lại session và biến hiển thị
                    $_SESSION['user_name'] = $fullName; 
                    $tutor['full_name']  = $fullName;
                    $tutor['phone']      = $phone;
                    $tutor['email']      = $email;
                    $tutor['university'] = $university;
                    $tutor['major']      = $major;
                    
                    $thongBao = 'Cập nhật hồ sơ thành công!';
                }
            }
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $loi = 'Số điện thoại hoặc Email này đã có gia sư khác sử dụng.';
            } else {
                $loi = 'Hệ thống đang bận, vui lòng thử lại sau.';
            }
        }
    }
}

$pageTitle = 'Hồ Sơ Gia Sư - VietEdu';
require __DIR__ . '/header.php';
?>

<style>
    .profile-wrap { max-width: 800px; margin: 40px auto; padding: 0 16px; }
    .profile-card { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: var(--shadow); padding: 32px; }
    .profile-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 20px; margin-bottom: 24px; }
    .profile-header h1 { color: var(--primary-dark); font-size: 24px; margin: 0; }
    
    .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-left: 10px; }
    .status-pending { background: #FFF8E7; color: #B78103; }
    .status-active { background: #EAF7ED; color: #2E7D32; }
    
    .btn-logout { background: #FDECEA; color: #B3261E; padding: 8px 16px; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: 14px; }
    .btn-logout:hover { background: #F9DEDC; }
    
    .form-section-title { font-size: 18px; color: var(--primary-red); margin-bottom: 16px; font-weight: 600; }
    .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 30px; }
    .profile-field { display: flex; flex-direction: column; gap: 6px; }
    .profile-field.full { grid-column: 1 / -1; }
    .profile-field label { font-size: 14px; font-weight: 600; }
    .profile-field input { padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px; }
    .profile-field input:focus { border-color: var(--primary-red); outline: none; }
    
    .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 18px; font-size: 14px; }
    .alert-error { background: #FDECEA; color: #B3261E; }
    .alert-success { background: #EAF7ED; color: #2E7D32; }
    
    .btn-submit { background: var(--primary-dark); color: #fff; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 700; font-size: 15px; cursor: pointer; }
    .btn-submit:hover { background: var(--primary-red); }
    @media (max-width: 600px) { .profile-grid { grid-template-columns: 1fr; } }
</style>

<div class="profile-wrap">
    <div class="profile-card">
        <div class="profile-header">
            <h1>
                <i class="fa-solid fa-key"></i> Tài Khoản &amp; Mật Khẩu
                <?php if ($tutor['status'] === 'active'): ?>
                    <span class="status-badge status-active"><i class="fa-solid fa-check-circle"></i> Đã duyệt</span>
                <?php else: ?>
                    <span class="status-badge status-pending"><i class="fa-solid fa-clock"></i> Chờ xác minh hồ sơ</span>
                <?php endif; ?>
            </h1>
            <a href="dang-xuat-nguoi-dung.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
        </div>

        <div class="alert" style="background:#F8ECEE;color:#540015">
            <i class="fa-solid fa-circle-info"></i>
            Ảnh đại diện, môn dạy, khối lớp, khu vực và kinh nghiệm được cập nhật tại
            <a href="gia-su/ho-so.php" style="font-weight:700;color:var(--primary-red)"><u>Hồ sơ gia sư</u></a>.
        </div>

        <?php if ($loi): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= e($loi) ?></div>
        <?php endif; ?>
        <?php if ($thongBao): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e($thongBao) ?></div>
        <?php endif; ?>

        <form method="post" action="thong-tin-gia-su.php">
            <input type="hidden" name="csrf_token" value="<?= e(ve_csrf()) ?>">
            <div class="form-section-title">Thông tin liên hệ & Học vấn</div>
            <div class="profile-grid">
                <div class="profile-field full">
                    <label>Họ và tên</label>
                    <input type="text" name="full_name" required maxlength="100" value="<?= e($tutor['full_name']) ?>">
                </div>
                <div class="profile-field">
                    <label>Số điện thoại</label>
                    <input type="tel" name="phone" required maxlength="15" value="<?= e($tutor['phone']) ?>">
                </div>
                <div class="profile-field">
                    <label>Email</label>
                    <input type="email" name="email" required maxlength="100" value="<?= e($tutor['email']) ?>">
                </div>
                <div class="profile-field">
                    <label>Trường Đại học / Cao đẳng</label>
                    <input type="text" name="university" maxlength="150" placeholder="VD: ĐH Sư Phạm Hà Nội" value="<?= e($tutor['university'] ?? '') ?>">
                </div>
                <div class="profile-field">
                    <label>Chuyên ngành</label>
                    <input type="text" name="major" maxlength="100" placeholder="VD: Sư phạm Toán" value="<?= e($tutor['major'] ?? '') ?>">
                </div>
                <div class="profile-field full">
                    <label>Đánh giá trung bình</label>
                    <input type="text" disabled value="<?= e($tutor['rating']) ?> / 5.0 Sao (Dựa trên phản hồi lớp học)" style="background: var(--bg-light); color: var(--text-muted);">
                </div>
            </div>

            <div class="form-section-title">Đổi mật khẩu (Bỏ trống nếu không muốn đổi)</div>
            <div class="profile-grid">
                <div class="profile-field full">
                    <label>Mật khẩu cũ</label>
                    <input type="password" name="old_password" placeholder="Nhập mật khẩu hiện tại" autocomplete="current-password">
                </div>
                <div class="profile-field">
                    <label>Mật khẩu mới</label>
                    <input type="password" name="new_password" minlength="8" maxlength="72" placeholder="Nhập mật khẩu mới" autocomplete="new-password"
                           pattern="<?= e(VE_MK_PATTERN) ?>" title="<?= e(VE_MK_MO_TA) ?>" data-pw-policy>
                </div>
                <div class="profile-field">
                    <label>Xác nhận mật khẩu mới</label>
                    <input type="password" name="new_password_confirm" minlength="8" maxlength="72" placeholder="Nhập lại mật khẩu mới" autocomplete="new-password">
                </div>
            </div>

            <button type="submit" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Lưu Hồ Sơ</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>