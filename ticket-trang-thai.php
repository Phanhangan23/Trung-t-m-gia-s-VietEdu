<?php
require_once 'db.php';
session_start();
require_once 'phan-quyen.php';

// Ai được xem mục tickets thì được đổi trạng thái (Admin và Tư vấn viên)
if (!coQuyen('tickets')) {
    http_response_code(403);
    exit('Bạn không có quyền thực hiện thao tác này.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php?view=tickets');
    exit;
}

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$quayVeUrl = 'admin.php?view=tickets&id=' . $ticketId;   // quay lại đúng phiếu hỗ trợ đang xem

function quayVe(string $url, string $loai, string $noiDung): void
{
    $_SESSION['flash'] = ['loai' => $loai, 'nd' => $noiDung];
    header('Location: ' . $url);
    exit;
}

if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    quayVe($quayVeUrl, 'loi', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
}

// Chỉ chấp nhận 3 trạng thái có trong database
$trangThai = $_POST['status'] ?? '';
$nhan = VE_NHAN_TICKET;
if (!isset($nhan[$trangThai])) {
    quayVe($quayVeUrl, 'loi', 'Trạng thái không hợp lệ.');
}

try {
    $st = $conn->prepare('SELECT COUNT(*) FROM support_tickets WHERE ticket_id = ?');
    $st->execute([$ticketId]);
    if (!$st->fetchColumn()) {
        quayVe('admin.php?view=tickets', 'loi', 'Không tìm thấy phiếu hỗ trợ này.');
    }

    $conn->prepare('UPDATE support_tickets SET status = ? WHERE ticket_id = ?')
         ->execute([$trangThai, $ticketId]);
    quayVe($quayVeUrl, 'ok', 'Đã chuyển phiếu hỗ trợ #' . $ticketId . ' sang trạng thái "' . $nhan[$trangThai] . '".');
} catch (PDOException $e) {
    error_log('Lỗi đổi trạng thái phiếu hỗ trợ: ' . $e->getMessage());
    quayVe($quayVeUrl, 'loi', 'Không thể lưu lúc này, vui lòng thử lại sau.');
}