<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

const VE_RESET_GIAY = 1800;   // liên kết có hiệu lực 30 phút

$token = (string) ($_GET['token'] ?? ($_POST['token'] ?? ''));
$loi = '';
$thanhCong = false;
$emailCanReset = '';

try {
    $pdo = db();
    // 1. Kiểm tra token (CSDL lưu bản băm SHA-256 của token)
    if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
        $stmt = $pdo->prepare("SELECT email, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS tuoi FROM password_resets WHERE token = ?");
        $stmt->execute([hash('sha256', $token)]);
        $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resetRecord) {
            $loi = 'Liên kết khôi phục không hợp lệ hoặc đã được sử dụng.';
        } elseif ((int) $resetRecord['tuoi'] > VE_RESET_GIAY) {
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$resetRecord['email']]);
            $loi = 'Liên kết khôi phục đã hết hạn (quá 30 phút). Vui lòng yêu cầu lại.';
        } else {
            $emailCanReset = $resetRecord['email'];
        }
    } else {
        $loi = 'Liên kết khôi phục không hợp lệ.';
    }

    // 2. Lưu mật khẩu mới
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $emailCanReset !== '') {
        $pass  = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password_confirm'] ?? '');

        if (!ve_csrf_hop_le()) {
            $loi = 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.';
        } elseif (($loiMk = ve_kiem_tra_mat_khau($pass, [$emailCanReset])) !== '') {
            $loi = $loiMk;
        } elseif ($pass !== $pass2) {
            $loi = 'Mật khẩu xác nhận không khớp.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->beginTransaction();
            // Cập nhật ở cả 2 bảng (chỉ bảng nào có email đó mới bị ảnh hưởng)
            $pdo->prepare("UPDATE parents SET password_hash = ? WHERE email = ?")->execute([$hash, $emailCanReset]);
            $pdo->prepare("UPDATE tutors SET password_hash = ? WHERE email = ?")->execute([$hash, $emailCanReset]);
            // Token chỉ dùng 1 lần
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$emailCanReset]);
            $pdo->commit();
            ve_xoa_that_bai($pdo, 'user', $emailCanReset);   // mở khóa đăng nhập nếu đang bị khóa
            $thanhCong = true;
        }
    }
} catch (PDOException $ex) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Đặt lại mật khẩu: ' . $ex->getMessage());
    $loi = 'Lỗi hệ thống, vui lòng thử lại sau.';
}

$pageTitle = 'Đặt Lại Mật Khẩu - VietEdu';
require __DIR__ . '/header.php';
?>

<style>
    .auth-wrap { max-width: 500px; margin: 60px auto; padding: 0 16px; }
    .auth-card { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: var(--shadow); padding: 32px; }
    .auth-card h1 { color: var(--primary-dark); font-size: 24px; margin-bottom: 24px; text-align: center; }
    .auth-field { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
    .auth-field label { font-size: 14px; font-weight: 600; }
    .auth-field input { padding: 12px 14px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px; width: 100%; box-sizing: border-box; }
    .auth-field input:focus { border-color: var(--primary-red); outline: none; }
    .auth-alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 18px; font-size: 14px; }
    .auth-alert.error { background: #FDECEA; color: #B3261E; }
    .auth-alert.success { background: #EAF7ED; color: #2E7D32; border: 1px solid #C8E6C9; }
    .auth-submit { width: 100%; background: var(--primary-red); color: #fff; border: none; padding: 14px; border-radius: 6px; font-weight: 700; font-size: 15px; cursor: pointer; }
    .auth-submit:hover { background: var(--primary-dark); }
</style>

<div class="auth-wrap">
    <div class="auth-card">
        <h1>Tạo Mật Khẩu Mới</h1>

        <?php if ($loi): ?>
            <div class="auth-alert error"><i class="fa-solid fa-circle-exclamation"></i> <?= e($loi) ?></div>
        <?php endif; ?>

        <?php if ($thanhCong): ?>
            <div class="auth-alert success">
                <i class="fa-solid fa-circle-check"></i> Đổi mật khẩu thành công! Bạn có thể đăng nhập bằng mật khẩu mới.
            </div>
            <a href="dang-nhap-nguoi-dung.php" class="auth-submit" style="display:block; text-align:center; text-decoration:none; margin-top:20px;">
                Đi tới Đăng nhập
            </a>
        <?php elseif ($emailCanReset !== ''): ?>
            <form method="post" action="dat-lai-mat-khau.php">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(ve_csrf()) ?>">

                <div class="auth-field">
                    <label>Mật khẩu mới</label>
                    <input type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password"
                           pattern="<?= e(VE_MK_PATTERN) ?>" title="<?= e(VE_MK_MO_TA) ?>" data-pw-policy>
                </div>

                <div class="auth-field">
                    <label>Xác nhận mật khẩu mới</label>
                    <input type="password" name="password_confirm" required minlength="8" maxlength="72" autocomplete="new-password">
                </div>
                
                <button type="submit" class="auth-submit"><i class="fa-solid fa-floppy-disk"></i> Lưu mật khẩu mới</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>