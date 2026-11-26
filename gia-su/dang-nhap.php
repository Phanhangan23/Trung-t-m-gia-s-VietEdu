<?php
/** ĐĂNG NHẬP / ĐĂNG KÝ / ĐĂNG XUẤT TÀI KHOẢN GIA SƯ */
require_once __DIR__ . '/core.php';

// Đăng xuất (kèm token để không bị đăng xuất bởi link lạ)
if (isset($_GET['logout'])) {
    if (hash_equals(gs_csrf(), (string) $_GET['logout'])) {
        unset($_SESSION['tutor_id'], $_SESSION['parent_id'], $_SESSION['user_type'], $_SESSION['user_id'], $_SESSION['user_name']);
        session_regenerate_id(true);
        gs_flash('ok', 'Bạn đã đăng xuất.');
    }
    gs_redirect('dang-nhap.php');
}

if (gs_current_tutor()) gs_redirect('ho-so.php');

// Chỉ cho phép quay lại trang nội bộ cùng thư mục
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
if (!preg_match('/^[a-z0-9\-]+\.php(\?[^\/\\\\]*)?$/i', $next)) $next = 'ho-so.php';

$tab = ($_GET['tab'] ?? $_POST['tab'] ?? 'login') === 'register' ? 'register' : 'login';
$err = [];
$val = ['login' => '', 'full_name' => '', 'phone' => '', 'email' => '', 'university' => '', 'major' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    gs_check_csrf();
    foreach ($val as $k => $_) $val[$k] = trim((string) ($_POST[$k] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');

    if ($tab === 'login') {
        $login = $val['login'];
        $phone = str_replace([' ', '.', '-'], '', $login);
        if (strpos($phone, '+84') === 0) $phone = '0' . substr($phone, 3);
        $dinhDanh = $login !== '' && strpos($login, '@') !== false ? $login : $phone;

        if ($login === '' || $pass === '') {
            $err['login'] = 'Vui lòng nhập đầy đủ số điện thoại/email và mật khẩu.';
        } elseif (($conKhoa = ve_so_giay_bi_khoa($conn, 'user', $dinhDanh)) > 0) {
            $err['login'] = ve_thong_bao_khoa($conKhoa);
        } else {
            $st = $conn->prepare('SELECT * FROM tutors WHERE phone = ? OR email = ? LIMIT 1');
            $st->execute([$phone, $login]);
            $t = $st->fetch(PDO::FETCH_ASSOC);

            $ok = ve_xac_thuc_mat_khau($pass, $t['password_hash'] ?? null, function ($hashMoi) use ($conn, $t) {
                $conn->prepare('UPDATE tutors SET password_hash = ? WHERE tutor_id = ?')->execute([$hashMoi, $t['tutor_id']]);
            });
            if (!$ok) {
                ve_ghi_that_bai($conn, 'user', $dinhDanh);
                $err['login'] = 'Số điện thoại/email hoặc mật khẩu không đúng.';
            } elseif ($t['status'] === 'locked') {
                $err['login'] = 'Tài khoản đã bị khóa. Vui lòng liên hệ trung tâm.';
            }
        }
        if (!$err) {
            ve_xoa_that_bai($conn, 'user', $dinhDanh);
            session_regenerate_id(true);
            unset($_SESSION['gs_csrf']);
            $_SESSION['tutor_id'] = (int) $t['tutor_id'];
            gs_flash('ok', 'Xin chào ' . $t['full_name'] . '!');
            gs_redirect($next);
        }
    } else {
        $l = gs_len($val['full_name']);
        if ($l < 2 || $l > 100) $err['full_name'] = 'Họ tên phải từ 2 đến 100 ký tự.';
        $val['phone'] = str_replace([' ', '.', '-'], '', $val['phone']);
        if (!preg_match('/^0(3|5|7|8|9)\d{8}$/', $val['phone'])) $err['phone'] = 'Số điện thoại không hợp lệ (10 số).';
        if ($val['email'] !== '' && !filter_var($val['email'], FILTER_VALIDATE_EMAIL)) $err['email'] = 'Email không hợp lệ.';
        if (($loiMk = ve_kiem_tra_mat_khau($pass, [$val['phone'], $val['email']])) !== '') $err['password'] = $loiMk;
        elseif ($pass !== (string) ($_POST['password2'] ?? '')) $err['password2'] = 'Mật khẩu nhập lại không khớp.';
        if (gs_len($val['university']) > 150) $err['university'] = 'Tối đa 150 ký tự.';
        if (gs_len($val['major']) > 100) $err['major'] = 'Tối đa 100 ký tự.';

        if (!$err) {
            $st = $conn->prepare('SELECT phone, email FROM tutors WHERE phone = ? OR (email IS NOT NULL AND email = ?)');
            $st->execute([$val['phone'], $val['email']]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $dup) {
                if ($dup['phone'] === $val['phone']) $err['phone'] = 'Số điện thoại đã được đăng ký.';
                if ($val['email'] !== '' && $dup['email'] === $val['email']) $err['email'] = 'Email đã được đăng ký.';
            }
            // SĐT / email cũng không được trùng với tài khoản phụ huynh (đăng nhập dùng chung 1 ô)
            $st = $conn->prepare('SELECT phone, email FROM parents WHERE phone = ? OR (email IS NOT NULL AND email = ?)');
            $st->execute([$val['phone'], $val['email']]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $dup) {
                if ($dup['phone'] === $val['phone']) $err['phone'] = 'Số điện thoại đã được sử dụng.';
                if ($val['email'] !== '' && $dup['email'] === $val['email']) $err['email'] = 'Email đã được sử dụng.';
            }
        }
        if (!$err) {
            $conn->prepare("INSERT INTO tutors (full_name, phone, email, password_hash, university, major, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')")
                 ->execute([$val['full_name'], $val['phone'], $val['email'] ?: null, password_hash($pass, PASSWORD_DEFAULT),
                            $val['university'] ?: null, $val['major'] ?: null]);
            session_regenerate_id(true);
            $_SESSION['tutor_id'] = (int) $conn->lastInsertId();
            gs_flash('ok', 'Đăng ký thành công! Hãy hoàn thiện hồ sơ và gửi xác minh hồ sơ để được kích hoạt nhận lớp.');
            gs_redirect('ho-so.php');
        }
    }
}

$pageTitle = ($tab === 'login' ? 'Đăng nhập' : 'Đăng ký') . ' gia sư - VietEdu';
require __DIR__ . '/../header.php';
$flashes = $_SESSION['gs_flash'] ?? [];
unset($_SESSION['gs_flash']);
$fe = function ($k) use (&$err) {
    return isset($err[$k]) ? '<span class="gs-err">' . gs_e($err[$k]) . '</span>' : '';
};
?>
<link rel="stylesheet" href="gia-su.css">
<div class="gs-auth">
    <div class="gs-tabs">
        <a href="?tab=login&amp;next=<?= rawurlencode($next) ?>" class="<?= $tab === 'login' ? 'active' : '' ?>">Đăng nhập</a>
        <a href="?tab=register&amp;next=<?= rawurlencode($next) ?>" class="<?= $tab === 'register' ? 'active' : '' ?>">Đăng ký làm gia sư</a>
    </div>
    <?php foreach ($flashes as $f): ?>
        <div class="gs-alert gs-alert-<?= $f['type'] === 'error' ? 'error' : 'ok' ?>"><?= gs_e($f['msg']) ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
        <?= gs_csrf_field() ?>
        <input type="hidden" name="tab" value="<?= $tab ?>">
        <input type="hidden" name="next" value="<?= gs_e($next) ?>">
        <?php if ($tab === 'login'): ?>
            <div class="gs-form" style="grid-template-columns:1fr">
                <div class="gs-field">
                    <label>Số điện thoại hoặc Email</label>
                    <input type="text" name="login" required autofocus value="<?= gs_e($val['login']) ?>">
                    <?= $fe('login') ?>
                </div>
                <div class="gs-field">
                    <label>Mật khẩu</label>
                    <input type="password" name="password" required autocomplete="current-password">
                </div>
            </div>
            <button class="gs-btn" type="submit" style="margin-top:18px;width:100%;justify-content:center"><i class="fa-solid fa-right-to-bracket"></i> Đăng nhập</button>
        <?php else: ?>
            <div class="gs-form">
                <div class="gs-field full"><label>Họ và tên <span class="req">*</span></label>
                    <input type="text" name="full_name" maxlength="100" required value="<?= gs_e($val['full_name']) ?>"><?= $fe('full_name') ?></div>
                <div class="gs-field"><label>Số điện thoại <span class="req">*</span></label>
                    <input type="tel" name="phone" maxlength="15" required value="<?= gs_e($val['phone']) ?>"><?= $fe('phone') ?></div>
                <div class="gs-field"><label>Email</label>
                    <input type="email" name="email" maxlength="100" value="<?= gs_e($val['email']) ?>"><?= $fe('email') ?></div>
                <div class="gs-field"><label>Trường đại học</label>
                    <input type="text" name="university" maxlength="150" value="<?= gs_e($val['university']) ?>"><?= $fe('university') ?></div>
                <div class="gs-field"><label>Chuyên ngành</label>
                    <input type="text" name="major" maxlength="100" value="<?= gs_e($val['major']) ?>"><?= $fe('major') ?></div>
                <div class="gs-field"><label>Mật khẩu <span class="req">*</span></label>
                    <input type="password" name="password" required minlength="8" maxlength="72" pattern="<?= gs_e(VE_MK_PATTERN) ?>" title="<?= gs_e(VE_MK_MO_TA) ?>" autocomplete="new-password" data-pw-policy><?= $fe('password') ?></div>
                <div class="gs-field"><label>Nhập lại mật khẩu <span class="req">*</span></label>
                    <input type="password" name="password2" required autocomplete="new-password"><?= $fe('password2') ?></div>
            </div>
            <button class="gs-btn" type="submit" style="margin-top:18px;width:100%;justify-content:center"><i class="fa-solid fa-user-plus"></i> Tạo tài khoản gia sư</button>
        <?php endif; ?>
    </form>
</div>
<?php require __DIR__ . '/../footer.php'; ?>
