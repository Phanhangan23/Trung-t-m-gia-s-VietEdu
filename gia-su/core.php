<?php
/**
 * LÕI DÙNG CHUNG CHO KHU VỰC GIA SƯ
 * - Kết nối CSDL, session, CSRF, thông báo flash, đăng nhập gia sư.
 * - Tự bổ sung các cột CSDL còn thiếu cho chức năng gia sư (chạy 1 lần mỗi phiên).
 * - Khung giao diện (header + menu gia sư + footer).
 */
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Ho_Chi_Minh');

/* ---------- CẤU HÌNH ---------- */
const GS_FEE_RATE      = 0.30;          // Phí nhận lớp = 30% học phí tháng đầu
const GS_PAY_HOURS     = 24;            // Thời hạn thanh toán phí nhận lớp
const GS_UPLOAD_DIR    = __DIR__ . '/uploads';
// Tài khoản ngân hàng lấy từ cấu hình chung (cau-hinh.php) để cổng gia sư, phụ huynh và admin luôn khớp nhau
const GS_BANK = VE_NGAN_HANG;
const GS_SUBJECTS = ['Toán', 'Tiếng Việt', 'Ngữ Văn', 'Tiếng Anh', 'Vật Lý', 'Hóa Học', 'Sinh Học',
                     'Lịch Sử', 'Địa Lý', 'Tin học', 'IELTS', 'Luyện Chữ', 'Báo bài'];
const GS_AREAS = ['Ba Đình', 'Hoàn Kiếm', 'Tây Hồ', 'Long Biên', 'Cầu Giấy', 'Đống Đa', 'Hai Bà Trưng',
                  'Hoàng Mai', 'Thanh Xuân', 'Nam Từ Liêm', 'Bắc Từ Liêm', 'Hà Đông'];

/* ---------- TIỆN ÍCH ---------- */
/** Tiền tố đường dẫn về thư mục gốc website: '../' khi trang nằm trong thư mục gia-su/, '' khi ở gốc. */
function gs_root()
{
    return realpath(dirname($_SERVER['SCRIPT_FILENAME'])) === realpath(__DIR__) ? '../' : '';
}

function gs_e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function gs_money($v)
{
    return $v === null || $v === '' ? '—' : number_format((float) $v, 0, ',', '.') . ' đ';
}

function gs_date($v, $fmt = 'd/m/Y')
{
    return $v ? date($fmt, strtotime($v)) : '—';
}

function gs_grade($g)
{
    return ctype_digit((string) $g) ? 'Lớp ' . $g : (string) $g;
}

function gs_len($s)
{
    return mb_strlen((string) $s, 'UTF-8');
}

function gs_valid_date($s)
{
    $d = DateTime::createFromFormat('Y-m-d', (string) $s);
    return $d && $d->format('Y-m-d') === $s;
}

function gs_redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function gs_flash($type, $msg)
{
    $_SESSION['gs_flash'][] = ['type' => $type, 'msg' => $msg];
}

/* ---------- CSRF ---------- */
function gs_csrf()
{
    if (empty($_SESSION['gs_csrf'])) {
        $_SESSION['gs_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['gs_csrf'];
}

function gs_csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . gs_e(gs_csrf()) . '">';
}

/** Gọi đầu mỗi xử lý POST. Sai token -> báo lỗi và quay lại trang. */
function gs_check_csrf()
{
    if (!hash_equals(gs_csrf(), (string) ($_POST['csrf'] ?? ''))) {
        gs_flash('error', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
        gs_redirect($_SERVER['REQUEST_URI']);
    }
}

/* ---------- BỔ SUNG CỘT CSDL CÒN THIẾU ---------- */
function gs_ensure_schema(PDO $conn)
{
    if (($_SESSION['gs_schema'] ?? 0) === 1) return;
    $need = [
        'tutors' => [
            'avatar'          => 'VARCHAR(255) NULL',
            'birth_date'      => 'DATE NULL',
            'gender'          => "ENUM('male','female','other') NULL",
            'subjects'        => 'VARCHAR(255) NULL',
            'teaching_areas'  => 'VARCHAR(255) NULL',
            'grade_min'       => 'TINYINT NULL',
            'grade_max'       => 'TINYINT NULL',
            'experience_years'=> 'TINYINT NULL',
            'experience_note' => 'TEXT NULL',
            'expected_fee'    => 'DECIMAL(10,2) NULL',
        ],
        'kyc_profiles'    => ['review_note' => 'TEXT NULL'],
        'transactions'    => ['paid_confirmed_at' => 'DATETIME NULL'],
    ];
    try {
        $st = $conn->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        foreach ($need as $table => $cols) {
            $st->execute([$table]);
            $have = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
            foreach ($cols as $col => $def) {
                if (!isset($have[$col])) {
                    $conn->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
                }
            }
        }
        $_SESSION['gs_schema'] = 1;
    } catch (PDOException $e) {
        error_log('gs_ensure_schema: ' . $e->getMessage());
    }
}
gs_ensure_schema($conn);

/* ---------- ĐĂNG NHẬP GIA SƯ ---------- */
function gs_current_tutor()
{
    global $conn;
    static $cache = null;
    if ($cache !== null) return $cache ?: null;
    $cache = false;
    // Đăng nhập qua trang chung (dang-nhap-nguoi-dung.php) chỉ có user_type/user_id
    if (empty($_SESSION['tutor_id']) && ($_SESSION['user_type'] ?? '') === 'tutor' && !empty($_SESSION['user_id'])) {
        $_SESSION['tutor_id'] = (int) $_SESSION['user_id'];
    }
    if (!empty($_SESSION['tutor_id'])) {
        $st = $conn->prepare('SELECT * FROM tutors WHERE tutor_id = ?');
        $st->execute([(int) $_SESSION['tutor_id']]);
        $t = $st->fetch(PDO::FETCH_ASSOC);
        if ($t && $t['status'] !== 'locked') {
            $cache = $t;
            // Đồng bộ với header chung (hiện tên + nút Đăng xuất)
            $_SESSION['user_type'] = 'tutor';
            $_SESSION['user_id']   = (int) $t['tutor_id'];
            $_SESSION['user_name'] = $t['full_name'];
        } else {
            unset($_SESSION['tutor_id'], $_SESSION['user_type'], $_SESSION['user_id'], $_SESSION['user_name']);
        }
    }
    return $cache ?: null;
}

function gs_require_login()
{
    $t = gs_current_tutor();
    if (!$t) {
        gs_flash('error', 'Vui lòng đăng nhập tài khoản gia sư để tiếp tục.');
        gs_redirect('dang-nhap.php?next=' . rawurlencode(basename($_SERVER['REQUEST_URI'])));
    }
    return $t;
}

/** Trạng thái KYC hiển thị cho gia sư: none | pending | approved | rejected (cần bổ sung). */
function gs_kyc(PDO $conn, $tutorId)
{
    $st = $conn->prepare('SELECT * FROM kyc_profiles WHERE tutor_id = ? ORDER BY kyc_id DESC LIMIT 1');
    $st->execute([$tutorId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/* ---------- NHÃN TRẠNG THÁI ---------- */
const GS_APPLY_LABELS = [
    'applied'      => ['Chờ phản hồi', 'gs-b-warn'],
    'interviewing' => ['Chờ phản hồi (đang phỏng vấn)', 'gs-b-info'],
    'suggested'    => ['Được đề xuất', 'gs-b-info'],
    'matched'      => ['Được chọn', 'gs-b-ok'],
    'rejected'     => ['Không được chọn', 'gs-b-bad'],
    'cancelled'    => ['Đã hủy', 'gs-b-bad'],
];
const GS_KYC_LABELS = [
    'none'     => ['Chưa gửi', 'gs-b-muted'],
    'pending'  => ['Đang chờ xác minh', 'gs-b-warn'],
    'approved' => ['Đã xác minh', 'gs-b-ok'],
    'rejected' => ['Cần bổ sung thông tin', 'gs-b-bad'],
];
const GS_CLASS_LABELS = [
    'pending'   => ['Chờ duyệt', 'gs-b-muted'],
    'finding'   => ['Đang tìm gia sư', 'gs-b-info'],
    'matched'   => ['Đang dạy', 'gs-b-ok'],
    'completed' => ['Đã kết thúc', 'gs-b-muted'],
    'cancelled' => ['Đã hủy', 'gs-b-bad'],
    'rejected'  => ['Bị từ chối', 'gs-b-bad'],
];
const GS_TICKET_LABELS = [
    'open'       => [VE_NHAN_TICKET['open'], 'gs-b-warn'],
    'processing' => [VE_NHAN_TICKET['processing'], 'gs-b-info'],
    'resolved'   => [VE_NHAN_TICKET['resolved'], 'gs-b-ok'],
];

function gs_badge(array $map, $key)
{
    $l = $map[$key] ?? [(string) $key, 'gs-b-muted'];
    return '<span class="gs-badge ' . $l[1] . '">' . gs_e($l[0]) . '</span>';
}

/* ---------- UPLOAD FILE ---------- */
/**
 * Lưu file upload an toàn (kiểm tra MIME thật, dung lượng, đổi tên ngẫu nhiên).
 * Trả về [đường dẫn tương đối | null, lỗi | null]. Không chọn file -> [null, null].
 */
function gs_upload($field, $subdir, array $allowed, $maxMb)
{
    $f = $_FILES[$field] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($f['error'] !== UPLOAD_ERR_OK) return [null, 'Tải file thất bại (mã lỗi ' . $f['error'] . ').'];
    if ($f['size'] > $maxMb * 1024 * 1024) return [null, "File vượt quá {$maxMb}MB."];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($allowed[$mime])) return [null, 'Định dạng không hợp lệ (chỉ nhận ' . implode(', ', array_unique($allowed)) . ').'];

    $dir = GS_UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) return [null, 'Không lưu được file.'];
    return ['uploads/' . $subdir . '/' . $name, null];
}

/* ---------- LIÊN HỆ PHỤ HUYNH (mở khóa khi đã thanh toán phí nhận lớp) ---------- */
/**
 * Các lớp gia sư đã được giao VÀ có giao dịch phí nhận lớp thành công -> trả kèm thông tin phụ huynh.
 * Trả về mảng [class_id => dòng]. Truyền $classId để chỉ lấy 1 lớp.
 */
function gs_lop_da_mo_lien_he(PDO $conn, int $tutorId, ?int $classId = null): array
{
    $sql = "SELECT c.class_id, c.class_code, c.title, c.subject, c.grade, c.location, c.schedule, c.status AS class_status,
                   p.full_name AS parent_name, p.phone AS parent_phone, p.email AS parent_email, p.address AS parent_address,
                   t.transaction_id, t.tx_code, t.amount, t.tx_time, t.paid_confirmed_at
            FROM transactions t
            JOIN classes c        ON c.class_id = t.class_id
            JOIN class_matching m ON m.class_id = t.class_id AND m.tutor_id = t.tutor_id AND m.status = 'matched'
            LEFT JOIN parents p   ON p.parent_id = c.parent_id
            WHERE t.tutor_id = ? AND t.status = 'success'" . ($classId ? ' AND t.class_id = ?' : '') . "
            ORDER BY t.transaction_id DESC";
    $st = $conn->prepare($sql);
    $st->execute($classId ? [$tutorId, $classId] : [$tutorId]);
    $ds = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ds[$r['class_id']] ??= $r;      // giữ giao dịch thành công mới nhất của mỗi lớp
    }
    return $ds;
}

/** Khung liên hệ phụ huynh: tên, SĐT lớn kèm nút Gọi / Zalo / Sao chép, email, địa chỉ. */
function gs_the_lien_he(array $r): string
{
    $sdt  = preg_replace('/\D/', '', (string) $r['parent_phone']);
    $html = '<div class="gs-contact">'
          . '<div class="gs-contact-head"><i class="fa-solid fa-user"></i> <strong>' . gs_e($r['parent_name'] ?: 'Phụ huynh') . '</strong></div>';
    if ($sdt !== '') {
        $html .= '<div class="gs-contact-phone"><i class="fa-solid fa-phone"></i> <span class="gs-phone-num">' . gs_e($r['parent_phone']) . '</span></div>'
               . '<div class="gs-contact-actions">'
               . '<a class="gs-btn sm" href="tel:' . gs_e($sdt) . '"><i class="fa-solid fa-phone"></i> Gọi ngay</a>'
               . '<a class="gs-btn outline sm" href="https://zalo.me/' . gs_e($sdt) . '" target="_blank" rel="noopener"><i class="fa-solid fa-comment-dots"></i> Nhắn Zalo</a>'
               . '<button type="button" class="gs-btn outline sm" data-copy="' . gs_e($sdt) . '"><i class="fa-regular fa-copy"></i> Sao chép số</button>'
               . '</div>';
    } else {
        $html .= '<p class="gs-muted">Phụ huynh chưa cập nhật số điện thoại, vui lòng liên hệ trung tâm.</p>';
    }
    if (!empty($r['parent_email']))   $html .= '<div class="gs-muted"><i class="fa-solid fa-envelope"></i> ' . gs_e($r['parent_email']) . '</div>';
    if (!empty($r['parent_address'])) $html .= '<div class="gs-muted"><i class="fa-solid fa-location-dot"></i> ' . gs_e($r['parent_address']) . '</div>';
    return $html . '</div>';
}

/* ---------- LỊCH HỌC: tách ngày trong tuần từ chuỗi "3 buổi/tuần (Tối T2-T4-T6)" ---------- */
function gs_parse_schedule($text)
{
    $t = mb_strtolower((string) $text, 'UTF-8');
    $days = [];
    if (preg_match_all('/(?:\bt|thứ\s*)([2-7])\b/u', $t, $m)) {
        foreach ($m[1] as $d) $days[] = (int) $d - 1;        // T2 -> 1 (thứ Hai, ISO-8601)
    }
    if (preg_match('/\bcn\b|chủ nhật/u', $t)) $days[] = 7;
    $days = array_values(array_unique($days));
    sort($days);

    if (preg_match('/(\d{1,2})[h:](\d{2})?\s*[-–]\s*(\d{1,2})[h:](\d{2})?/u', $t, $tm)) {
        $time = sprintf('%02d:%02d - %02d:%02d', (int) $tm[1], (int) ($tm[2] ?? 0), (int) $tm[3], (int) ($tm[4] ?? 0));
    } elseif (strpos($t, 'sáng') !== false) {
        $time = 'Sáng (08:00 - 10:00)';
    } elseif (strpos($t, 'chiều') !== false) {
        $time = 'Chiều (14:00 - 16:00)';
    } elseif (strpos($t, 'tối') !== false) {
        $time = 'Tối (19:00 - 21:00)';
    } else {
        $time = 'Giờ theo thỏa thuận';
    }
    return ['days' => $days, 'time' => $time];
}

/* ---------- KHUNG GIAO DIỆN ---------- */
const GS_MENU = [
    'ho-so'      => ['ho-so.php', 'fa-user', 'Hồ sơ gia sư'],
    'kyc'        => ['kyc.php', 'fa-id-card', 'Xác minh hồ sơ'],
    'bang-tin'   => ['../index.php#bang-tin', 'fa-border-all', 'Bảng tin lớp trống'],
    'ung-tuyen'  => ['ung-tuyen.php', 'fa-paper-plane', 'Lớp đã ứng tuyển'],
    'lop-cua-toi'=> ['lop-cua-toi.php', 'fa-chalkboard-user', 'Lớp học của tôi'],
    'thanh-toan' => ['thanh-toan.php', 'fa-qrcode', 'Thanh toán phí nhận lớp'],
    'ho-tro'     => ['ho-tro.php', 'fa-headset', 'Hỗ trợ / Khiếu nại'],
];

function gs_layout_start($title, $active, $tutor)
{
    global $pageTitle;
    $pageTitle = $title . ' - VietEdu';
    require __DIR__ . '/../header.php';
    $flashes = $_SESSION['gs_flash'] ?? [];
    unset($_SESSION['gs_flash']);
    ?>
    <link rel="stylesheet" href="gia-su.css">
    <div class="main-layout">
        <aside class="sidebar">
            <div class="gs-me">
                <?= ve_the_anh_gia_su($tutor['avatar'] ?? null, 48) ?>
                <div>
                    <strong><?= gs_e($tutor['full_name']) ?></strong>
                    <div class="gs-muted"><?= $tutor['status'] === 'active' ? '<i class="fa-solid fa-circle-check" style="color:#2E7D32"></i> Đã kích hoạt' : '<i class="fa-solid fa-hourglass-half"></i> Chờ kích hoạt' ?></div>
                </div>
            </div>
            <ul class="sidebar-menu">
                <?php foreach (GS_MENU as $key => $m): ?>
                    <li><a href="<?= $m[0] ?>" class="<?= $key === $active ? 'active' : '' ?>"><i class="fa-solid <?= $m[1] ?>"></i> <?= $m[2] ?></a></li>
                <?php endforeach; ?>
            </ul>
        </aside>
        <main class="content-area">
            <div class="section-header"><h2 class="section-title"><?= gs_e($title) ?></h2></div>
            <?php foreach ($flashes as $f): ?>
                <div class="gs-alert gs-alert-<?= $f['type'] === 'error' ? 'error' : 'ok' ?>"><?= gs_e($f['msg']) ?></div>
            <?php endforeach; ?>
    <?php
}

function gs_layout_end()
{
    echo "        </main>\n    </div>\n";
    // Nút "Sao chép số" trong khung liên hệ phụ huynh
    echo <<<'JS'
<script>
document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (!b) return;
    var text = b.getAttribute('data-copy'), cu = b.innerHTML;
    var xong = function () { b.innerHTML = '<i class="fa-solid fa-check"></i> Đã sao chép'; setTimeout(function () { b.innerHTML = cu; }, 1800); };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(xong, function () { window.prompt('Sao chép số điện thoại:', text); });
    } else {
        var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); xong(); } catch (err) { window.prompt('Sao chép số điện thoại:', text); }
        document.body.removeChild(t);
    }
});
</script>
JS;
    require __DIR__ . '/../footer.php';
}