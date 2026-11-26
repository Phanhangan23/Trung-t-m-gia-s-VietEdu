<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

const VE_RESET_PHUT = 30;   // liên kết đặt lại mật khẩu có hiệu lực 30 phút

$loi = '';
$thongBao = '';
$linkNoiBo = '';   // chỉ hiển thị khi chạy trên máy chủ nội bộ (chưa cấu hình gửi email)
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));

    if (!ve_csrf_hop_le()) {
        $loi = 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.';
    } elseif ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $loi = 'Vui lòng nhập một địa chỉ email hợp lệ.';
    } elseif (($conKhoa = ve_so_giay_bi_khoa(db(), 'reset', $email)) > 0) {
        $loi = 'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau ' . max(1, (int) ceil($conKhoa / 60)) . ' phút.';
    } else {
        try {
            $pdo = db();
            ve_ghi_that_bai($pdo, 'reset', $email);   // đếm số lần yêu cầu để chống spam

            $stmt = $pdo->prepare("SELECT email FROM parents WHERE email = ? UNION SELECT email FROM tutors WHERE email = ?");
            $stmt->execute([$email, $email]);

            if ($stmt->fetch()) {
                // Token gửi cho người dùng; CSDL chỉ lưu bản băm SHA-256 (lộ CSDL cũng không dùng được token)
                $token = bin2hex(random_bytes(32));
                $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
                $pdo->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)")->execute([$email, hash('sha256', $token)]);

                // TODO: khi có máy chủ email, gửi liên kết này qua email thay vì hiển thị
                if (ve_la_localhost()) {
                    $linkNoiBo = 'dat-lai-mat-khau.php?token=' . $token;
                }
            }
            // Thông báo giống nhau dù email có tồn tại hay không (không để lộ email nào đã đăng ký)
            $thongBao = 'Nếu email này đã đăng ký tài khoản, chúng tôi đã gửi liên kết đặt lại mật khẩu (hiệu lực '
                      . VE_RESET_PHUT . ' phút). Vui lòng kiểm tra hộp thư.';
        } catch (PDOException $ex) {
            error_log('Quên mật khẩu: ' . $ex->getMessage());
            $loi = 'Lỗi hệ thống, vui lòng thử lại sau.';
        }
    }
}

$pageTitle = 'Quên Mật Khẩu - VietEdu';
require __DIR__ . '/header.php';
?>

<style>
    .auth-wrap { max-width: 500px; margin: 60px auto; padding: 0 16px; }
    .auth-card { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: var(--shadow); padding: 32px; }
    .auth-card h1 { color: var(--primary-dark); font-size: 24px; margin-bottom: 10px; text-align: center; }
    .auth-card .lead { color: var(--text-muted); font-size: 14px; margin-bottom: 24px; text-align: center; line-height: 1.5; }
    .auth-field { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
    .auth-field label { font-size: 14px; font-weight: 600; }
    .auth-field input { padding: 12px 14px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px; width: 100%; box-sizing: border-box; }
    .auth-field input:focus { border-color: var(--primary-red); outline: none; }
    .auth-alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 18px; font-size: 14px; }
    .auth-alert.error { background: #FDECEA; color: #B3261E; }
    .auth-alert.success { background: #EAF7ED; color: #2E7D32; border: 1px solid #C8E6C9; }
    .auth-submit { width: 100%; background: var(--primary-red); color: #fff; border: none; padding: 14px; border-radius: 6px; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.2s; }
    .auth-submit:hover { background: var(--primary-dark); }
</style>

<div class="auth-wrap">
    <div class="auth-card">
        <h1>Khôi Phục Mật Khẩu</h1>
        <p class="lead">Nhập địa chỉ email bạn đã dùng để đăng ký. Chúng tôi sẽ gửi cho bạn một liên kết để tạo mật khẩu mới.</p>

        <?php if ($loi): ?>
            <div class="auth-alert error"><i class="fa-solid fa-circle-exclamation"></i> <?= e($loi) ?></div>
        <?php endif; ?>

        <?php if ($thongBao): ?>
            <div class="auth-alert success"><i class="fa-solid fa-circle-check"></i> <?= e($thongBao) ?></div>
            <?php if ($linkNoiBo): ?>
                <div class="auth-alert" style="background:#FFF8E7; color:#7A5600; border:1px dashed #E0B252;">
                    <strong>Chế độ máy chủ nội bộ:</strong> hệ thống chưa cấu hình gửi email nên liên kết được hiển thị tại đây
                    (chỉ hiện khi truy cập từ chính máy chủ).<br>
                    <a href="<?= e($linkNoiBo) ?>" style="color:var(--primary-red); font-weight:bold; text-decoration:underline;">Bấm vào đây để đặt lại mật khẩu</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <form method="post" action="quen-mat-khau.php">
                <input type="hidden" name="csrf_token" value="<?= e(ve_csrf()) ?>">
                <div class="auth-field">
                    <label>Địa chỉ Email</label>
                    <input type="email" name="email" required placeholder="Nhập email của bạn" value="<?= e($email) ?>">
                </div>
                <button type="submit" class="auth-submit"><i class="fa-solid fa-envelope"></i> Gửi yêu cầu</button>
            </form>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="dang-nhap-nguoi-dung.php" style="color: var(--text-muted); font-size: 14px; font-weight: 600; text-decoration: none;">
                <i class="fa-solid fa-arrow-left"></i> Quay lại đăng nhập
            </a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>