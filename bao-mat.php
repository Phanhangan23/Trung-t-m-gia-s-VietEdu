<?php
/**
 * BẢO MẬT DÙNG CHUNG CHO TOÀN HỆ THỐNG
 * - Cấu hình cookie phiên an toàn
 * - Chính sách mật khẩu mạnh
 * - Chống dò mật khẩu (khóa tạm thời khi đăng nhập sai nhiều lần)
 * - Mã chống giả mạo form (CSRF)
 * File này được nạp tự động từ db.php.
 */

/* ---------- 1. COOKIE PHIÊN AN TOÀN (phải đặt trước session_start) ---------- */
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.use_strict_mode', '1');     // không nhận mã phiên do trình duyệt tự bịa ra
    ini_set('session.use_only_cookies', '1');    // không truyền mã phiên qua URL
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,                      // JavaScript không đọc được cookie phiên
        'samesite' => 'Lax',                     // hạn chế gửi cookie từ trang web khác
    ]);
}

/* ---------- 2. CHÍNH SÁCH MẬT KHẨU ---------- */
const VE_MK_TOI_THIEU = 8;
const VE_MK_TOI_DA    = 72;   // giới hạn của thuật toán bcrypt
const VE_MK_MO_TA     = 'Mật khẩu từ 8–72 ký tự, gồm ít nhất 1 chữ hoa, 1 chữ thường, 1 chữ số và 1 ký tự đặc biệt (VD: @ # $ % ! & *), không chứa khoảng trắng.';
// Dùng cho thuộc tính pattern của ô nhập (kiểm tra ngay trên trình duyệt)
const VE_MK_PATTERN   = '(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9])\S{8,72}';

/**
 * Kiểm tra độ mạnh mật khẩu. Trả về chuỗi lỗi (tiếng Việt) hoặc '' nếu hợp lệ.
 * $thongTin: các thông tin cá nhân không được xuất hiện trong mật khẩu (SĐT, email, tên đăng nhập...).
 */
function ve_kiem_tra_mat_khau(string $mk, array $thongTin = []): string
{
    $thieu = [];
    if (strlen($mk) < VE_MK_TOI_THIEU) $thieu[] = 'tối thiểu ' . VE_MK_TOI_THIEU . ' ký tự';
    if (!preg_match('/[A-Z]/', $mk))   $thieu[] = '1 chữ in hoa';
    if (!preg_match('/[a-z]/', $mk))   $thieu[] = '1 chữ thường';
    if (!preg_match('/\d/', $mk))      $thieu[] = '1 chữ số';
    if (!preg_match('/[^A-Za-z0-9\s]/', $mk)) $thieu[] = '1 ký tự đặc biệt';
    if ($thieu) return 'Mật khẩu chưa đủ mạnh, cần có: ' . implode(', ', $thieu) . '.';

    if (strlen($mk) > VE_MK_TOI_DA)  return 'Mật khẩu tối đa ' . VE_MK_TOI_DA . ' ký tự.';
    if (preg_match('/\s/', $mk))     return 'Mật khẩu không được chứa khoảng trắng.';

    $mkThuong = mb_strtolower($mk, 'UTF-8');
    foreach ($thongTin as $tt) {
        $tt = mb_strtolower(trim((string) $tt), 'UTF-8');
        if (strpos($tt, '@') !== false) $tt = strstr($tt, '@', true);   // chỉ xét phần trước @ của email
        if (mb_strlen($tt, 'UTF-8') >= 4 && mb_strpos($mkThuong, $tt, 0, 'UTF-8') !== false) {
            return 'Mật khẩu không được chứa số điện thoại, email hoặc tên đăng nhập của bạn.';
        }
    }
    return '';
}

/** Tạo mật khẩu ngẫu nhiên đáp ứng chính sách (dùng khi admin tạo tài khoản hộ). */
function ve_tao_mat_khau_ngau_nhien(int $doDai = 12): string
{
    $nhom = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '@#$%&*!?'];
    $tatCa = implode('', $nhom);
    $kyTu = [];
    foreach ($nhom as $n) $kyTu[] = $n[random_int(0, strlen($n) - 1)];
    while (count($kyTu) < $doDai) $kyTu[] = $tatCa[random_int(0, strlen($tatCa) - 1)];
    for ($i = count($kyTu) - 1; $i > 0; $i--) {      // trộn ngẫu nhiên
        $j = random_int(0, $i);
        [$kyTu[$i], $kyTu[$j]] = [$kyTu[$j], $kyTu[$i]];
    }
    return implode('', $kyTu);
}

/**
 * Xác thực mật khẩu với mã băm đã lưu, luôn tốn thời gian như nhau dù tài khoản có tồn tại hay không
 * (tránh bị đoán tài khoản qua thời gian phản hồi). Tự băm lại nếu thuật toán băm đã cũ.
 */
function ve_xac_thuc_mat_khau(string $mk, ?string $hash, ?callable $luuHashMoi = null): bool
{
    static $hashGia = null;
    if ($hash === null || $hash === '' || strpos($hash, '$') !== 0) {
        $hashGia ??= password_hash('mat-khau-gia-de-can-thoi-gian', PASSWORD_DEFAULT);
        password_verify($mk, $hashGia);
        return false;
    }
    if (!password_verify($mk, $hash)) return false;
    if ($luuHashMoi && password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $luuHashMoi(password_hash($mk, PASSWORD_DEFAULT));
    }
    return true;
}

/* ---------- 3. CHỐNG DÒ MẬT KHẨU ---------- */
const VE_SAI_TOI_DA_TK = 5;    // sai 5 lần với cùng 1 tài khoản ...
const VE_SAI_TOI_DA_IP = 20;   // ... hoặc 20 lần từ cùng 1 địa chỉ IP
const VE_THOI_GIAN_KHOA = 900; // ... trong 15 phút -> khóa 15 phút

function ve_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function ve_bang_dang_nhap(PDO $conn): void
{
    static $daTao = false;
    if ($daTao) return;
    $conn->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        scope VARCHAR(20) NOT NULL,
        identifier VARCHAR(150) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_id (scope, identifier, attempted_at),
        INDEX idx_ip (scope, ip, attempted_at)
    )");
    $daTao = true;
}

/** Số giây còn bị khóa (0 = được phép thử). $scope: 'user' | 'staff' | 'reset'. */
function ve_so_giay_bi_khoa(PDO $conn, string $scope, string $dinhDanh): int
{
    ve_bang_dang_nhap($conn);
    $dinhDanh = mb_strtolower(trim($dinhDanh), 'UTF-8');
    $con = 0;
    foreach ([['identifier', $dinhDanh, VE_SAI_TOI_DA_TK], ['ip', ve_ip(), VE_SAI_TOI_DA_IP]] as [$cot, $giaTri, $toiDa]) {
        $st = $conn->prepare("SELECT attempted_at FROM login_attempts
                              WHERE scope = ? AND $cot = ? AND attempted_at > NOW() - INTERVAL " . VE_THOI_GIAN_KHOA . " SECOND
                              ORDER BY attempted_at DESC LIMIT 1 OFFSET " . ($toiDa - 1));
        $st->execute([$scope, $giaTri]);
        $lanThu = $st->fetchColumn();   // lần sai thứ N gần nhất còn trong cửa sổ thời gian
        if ($lanThu) {
            $tg = $conn->query("SELECT TIMESTAMPDIFF(SECOND, NOW(), " . $conn->quote($lanThu) . " + INTERVAL " . VE_THOI_GIAN_KHOA . " SECOND)")->fetchColumn();
            $con = max($con, (int) $tg);
        }
    }
    return $con;
}

function ve_ghi_that_bai(PDO $conn, string $scope, string $dinhDanh): void
{
    ve_bang_dang_nhap($conn);
    $conn->prepare('INSERT INTO login_attempts (scope, identifier, ip) VALUES (?, ?, ?)')
         ->execute([$scope, mb_strtolower(trim($dinhDanh), 'UTF-8'), ve_ip()]);
    // Dọn bản ghi cũ hơn 1 ngày
    if (random_int(1, 50) === 1) $conn->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
}

function ve_xoa_that_bai(PDO $conn, string $scope, string $dinhDanh): void
{
    ve_bang_dang_nhap($conn);
    $conn->prepare('DELETE FROM login_attempts WHERE scope = ? AND identifier = ?')
         ->execute([$scope, mb_strtolower(trim($dinhDanh), 'UTF-8')]);
}

function ve_thong_bao_khoa(int $giay): string
{
    return 'Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau ' . max(1, (int) ceil($giay / 60)) . ' phút.';
}

/* ---------- 4. MÃ CHỐNG GIẢ MẠO FORM (CSRF) ---------- */
function ve_csrf(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function ve_csrf_hop_le(): bool
{
    return hash_equals(ve_csrf(), (string) ($_POST['csrf_token'] ?? ''));
}

/** Truy cập từ chính máy chủ (môi trường phát triển localhost). */
function ve_la_localhost(): bool
{
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}

/* ---------- 5. ẢNH ĐẠI DIỆN GIA SƯ ---------- */
/**
 * Đổi giá trị cột tutors.avatar (dạng "uploads/avatars/xxx.jpg", lưu trong thư mục gia-su/)
 * thành đường dẫn hiển thị đúng cho trang đang chạy: trong gia-su/ trả "uploads/...",
 * ở thư mục gốc (admin.php, index.php...) trả "gia-su/uploads/...".
 * Giá trị không hợp lệ hoặc file đã bị xóa -> null (trang hiện icon mặc định).
 */
function ve_url_anh_gia_su(?string $duongDan): ?string
{
    if (!$duongDan || !preg_match('#^uploads/avatars/[a-f0-9]{8,64}\.(jpg|png|webp)$#', $duongDan)) return null;
    if (!is_file(__DIR__ . '/gia-su/' . $duongDan)) return null;
    $trongGiaSu = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__DIR__ . '/gia-su');
    return ($trongGiaSu ? '' : 'gia-su/') . $duongDan;
}

/** Thẻ <img> ảnh đại diện gia sư, hoặc vòng tròn icon khi chưa có ảnh. */
function ve_the_anh_gia_su(?string $duongDan, int $kichThuoc = 40, string $class = ''): string
{
    $url = ve_url_anh_gia_su($duongDan);
    $kieu = "width:{$kichThuoc}px;height:{$kichThuoc}px;border-radius:50%;flex-shrink:0;";
    if ($url) {
        return '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="Ảnh đại diện" class="' . $class
             . '" style="' . $kieu . 'object-fit:cover;border:1px solid #e3e3e3" loading="lazy">';
    }
    return '<span class="' . $class . '" style="' . $kieu . 'display:inline-flex;align-items:center;justify-content:center;'
         . 'background:#f1e6e8;color:#800020;font-size:' . (int) round($kichThuoc * 0.42) . 'px"><i class="fa-solid fa-user"></i></span>';
}

/* ---------- 6. THÔNG BÁO LỖI CSDL THÂN THIỆN ---------- */
/**
 * Ghi chi tiết lỗi vào error log (cho lập trình viên) và trả về câu thông báo dễ hiểu cho người dùng,
 * KHÔNG để lộ tên bảng / cột / câu SQL ra giao diện.
 * $viec: việc đang làm, ví dụ "tải dữ liệu", "lưu hồ sơ".
 */
function ve_loi_csdl(Throwable $e, string $viec = 'thực hiện thao tác'): string
{
    error_log('[VietEdu] ' . ($_SERVER['SCRIPT_NAME'] ?? 'cli') . ' – ' . $viec . ': ' . $e->getMessage());
    if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
        return stripos($e->getMessage(), 'Duplicate') !== false
            ? "Không thể $viec: dữ liệu bị trùng (số điện thoại, email hoặc mã đã tồn tại)."
            : "Không thể $viec: dữ liệu này đang được dùng ở nơi khác (ví dụ đã có lớp học hoặc giao dịch liên quan).";
    }
    return "Không thể $viec do lỗi hệ thống. Vui lòng thử lại sau.";
}
