<?php
/** 9. TRANG THANH TOÁN PHÍ NHẬN LỚP – VietQR, xác nhận đã chuyển khoản, lịch sử */
require_once __DIR__ . '/core.php';
$tutor = gs_require_login();
$tid = (int) $tutor['tutor_id'];

$txLabels = [
    'pending_24h' => [VE_NHAN_GIAO_DICH['pending_24h'], 'gs-b-warn'],
    'confirmed'   => ['Đã báo chuyển khoản – chờ xác nhận', 'gs-b-info'],
    'expired'     => ['Quá hạn 24h', 'gs-b-bad'],
    'success'     => [VE_NHAN_GIAO_DICH['success'], 'gs-b-ok'],
    'failed'      => [VE_NHAN_GIAO_DICH['failed'], 'gs-b-bad'],
];
/** Trạng thái hiển thị của 1 giao dịch. */
$txState = function (array $tx) {
    if ($tx['status'] !== 'pending_24h') return $tx['status'];
    if (!empty($tx['paid_confirmed_at'])) return 'confirmed';
    return strtotime($tx['tx_time']) + GS_PAY_HOURS * 3600 < time() ? 'expired' : 'pending_24h';
};
$feeOf = function ($tuition) {
    return max(0, round((float) $tuition * GS_FEE_RATE, -3));
};

// Kiểm tra nhanh (gọi bằng JS mỗi 15 giây): số giao dịch đã thành công của gia sư.
// Khi trung tâm xác nhận tiền về, con số tăng lên -> trang tự tải lại và hiện SĐT phụ huynh.
if (isset($_GET['kiem_tra'])) {
    $st = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE tutor_id = ? AND status = 'success'");
    $st->execute([$tid]);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['so_thanh_cong' => (int) $st->fetchColumn()]);
    exit;
}

// CHỈ lấy lớp đã được admin duyệt "Giao lớp" (class_matching = matched VÀ lớp = matched).
// Chỉ những lớp này mới có nút lấy mã / hiện QR thanh toán.
// Lớp + giao dịch gần nhất của từng lớp
$st = $conn->prepare("SELECT c.class_id, c.class_code, c.title, c.tuition_fee, m.apply_date,
                             t.transaction_id, t.tx_code, t.amount, t.tx_time, t.status AS tx_status, t.paid_confirmed_at
                      FROM class_matching m
                      JOIN classes c ON c.class_id = m.class_id
                      LEFT JOIN transactions t ON t.transaction_id = (
                          SELECT x.transaction_id FROM transactions x WHERE x.class_id = c.class_id AND x.tutor_id = m.tutor_id
                          ORDER BY x.transaction_id DESC LIMIT 1)
                      WHERE m.tutor_id = ? AND m.status = 'matched' AND c.status = 'matched'
                      ORDER BY m.apply_date DESC");
$st->execute([$tid]);
$items = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $items[$r['class_id']] = $r;

// Lớp đã ứng tuyển nhưng trung tâm CHƯA duyệt (chưa giao lớp) -> chưa thanh toán được, KHÔNG hiện QR
$st = $conn->prepare("SELECT c.class_code, c.title, m.status AS match_status
                      FROM class_matching m
                      JOIN classes c ON c.class_id = m.class_id
                      WHERE m.tutor_id = ? AND m.status IN ('applied', 'interviewing') AND c.status = 'finding'
                      ORDER BY m.apply_date DESC");
$st->execute([$tid]);
$waiting = $st->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    gs_check_csrf();
    $cid = (int) ($_POST['class_id'] ?? 0);
    $act = $_POST['action'] ?? '';
    $it = $items[$cid] ?? null;
    if (!$it) {
        gs_flash('error', 'Lớp này chưa được trung tâm duyệt (giao lớp) cho bạn nên chưa thể thanh toán.');
        gs_redirect('thanh-toan.php');
    }
    $tx = $it['transaction_id'] ? ['status' => $it['tx_status'], 'tx_time' => $it['tx_time'], 'paid_confirmed_at' => $it['paid_confirmed_at']] : null;
    $state = $tx ? $txState($tx) : null;

    if ($act === 'create' && (!$tx || in_array($state, ['expired', 'failed'], true))) {
        $amount = $feeOf($it['tuition_fee']);
        if ($amount <= 0) {
            gs_flash('error', 'Lớp chưa có học phí, vui lòng liên hệ trung tâm.');
            gs_redirect('thanh-toan.php');
        }
        $conn->beginTransaction();
        if ($state === 'expired') {
            $conn->prepare("UPDATE transactions SET status = 'failed' WHERE transaction_id = ?")->execute([$it['transaction_id']]);
        }
        $n = (int) $conn->query("SELECT MAX(CAST(SUBSTRING(tx_code, 5) AS UNSIGNED)) FROM transactions WHERE tx_code LIKE '#TX-%'")->fetchColumn();
        $code = '#TX-' . max(9001, $n + 1);
        $conn->prepare("INSERT INTO transactions (tx_code, class_id, tutor_id, amount, status) VALUES (?, ?, ?, ?, 'pending_24h')")
             ->execute([$code, $cid, $tid, $amount]);
        $conn->commit();
        gs_flash('ok', "Đã tạo mã thanh toán $code. Vui lòng chuyển khoản trong " . GS_PAY_HOURS . ' giờ.');
    } elseif ($act === 'confirm' && $state === 'pending_24h') {
        $conn->prepare('UPDATE transactions SET paid_confirmed_at = NOW() WHERE transaction_id = ? AND tutor_id = ?')
             ->execute([$it['transaction_id'], $tid]);
        gs_flash('ok', 'Đã ghi nhận bạn báo chuyển khoản. Trung tâm sẽ đối soát và xác nhận sớm nhất.');
    } else {
        gs_flash('error', 'Thao tác không hợp lệ với trạng thái thanh toán hiện tại.');
    }
    gs_redirect('thanh-toan.php');
}

// Lịch sử thanh toán
$st = $conn->prepare('SELECT t.*, c.class_code, c.title FROM transactions t JOIN classes c ON c.class_id = t.class_id WHERE t.tutor_id = ? ORDER BY t.tx_time DESC, t.transaction_id DESC');
$st->execute([$tid]);
$history = $st->fetchAll(PDO::FETCH_ASSOC);
$paidTotal = array_sum(array_map(function ($h) { return $h['status'] === 'success' ? (float) $h['amount'] : 0; }, $history));
$soThanhCong = count(array_filter($history, function ($h) { return $h['status'] === 'success'; }));

// Lớp đã thanh toán thành công -> mở thông tin liên hệ phụ huynh
$daMo = gs_lop_da_mo_lien_he($conn, $tid);
// Giao dịch thành công mà gia sư chưa xem lần nào -> đánh dấu "Mới" và báo nổi bật
$daXem = $_SESSION['gs_paid_seen'] ?? null;
if ($daXem === null) {
    // Lần đầu mở trang trong phiên: chỉ coi là "mới" các giao dịch thành công trong 3 ngày gần đây
    $daXem = array_values(array_map(function ($r) { return (int) $r['transaction_id']; },
        array_filter($daMo, function ($r) { return strtotime($r['paid_confirmed_at'] ?: $r['tx_time']) < time() - 3 * 86400; })));
}
$moi = array_filter($daMo, function ($r) use ($daXem) { return !in_array((int) $r['transaction_id'], $daXem, true); });
$_SESSION['gs_paid_seen'] = array_values(array_unique(array_merge($daXem, array_map('intval', array_column($daMo, 'transaction_id')))));
// Còn giao dịch đang chờ đối soát -> bật tự kiểm tra
$dangCho = count(array_filter($history, function ($h) use ($txState) { return in_array($txState($h), ['pending_24h', 'confirmed'], true); })) > 0;

gs_layout_start('Thanh toán phí nhận lớp', 'thanh-toan', $tutor);
?>
<?php foreach ($moi as $r): ?>
    <div class="gs-alert gs-alert-ok" role="status" style="font-size:15px">
        <i class="fa-solid fa-circle-check"></i>
        <strong>Thanh toán thành công lớp <?= gs_e($r['class_code']) ?>!</strong>
        Số điện thoại phụ huynh <strong><?= gs_e($r['parent_name']) ?></strong>: <strong style="font-size:17px"><?= gs_e($r['parent_phone']) ?></strong>.
        Hãy liên hệ ngay để sắp xếp buổi học đầu tiên.
    </div>
<?php endforeach; ?>
<div class="gs-stats">
    <div class="gs-stat"><span>Tổng đã thanh toán</span><b><?= gs_money($paidTotal) ?></b></div>
    <div class="gs-stat"><span>Phí nhận lớp</span><b><?= GS_FEE_RATE * 100 ?>%</b><span>học phí tháng đầu</span></div>
    <div class="gs-stat"><span>Thời hạn thanh toán</span><b><?= GS_PAY_HOURS ?> giờ</b><span>kể từ khi tạo mã</span></div>
</div>

<?php if ($daMo): ?>
    <h3 style="color:var(--primary-dark);margin-bottom:12px"><i class="fa-solid fa-address-book"></i> Lớp đã thanh toán – Liên hệ phụ huynh</h3>
    <?php foreach ($daMo as $r): $laMoi = isset($moi[$r['class_id']]); ?>
        <div class="gs-card <?= $laMoi ? 'gs-paid-new' : '' ?>" id="lien-he-<?= (int) $r['class_id'] ?>">
            <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px">
                <h3 style="margin:0"><?= gs_e($r['class_code']) ?> – <?= gs_e($r['title']) ?><?= $laMoi ? '<span class="gs-new-tag">Mới</span>' : '' ?></h3>
                <span class="gs-badge gs-b-ok"><i class="fa-solid fa-unlock"></i> Đã mở liên hệ</span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;align-items:start">
                <?= gs_the_lien_he($r) ?>
                <div class="gs-info" style="grid-template-columns:1fr">
                    <div><span>Môn / Lớp</span><?= gs_e($r['subject']) ?> · <?= gs_e(gs_grade($r['grade'])) ?></div>
                    <div><span>Địa điểm</span><?= gs_e($r['location'] ?: '—') ?></div>
                    <div><span>Lịch học</span><?= gs_e($r['schedule'] ?: 'Thỏa thuận với phụ huynh') ?></div>
                    <div><span>Đã thanh toán</span><?= gs_money($r['amount']) ?> · mã <?= gs_e($r['tx_code']) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<h3 style="color:var(--primary-dark);margin-bottom:12px">Khoản phí cần thanh toán</h3>
<?php
$due = array_filter($items, function ($it) use ($txState) {
    return !$it['transaction_id'] || $txState(['status' => $it['tx_status'], 'tx_time' => $it['tx_time'], 'paid_confirmed_at' => $it['paid_confirmed_at']]) !== 'success';
});
if (!$due): ?>
    <p class="gs-empty" style="padding:16px">Không có khoản phí nào cần thanh toán.</p>
<?php endif; ?>

<?php foreach ($due as $it):
    $tx = $it['transaction_id'] ? ['status' => $it['tx_status'], 'tx_time' => $it['tx_time'], 'paid_confirmed_at' => $it['paid_confirmed_at']] : null;
    $state = $tx ? $txState($tx) : null;
    $amount = $tx ? (float) $it['amount'] : $feeOf($it['tuition_fee']);
?>
    <div class="gs-card">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px">
            <h3 style="margin:0"><?= gs_e($it['class_code']) ?> – <?= gs_e($it['title']) ?></h3>
            <?= $state ? gs_badge($txLabels, $state) : '<span class="gs-badge gs-b-muted">Chưa tạo mã thanh toán</span>' ?>
        </div>

        <?php if (!$tx || in_array($state, ['expired', 'failed'], true)): ?>
            <div class="gs-info" style="margin-bottom:12px">
                <div><span>Học phí tháng</span><?= gs_money($it['tuition_fee']) ?></div>
                <div><span>Phí nhận lớp cần thanh toán</span><strong style="color:var(--primary-red);font-size:18px"><?= gs_money($amount) ?></strong></div>
            </div>
            <?php if ($state === 'expired'): ?><p class="gs-err" style="margin-bottom:10px">Mã <?= gs_e($it['tx_code']) ?> đã quá hạn. Tạo mã mới để tiếp tục giữ lớp.</p><?php endif; ?>
            <form method="post">
                <?= gs_csrf_field() ?><input type="hidden" name="class_id" value="<?= (int) $it['class_id'] ?>"><input type="hidden" name="action" value="create">
                <button class="gs-btn gold" type="submit"><i class="fa-solid fa-qrcode"></i> <?= $tx ? 'Tạo mã thanh toán mới' : 'Lấy mã thanh toán' ?></button>
            </form>
        <?php else:
            $deadline = strtotime($it['tx_time']) + GS_PAY_HOURS * 3600;
            $memo = ve_noi_dung_ck($it['tx_code']);
            $qr = ve_link_qr($amount, $it['tx_code']);
        ?>
            <div class="gs-pay">
                <img src="<?= gs_e($qr) ?>" alt="Mã VietQR thanh toán <?= gs_e($it['tx_code']) ?>" loading="lazy">
                <div>
                    <div class="gs-info" style="grid-template-columns:1fr 1fr">
                        <div><span>Số tiền</span><strong style="color:var(--primary-red);font-size:18px"><?= gs_money($amount) ?></strong></div>
                        <div><span>Mã giao dịch</span><strong><?= gs_e($it['tx_code']) ?></strong></div>
                        <div><span>Ngân hàng</span><?= gs_e(GS_BANK['bank_name']) ?></div>
                        <div><span>Số tài khoản</span><strong><?= gs_e(GS_BANK['account']) ?></strong></div>
                        <div><span>Chủ tài khoản</span><?= gs_e(GS_BANK['owner']) ?></div>
                        <div><span>Nội dung chuyển khoản</span><strong><?= gs_e($memo) ?></strong></div>
                    </div>
                    <?php if ($state === 'pending_24h'): ?>
                        <p style="margin-top:14px">Thời gian còn lại: <span class="gs-countdown" data-deadline="<?= $deadline * 1000 ?>">--:--:--</span></p>
                        <p class="gs-muted">Hạn chót: <?= date('H:i d/m/Y', $deadline) ?>. Quét mã bằng ứng dụng ngân hàng, giữ đúng nội dung chuyển khoản.</p>
                        <form method="post" style="margin-top:12px" onsubmit="return confirm('Xác nhận bạn đã chuyển khoản <?= gs_money($amount) ?>?')">
                            <?= gs_csrf_field() ?><input type="hidden" name="class_id" value="<?= (int) $it['class_id'] ?>"><input type="hidden" name="action" value="confirm">
                            <button class="gs-btn" type="submit"><i class="fa-solid fa-circle-check"></i> Tôi đã chuyển khoản</button>
                        </form>
                    <?php else: ?>
                        <p class="gs-alert gs-alert-info" style="margin-top:14px">Bạn đã báo chuyển khoản lúc <?= gs_date($it['paid_confirmed_at'], 'H:i d/m/Y') ?>. Trung tâm đang đối soát, trạng thái sẽ cập nhật khi nhận được tiền.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php if ($waiting): ?>
    <h3 style="color:var(--primary-dark);margin:24px 0 12px">Lớp đang chờ trung tâm duyệt</h3>
    <?php foreach ($waiting as $w): ?>
        <div class="gs-card">
            <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:8px">
                <h3 style="margin:0"><?= gs_e($w['class_code']) ?> – <?= gs_e($w['title']) ?></h3>
                <?= gs_badge(GS_APPLY_LABELS, $w['match_status']) ?>
            </div>
            <p class="gs-muted"><i class="fa-solid fa-lock"></i> Lớp chưa được trung tâm duyệt nên chưa thể thanh toán. Mã QR phí nhận lớp sẽ hiện sau khi trung tâm giao lớp này cho bạn.</p>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<h3 style="color:var(--primary-dark);margin:24px 0 12px">Lịch sử thanh toán</h3>
<div class="gs-table-wrap">
<table class="gs-table">
    <thead><tr><th>Mã giao dịch</th><th>Lớp</th><th>Số tiền</th><th>Thời gian tạo</th><th>Trạng thái</th><th>SĐT phụ huynh</th></tr></thead>
    <tbody>
    <?php if (!$history): ?><tr><td colspan="6" class="gs-empty">Chưa có giao dịch nào.</td></tr><?php endif; ?>
    <?php foreach ($history as $h): $lh = $h['status'] === 'success' ? ($daMo[$h['class_id']] ?? null) : null; ?>
        <tr>
            <td><strong><?= gs_e($h['tx_code']) ?></strong></td>
            <td><?= gs_e($h['class_code']) ?><div class="gs-muted"><?= gs_e($h['title']) ?></div></td>
            <td><?= gs_money($h['amount']) ?></td>
            <td><?= gs_date($h['tx_time'], 'H:i d/m/Y') ?></td>
            <td><?= gs_badge($txLabels, $txState($h)) ?></td>
            <td>
                <?php if ($lh && $lh['parent_phone']): ?>
                    <a href="tel:<?= gs_e(preg_replace('/\D/', '', $lh['parent_phone'])) ?>" style="font-weight:700;color:#1B5E20"><i class="fa-solid fa-phone"></i> <?= gs_e($lh['parent_phone']) ?></a>
                <?php elseif ($h['status'] === 'success'): ?>
                    <span class="gs-muted">—</span>
                <?php else: ?>
                    <span class="gs-muted"><i class="fa-solid fa-lock"></i> Mở khi thanh toán xong</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<script>
// Đếm ngược thời hạn thanh toán 24h
document.querySelectorAll('.gs-countdown').forEach(function (el) {
    var end = +el.dataset.deadline;
    (function tick() {
        var s = Math.max(0, Math.floor((end - Date.now()) / 1000));
        var p = function (n) { return String(n).padStart(2, '0'); };
        el.textContent = p(Math.floor(s / 3600)) + ':' + p(Math.floor(s % 3600 / 60)) + ':' + p(s % 60);
        if (s > 0) setTimeout(tick, 1000); else el.textContent = 'Đã hết hạn – tải lại trang';
    })();
});

<?php if ($dangCho): ?>
// Đang có khoản chờ trung tâm xác nhận: kiểm tra mỗi 15 giây, khi thanh toán thành công thì
// tải lại trang để hiện ngay số điện thoại phụ huynh (không cần gia sư tự bấm tải lại).
(function () {
    var daBiet = <?= (int) $soThanhCong ?>;
    function kiemTra() {
        if (document.hidden) return;   // tab đang ẩn thì tạm dừng
        fetch('thanh-toan.php?kiem_tra=1', { cache: 'no-store', credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) { if (d && d.so_thanh_cong > daBiet) location.reload(); })
            .catch(function () {});
    }
    setInterval(kiemTra, 15000);
    document.addEventListener('visibilitychange', kiemTra);
})();
<?php endif; ?>
</script>
<?php gs_layout_end(); ?>
