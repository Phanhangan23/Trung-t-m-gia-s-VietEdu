<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Bắt buộc phải là Phụ huynh đã đăng nhập
if (($_SESSION['user_type'] ?? '') !== 'parent' || empty($_SESSION['user_id'])) {
    header('Location: dang-nhap-nguoi-dung.php');
    exit;
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$parentId = (int)$_SESSION['user_id'];
$loi = '';
$thongBao = '';

$pdo = db();

// Lấy thông tin hiện tại
$stmt = $pdo->prepare("SELECT full_name, phone, email, address, password_hash FROM parents WHERE parent_id = ?");
$stmt->execute([$parentId]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    session_destroy();
    header('Location: dang-nhap-nguoi-dung.php');
    exit;
}

// Xử lý khi Phụ huynh bấm Cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    
    $oldPass  = $_POST['old_password'] ?? '';
    $newPass  = $_POST['new_password'] ?? '';
    $newPass2 = $_POST['new_password_confirm'] ?? '';

    // Chuẩn hóa SĐT
    $phone = str_replace([' ', '.', '-'], '', $phone);
    if (strpos($phone, '+84') === 0) $phone = '0' . substr($phone, 3);

    if (mb_strlen($fullName) < 2) {
        $loi = 'Họ tên phải từ 2 ký tự trở lên.';
    } elseif (!preg_match('/^(0)(3|5|7|8|9)\d{8}$/', $phone)) {
        $loi = 'Số điện thoại không hợp lệ.';
    } else {
        try {
            // Chuẩn bị câu lệnh cập nhật thông tin cơ bản
            $updateSql = "UPDATE parents SET full_name = ?, phone = ?, email = ?, address = ? WHERE parent_id = ?";
            $params = [$fullName, $phone, $email !== '' ? $email : null, $address !== '' ? $address : null, $parentId];
            
            // Nếu người dùng có nhập mật khẩu cũ tức là họ muốn đổi mật khẩu
            if ($oldPass !== '') {
                if (!password_verify($oldPass, $parent['password_hash'])) {
                    $loi = 'Mật khẩu cũ không chính xác.';
                } elseif (strlen($newPass) < 8) {
                    $loi = 'Mật khẩu mới phải từ 8 ký tự.';
                } elseif ($newPass !== $newPass2) {
                    $loi = 'Xác nhận mật khẩu mới không khớp.';
                } else {
                    $updateSql = "UPDATE parents SET full_name = ?, phone = ?, email = ?, address = ?, password_hash = ? WHERE parent_id = ?";
                    $params = [$fullName, $phone, $email !== '' ? $email : null, $address !== '' ? $address : null, password_hash($newPass, PASSWORD_DEFAULT), $parentId];
                }
            }

            if (!$loi) {
                $pdo->prepare($updateSql)->execute($params);
                
                // Cập nhật lại session và biến hiển thị
                $_SESSION['user_name'] = $fullName; 
                $parent['full_name'] = $fullName;
                $parent['phone'] = $phone;
                $parent['email'] = $email;
                $parent['address'] = $address;
                
                $thongBao = 'Cập nhật thông tin thành công!';
            }
        } catch (PDOException $ex) {
            // Bắt lỗi Duplicate entry nếu SĐT hoặc Email bị trùng với người khác
            if ($ex->getCode() === '23000') {
                $loi = 'Số điện thoại hoặc Email này đã được sử dụng bởi tài khoản khác.';
            } else {
                $loi = 'Hệ thống đang bận, vui lòng thử lại sau.';
            }
        }
    }
}

$pageTitle = 'Thông Tin Cá Nhân - VietEdu';
require __DIR__ . '/header.php';
?>

<style>
    .profile-wrap { max-width: 800px; margin: 40px auto; padding: 0 16px; }
    .profile-card { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: var(--shadow); padding: 32px; }
    .profile-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 20px; margin-bottom: 24px; }
    .profile-header h1 { color: var(--primary-dark); font-size: 24px; margin: 0; }
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
            <h1><i class="fa-solid fa-user-gear"></i> Hồ Sơ Phụ Huynh</h1>
            <a href="dang-xuat-nguoi-dung.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
        </div>

        <?php if ($loi): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= e($loi) ?></div>
        <?php endif; ?>
        <?php if ($thongBao): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e($thongBao) ?></div>
        <?php endif; ?>

        <form method="post" action="thong-tin-phu-huynh.php">
            <div class="form-section-title">Thông tin cơ bản</div>
            <div class="profile-grid">
                <div class="profile-field full">
                    <label>Họ và tên</label>
                    <input type="text" name="full_name" required maxlength="100" value="<?= e($parent['full_name']) ?>">
                </div>
                <div class="profile-field">
                    <label>Số điện thoại</label>
                    <input type="tel" name="phone" required maxlength="15" value="<?= e($parent['phone']) ?>">
                </div>
                <div class="profile-field">
                    <label>Email</label>
                    <input type="email" name="email" maxlength="150" value="<?= e($parent['email'] ?? '') ?>">
                </div>
                <div class="profile-field full">
                    <label>Địa chỉ</label>
                    <input type="text" name="address" maxlength="255" value="<?= e($parent['address'] ?? '') ?>">
                </div>
            </div>

            <div class="form-section-title">Đổi mật khẩu (Bỏ trống nếu không muốn đổi)</div>
            <div class="profile-grid">
                <div class="profile-field full">
                    <label>Mật khẩu cũ</label>
                    <input type="password" name="old_password" placeholder="Nhập mật khẩu hiện tại">
                </div>
                <div class="profile-field">
                    <label>Mật khẩu mới</label>
                    <input type="password" name="new_password" minlength="8" placeholder="Nhập mật khẩu mới">
                </div>
                <div class="profile-field">
                    <label>Xác nhận mật khẩu mới</label>
                    <input type="password" name="new_password_confirm" minlength="8" placeholder="Nhập lại mật khẩu mới">
                </div>
            </div>

            <button type="submit" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Lưu Thay Đổi</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>