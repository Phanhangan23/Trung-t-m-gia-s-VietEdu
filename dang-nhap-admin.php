<?php
/** ĐĂNG NHẬP NHÂN VIÊN (Admin / Tư vấn viên) VÀO TRANG QUẢN TRỊ */
require_once __DIR__ . '/db.php';
session_start();

if (!empty($_SESSION['staff_id'])) {
    header('Location: admin.php');
    exit;
}

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$loi = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $matKhau  = (string) ($_POST['password'] ?? '');

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $loi = 'Phiên làm việc đã hết hạn, vui lòng thử lại.';
    } elseif ($username === '' || $matKhau === '') {
        $loi = 'Vui lòng nhập tên đăng nhập và mật khẩu.';
    } elseif (($conKhoa = ve_so_giay_bi_khoa($conn, 'staff', $username)) > 0) {
        $loi = ve_thong_bao_khoa($conKhoa);
    } else {
        try {
            $st = $conn->prepare('SELECT s.staff_id, s.full_name, s.password_hash, s.role_id, r.role_name
                                  FROM staffs s LEFT JOIN roles r ON s.role_id = r.role_id
                                  WHERE s.username = ?');
            $st->execute([$username]);
            $nv = $st->fetch(PDO::FETCH_ASSOC);

            $dung = ve_xac_thuc_mat_khau($matKhau, $nv['password_hash'] ?? null, function ($hashMoi) use ($conn, $nv) {
                $conn->prepare('UPDATE staffs SET password_hash = ? WHERE staff_id = ?')->execute([$hashMoi, $nv['staff_id']]);
            });

            if (!$dung) {
                ve_ghi_that_bai($conn, 'staff', $username);
                $loi = 'Tên đăng nhập hoặc mật khẩu không đúng.';
            } else {
                ve_xoa_that_bai($conn, 'staff', $username);
                session_regenerate_id(true);
                $_SESSION['staff_id']  = (int) $nv['staff_id'];
                $_SESSION['full_name'] = $nv['full_name'];
                $_SESSION['role_id']   = (int) $nv['role_id'];
                $_SESSION['role_name'] = $nv['role_name'];
                $_SESSION['csrf']      = bin2hex(random_bytes(32));
                header('Location: admin.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Lỗi đăng nhập nhân viên: ' . $e->getMessage());
            $loi = 'Hệ thống đang bận, vui lòng thử lại sau.';
        }
    }
}

function e($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập quản trị - VietEdu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #F5F0F1; min-height: 100vh;
               display: flex; align-items: center; justify-content: center; padding: 16px; color: #333; }
        .card { background: #fff; width: 100%; max-width: 400px; border-radius: 12px; padding: 32px;
                box-shadow: 0 8px 30px rgba(128, 0, 32, .12); border-top: 4px solid #8B0000; }
        .logo { text-align: center; font-size: 26px; font-weight: 800; color: #8B0000; margin-bottom: 6px; }
        .logo span { color: #333; }
        .sub { text-align: center; color: #777; font-size: 14px; margin-bottom: 24px; }
        label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 6px; }
        input { width: 100%; padding: 11px 13px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; margin-bottom: 16px; }
        input:focus { border-color: #8B0000; outline: none; }
        button { width: 100%; padding: 12px; background: #8B0000; color: #fff; border: none; border-radius: 6px;
                 font-size: 15px; font-weight: 700; cursor: pointer; }
        button:hover { background: #A52A2A; }
        .err { background: #FDECEA; color: #B3261E; padding: 10px 14px; border-radius: 6px; font-size: 14px; margin-bottom: 16px; }
        .back { display: block; text-align: center; margin-top: 18px; color: #8B0000; font-size: 14px; text-decoration: none; }
    </style>
</head>
<body>
    <form class="card" method="post" action="dang-nhap-admin.php">
        <div class="logo"><i class="fa-solid fa-graduation-cap"></i> Viet<span>Edu Quản Trị</span></div>
        <p class="sub">Đăng nhập dành cho nhân viên trung tâm</p>

        <?php if ($loi): ?><div class="err"><?= e($loi) ?></div><?php endif; ?>

        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <label for="username">Tên đăng nhập</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>
        <label for="password">Mật khẩu</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button type="submit"><i class="fa-solid fa-right-to-bracket"></i> Đăng nhập</button>
        <a class="back" href="index.php">&larr; Về trang chủ</a>
    </form>
</body>
</html>
