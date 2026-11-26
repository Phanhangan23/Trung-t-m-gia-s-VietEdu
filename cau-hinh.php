<?php
/**
 * CẤU HÌNH DÙNG CHUNG TOÀN WEBSITE (được nạp tự động qua db.php)
 * Sửa thông tin ở ĐÂY là cả 3 nơi (cổng gia sư, trang phụ huynh, admin) cùng đổi theo.
 */

/**
 * Tài khoản nhận tiền phí nhận lớp (hiển thị + tạo mã VietQR).
 * TODO trước khi chạy thật: thay bằng tài khoản ngân hàng thật của trung tâm.
 *   bin: mã ngân hàng theo chuẩn VietQR (Vietcombank 970436, MB 970422, Techcombank 970407, ...)
 */
const VE_NGAN_HANG = [
    'bin'       => '970436',
    'bank_name' => 'Vietcombank',
    'account'   => '0011001234567',
    'owner'     => 'TRUNG TAM GIA SU VIETEDU',
];

/** Nội dung chuyển khoản chuẩn để đối soát: "#TX-9081" -> "VIETEDU TX9081" (chỉ chữ + số, ngân hàng nào cũng giữ nguyên). */
function ve_noi_dung_ck(string $maGiaoDich): string
{
    return 'VIETEDU ' . preg_replace('/[^A-Za-z0-9]/', '', $maGiaoDich);
}

/** Link ảnh mã VietQR cho 1 giao dịch. */
function ve_link_qr(float $soTien, string $maGiaoDich): string
{
    return 'https://img.vietqr.io/image/' . VE_NGAN_HANG['bin'] . '-' . VE_NGAN_HANG['account'] . '-compact2.png?'
         . http_build_query([
               'amount'      => (int) $soTien,
               'addInfo'     => ve_noi_dung_ck($maGiaoDich),
               'accountName' => VE_NGAN_HANG['owner'],
           ]);
}

/* Tên trạng thái hiển thị – DÙNG CHUNG để mọi trang (admin, phụ huynh, gia sư) gọi giống nhau */
const VE_NHAN_GIAO_DICH = [
    'success'     => 'Đã thanh toán',
    'pending_24h' => 'Chờ thanh toán',
    'failed'      => 'Đã hủy / Hết hạn',
];
const VE_NHAN_TICKET = [
    'open'       => 'Chờ xử lý',
    'processing' => 'Đang xử lý',
    'resolved'   => 'Đã xử lý',
];
