<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$loi = '';
$thongBao = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $loi = 'Vui lòng nhập một địa chỉ email hợp lệ.';
    } else {
        try {
            $pdo = db();
            // Kiểm tra email có tồn tại ở bảng Phụ huynh hoặc Gia sư không
            $stmt = $pdo->prepare("SELECT email FROM parents WHERE email = ? UNION SELECT email FROM tutors WHERE email = ?");
            $stmt->execute([$email, $email]);
            
            if ($stmt->fetch()) {
                // Tạo token ngẫu nhiên và lưu vào CSDL
                $token = bin2hex(random_bytes(32));
                
                // Xóa token cũ của email này (nếu có) để tránh rác DB
                $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
                
                // Thêm token mới
                $pdo->prepare("INSERT INTO password_resets (email, token) VALUES (?, ?)")->execute([$email, $token]);

                // Hiển thị link ra màn hình thay vì gửi mail thực tế:
                $linkReset = "dat-lai-mat-khau.php?token=" . $token;
                $thongBao = 'Yêu cầu thành công! (Môi trường Localhost) <br><br>
                             <a href="' . $linkReset . '" style="color:var(--primary-red); font-weight:bold; text-decoration:underline;">
                             Bấm vào đây để đặt lại mật khẩu</a>';
            } else {
                $loi = 'Không tìm thấy tài khoản nào liên kết với email này.';
            }
        } catch (PDOException $ex) {
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
            <div class="auth-alert success"><?= $thongBao ?></div>
        <?php else: ?>
            <form method="post" action="quen-mat-khau.php">
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