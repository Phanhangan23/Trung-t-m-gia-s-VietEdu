<?php
// Mã bảo vệ form + thông báo từ lần đổi trạng thái trước (hiện 1 lần rồi xóa)
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Bộ lọc trạng thái lấy từ URL (&status=...), chỉ chấp nhận 3 giá trị hợp lệ
$loc = in_array($_GET['status'] ?? '', ['open', 'processing', 'resolved'], true) ? $_GET['status'] : '';
// Tìm kiếm: mã phiếu (#12 hoặc 12), tiêu đề, nội dung, tên / SĐT người gửi
$tuKhoa = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100, 'UTF-8');
$locNguoiGui = in_array($_GET['sender'] ?? '', ['parent', 'tutor'], true) ? $_GET['sender'] : '';

try {
    // Người gửi có thể là phụ huynh hoặc gia sư, nên nối cả 2 bảng rồi lấy tên của bên tương ứng.
    $tu  = "FROM support_tickets t
            LEFT JOIN parents p  ON t.sender_type = 'parent' AND t.sender_id = p.parent_id
            LEFT JOIN tutors  tu ON t.sender_type = 'tutor'  AND t.sender_id = tu.tutor_id";
    $dk  = [];
    $tso = [];
    if ($tuKhoa !== '') {
        $kw = '%' . $tuKhoa . '%';
        $dkTim = 't.issue_title LIKE ? OR t.content LIKE ? OR p.full_name LIKE ? OR tu.full_name LIKE ? OR p.phone LIKE ? OR tu.phone LIKE ?';
        array_push($tso, $kw, $kw, $kw, $kw, $kw, $kw);
        if (preg_match('/^#?(\d+)$/', $tuKhoa, $mso)) {        // gõ "#12" hoặc "12" -> tìm đúng mã phiếu
            $dkTim .= ' OR t.ticket_id = ?';
            $tso[] = (int) $mso[1];
        }
        $dk[] = "($dkTim)";
    }
    if ($locNguoiGui !== '') {
        $dk[]  = 't.sender_type = ?';
        $tso[] = $locNguoiGui;
    }

    // Đếm theo trạng thái cho các tab: cùng từ khóa + loại người gửi
    $st = $conn->prepare("SELECT t.status, COUNT(*) $tu" . ($dk ? ' WHERE ' . implode(' AND ', $dk) : '') . ' GROUP BY t.status');
    $st->execute($tso);
    $demTheo = $st->fetchAll(PDO::FETCH_KEY_PAIR);

    if ($loc !== '') {
        $dk[]  = 't.status = ?';
        $tso[] = $loc;
    }
    $sql = "SELECT t.ticket_id, t.sender_type, t.issue_title, t.content, t.status, t.created_at,
                   COALESCE(p.full_name, tu.full_name) AS ten_nguoi_gui,
                   COALESCE(p.phone, tu.phone) AS sdt_nguoi_gui
            $tu" . ($dk ? ' WHERE ' . implode(' AND ', $dk) : '')
         . " ORDER BY FIELD(t.status, 'open', 'processing', 'resolved'), t.created_at DESC";

    $st = $conn->prepare($sql);
    $st->execute($tso);
    $tickets = $st->fetchAll(PDO::FETCH_ASSOC);

    // Phân trang 10 phiếu / trang
    $pt = phan_trang($tickets, 10);
    $tickets = $pt['rows'];
} catch (PDOException $e) {
    error_log('Lỗi tải phiếu hỗ trợ: ' . $e->getMessage());
    $tickets = [];
    $demTheo = [];
    $loiTai = 'Không tải được danh sách phiếu hỗ trợ.';
}

// Nhãn tiếng Việt cho từng trạng thái: [chữ hiển thị, class badge, style thêm]
$nhanTrangThai = [
    'open'       => [VE_NHAN_TICKET['open'], 'badge',               'background:#FDECEA; color:#B3261E;'],
    'processing' => [VE_NHAN_TICKET['processing'], 'badge badge-warning', ''],
    'resolved'   => [VE_NHAN_TICKET['resolved'], 'badge badge-success', ''],
];
$nhanNguoiGui = ['parent' => 'Phụ huynh', 'tutor' => 'Gia sư'];
$cacTab = ['' => 'Tất cả'] + VE_NHAN_TICKET;

// Nếu URL có &id=... thì lấy ticket đó để hiện chi tiết
$chiTiet = null;
if (isset($_GET['id'])) {
    try {
        $st = $conn->prepare(
            "SELECT t.*,
                    COALESCE(p.full_name, tu.full_name) AS ten_nguoi_gui,
                    COALESCE(p.phone, tu.phone)         AS sdt_nguoi_gui
             FROM support_tickets t
             LEFT JOIN parents p  ON t.sender_type = 'parent' AND t.sender_id = p.parent_id
             LEFT JOIN tutors  tu ON t.sender_type = 'tutor'  AND t.sender_id = tu.tutor_id
             WHERE t.ticket_id = ?"
        );
        $st->execute([(int) $_GET['id']]);
        $chiTiet = $st->fetch(PDO::FETCH_ASSOC) ?: null;

        // Các phản hồi nhân viên đã gửi cho phiếu hỗ trợ này
        $phanHoi = [];
        if ($chiTiet) {
            $st = $conn->prepare(
                "SELECT r.content, r.created_at, s.full_name
                 FROM ticket_replies r LEFT JOIN staffs s ON s.staff_id = r.staff_id
                 WHERE r.ticket_id = ? ORDER BY r.created_at, r.reply_id"
            );
            $st->execute([(int) $chiTiet['ticket_id']]);
            $phanHoi = $st->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log('Lỗi tải chi tiết phiếu hỗ trợ: ' . $e->getMessage());
    }
}

// Đuôi &status=... để giữ bộ lọc khi bấm Xem / Đóng
$duoiLoc = http_build_query(array_filter([
    'status' => $loc, 'q' => $tuKhoa, 'sender' => $locNguoiGui, 'page' => (int) ($_GET['page'] ?? 0) ?: '', 'per' => $_GET['per'] ?? '',
], 'strlen'));
$duoiLoc = $duoiLoc !== '' ? '&' . htmlspecialchars($duoiLoc, ENT_QUOTES, 'UTF-8') : '';
/** Link tab trạng thái, giữ nguyên từ khóa + loại người gửi. */
$linkTab = function ($ma) use ($tuKhoa, $locNguoiGui) {
    return 'admin.php?' . http_build_query(array_filter(['view' => 'tickets', 'status' => $ma, 'q' => $tuKhoa, 'sender' => $locNguoiGui], 'strlen'));
};
?>

<section id="view-tickets" class="content-view active">
    <div class="view-header">
        <h2>Phân hệ 1: Xử Lý Khiếu Nại & Hỗ Trợ</h2>
    </div>

    <?php if (!empty($loiTai)): ?>
        <p style="color:#B3261E;"><?= htmlspecialchars($loiTai) ?></p>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div style="padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;
                    background: <?= $flash['loai'] === 'ok' ? '#EAF7ED' : '#FDECEA' ?>;
                    color: <?= $flash['loai'] === 'ok' ? '#2E7D32' : '#B3261E' ?>;">
            <?= htmlspecialchars($flash['nd']) ?>
        </div>
    <?php endif; ?>

    <!-- KHUNG CHI TIẾT TICKET (chỉ hiện khi có &id=...) -->
    <?php if ($chiTiet): ?>
        <?php [$chu, $class, $style] = $nhanTrangThai[$chiTiet['status']] ?? [$chiTiet['status'], 'badge', '']; ?>
        <div class="data-table-card" style="margin-bottom: 20px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3>Phiếu hỗ trợ #<?= (int) $chiTiet['ticket_id'] ?>: <?= htmlspecialchars($chiTiet['issue_title'] ?? '(không có tiêu đề)') ?></h3>
                <a href="admin.php?view=tickets<?= $duoiLoc ?>" style="color: var(--primary-red); font-weight: 600;">Đóng ✕</a>
            </div>

            <p style="margin: 6px 0;">
                <strong>Người gửi:</strong> <?= htmlspecialchars($chiTiet['ten_nguoi_gui'] ?? 'Không rõ') ?>
                (<?= htmlspecialchars($nhanNguoiGui[$chiTiet['sender_type']] ?? '') ?>)
                &nbsp;|&nbsp; <strong>SĐT:</strong> <?= htmlspecialchars($chiTiet['sdt_nguoi_gui'] ?? 'Không rõ') ?>
            </p>
            <p style="margin: 6px 0;">
                <strong>Ngày gửi:</strong> <?= date('d/m/Y H:i', strtotime($chiTiet['created_at'])) ?>
                &nbsp;|&nbsp; <strong>Trạng thái:</strong>
                <span class="<?= $class ?>" style="<?= $style ?>"><?= htmlspecialchars($chu) ?></span>
            </p>

            <div style="margin-top: 12px; padding: 14px; background: #FDF9F9; border: 1px solid var(--border-color); border-radius: 6px; line-height: 1.6;">
                <?= nl2br(htmlspecialchars($chiTiet['content'])) ?>
            </div>

            <form method="post" action="ticket-trang-thai.php" style="margin-top: 16px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <input type="hidden" name="ticket_id" value="<?= (int) $chiTiet['ticket_id'] ?>">

                <label for="trangThaiMoi"><strong>Đổi trạng thái:</strong></label>
                <select name="status" id="trangThaiMoi" style="padding: 8px; border: 1px solid var(--border-color); border-radius: 4px;">
                    <?php foreach ($nhanTrangThai as $ma => $nhan): ?>
                        <option value="<?= htmlspecialchars($ma) ?>" <?= $chiTiet['status'] === $ma ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nhan[0]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" style="background: var(--primary-red); color: #fff; padding: 9px 18px; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">Cập nhật</button>
            </form>

            <!-- PHẢN HỒI CỦA NHÂN VIÊN -->
            <h4 style="margin-top: 22px;">Phản hồi (<?= count($phanHoi ?? []) ?>)</h4>
            <?php foreach ($phanHoi ?? [] as $ph): ?>
                <div style="margin-top: 10px; padding: 12px 14px; background: #F4F8FB; border-left: 3px solid #3498db; border-radius: 4px;">
                    <small style="color: var(--text-muted);">
                        <strong><?= htmlspecialchars($ph['full_name'] ?? 'Nhân viên đã nghỉ') ?></strong>
                        · <?= date('d/m/Y H:i', strtotime($ph['created_at'])) ?>
                    </small>
                    <div style="margin-top: 4px; line-height: 1.6;"><?= nl2br(htmlspecialchars($ph['content'])) ?></div>
                </div>
            <?php endforeach; ?>

            <form method="post" action="ticket-phan-hoi.php" style="margin-top: 14px;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <input type="hidden" name="ticket_id" value="<?= (int) $chiTiet['ticket_id'] ?>">
                <textarea name="content" rows="3" maxlength="2000" required placeholder="Nhập nội dung phản hồi cho người gửi..."
                          style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; font-family: inherit;"></textarea>
                <button type="submit" style="margin-top: 8px; background: #3498db; color: #fff; padding: 9px 18px; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">
                    <i class="fa-solid fa-reply"></i> Gửi phản hồi
                </button>
            </form>
        </div>
    <?php elseif (isset($_GET['id'])): ?>
        <p style="color:#B3261E; margin-bottom: 16px;">Không tìm thấy phiếu hỗ trợ này. <a href="admin.php?view=tickets">Quay lại danh sách</a></p>
    <?php endif; ?>

    <!-- TÌM KIẾM & LỌC -->
    <form method="get" action="admin.php" class="db-filter" role="search">
        <input type="hidden" name="view" value="tickets">
        <?php if ($loc !== ''): ?><input type="hidden" name="status" value="<?= htmlspecialchars($loc) ?>"><?php endif; ?>
        <div class="db-field db-field-search">
            <label for="tk-q">Tìm phiếu hỗ trợ</label>
            <div class="db-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="tk-q" name="q" value="<?= htmlspecialchars($tuKhoa) ?>" maxlength="100"
                       placeholder="Mã phiếu (#12), tiêu đề, nội dung, tên hoặc SĐT người gửi…">
            </div>
        </div>
        <div class="db-field">
            <label for="tk-sender">Người gửi</label>
            <select id="tk-sender" name="sender" onchange="this.form.submit()">
                <option value="">Tất cả</option>
                <option value="parent" <?= $locNguoiGui === 'parent' ? 'selected' : '' ?>>Phụ huynh</option>
                <option value="tutor" <?= $locNguoiGui === 'tutor' ? 'selected' : '' ?>>Gia sư</option>
            </select>
        </div>
        <div class="db-actions">
            <button type="submit" class="db-btn db-btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Tìm</button>
            <?php if ($tuKhoa !== '' || $locNguoiGui !== '' || $loc !== ''): ?>
                <a href="admin.php?view=tickets" class="db-btn db-btn-ghost"><i class="fa-solid fa-rotate-left"></i> Xóa lọc</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- 4 TAB LỌC (luôn hiện, nằm ngoài khung chi tiết) -->
    <div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
        <?php foreach ($cacTab as $ma => $ten): ?>
            <?php
            $dem = ($ma === '') ? array_sum($demTheo) : (int) ($demTheo[$ma] ?? 0);
            $dangChon = ($loc === $ma);
            ?>
            <a href="<?= htmlspecialchars($linkTab($ma)) ?>"
               style="padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 600;
                      border: 1px solid var(--primary-red);
                      background: <?= $dangChon ? 'var(--primary-red)' : '#fff' ?>;
                      color: <?= $dangChon ? '#fff' : 'var(--primary-red)' ?>;">
                <?= htmlspecialchars($ten) ?> (<?= $dem ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <!-- BẢNG DANH SÁCH -->
    <div class="data-table-card">
        <h3>Danh sách phiếu hỗ trợ (<?= isset($pt) ? $pt['total'] : count($tickets) ?>)</h3>
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Mã</th>
                    <th>Người gửi</th>
                    <th>Tiêu đề</th>
                    <th>Nội dung</th>
                    <th>Trạng thái</th>
                    <th>Ngày gửi</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$tickets): ?>
                    <tr><td colspan="7" class="db-empty"><i class="fa-regular fa-folder-open"></i>
                        <?= ($tuKhoa !== '' || $locNguoiGui !== '' || $loc !== '') ? 'Không tìm thấy phiếu hỗ trợ nào khớp bộ lọc.' : 'Chưa có phiếu hỗ trợ nào.' ?>
                        <?php if ($tuKhoa !== '' || $locNguoiGui !== '' || $loc !== ''): ?><a href="admin.php?view=tickets">Xóa bộ lọc</a><?php endif; ?>
                    </td></tr>
                <?php endif; ?>

                <?php foreach ($tickets as $tk): ?>
                    <?php [$chu, $class, $style] = $nhanTrangThai[$tk['status']] ?? [$tk['status'], 'badge', '']; ?>
                    <tr>
                        <td>#<?= (int) $tk['ticket_id'] ?></td>
                        <td>
                            <strong><?= to_dam_tu_khoa($tk['ten_nguoi_gui'] ?? 'Không rõ', $tuKhoa) ?></strong><br>
                            <?php if (!empty($tk['sdt_nguoi_gui'])): ?><small style="color:var(--text-muted);"><?= to_dam_tu_khoa($tk['sdt_nguoi_gui'], $tuKhoa) ?></small><br><?php endif; ?>
                            <small style="color:var(--text-muted);"><?= htmlspecialchars($nhanNguoiGui[$tk['sender_type']] ?? '') ?></small>
                        </td>
                        <td><?= to_dam_tu_khoa($tk['issue_title'] ?? '(không có tiêu đề)', $tuKhoa) ?></td>
                        <td><?= to_dam_tu_khoa(mb_strimwidth($tk['content'], 0, 80, '…'), $tuKhoa) ?></td>
                        <td><span class="<?= $class ?>" style="<?= $style ?>"><?= htmlspecialchars($chu) ?></span></td>
                        <td><?= date('d/m/Y H:i', strtotime($tk['created_at'])) ?></td>
                        <td>
                            <a href="admin.php?view=tickets&id=<?= (int) $tk['ticket_id'] ?><?= $duoiLoc ?>"
                               style="color: var(--primary-red); font-weight: 600;"><i class="fa-solid fa-eye"></i> Xem</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?= isset($pt) ? phan_trang_html($pt, 'phiếu hỗ trợ') : '' ?>
    </div>
</section>