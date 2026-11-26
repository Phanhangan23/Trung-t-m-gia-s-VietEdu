<?php
require_once 'db.php';
session_start();
require_once 'phan-quyen.php';

// Ai được xem mục tickets thì được phản hồi (Admin và Tư vấn viên)
if (!coQuyen('tickets')) {
    http_response_code(403);
    exit('Bạn không có quyền thực hiện thao tác này.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php?view=tickets');
    exit;
}

$ticketId  = (int) ($_POST['ticket_id'] ?? 0);
$quayVeUrl = 'admin.php?view=tickets&id=' . $ticketId;

function quayVe(string $url, string $loai, string $noiDung): void
{
    $_SESSION['flash'] = ['loai' => $loai, 'nd' => $noiDung];
    header('Location: ' . $url);
    exit;
}

if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    quayVe($quayVeUrl, 'loi', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
}

$noiDung = trim($_POST['content'] ?? '');
if ($noiDung === '' || mb_strlen($noiDung) > 2000) {
    quayVe($quayVeUrl, 'loi', 'Nội dung phản hồi phải từ 1 đến 2000 ký tự.');
}

try {
    $st = $conn->prepare('SELECT status FROM support_tickets WHERE ticket_id = ?');
    $st->execute([$ticketId]);
    $trangThai = $st->fetchColumn();
    if ($trangThai === false) {
        quayVe('admin.php?view=tickets', 'loi', 'Không tìm thấy phiếu hỗ trợ này.');
    }

    $conn->beginTransaction();

    $conn->prepare('INSERT INTO ticket_replies (ticket_id, staff_id, content) VALUES (?, ?, ?)')
         ->execute([$ticketId, (int) $_SESSION['staff_id'], $noiDung]);

    // Ticket đang "Mới" mà đã có người trả lời thì chuyển sang "Đang xử lý"
    if ($trangThai === 'open') {
        $conn->prepare("UPDATE support_tickets SET status = 'processing' WHERE ticket_id = ?")
             ->execute([$ticketId]);
    }

    $conn->commit();
    quayVe($quayVeUrl, 'ok', 'Đã gửi phản hồi cho phiếu hỗ trợ #' . $ticketId . '.');
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Lỗi gửi phản hồi phiếu hỗ trợ: ' . $e->getMessage());
    quayVe($quayVeUrl, 'loi', 'Không thể gửi lúc này, vui lòng thử lại sau.');
}