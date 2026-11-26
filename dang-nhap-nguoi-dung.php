<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Nếu người dùng đã đăng nhập thì tự động đẩy về trang chủ
if (isset($_SESSION['user_type'], $_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'tutor') {
        header('Location: gia-su/ho-so.php'); // Đẩy gia sư vào khu vực gia sư
    } else {
        header('Location: index.php');        // Đẩy phụ huynh ra trang chủ
    }
    exit;
}
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$loi = '';
$giaTri = ['login_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginId = trim((string)($_POST['login_id'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    
    $giaTri['login_id'] = $loginId;

    // Chuẩn hóa +84 về 0 nếu người dùng nhập theo định dạng quốc tế
    if (strpos($loginId, '+84') === 0) {
        $loginId = '0' . substr($loginId, 3);
    }
    $conKhoa = 0;

    if (!ve_csrf_hop_le()) {
        $loi = 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.';
    } elseif ($loginId === '' || $password === '') {
        $loi = 'Vui lòng nhập đầy đủ Số điện thoại/Email và Mật khẩu.';
    } elseif (($conKhoa = ve_so_giay_bi_khoa(db(), 'user', $loginId)) > 0) {
        $loi = ve_thong_bao_khoa($conKhoa);
    } else {
        try {
            $pdo = db();
            $userFound = false;
            $userData = null;
            $userType = '';

            // 1. Quét tìm trong bảng Phụ Huynh trước
            $stmt = $pdo->prepare("SELECT parent_id as id, full_name, password_hash, status FROM parents WHERE phone = ? OR email = ?");
            $stmt->execute([$loginId, $loginId]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $userFound = true;
                $userData = $row;
                $userType = 'parent';
            }

            // 2. Nếu không thấy, quét tiếp trong bảng Gia Sư
            if (!$userFound) {
                $stmt = $pdo->prepare("SELECT tutor_id as id, full_name, password_hash, status FROM tutors WHERE phone = ? OR email = ?");
                $stmt->execute([$loginId, $loginId]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $userFound = true;
                    $userData = $row;
                    $userType = 'tutor';
                }
            }

            // 3. Xác thực Mật khẩu & Trạng thái tài khoản
            $bang = $userType === 'tutor' ? 'tutors' : 'parents';
            $cot  = $userType === 'tutor' ? 'tutor_id' : 'parent_id';
            $matKhauDung = ve_xac_thuc_mat_khau($password, $userData['password_hash'] ?? null,
                function ($hashMoi) use ($pdo, $bang, $cot, $userData) {
                    $pdo->prepare("UPDATE $bang SET password_hash = ? WHERE $cot = ?")->execute([$hashMoi, $userData['id']]);
                });

            if ($matKhauDung) {
                ve_xoa_that_bai($pdo, 'user', $loginId);
                if ($userData['status'] === 'locked') {
                    $loi = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ CSKH để được hỗ trợ.';
                } else {
                    // Đăng nhập thành công, tạo mới ID phiên làm việc (Bảo mật Session Fixation)
                    session_regenerate_id(true);
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $_SESSION['user_type'] = $userType;
                    $_SESSION['user_id']   = (int) $userData['id'];
                    $_SESSION['user_name'] = $userData['full_name'];
                    unset($_SESSION['tutor_id'], $_SESSION['parent_id']);
                    if ($userType === 'tutor') {
                        // Khu vực gia-su/ dùng khóa tutor_id
                        $_SESSION['tutor_id'] = (int) $userData['id'];
                        header('Location: gia-su/ho-so.php');
                    } else {
                        $_SESSION['parent_id'] = (int) $userData['id'];
                        header('Location: index.php');
                    }
                    exit;
                }
            } else {
                ve_ghi_that_bai($pdo, 'user', $loginId);
                $loi = 'Số điện thoại/Email hoặc mật khẩu không chính xác.';
            }

        } catch (PDOException $ex) {
            error_log('Lỗi đăng nhập người dùng: ' . $ex->getMessage());
            $loi = 'Hệ thống đang bận. Vui lòng thử lại sau.';
        }
    }
}

$pageTitle = 'Đăng Nhập - VietEdu';
require __DIR__ . '/header.php';
?>

<style>
    .auth-wrap { max-width: 500px; margin: 60px auto; padding: 0 16px; }
    .auth-card { background: #fff; border: 1px solid var(--border-color);
                 border-radius: 12px; box-shadow: var(--shadow); padding: 32px; }
    .auth-card h1 { color: var(--primary-dark); font-size: 24px; margin-bottom: 6px; text-align: center; }
    .auth-card .lead { color: var(--text-muted); font-size: 14px; margin-bottom: 24px; text-align: center; }
    
    .auth-field { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
    .auth-field label { font-size: 14px; font-weight: 600; }
    .auth-field input { padding: 12px 14px; border: 1px solid var(--border-color);
                        border-radius: 6px; font-size: 14px; width: 100%; box-sizing: border-box; }
    .auth-field input:focus { border-color: var(--primary-red); outline: none; }
    
    .auth-alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 18px; font-size: 14px; }
    .auth-alert.error { background: #FDECEA; color: #B3261E; }
    
    .auth-submit { width: 100%; margin-top: 10px; background: var(--primary-red); color: #fff;
                   border: none; padding: 14px; border-radius: 6px;
                   font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.2s; }
    .auth-submit:hover { background: var(--primary-dark); }
    
    .auth-links { margin-top: 24px; text-align: center; font-size: 14px; display: flex; flex-direction: column; gap: 8px; }
    .auth-links a { color: var(--primary-red); font-weight: 600; text-decoration: none; }
    .auth-links a:hover { text-decoration: underline; }
</style>

<div class="auth-wrap">
    <div class="auth-card">
        <h1>Đăng Nhập Cổng VietEdu</h1>
        <p class="lead">Dành cho Phụ Huynh & Gia Sư</p>

        <?php if ($loi): ?>
            <div class="auth-alert error"><i class="fa-solid fa-circle-exclamation"></i> <?= e($loi) ?></div>
        <?php endif; ?>

        <form method="post" action="dang-nhap-nguoi-dung.php">
            <input type="hidden" name="csrf_token" value="<?= e(ve_csrf()) ?>">
            <div class="auth-field">
                <label>Số điện thoại hoặc Email</label>
                <input type="text" name="login_id" required placeholder="Nhập SĐT hoặc Email của bạn"
                       value="<?= e($giaTri['login_id']) ?>" autocomplete="username">
            </div>

            <div class="auth-field">
                <div style="display: flex; justify-content: space-between;">
                    <label>Mật khẩu</label>
                    <a href="quen-mat-khau.php" style="font-size: 13px; color: var(--primary-red); text-decoration: none; font-weight: 600;">Quên mật khẩu?</a>
                </div>
                <input type="password" name="password" required placeholder="Nhập mật khẩu" autocomplete="current-password">
            </div>

            <button type="submit" class="auth-submit">
                <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập
            </button>
        </form>

        <div class="auth-links">
            <div>Chưa có tài khoản? <a href="dang-ky-phu-huynh.php">Đăng ký Phụ huynh</a></div>
            <div>Bạn là gia sư? <a href="dang-ky-gia-su.php">Trở thành Gia sư</a></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>