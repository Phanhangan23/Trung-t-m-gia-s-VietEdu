<?php
$parent_id = (int) ($_SESSION['parent_id'] ?? 0);
// Việc xác nhận đã nhận tiền (gạch nợ) chỉ do nhân viên thực hiện ở trang quản trị.
$nhanGiaoDich = [
    'pending_24h' => [VE_NHAN_GIAO_DICH['pending_24h'], 'badge-warning'],
    'success'     => [VE_NHAN_GIAO_DICH['success'], 'badge-success'],
    'failed'      => [VE_NHAN_GIAO_DICH['failed'], 'badge-danger'],
];
$st =$conn->prepare("SELECT t.*, c.class_code, c.title FROM transactions t JOIN classes c ON c.class_id=t.class_id WHERE c.parent_id=? ORDER BY t.transaction_id DESC");
$st->execute([$parent_id]);
$txs = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="section-title"><span>6. Trang Thanh Toán Học Phí & Mã VietQR</span></div>
<table class="custom-table" style="width:100%; border-collapse:collapse;">
    <thead><tr style="background:#fdf2f4;"><th style="padding:10px;">Mã GD</th><th style="padding:10px;">Lớp Học</th><th style="padding:10px;">Số Tiền</th><th style="padding:10px;">VietQR</th><th style="padding:10px;">Trạng Thái</th></tr></thead>
    <tbody>
        <?php if ($txs): foreach ($txs as $tx):
            $qr = ve_link_qr((float) $tx['amount'], $tx['tx_code']);
        ?>
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:10px;"><strong><?= htmlspecialchars($tx['tx_code']) ?></strong></td>
                <td style="padding:10px;"><?= htmlspecialchars($tx['class_code']) ?></td>
                <td style="padding:10px; color:var(--primary-red); font-weight:bold;"><?= number_format($tx['amount'], 0, ',', '.') ?> đ</td>
                <td style="padding:10px;"><a href="<?= $qr ?>" target="_blank" class="btn-search" style="padding:5px 8px; font-size:12px;">Xem mã QR</a></td>
                <td style="padding:10px;"><?php [$nhan, $mau] = $nhanGiaoDich[$tx['status']] ?? [$tx['status'], 'badge-warning']; ?><span class="badge <?= $mau ?>"><?= htmlspecialchars($nhan) ?></span></td>
            </tr>
        <?php endforeach; else: ?><tr><td colspan="5" style="padding:15px; text-align:center;">Chưa có giao dịch nào.</td></tr><?php endif; ?>
    </tbody>
</table>