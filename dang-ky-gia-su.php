<?php
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$loi    = [];
$giaTri = ['full_name' => '', 'phone' => '', 'email' => '', 'password' => '', 'password2' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $loi['chung'] = 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.';
    } else {
        foreach ($giaTri as $k => $_) {
            $v = (string) ($_POST[$k] ?? '');
            $giaTri[$k] = in_array($k, ['password', 'password2'], true) ? $v : trim($v);
        }

        if (mb_strlen($giaTri['full_name']) < 2 || mb_strlen($giaTri['full_name']) > 100)
            $loi['full_name'] = 'Họ tên từ 2–100 ký tự.';

        $phone = str_replace([' ', '.', '-'], '', $giaTri['phone']);
        if (!preg_match('/^(0|\+84)(3|5|7|8|9)\d{8}$/', $phone)) {
            $loi['phone'] = 'Số điện thoại không hợp lệ.';
        } else {
            if (strpos($phone, '+84') === 0) $phone = '0' . substr($phone, 3);
            $giaTri['phone'] = $phone;
        }

        if ($giaTri['email'] === '' || filter_var($giaTri['email'], FILTER_VALIDATE_EMAIL) === false)
            $loi['email'] = 'Email không hợp lệ và bắt buộc nhập.';

        if (($loiMk = ve_kiem_tra_mat_khau($giaTri['password'], [$phone, $giaTri['email']])) !== '')
            $loi['password'] = $loiMk;
        elseif ($giaTri['password'] !== $giaTri['password2'])
            $loi['password2'] = 'Mật khẩu xác nhận không khớp.';

        if (!$loi) {
            try {
                // Kiểm tra trùng SĐT trên cả 2 bảng
                $st = db()->prepare(
                    'SELECT (SELECT COUNT(*) FROM parents WHERE phone = ?) +
                            (SELECT COUNT(*) FROM tutors  WHERE phone = ?)'
                );
                $st->execute([$phone, $phone]);
                if ($st->fetchColumn() > 0) $loi['phone'] = 'Số điện thoại đã được sử dụng.';

                // Kiểm tra trùng Email
                $st = db()->prepare(
                    'SELECT (SELECT COUNT(*) FROM parents WHERE email = ?) +
                            (SELECT COUNT(*) FROM tutors  WHERE email = ?)'
                );
                $st->execute([$giaTri['email'], $giaTri['email']]);
                if ($st->fetchColumn() > 0) $loi['email'] = 'Email đã được sử dụng.';

                if (!$loi) {
                    // Trạng thái mặc định là 'pending' (theo CSDL) để chờ duyệt hồ sơ
                    $st = db()->prepare(
                        'INSERT INTO tutors (full_name, phone, email, password_hash, status)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $st->execute([
                        $giaTri['full_name'],
                        $phone,
                        $giaTri['email'] !== '' ? $giaTri['email'] : null, // email rỗng -> NULL để không trùng UNIQUE
                        password_hash($giaTri['password'], PASSWORD_DEFAULT),
                        'pending',
                    ]);

                    // Đăng nhập tự động
                    session_regenerate_id(true);
                    $_SESSION['user_type'] = 'tutor';
                    $_SESSION['user_id']   = (int) db()->lastInsertId();
                    $_SESSION['user_name'] = $giaTri['full_name'];
                    $_SESSION['tutor_id']  = $_SESSION['user_id']; // khu vực gia-su/ dùng khóa này
                    unset($_SESSION['parent_id']);
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header('Location: gia-su/ho-so.php');
                    exit;
                }
            } catch (PDOException $ex) {
                $loi['chung'] = ve_loi_csdl($ex, 'tạo tài khoản gia sư');
            }
        }
    }
}

$pageTitle = 'Đăng Ký Gia Sư - VietEdu';
require __DIR__ . '/header.php';
?>

<style>
    .auth-wrap { max-width: 720px; margin: 40px auto; padding: 0 16px; }
    .auth-card { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: var(--shadow); padding: 32px; }
    .auth-card h1 { color: var(--primary-dark); font-size: 24px; margin-bottom: 6px; }
    .auth-card .lead { color: var(--text-muted); font-size: 14px; margin-bottom: 22px; }
    .auth-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .auth-field { display: flex; flex-direction: column; gap: 6px; }
    .auth-field.full { grid-column: 1 / -1; }
    .auth-field label { font-size: 14px; font-weight: 600; }
    .auth-field label .req { color: var(--primary-red); }
    .auth-field input { padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px; }
    .auth-field input:focus { border-color: var(--primary-red); outline: none; }
    .auth-err { color: #B3261E; font-size: 13px; }
    .auth-alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 18px; font-size: 14px; }
    .auth-alert.error { background: #FDECEA; color: #B3261E; }
    .auth-submit { margin-top: 20px; background: var(--primary-dark); color: #fff; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 700; font-size: 15px; cursor: pointer; }
    .auth-submit:hover { background: var(--primary-red); }
    @media (max-width: 600px) { .auth-grid { grid-template-columns: 1fr; } .auth-card { padding: 22px; } }
</style>

<div class="auth-wrap">
    <div class="auth-card">
        <h1>Trở Thành Gia Sư VietEdu</h1>
        <p class="lead">Đăng ký tài khoản để ứng tuyển và nhận lớp phù hợp với chuyên môn của bạn.</p>

        <?php if (!empty($loi['chung'])): ?>
            <div class="auth-alert error"><?= e($loi['chung']) ?></div>
        <?php endif; ?>

        <form method="post" action="dang-ky-gia-su.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

            <div class="auth-grid">
                <div class="auth-field full">
                    <label>Họ và tên <span class="req">*</span></label>
                    <input type="text" name="full_name" maxlength="100" required value="<?= e($giaTri['full_name']) ?>">
                    <?php if (isset($loi['full_name'])): ?><span class="auth-err"><?= e($loi['full_name']) ?></span><?php endif; ?>
                </div>

                <div class="auth-field">
                    <label>Số điện thoại <span class="req">*</span></label>
                    <input type="tel" name="phone" maxlength="15" required placeholder="VD: 0912345678" value="<?= e($giaTri['phone']) ?>">
                    <?php if (isset($loi['phone'])): ?><span class="auth-err"><?= e($loi['phone']) ?></span><?php endif; ?>
                </div>

                <div class="auth-field">
                    <label>Email <span class="req">*</span></label>
                    <input type="email" name="email" maxlength="150" required value="<?= e($giaTri['email']) ?>">
                    <?php if (isset($loi['email'])): ?><span class="auth-err"><?= e($loi['email']) ?></span><?php endif; ?>
                </div>

                <div class="auth-field">
                    <label>Mật khẩu <span class="req">*</span></label>
                    <input type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password"
                           pattern="<?= e(VE_MK_PATTERN) ?>" title="<?= e(VE_MK_MO_TA) ?>" data-pw-policy>
                    <?php if (isset($loi['password'])): ?><span class="auth-err"><?= e($loi['password']) ?></span><?php endif; ?>
                </div>

                <div class="auth-field">
                    <label>Nhập lại mật khẩu <span class="req">*</span></label>
                    <input type="password" name="password2" minlength="8" maxlength="72" required autocomplete="new-password">
                    <?php if (isset($loi['password2'])): ?><span class="auth-err"><?= e($loi['password2']) ?></span><?php endif; ?>
                </div>
            </div>

            <button type="submit" class="auth-submit">
                <i class="fa-solid fa-user-tie"></i> Đăng ký Gia sư
            </button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>