<?php
/* ============================================================
 * TỔNG QUAN & DOANH THU – có bộ lọc thời gian, trạng thái, tìm kiếm
 * Tham số GET:
 *   range  : all | today | 7d | 30d | month | lastmonth | custom
 *   from,to: Y-m-d (dùng khi range = custom)
 *   status : '' | success | pending_24h | failed   (trạng thái giao dịch)
 *   q      : từ khóa (mã GD, mã lớp, tên lớp, tên / SĐT gia sư)
 * ============================================================ */
$db_e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$tz   = new DateTimeZone('Asia/Ho_Chi_Minh');
$homNay = new DateTimeImmutable('today', $tz);

$db_ranges = [
    'all'       => 'Toàn bộ thời gian',
    'today'     => 'Hôm nay',
    '7d'        => '7 ngày qua',
    '30d'       => '30 ngày qua',
    'month'     => 'Tháng này',
    'lastmonth' => 'Tháng trước',
    'custom'    => 'Tùy chọn ngày…',
];
$db_tx_labels = [
    'success'     => [VE_NHAN_GIAO_DICH['success'], 'badge-success'],
    'pending_24h' => [VE_NHAN_GIAO_DICH['pending_24h'], 'badge-warning'],
    'failed'      => [VE_NHAN_GIAO_DICH['failed'], 'badge-danger'],
];

// ---- Đọc & kiểm tra bộ lọc ----
$range  = isset($db_ranges[$_GET['range'] ?? '']) ? $_GET['range'] : '30d';
$status = isset($db_tx_labels[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$q      = trim((string) ($_GET['q'] ?? ''));
$q      = mb_substr($q, 0, 100, 'UTF-8');

$docNgay = function ($s) use ($tz) {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $s, $tz);
    return $d && $d->format('Y-m-d') === $s ? $d : null;
};
$tu = $den = null;   // khoảng [tu, den] tính theo ngày, null = không giới hạn
switch ($range) {
    case 'today':     $tu = $den = $homNay; break;
    case '7d':        $tu = $homNay->modify('-6 days');  $den = $homNay; break;
    case '30d':       $tu = $homNay->modify('-29 days'); $den = $homNay; break;
    case 'month':     $tu = $homNay->modify('first day of this month'); $den = $homNay; break;
    case 'lastmonth': $tu = $homNay->modify('first day of last month'); $den = $homNay->modify('last day of last month'); break;
    case 'custom':
        $tu  = $docNgay($_GET['from'] ?? '');
        $den = $docNgay($_GET['to'] ?? '');
        if ($tu && $den && $tu > $den) { [$tu, $den] = [$den, $tu]; }   // nhập ngược thì tự đảo
        break;
}
$fromVal = $tu ? $tu->format('Y-m-d') : '';
$toVal   = $den ? $den->format('Y-m-d') : '';

/** Điều kiện thời gian cho một cột ngày giờ. */
$dieuKienNgay = function ($cot, array &$params) use ($tu, $den) {
    $w = [];
    if ($tu)  { $w[] = "$cot >= ?"; $params[] = $tu->format('Y-m-d 00:00:00'); }
    if ($den) { $w[] = "$cot < ?";  $params[] = $den->modify('+1 day')->format('Y-m-d 00:00:00'); }
    return $w;
};

$kpi = ['revenue' => 0, 'tx_success' => 0, 'new_classes' => 0, 'matched' => 0, 'match_base' => 0, 'kyc_pending' => 0];
$tx_counts = ['' => 0, 'success' => 0, 'pending_24h' => 0, 'failed' => 0];
$tx_sums   = ['' => 0, 'success' => 0, 'pending_24h' => 0, 'failed' => 0];   // tổng tiền theo trạng thái
$allTx = [];
$transactions = [];
$pt = null;

try {
    // 1. Doanh thu (giao dịch thành công trong kỳ)
    $p = [];
    $w = array_merge(["status = 'success'"], $dieuKienNgay('tx_time', $p));
    $st = $conn->prepare('SELECT COALESCE(SUM(amount),0), COUNT(*) FROM transactions WHERE ' . implode(' AND ', $w));
    $st->execute($p);
    [$kpi['revenue'], $kpi['tx_success']] = $st->fetch(PDO::FETCH_NUM);

    // 2. Lớp mới & tỷ lệ ghép thành công (lớp tạo trong kỳ, không tính lớp chờ duyệt / bị từ chối)
    $p = [];
    $w = $dieuKienNgay('created_at', $p);
    $st = $conn->prepare("SELECT COUNT(*),
                                 SUM(status IN ('matched','completed')),
                                 SUM(status IN ('finding','matched','completed','cancelled'))
                          FROM classes" . ($w ? ' WHERE ' . implode(' AND ', $w) : ''));
    $st->execute($p);
    [$kpi['new_classes'], $kpi['matched'], $kpi['match_base']] = array_map('intval', $st->fetch(PDO::FETCH_NUM));

    // 3. Hồ sơ KYC đang chờ duyệt (việc cần làm ngay, không phụ thuộc kỳ lọc)
    $kpi['kyc_pending'] = (int) $conn->query("SELECT COUNT(*) FROM kyc_profiles WHERE status = 'pending'")->fetchColumn();

    // 4. Danh sách giao dịch theo bộ lọc
    $p = [];
    $w = $dieuKienNgay('t.tx_time', $p);
    if ($q !== '') {
        $w[] = '(t.tx_code LIKE ? OR c.class_code LIKE ? OR c.title LIKE ? OR tu.full_name LIKE ? OR tu.phone LIKE ?)';
        array_push($p, ...array_fill(0, 5, '%' . $q . '%'));
    }
    $sqlGoc = 'FROM transactions t
               JOIN classes c ON t.class_id = c.class_id
               JOIN tutors tu ON t.tutor_id = tu.tutor_id' . ($w ? ' WHERE ' . implode(' AND ', $w) : '');

    // Đếm theo trạng thái (cho các tab) – cùng bộ lọc thời gian + từ khóa
    $st = $conn->prepare("SELECT t.status, COUNT(*) AS so, COALESCE(SUM(t.amount),0) AS tien $sqlGoc GROUP BY t.status");
    $st->execute($p);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($tx_counts[$r['status']])) {
            $tx_counts[$r['status']] = (int) $r['so'];
            $tx_sums[$r['status']]   = (float) $r['tien'];
        }
        $tx_counts[''] += (int) $r['so'];
        $tx_sums['']   += (float) $r['tien'];
    }

    if ($status !== '') {
        $sqlGoc .= ($w ? ' AND ' : ' WHERE ') . 't.status = ?';
        $p[] = $status;
    }
    $st = $conn->prepare("SELECT t.*, c.class_code, c.title AS class_title, tu.full_name, tu.phone $sqlGoc ORDER BY t.tx_time DESC");
    $st->execute($p);
    $allTx = $st->fetchAll(PDO::FETCH_ASSOC);
    $pt = phan_trang($allTx, 10);
    $transactions = $pt['rows'];
} catch (PDOException $e) {
    error_log('Dashboard: ' . $e->getMessage());
    $error_msg = 'Không tải được dữ liệu tổng quan. Vui lòng thử lại sau.';
}

// ---- Tiện ích hiển thị ----
$tienVN  = function ($v) { return number_format((float) $v, 0, ',', '.') . ' VNĐ'; };
$tyLe    = $kpi['match_base'] > 0 ? round($kpi['matched'] / $kpi['match_base'] * 100, 1) : null;
$moTaKy  = $range === 'all' || (!$tu && !$den)
    ? 'toàn bộ thời gian'
    : ($tu ? $tu->format('d/m/Y') : '…') . ' – ' . ($den ? $den->format('d/m/Y') : '…');
$dangLoc = $range !== '30d' || $status !== '' || $q !== '';
$linkLoc = function (array $doi) use ($range, $fromVal, $toVal, $status, $q) {
    $base = ['view' => 'dashboard', 'range' => $range, 'status' => $status, 'q' => $q];
    if ($range === 'custom') { $base['from'] = $fromVal; $base['to'] = $toVal; }
    return 'admin.php?' . http_build_query(array_filter(array_merge($base, $doi), 'strlen'));
};
/** Tô đậm từ khóa tìm kiếm trong kết quả. */
$toDam = function ($text) use ($q, $db_e) {
    $text = (string) $text;
    if ($q === '') return $db_e($text);
    $parts = preg_split('/(' . preg_quote($q, '/') . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    foreach ($parts as $i => $part) $out .= $i % 2 ? '<mark>' . $db_e($part) . '</mark>' : $db_e($part);
    return $out;
};

/* ============================================================
 * XUẤT EXCEL: admin.php gọi file này với $XUAT_EXCEL = true (trước khi in HTML)
 * -> tạo file .xlsx theo đúng bộ lọc đang xem rồi dừng.
 * ============================================================ */
if (!empty($XUAT_EXCEL)) {
    $nhanTT  = array_map(function ($l) { return $l[0]; }, $db_tx_labels);
    $boLoc   = 'Kỳ: ' . $moTaKy
             . ($status !== '' ? ' · Trạng thái: ' . $nhanTT[$status] : '')
             . ($q !== '' ? ' · Từ khóa: "' . $q . '"' : '');
    $xuatLuc = 'Xuất lúc ' . (new DateTimeImmutable('now', $tz))->format('H:i d/m/Y')
             . ' bởi ' . ($_SESSION['full_name'] ?? 'quản trị viên');

    // --- Sheet 1: Tổng quan ---
    $tq = [
        [['v' => 'BÁO CÁO TỔNG QUAN & DOANH THU – TRUNG TÂM GIA SƯ VIETEDU', 's' => 'title']],
        [['v' => $boLoc, 's' => 'sub']],
        [['v' => $xuatLuc, 's' => 'sub']],
        [],
        [['v' => 'Chỉ số', 's' => 'head'], ['v' => 'Giá trị', 's' => 'head'], ['v' => 'Ghi chú', 's' => 'head']],
        [['v' => 'Doanh thu phí nhận lớp', 's' => 'label'], ['v' => (float) $kpi['revenue'], 's' => 'money'], ['v' => 'Tổng giao dịch thành công trong kỳ', 's' => 'cell']],
        [['v' => 'Số giao dịch thành công', 's' => 'label'], ['v' => (int) $kpi['tx_success'], 's' => 'number'], ['v' => '', 's' => 'cell']],
        [['v' => 'Lớp học mới', 's' => 'label'], ['v' => (int) $kpi['new_classes'], 's' => 'number'], ['v' => 'Lớp đăng trong kỳ', 's' => 'cell']],
        [['v' => 'Tỷ lệ ghép lớp thành công', 's' => 'label'],
         $kpi['match_base'] > 0 ? ['v' => $kpi['matched'] / $kpi['match_base'], 's' => 'percent'] : ['v' => 'Chưa có dữ liệu', 's' => 'cell'],
         ['v' => $kpi['matched'] . ' / ' . $kpi['match_base'] . ' lớp đã duyệt', 's' => 'cell']],
        [['v' => 'Hồ sơ KYC chờ duyệt', 's' => 'label'], ['v' => (int) $kpi['kyc_pending'], 's' => 'number'], ['v' => 'Tại thời điểm xuất', 's' => 'cell']],
        [],
        [['v' => 'Trạng thái giao dịch', 's' => 'head'], ['v' => 'Số giao dịch', 's' => 'head'], ['v' => 'Tổng tiền', 's' => 'head']],
    ];
    foreach ($db_tx_labels as $k => $lb) {
        $tq[] = [['v' => $lb[0], 's' => 'cell'], ['v' => $tx_counts[$k], 's' => 'number'], ['v' => $tx_sums[$k], 's' => 'money']];
    }
    // Dòng 13–15 là 3 trạng thái ở trên
    $tq[] = [['v' => 'Tổng cộng', 's' => 'total_label'], ['v' => '=SUM(B13:B15)', 's' => 'total_label'], ['v' => '=SUM(C13:C15)', 's' => 'money_total']];

    // --- Sheet 2: Danh sách giao dịch ---
    $dauBang = ['STT', 'Mã GD', 'Mã lớp', 'Tên lớp', 'Gia sư', 'SĐT gia sư', 'Số tiền', 'Thời gian tạo', 'Xác nhận lúc', 'Trạng thái'];
    $gd = [
        [['v' => 'DANH SÁCH GIAO DỊCH VIETQR – PHÍ NHẬN LỚP', 's' => 'title']],
        [['v' => $boLoc . ' · ' . count($allTx) . ' giao dịch', 's' => 'sub']],
        [['v' => $xuatLuc, 's' => 'sub']],
        array_map(function ($h) { return ['v' => $h, 's' => 'head']; }, $dauBang),
    ];
    $ngayGio = function ($s) use ($tz) {
        if (!$s) return ['v' => '', 's' => 'cell'];
        try { return ['v' => new DateTimeImmutable($s, $tz), 's' => 'datetime']; } catch (Exception $e) { return ['v' => $s, 's' => 'cell']; }
    };
    foreach ($allTx as $i => $tx) {
        $gd[] = [
            ['v' => $i + 1, 's' => 'number'],
            ['v' => $tx['tx_code'], 's' => 'cell'],
            ['v' => $tx['class_code'], 's' => 'cell'],
            ['v' => $tx['class_title'], 's' => 'cell'],
            ['v' => $tx['full_name'], 's' => 'cell'],
            ['v' => (string) $tx['phone'], 's' => 'cell'],          // giữ số 0 đầu
            ['v' => (float) $tx['amount'], 's' => 'money'],
            $ngayGio($tx['tx_time']),
            $ngayGio($tx['paid_confirmed_at'] ?? null),
            ['v' => $nhanTT[$tx['status']] ?? $tx['status'], 's' => 'cell'],
        ];
    }
    $dau  = 5;
    $cuoi = max($dau, $dau + count($allTx) - 1);
    $mergeTong = [];
    if ($allTx) {
        $trong = array_fill(0, 5, ['v' => '', 's' => 'total_label']);
        $gd[] = array_merge([['v' => 'Tổng cộng', 's' => 'total_label']], $trong, [['v' => "=SUM(G$dau:G$cuoi)", 's' => 'money_total']]);
        $gd[] = array_merge([['v' => 'Trong đó đã thu (thành công)', 's' => 'total_label']], $trong,
                            [['v' => "=SUMIF(J$dau:J$cuoi,\"{$nhanTT['success']}\",G$dau:G$cuoi)", 's' => 'money_total']]);
        $mergeTong = ['A' . ($cuoi + 1) . ':F' . ($cuoi + 1), 'A' . ($cuoi + 2) . ':F' . ($cuoi + 2)];
    } else {
        $gd[] = [['v' => 'Không có giao dịch nào khớp bộ lọc.', 's' => 'sub']];
    }

    $tenFile = 'VietEdu_giao-dich_' . ($tu ? $tu->format('Ymd') : 'tu-dau') . '-' . ($den ? $den->format('Ymd') : 'nay') . '.xlsx';
    xlsx_tai_ve($tenFile, [
        ['name' => 'Tổng quan', 'cols' => [34, 22, 36], 'rows' => $tq, 'merge' => ['A1:C1', 'A2:C2', 'A3:C3']],
        ['name' => 'Giao dịch', 'cols' => [6, 12, 11, 34, 24, 14, 16, 17, 17, 22], 'rows' => $gd,
         'freeze' => 'A5', 'filter' => $allTx ? "A4:J$cuoi" : 'A4:J4', 'merge' => array_merge(['A1:J1', 'A2:J2', 'A3:J3'], $mergeTong)],
    ]);
}
?>
<section id="view-dashboard" class="content-view active">
    <div class="view-header">
        <h2>Tổng Quan Quản Lý &amp; Doanh Thu</h2>
        <a class="btn-excel" href="<?= $db_e($linkLoc(['export' => 'xlsx'])) ?>" style="text-decoration:none"
           title="Tải về <?= $tx_counts[$status] ?> giao dịch theo bộ lọc đang chọn">
            <i class="fa-solid fa-file-excel"></i> Xuất tệp Excel (.xlsx)
        </a>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="db-alert"><i class="fa-solid fa-triangle-exclamation"></i> <?= $db_e($error_msg) ?></div>
    <?php endif; ?>

    <!-- ===== BỘ LỌC & TÌM KIẾM ===== -->
    <form method="get" action="admin.php" class="db-filter" id="dbFilter">
        <input type="hidden" name="view" value="dashboard">
        <div class="db-field db-field-search">
            <label for="db-q">Tìm kiếm giao dịch</label>
            <div class="db-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="db-q" name="q" value="<?= $db_e($q) ?>" maxlength="100"
                       placeholder="Mã GD, mã lớp, tên lớp, tên hoặc SĐT gia sư…">
            </div>
        </div>
        <div class="db-field">
            <label for="db-range">Khoảng thời gian</label>
            <select id="db-range" name="range">
                <?php foreach ($db_ranges as $k => $lb): ?>
                    <option value="<?= $k ?>" <?= $k === $range ? 'selected' : '' ?>><?= $lb ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="db-field db-dates" id="db-dates" <?= $range === 'custom' ? '' : 'hidden' ?>>
            <label for="db-from">Từ ngày – đến ngày</label>
            <div class="db-date-pair">
                <input type="date" id="db-from" name="from" value="<?= $db_e($fromVal) ?>" max="<?= $homNay->format('Y-m-d') ?>">
                <span>–</span>
                <input type="date" id="db-to" name="to" value="<?= $db_e($toVal) ?>" max="<?= $homNay->format('Y-m-d') ?>" aria-label="Đến ngày">
            </div>
        </div>
        <div class="db-field">
            <label for="db-status">Trạng thái giao dịch</label>
            <select id="db-status" name="status">
                <option value="">Tất cả trạng thái</option>
                <?php foreach ($db_tx_labels as $k => $lb): ?>
                    <option value="<?= $k ?>" <?= $k === $status ? 'selected' : '' ?>><?= $lb[0] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="db-actions">
            <button type="submit" class="db-btn db-btn-primary"><i class="fa-solid fa-filter"></i> Lọc</button>
            <?php if ($dangLoc): ?>
                <a href="admin.php?view=dashboard" class="db-btn db-btn-ghost"><i class="fa-solid fa-rotate-left"></i> Xóa lọc</a>
            <?php endif; ?>
        </div>
    </form>

    <p class="db-period">
        Số liệu trong kỳ: <b><?= $db_e($moTaKy) ?></b>
        <?php if ($status !== ''): ?> · Trạng thái: <b><?= $db_e($db_tx_labels[$status][0]) ?></b><?php endif; ?>
        <?php if ($q !== ''): ?> · Từ khóa: <b>“<?= $db_e($q) ?>”</b><?php endif; ?>
    </p>

    <!-- ===== CHỈ SỐ NHANH (theo kỳ lọc) ===== -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <i class="fa-solid fa-money-bill-wave kpi-icon"></i>
            <div>
                <div class="kpi-title">Doanh thu phí nhận lớp</div>
                <div class="kpi-value"><?= $tienVN($kpi['revenue']) ?></div>
                <div class="db-kpi-sub"><?= (int) $kpi['tx_success'] ?> giao dịch thành công</div>
            </div>
        </div>
        <div class="kpi-card">
            <i class="fa-solid fa-door-open kpi-icon"></i>
            <div>
                <div class="kpi-title">Lớp học mới</div>
                <div class="kpi-value"><?= (int) $kpi['new_classes'] ?> lớp</div>
                <div class="db-kpi-sub">Đăng trong kỳ đã chọn</div>
            </div>
        </div>
        <div class="kpi-card">
            <i class="fa-solid fa-handshake kpi-icon"></i>
            <div>
                <div class="kpi-title">Tỷ lệ ghép lớp thành công</div>
                <div class="kpi-value"><?= $tyLe === null ? '—' : str_replace('.', ',', $tyLe) . '%' ?></div>
                <div class="db-kpi-sub"><?= $kpi['matched'] ?> / <?= $kpi['match_base'] ?> lớp đã duyệt</div>
            </div>
        </div>
        <a class="kpi-card db-kpi-link" href="admin.php?view=kyc&status=pending" title="Mở danh sách hồ sơ chờ duyệt">
            <i class="fa-solid fa-user-clock kpi-icon"></i>
            <div>
                <div class="kpi-title">Hồ sơ KYC chờ duyệt</div>
                <div class="kpi-value"><?= $kpi['kyc_pending'] ?> hồ sơ</div>
                <div class="db-kpi-sub">Hiện tại · bấm để duyệt <i class="fa-solid fa-arrow-right"></i></div>
            </div>
        </a>
    </div>

    <!-- ===== DANH SÁCH GIAO DỊCH ===== -->
    <div class="data-table-card">
        <div class="db-table-head">
            <h3>Giao dịch VietQR</h3>
            <nav class="db-tabs" aria-label="Lọc theo trạng thái">
                <a href="<?= $db_e($linkLoc(['status' => ''])) ?>" class="<?= $status === '' ? 'active' : '' ?>">Tất cả <span><?= $tx_counts[''] ?></span></a>
                <?php foreach ($db_tx_labels as $k => $lb): ?>
                    <a href="<?= $db_e($linkLoc(['status' => $k])) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= $lb[0] ?> <span><?= $tx_counts[$k] ?></span></a>
                <?php endforeach; ?>
            </nav>
        </div>
        <div style="overflow-x:auto">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Mã GD</th>
                    <th>Lớp</th>
                    <th>Gia sư</th>
                    <th style="text-align:right">Số tiền</th>
                    <th>Thời gian</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions): ?>
                    <?php foreach ($transactions as $tx): $lb = $db_tx_labels[$tx['status']] ?? [$tx['status'], 'badge-warning']; ?>
                        <tr>
                            <td><strong><?= $toDam($tx['tx_code']) ?></strong></td>
                            <td><?= $toDam($tx['class_code']) ?><div class="db-muted"><?= $toDam($tx['class_title']) ?></div></td>
                            <td><?= $toDam($tx['full_name']) ?><div class="db-muted"><?= $toDam($tx['phone']) ?></div></td>
                            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= $tienVN($tx['amount']) ?></td>
                            <td><?= date('H:i · d/m/Y', strtotime($tx['tx_time'])) ?></td>
                            <td><span class="badge <?= $lb[1] ?>"><?= $db_e($lb[0]) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="db-empty">
                        <i class="fa-regular fa-folder-open"></i>
                        Không có giao dịch nào khớp bộ lọc.
                        <?php if ($dangLoc): ?><a href="admin.php?view=dashboard">Xóa bộ lọc</a><?php endif; ?>
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
        <?= $pt ? phan_trang_html($pt, 'giao dịch') : '' ?>
    </div>
</section>

<script>
// Chọn "Tùy chọn ngày…" thì hiện ô ngày; chọn mốc có sẵn thì lọc ngay. Đổi trạng thái cũng lọc ngay.
(function () {
    var f = document.getElementById('dbFilter');
    var r = document.getElementById('db-range'), box = document.getElementById('db-dates');
    r.addEventListener('change', function () {
        box.hidden = r.value !== 'custom';
        if (r.value === 'custom') { document.getElementById('db-from').focus(); return; }
        f.submit();
    });
    document.getElementById('db-status').addEventListener('change', function () { f.submit(); });
    f.addEventListener('submit', function () {
        // Không gửi các ô trống để URL gọn
        f.querySelectorAll('input, select').forEach(function (el) { if (!el.value && el.name !== 'view') el.disabled = true; });
        if (r.value !== 'custom') { document.getElementById('db-from').disabled = true; document.getElementById('db-to').disabled = true; }
    });
})();
</script>
