<?php
/**
 * THÔNG BÁO CHO GIA SƯ — lớp đã được duyệt (admin bấm "Giao lớp") nhưng chưa thanh toán.
 * Dùng bởi header.php để hiện chuông thông báo.
 *
 * "Đã được duyệt" dùng ĐÚNG điều kiện của thanh-toan.php:
 *     class_matching.status = 'matched'  VÀ  classes.status = 'matched'
 * Thông báo tự biến mất khi giao dịch của lớp đó thành công, nên không cần thêm bảng dữ liệu nào.
 */

/** Lấy kết nối PDO: ưu tiên $conn toàn cục, nếu chưa có thì tự mở bằng db.php. */
function gs_tb_lay_ket_noi(): ?PDO
{
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO) {
        return $GLOBALS['conn'];
    }
    try {
        require __DIR__ . '/db.php';   // tạo biến $conn cục bộ trong hàm này
        return (isset($conn) && $conn instanceof PDO) ? $conn : null;
    } catch (Throwable $e) {
        return null;
    }
}

/** 19800 giây -> "5 giờ 30 phút"; dưới 1 giờ -> "45 phút". */
function gs_tb_con_lai(int $giay): string
{
    $gio  = intdiv($giay, 3600);
    $phut = max(1, intdiv($giay % 3600, 60));
    return $gio > 0 ? $gio . ' giờ ' . $phut . ' phút' : $phut . ' phút';
}

/**
 * Từ các dòng truy vấn (lớp đã giao + giao dịch gần nhất) tạo danh sách thông báo.
 * Tách riêng, không đụng database, để dễ kiểm tra.
 */
function gs_tb_tao_thong_bao(array $hang): array
{
    $ds = [];
    foreach ($hang as $r) {
        $tt = (string)($r['tx_status'] ?? '');
        if ($tt === 'success') {
            continue;                                   // đã thanh toán xong
        }
        if ($tt === 'pending_24h' && !empty($r['paid_confirmed_at'])) {
            continue;                                   // đã báo chuyển khoản, chờ đối soát
        }
        $giayConLai = (int)($r['seconds_left'] ?? 0);
        $hetHan = in_array($tt, ['failed', 'expired'], true) || ($tt === 'pending_24h' && $giayConLai <= 0);

        $ds[] = [
            'class_code' => $r['class_code'],
            'title'      => $r['title'],
            'tx_code'    => (string)($r['tx_code'] ?? ''),
            'het_han'    => $hetHan,
            'con_lai'    => ($tt === 'pending_24h' && !$hetHan) ? gs_tb_con_lai($giayConLai) : null,
        ];
    }
    return $ds;
}

/**
 * Danh sách lớp đã được duyệt cho gia sư $tutorId mà CHƯA thanh toán xong.
 * Mỗi phần tử: class_code, title, tx_code (có thể rỗng), het_han (bool), con_lai (chuỗi hoặc null).
 * Gia sư đã bấm "Tôi đã chuyển khoản" (chờ trung tâm đối soát) thì không còn hiện thông báo.
 * Lỗi truy vấn không làm hỏng trang: chỉ ghi log và trả về danh sách rỗng.
 */
function gs_tb_lop_can_thanh_toan(int $tutorId): array
{
    if ($tutorId <= 0) {
        return [];
    }
    $conn = gs_tb_lay_ket_noi();
    if (!$conn) {
        return [];
    }
    $gio = defined('GS_PAY_HOURS') ? (int)GS_PAY_HOURS : 24;

    try {
        $st = $conn->prepare(
            "SELECT c.class_code, c.title,
                    t.tx_code, t.status AS tx_status, t.paid_confirmed_at,
                    TIMESTAMPDIFF(SECOND, NOW(), t.tx_time + INTERVAL " . $gio . " HOUR) AS seconds_left
             FROM class_matching m
             JOIN classes c ON c.class_id = m.class_id
             LEFT JOIN transactions t ON t.transaction_id = (
                 SELECT x.transaction_id FROM transactions x
                 WHERE x.class_id = c.class_id AND x.tutor_id = m.tutor_id
                 ORDER BY x.transaction_id DESC LIMIT 1)
             WHERE m.tutor_id = ? AND m.status = 'matched' AND c.status = 'matched'
             ORDER BY m.apply_date DESC"
        );
        $st->execute([$tutorId]);

        return gs_tb_tao_thong_bao($st->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        error_log('[VietEdu thông báo gia sư] ' . $e->getMessage());
        return [];
    }
}

/**
 * Lớp vừa thanh toán phí nhận lớp THÀNH CÔNG trong $ngay ngày gần đây -> báo gia sư kèm SĐT phụ huynh.
 * Mỗi phần tử: class_code, title, parent_name, parent_phone.
 */
function gs_tb_lop_vua_thanh_toan(int $tutorId, int $ngay = 7): array
{
    if ($tutorId <= 0) {
        return [];
    }
    $conn = gs_tb_lay_ket_noi();
    if (!$conn) {
        return [];
    }
    try {
        $st = $conn->prepare(
            "SELECT c.class_id, c.class_code, c.title, p.full_name AS parent_name, p.phone AS parent_phone
             FROM transactions t
             JOIN classes c        ON c.class_id = t.class_id
             JOIN class_matching m ON m.class_id = t.class_id AND m.tutor_id = t.tutor_id AND m.status = 'matched'
             LEFT JOIN parents p   ON p.parent_id = c.parent_id
             WHERE t.tutor_id = ? AND t.status = 'success'
               AND COALESCE(t.paid_confirmed_at, t.tx_time) >= NOW() - INTERVAL " . max(1, $ngay) . " DAY
             ORDER BY COALESCE(t.paid_confirmed_at, t.tx_time) DESC"
        );
        $st->execute([$tutorId]);
        $ds = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ds[$r['class_id']] ??= $r;   // mỗi lớp 1 thông báo
        }
        return array_values($ds);
    } catch (Throwable $e) {
        error_log('[VietEdu thông báo gia sư] ' . $e->getMessage());
        return [];
    }
}
