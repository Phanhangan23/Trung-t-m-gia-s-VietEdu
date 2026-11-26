<?php
// 1. CẤU HÌNH HỆ THỐNG
// Tài khoản ngân hàng: dùng cấu hình chung VE_NGAN_HANG trong cau-hinh.php
$fee_rate     = 0.30;           
$qr_ttl_hours = 24;              
$per_page     = 15;             

// Danh sách (lớp + gia sư) được phép tạo mã QR:
// CHỈ khi admin đã duyệt "Giao lớp" ở mục Matching (class_matching = matched VÀ lớp = matched).
// Gia sư mới ứng tuyển / đang phỏng vấn thì chưa thanh toán được, chưa có QR.
$eligible_sql = "SELECT m.match_id, m.class_id, m.tutor_id, c.class_code, c.title, c.tuition_fee, tu.full_name AS tutor_name
                 FROM class_matching m
                 JOIN classes c ON c.class_id = m.class_id
                 JOIN tutors tu ON tu.tutor_id = m.tutor_id
                 WHERE c.status = 'matched'
                   AND m.status = 'matched'
                   AND tu.status = 'active'
                   AND c.tuition_fee > 0
                   AND NOT EXISTS (SELECT 1 FROM transactions x
                                   WHERE x.class_id = m.class_id AND x.status IN ('pending_24h', 'success'))";

// 2. HÀM TIỆN ÍCH DỄ ĐỌC
if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('money')) {
    function money($amount) {
        return number_format((float)$amount, 0, ',', '.') . ' VNĐ';
    }
}

// 3. TỰ ĐỘNG HỦY MÃ QR Quá 24H
try {
    $conn->exec("UPDATE transactions SET status = 'failed' 
                 WHERE status = 'pending_24h' 
                 AND tx_time <= DATE_SUB(NOW(), INTERVAL $qr_ttl_hours HOUR)");
} catch (PDOException $e) {
    error_log("Lỗi tự động hủy QR: " . $e->getMessage());
}

// 4. XỬ LÝ POST REQUEST (Gạch nợ / Tạo QR / Hủy / Tạo lại)
$flash_ok = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tx_id  = (int)($_POST['transaction_id'] ?? 0);

    try {
        // A. TẠO MÃ VIETQR MỚI – chỉ cho lớp đã được admin "Giao lớp" (kiểm tra lại ở server)
        if ($action === 'create_qr') {
            $stmt = $conn->prepare($eligible_sql . " AND m.match_id = ?");
            $stmt->execute([(int)($_POST['match_id'] ?? 0)]);
            $match = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($match) {
                // Mã giao dịch kế tiếp theo tx_code (cùng cách với trang thanh toán của gia sư để không bị trùng)
                $max_code = (int)$conn->query("SELECT MAX(CAST(SUBSTRING(tx_code, 5) AS UNSIGNED)) FROM transactions WHERE tx_code LIKE '#TX-%'")->fetchColumn();
                $tx_code  = '#TX-' . max(9001, $max_code + 1);
                $amount   = round($match['tuition_fee'] * $fee_rate);

                $ins = $conn->prepare("INSERT INTO transactions (tx_code, class_id, tutor_id, amount, tx_time, status)
                                       VALUES (?, ?, ?, ?, NOW(), 'pending_24h')");
                $ins->execute([$tx_code, $match['class_id'], $match['tutor_id'], $amount]);
                $flash_ok = "Đã tạo mã VietQR $tx_code thành công (Hiệu lực 24h).";
            } else {
                $flash_err = "Không tạo được mã: lớp chưa được giao cho gia sư này, đã có mã QR đang chờ / đã thu phí, hoặc chưa có học phí.";
            }
        }

        // B. GẠCH NỢ (XÁC NHẬN ĐÃ THU TIỀN) – chỉ khi lớp vẫn đang giao đúng cho gia sư đó
        elseif ($action === 'mark_paid') {
            $stmt = $conn->prepare("SELECT * FROM transactions WHERE transaction_id = ? AND status = 'pending_24h'");
            $stmt->execute([$tx_id]);
            $tx = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tx) {
                $flash_err = "Giao dịch không ở trạng thái chờ thanh toán (có thể đã hết hạn).";
            } else {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM class_matching m
                                        JOIN classes c ON c.class_id = m.class_id
                                        WHERE m.class_id = ? AND m.tutor_id = ? AND m.status = 'matched' AND c.status = 'matched'");
                $stmt->execute([$tx['class_id'], $tx['tutor_id']]);

                if ((int)$stmt->fetchColumn() === 0) {
                    $flash_err = "Lớp này chưa được giao cho gia sư (hoặc đã bị hủy giao) nên không thể gạch nợ. Hãy kiểm tra mục Matching.";
                } else {
                    // Lớp đã 'matched' từ bước Giao lớp -> chỉ cần gạch nợ.
                    // Sau đó gia sư được xem thông tin liên hệ phụ huynh ở trang Lớp học của tôi.
                    $conn->prepare("UPDATE transactions SET status = 'success', paid_confirmed_at = COALESCE(paid_confirmed_at, NOW()) WHERE transaction_id = ?")->execute([$tx_id]);
                    $flash_ok = "Gạch nợ thành công cho giao dịch {$tx['tx_code']}. Gia sư đã được mở thông tin liên hệ phụ huynh.";
                }
            }
        }

        // C. HỦY MÃ QR
        elseif ($action === 'cancel_qr') {
            $stmt = $conn->prepare("UPDATE transactions SET status = 'failed' WHERE transaction_id = ? AND status = 'pending_24h'");
            $stmt->execute([$tx_id]);
            if ($stmt->rowCount() > 0) {
                $flash_ok = "Đã hủy mã VietQR thành công.";
            } else {
                $flash_err = "Không hủy được: giao dịch không còn chờ thanh toán.";
            }
        }

        // D. TẠO LẠI MÃ QR – chỉ cho giao dịch đã hết hạn/hủy (failed), lớp vẫn đang giao cho gia sư đó
        elseif ($action === 'regen_qr') {
            $stmt = $conn->prepare("SELECT t.* FROM transactions t
                                    JOIN class_matching m ON m.class_id = t.class_id AND m.tutor_id = t.tutor_id AND m.status = 'matched'
                                    JOIN classes c ON c.class_id = t.class_id AND c.status = 'matched'
                                    WHERE t.transaction_id = ? AND t.status = 'failed'");
            $stmt->execute([$tx_id]);
            $tx = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tx) {
                $flash_err = "Chỉ tạo lại được mã đã hết hạn / bị hủy của lớp đang giao cho gia sư.";
            } else {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM transactions
                                        WHERE class_id = ? AND transaction_id <> ? AND status IN ('pending_24h', 'success')");
                $stmt->execute([$tx['class_id'], $tx_id]);

                if ((int)$stmt->fetchColumn() > 0) {
                    $flash_err = "Lớp này đã có mã QR khác đang chờ hoặc đã thu phí.";
                } else {
                    $conn->prepare("UPDATE transactions SET status = 'pending_24h', tx_time = NOW() WHERE transaction_id = ? AND status = 'failed'")
                         ->execute([$tx_id]);
                    $flash_ok = "Đã gia hạn mã VietQR thêm 24 giờ.";
                }
            }
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $flash_err = ve_loi_csdl($e, 'cập nhật giao dịch');
    }
}

// 5. TRUY VẤN DỮ LIỆU HIỂN THỊ
$status_filter = $_GET['status'] ?? 'all';
$keyword       = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));

// Thống kê tổng quan
$stats = $conn->query("SELECT
            COALESCE(SUM(CASE WHEN status = 'success' THEN amount END), 0) AS rev_success,
            COALESCE(SUM(CASE WHEN status = 'pending_24h' THEN amount END), 0) AS debt_pending,
            COALESCE(SUM(status = 'success'), 0) AS cnt_success,
            COALESCE(SUM(status = 'pending_24h'), 0) AS cnt_pending,
            COALESCE(SUM(status = 'failed'), 0) AS cnt_failed
          FROM transactions")->fetch(PDO::FETCH_ASSOC);

// Danh sách giao dịch
$sql = "SELECT t.*, c.class_code, c.title AS class_title, c.status AS class_status,
               tu.full_name AS tutor_name, tu.phone AS tutor_phone,
               TIMESTAMPDIFF(SECOND, NOW(), t.tx_time + INTERVAL $qr_ttl_hours HOUR) AS seconds_left
        FROM transactions t
        JOIN classes c ON t.class_id = c.class_id
        JOIN tutors tu ON t.tutor_id = tu.tutor_id
        WHERE 1=1";
$params = [];

if ($status_filter !== 'all') {
    $sql .= " AND t.status = ?";
    $params[] = $status_filter;
}
if ($keyword !== '') {
    $sql .= " AND (t.tx_code LIKE ? OR c.class_code LIKE ? OR tu.full_name LIKE ?)";
    $params[] = "%$keyword%"; $params[] = "%$keyword%"; $params[] = "%$keyword%";
}
$sql .= " ORDER BY (t.status = 'pending_24h') DESC, t.tx_time DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$all_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Phân trang đơn giản bằng PHP
$pt         = phan_trang($all_payments, $per_page);
$total_rows = $pt['total'];
$pages      = $pt['pages'];
$page       = $pt['page'];
$payments   = $pt['rows'];

// Lấy danh sách ghép nối đủ điều kiện tạo QR (lớp đã được giao)
$eligible = $conn->query($eligible_sql . " ORDER BY c.class_code")->fetchAll(PDO::FETCH_ASSOC);
?>

<section id="view-payments" class="content-view active">
    <div class="view-header">
        <h2>Phân hệ 4: Giao Dịch & Cổng VietQR 24h</h2>
    </div>

    <!-- THÔNG BÁO FLASH -->
    <?php if ($flash_ok): ?>
        <div class="p4-alert ok"><i class="fa-solid fa-check-circle"></i> <?= h($flash_ok) ?></div>
    <?php endif; ?>
    <?php if ($flash_err): ?>
        <div class="p4-alert err"><i class="fa-solid fa-exclamation-triangle"></i> <?= h($flash_err) ?></div>
    <?php endif; ?>

    <!-- KPI THỐNG KÊ -->
    <div class="p4-stats">
        <div class="p4-stat green">
            <div>Phí đã thu</div>
            <div class="val"><?= money($stats['rev_success']) ?></div>
            <small><?= $stats['cnt_success'] ?> giao dịch thành công</small>
        </div>
        <div class="p4-stat orange">
            <div>Công nợ chờ thu</div>
            <div class="val"><?= money($stats['debt_pending']) ?></div>
            <small><?= $stats['cnt_pending'] ?> mã QR còn hiệu lực</small>
        </div>
        <div class="p4-stat red">
            <div>Mã hết hạn / Hủy</div>
            <div class="val"><?= $stats['cnt_failed'] ?></div>
            <small>Cần xử lý lại</small>
        </div>
    </div>

    <!-- TẠO MÃ VIETQR MỚI -->
    <div class="data-table-card" style="margin-bottom: 20px;">
        <h3><i class="fa-solid fa-qrcode"></i> Tạo Mã VietQR Thu Phí Nhận Lớp</h3>
        <?php if (!empty($eligible)): ?>
            <form method="POST" action="admin.php?view=payments" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="action" value="create_qr">
                <select name="match_id" class="p4-input" style="min-width: 300px;" required>
                    <option value="">-- Chọn lớp & gia sư ứng tuyển --</option>
                    <?php foreach ($eligible as $e): ?>
                        <option value="<?= $e['match_id'] ?>">
                            <?= h($e['class_code']) ?> - <?= h($e['title']) ?> (GS: <?= h($e['tutor_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="p4-btn p4-btn-red"><i class="fa-solid fa-plus"></i> Tạo Mã QR</button>
            </form>
        <?php else: ?>
            <p style="color:#666; margin:0;">Hiện chưa có lớp nào cần tạo mã thu phí mới.</p>
        <?php endif; ?>
    </div>

    <!-- DANH SÁCH GIAO DỊCH -->
    <div class="data-table-card">
        <h3>Lịch Sử Giao Dịch</h3>

        <!-- BỘ LỌC TABS -->
        <div class="p4-tabs">
            <a href="admin.php?view=payments&status=all" class="<?= $status_filter === 'all' ? 'active' : '' ?>">Tất cả</a>
            <a href="admin.php?view=payments&status=pending_24h" class="<?= $status_filter === 'pending_24h' ? 'active' : '' ?>">Chờ thanh toán</a>
            <a href="admin.php?view=payments&status=success" class="<?= $status_filter === 'success' ? 'active' : '' ?>">Đã thanh toán</a>
            <a href="admin.php?view=payments&status=failed" class="<?= $status_filter === 'failed' ? 'active' : '' ?>">Hết hạn / Hủy</a>
        </div>

        <!-- TÌM KIẾM -->
        <form method="GET" action="admin.php" style="margin-bottom: 15px; display:flex; gap:10px;">
            <input type="hidden" name="view" value="payments">
            <input type="hidden" name="status" value="<?= h($status_filter) ?>">
            <input type="text" name="q" value="<?= h($keyword) ?>" class="p4-input" placeholder="Tìm theo mã GD, lớp, tên gia sư..." style="width: 250px;">
            <button type="submit" class="p4-btn p4-btn-blue"><i class="fa-solid fa-search"></i> Tìm</button>
        </form>

        <table class="custom-table">
            <thead>
                <tr>
                    <th>Mã GD</th>
                    <th>Mã Lớp</th>
                    <th>Gia Sư</th>
                    <th>Số Tiền</th>
                    <th>Thời Gian Tạo</th>
                    <th>Thời Gian Còn Lại</th>
                    <th>Trạng Thái GD</th>
                    <th>Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($payments)): ?>
                    <?php foreach ($payments as $p): ?>
                        <?php
                            // Tính thời gian còn lại (seconds)
                            $seconds_left = max(0, (int)$p['seconds_left']);
                            
                            // Link VietQR API chuẩn
                            $content = ve_noi_dung_ck($p['tx_code']);
                            $qr_url  = ve_link_qr((float) $p['amount'], $p['tx_code']);
                        ?>
                        <tr>
                            <td><strong><?= h($p['tx_code']) ?></strong></td>
                            <td><?= h($p['class_code']) ?></td>
                            <td><?= h($p['tutor_name']) ?></td>
                            <td style="font-weight:bold; color:var(--primary-red);"><?= money($p['amount']) ?></td>
                            <td><?= date('H:i - d/m/Y', strtotime($p['tx_time'])) ?></td>
                            <td>
                                <?php if ($p['status'] === 'pending_24h'): ?>
                                    <span class="countdown" data-left="<?= $seconds_left ?>" style="font-weight:bold; color:#d68910;">--:--:--</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $badge = ($p['status'] == 'success') ? 'badge-success' : (($p['status'] == 'pending_24h') ? 'badge-warning' : 'badge-danger');
                                ?>
                                <?php $nhanTT = VE_NHAN_GIAO_DICH; ?>
                                <span class="badge <?= $badge ?>"><?= h($nhanTT[$p['status']] ?? $p['status']) ?></span>
                            </td>
                            <td>
                                <div style="display:flex; gap:5px;">
                                    <?php if ($p['status'] === 'pending_24h'): ?>
                                        <button class="p4-btn p4-btn-blue btn-show-qr" 
                                                data-code="<?= h($p['tx_code']) ?>" 
                                                data-amount="<?= money($p['amount']) ?>" 
                                                data-qr="<?= h($qr_url) ?>" 
                                                data-content="<?= h($content) ?>">
                                            <i class="fa-solid fa-qrcode"></i> Xem QR
                                        </button>

                                        <form method="POST" action="admin.php?view=payments" onsubmit="return confirm('Xác nhận đã nhận tiền và gạch nợ cho giao dịch này?');">
                                            <input type="hidden" name="action" value="mark_paid">
                                            <input type="hidden" name="transaction_id" value="<?= $p['transaction_id'] ?>">
                                            <button type="submit" class="p4-btn p4-btn-green"><i class="fa-solid fa-check"></i> Gạch nợ</button>
                                        </form>

                                        <form method="POST" action="admin.php?view=payments" onsubmit="return confirm('Bạn có chắc muốn hủy mã QR này?');">
                                            <input type="hidden" name="action" value="cancel_qr">
                                            <input type="hidden" name="transaction_id" value="<?= $p['transaction_id'] ?>">
                                            <button type="submit" class="p4-btn"><i class="fa-solid fa-times"></i> Hủy</button>
                                        </form>

                                    <?php elseif ($p['status'] === 'failed'): ?>
                                        <form method="POST" action="admin.php?view=payments" onsubmit="return confirm('Tạo lại đếm ngược 24h cho giao dịch này?');">
                                            <input type="hidden" name="action" value="regen_qr">
                                            <input type="hidden" name="transaction_id" value="<?= $p['transaction_id'] ?>">
                                            <button type="submit" class="p4-btn p4-btn-blue"><i class="fa-solid fa-rotate"></i> Tạo lại QR</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:#27ae60; font-size:12px; font-weight:bold;"><i class="fa-solid fa-check-circle"></i> Đã hoàn tất</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;">Chưa có giao dịch nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- PHÂN TRANG -->
        <?= phan_trang_html($pt, 'giao dịch') ?>
    </div>
</section>

<!-- MODAL XEM MÃ QR -->
<div class="p4-modal" id="qrModal" style="display:none;">
    <div class="p4-modal-box">
        <button class="p4-modal-close" onclick="closeQrModal()">&times;</button>
        <h3 style="margin-top:0;">Mã VietQR Thanh Toán</h3>
        <div style="text-align:center; margin: 15px 0;">
            <img id="modalQrImg" src="" alt="Mã VietQR" style="max-width:100%; height:auto; border-radius:6px;">
        </div>
        <p><strong>Mã GD:</strong> <span id="modalCode"></span></p>
        <p><strong>Số tiền:</strong> <span id="modalAmount"></span></p>
        <p><strong>Nội dung CK:</strong> <span id="modalContent" style="color:var(--primary-red); font-weight:bold;"></span></p>
    </div>
</div>

<script>
// ===== Popup xem mã VietQR =====
function closeQrModal() {
    document.getElementById('qrModal').style.display = 'none';
}
document.querySelectorAll('.btn-show-qr').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('modalQrImg').src = btn.dataset.qr;
        document.getElementById('modalCode').textContent = btn.dataset.code;
        document.getElementById('modalAmount').textContent = btn.dataset.amount;
        document.getElementById('modalContent').textContent = btn.dataset.content;
        document.getElementById('qrModal').style.display = 'flex';
    });
});
const qrModal = document.getElementById('qrModal');
qrModal.addEventListener('click', e => { if (e.target === qrModal) closeQrModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeQrModal(); });

// ===== Đếm ngược 24h (số giây còn lại nằm trong data-left) =====
function updateCountdown() {
    document.querySelectorAll('.countdown[data-left]').forEach(el => {
        const s = parseInt(el.dataset.left, 10);
        if (s <= 0) { el.textContent = 'Hết hạn (F5 để cập nhật)'; return; }
        const pad = n => String(n).padStart(2, '0');
        el.textContent = pad(Math.floor(s / 3600)) + ':' + pad(Math.floor((s % 3600) / 60)) + ':' + pad(s % 60);
        el.dataset.left = s - 1;
    });
}
setInterval(updateCountdown, 1000);
updateCountdown();

// ===== Tránh gửi lại form (tạo QR trùng) khi bấm F5 sau khi thao tác =====
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>
