<?php
/**
 * XEM ẢNH MINH CHỨNG KYC CHO NHÂN VIÊN
 * Ảnh CCCD / thẻ SV nằm trong gia-su/uploads/kyc/ và bị .htaccess chặn truy cập trực tiếp,
 * nên admin xem qua trang này: kiểm tra đăng nhập + quyền 'kyc', tra đường dẫn theo mã hồ sơ
 * (không nhận đường dẫn từ URL), chỉ trả file nằm đúng trong thư mục KYC.
 *
 *   xem-anh-kyc.php?id=7&loai=id_card_front
 */
require_once __DIR__ . '/db.php';
session_start();
require_once __DIR__ . '/phan-quyen.php';

const KYC_LOAI_ANH = ['id_card_front', 'id_card_back', 'student_card', 'degree_cert'];

function kyc_tu_choi(int $ma, string $lyDo)
{
    http_response_code($ma);
    header('Content-Type: text/plain; charset=utf-8');
    exit($lyDo);
}

if (empty($_SESSION['staff_id'])) kyc_tu_choi(403, 'Vui lòng đăng nhập tài khoản nhân viên.');
if (!coQuyen('kyc'))              kyc_tu_choi(403, 'Bạn không có quyền xem hồ sơ xác minh.');

$id   = (int) ($_GET['id'] ?? 0);
$loai = (string) ($_GET['loai'] ?? '');
if ($id <= 0 || !in_array($loai, KYC_LOAI_ANH, true)) kyc_tu_choi(400, 'Yêu cầu không hợp lệ.');

try {
    $st = $conn->prepare("SELECT `$loai` FROM kyc_profiles WHERE kyc_id = ?");
    $st->execute([$id]);
    $duongDan = (string) $st->fetchColumn();
} catch (PDOException $e) {
    error_log('xem-anh-kyc: ' . $e->getMessage());
    kyc_tu_choi(500, 'Không tải được ảnh.');
}
if ($duongDan === '') kyc_tu_choi(404, 'Hồ sơ chưa có ảnh này.');

// Đường dẫn trong CSDL dạng "uploads/kyc/xxx.png" (tính từ thư mục gia-su/)
$thuMuc = realpath(__DIR__ . '/gia-su/uploads/kyc');
$file   = realpath(__DIR__ . '/gia-su/' . $duongDan);
if (!$thuMuc || !$file || strpos($file, $thuMuc . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
    kyc_tu_choi(404, 'Không tìm thấy file ảnh.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) kyc_tu_choi(415, 'Định dạng file không được hỗ trợ.');

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=600');   // giấy tờ cá nhân: chỉ lưu tạm trên máy người xem
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline');
readfile($file);
