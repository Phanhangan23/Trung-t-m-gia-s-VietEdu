<?php
/**
 * PHÂN HỆ 3 – KIỂM DUYỆT & CÔNG BỐ LỚP HỌC
 * Luồng: Phụ huynh gửi lớp (Chờ duyệt) -> Admin kiểm tra / sửa nội dung -> Công bố (Đang tìm gia sư)
 *        -> Ghép gia sư (Đã giao lớp – thực hiện ở màn Ghép lớp) -> Hoàn thành / Hủy.
 */

if (!function_exists('cls_e')) {
    function cls_e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    /** PRG: header đã được gửi trước khi include view nên chuyển hướng bằng JS. */
    function cls_redirect(array $params)
    {
        $url = 'admin.php?' . http_build_query(array_merge(['view' => 'classes'], $params));
        echo '<script>location.replace(' . json_encode($url) . ');</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . cls_e($url) . '"></noscript>';
    }

    function cls_len($s)
    {
        return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
    }

    /** Chuẩn hoá & kiểm tra dữ liệu form lớp học. */
    function cls_validate(PDO $conn, array $in)
    {
        $d = [
            'title'        => trim((string) ($in['title'] ?? '')),
            'subject'      => trim((string) ($in['subject'] ?? '')),
            'grade'        => trim((string) ($in['grade'] ?? '')),
            'location'     => trim((string) ($in['location'] ?? '')),
            'schedule'     => trim((string) ($in['schedule'] ?? '')),
            'requirements' => trim((string) ($in['requirements'] ?? '')),
            'tuition_fee'  => preg_replace('/[^\d]/', '', (string) ($in['tuition_fee'] ?? '')),
            'parent_id'    => (string) ($in['parent_id'] ?? ''),
        ];
        $err = [];

        $l = cls_len($d['title']);
        if ($l < 5 || $l > 150) $err['title'] = 'Tiêu đề phải từ 5 đến 150 ký tự.';
        if ($d['subject'] === '' || cls_len($d['subject']) > 50) $err['subject'] = 'Môn học bắt buộc, tối đa 50 ký tự.';
        if ($d['grade'] === '' || cls_len($d['grade']) > 20) $err['grade'] = 'Khối lớp bắt buộc, tối đa 20 ký tự.';
        if (cls_len($d['location']) > 100) $err['location'] = 'Khu vực tối đa 100 ký tự.';
        if (cls_len($d['schedule']) > 100) $err['schedule'] = 'Lịch học tối đa 100 ký tự.';
        if (cls_len($d['requirements']) > 2000) $err['requirements'] = 'Yêu cầu tối đa 2000 ký tự.';

        if ($d['tuition_fee'] === '') {
            $d['tuition_fee'] = null;
        } elseif ((float) $d['tuition_fee'] > 99999999) {
            $err['tuition_fee'] = 'Học phí vượt quá giới hạn cho phép.';
        }

        if ($d['parent_id'] === '') {
            $d['parent_id'] = null;
        } else {
            $st = $conn->prepare('SELECT COUNT(*) FROM parents WHERE parent_id = ?');
            $st->execute([(int) $d['parent_id']]);
            if (!$st->fetchColumn()) $err['parent_id'] = 'Phụ huynh không tồn tại.';
            $d['parent_id'] = (int) $d['parent_id'];
        }
        return [$d, $err];
    }

    /** Điều kiện tối thiểu để một lớp được công bố cho gia sư xem. */
    function cls_publish_problems(array $c)
    {
        $p = [];
        if (trim((string) $c['location']) === '') $p[] = 'khu vực';
        if (trim((string) $c['schedule']) === '') $p[] = 'lịch học';
        if ((float) $c['tuition_fee'] <= 0) $p[] = 'học phí';
        return $p;
    }

    function cls_next_code(PDO $conn)
    {
        $max = (int) $conn->query("SELECT MAX(CAST(SUBSTRING(class_code, 4) AS UNSIGNED)) FROM classes WHERE class_code LIKE 'VE-%'")->fetchColumn();
        return 'VE-' . max(1001, $max + 1);
    }
}

$cls_labels = [
    'pending'   => ['Chờ duyệt', 'badge-warning'],
    'finding'   => ['Đang tìm gia sư', 'badge-info'],
    'matched'   => ['Đã giao lớp', 'badge-success'],
    'completed' => ['Hoàn thành', 'badge-muted'],
    'cancelled' => ['Đã hủy', 'badge-danger'],
    'rejected'  => ['Bị từ chối', 'badge-danger'],
];

// ---------------------------------------------------------------
// Bổ sung trạng thái "pending" (Chờ duyệt) cho bảng classes nếu chưa có.
// Giữ nguyên DEFAULT 'finding' để không ảnh hưởng các chức năng khác.
// ---------------------------------------------------------------
$cls_has_pending = false;
try {
    $colType = (string) $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes' AND COLUMN_NAME = 'status'")->fetchColumn();
    if ($colType !== '' && strpos($colType, "'pending'") === false) {
        $conn->exec("ALTER TABLE classes MODIFY status ENUM('pending','finding','matched','completed','cancelled') DEFAULT 'finding'");
        $colType .= "'pending'";
    }
    $cls_has_pending = strpos($colType, "'pending'") !== false;
} catch (PDOException $e) {
    $cls_has_pending = false;
}
if (!$cls_has_pending) unset($cls_labels['pending']);

// ---------------------------------------------------------------
// XỬ LÝ THAO TÁC (POST)
// ---------------------------------------------------------------
$cls_form_errors = [];
$cls_form_values = null;
$cls_form_mode   = null; // 'create' | 'edit'
$cls_flash_now   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cls_action'])) {
    $action  = $_POST['cls_action'];
    $classId = (int) ($_POST['class_id'] ?? 0);
    $back    = array_filter([
        'status' => $_GET['status'] ?? '',
        'q'      => $_GET['q'] ?? '',
    ], 'strlen');

    try {
        $cur = null;
        if ($classId > 0) {
            $st = $conn->prepare('SELECT * FROM classes WHERE class_id = ?');
            $st->execute([$classId]);
            $cur = $st->fetch(PDO::FETCH_ASSOC);
        }

        if (in_array($action, ['update', 'publish', 'unpublish', 'cancel', 'reopen', 'complete', 'delete'], true) && !$cur) {
            cls_redirect($back + ['type' => 'error', 'msg' => 'Không tìm thấy lớp học.']);
            return;
        }

        switch ($action) {
            case 'create':
            case 'update':
                list($data, $cls_form_errors) = cls_validate($conn, $_POST);
                $publishNow = ($action === 'create' && (!empty($_POST['publish_now']) || !$cls_has_pending));
                if ($publishNow && !$cls_form_errors) {
                    $miss = cls_publish_problems($data);
                    if ($miss) $cls_form_errors['general'] = 'Muốn công bố ngay cần nhập đủ: ' . implode(', ', $miss) . '.';
                }
                if ($cls_form_errors) {
                    $cls_form_values = $data + ['class_id' => $classId, 'class_code' => $cur['class_code'] ?? ''];
                    $cls_form_mode   = $action === 'create' ? 'create' : 'edit';
                    break;
                }
                if ($action === 'create') {
                    $code = cls_next_code($conn);
                    $st = $conn->prepare('INSERT INTO classes (class_code, title, subject, grade, location, schedule, requirements, tuition_fee, parent_id, status)
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $st->execute([$code, $data['title'], $data['subject'], $data['grade'], $data['location'], $data['schedule'],
                                  $data['requirements'], $data['tuition_fee'], $data['parent_id'], $publishNow ? 'finding' : 'pending']);
                    cls_redirect($back + ['msg' => "Đã tạo lớp $code" . ($publishNow ? ' và công bố lên hệ thống.' : ' (đang chờ duyệt).')]);
                } else {
                    if ($cur['status'] === 'finding' && cls_publish_problems($data)) {
                        $cls_form_errors['general'] = 'Lớp đang công bố, không được để trống: ' . implode(', ', cls_publish_problems($data)) . '.';
                        $cls_form_values = $data + ['class_id' => $classId, 'class_code' => $cur['class_code']];
                        $cls_form_mode = 'edit';
                        break;
                    }
                    $st = $conn->prepare('UPDATE classes SET title=?, subject=?, grade=?, location=?, schedule=?, requirements=?, tuition_fee=?, parent_id=? WHERE class_id=?');
                    $st->execute([$data['title'], $data['subject'], $data['grade'], $data['location'], $data['schedule'],
                                  $data['requirements'], $data['tuition_fee'], $data['parent_id'], $classId]);
                    cls_redirect($back + ['msg' => 'Đã cập nhật nội dung lớp ' . $cur['class_code'] . '.']);
                }
                return;

            case 'publish':
            case 'reopen':
                $from = $action === 'publish' ? 'pending' : 'cancelled';
                if ($cur['status'] !== $from) {
                    cls_redirect($back + ['type' => 'error', 'msg' => 'Trạng thái lớp đã thay đổi, vui lòng thử lại.']);
                    return;
                }
                $miss = cls_publish_problems($cur);
                if ($miss) {
                    cls_redirect($back + ['type' => 'error', 'edit' => $classId,
                        'msg' => 'Chưa thể công bố ' . $cur['class_code'] . ': thiếu ' . implode(', ', $miss) . '. Hãy bổ sung rồi duyệt lại.']);
                    return;
                }
                $st = $conn->prepare("UPDATE classes SET status='finding' WHERE class_id=? AND status=?");
                $st->execute([$classId, $from]);
                cls_redirect($back + ['msg' => 'Đã duyệt & công bố lớp ' . $cur['class_code'] . '. Gia sư đã có thể nhìn thấy và đăng ký nhận lớp.']);
                return;

            case 'unpublish':
                if (!$cls_has_pending || $cur['status'] !== 'finding') {
                    cls_redirect($back + ['type' => 'error', 'msg' => 'Chỉ gỡ được lớp đang ở trạng thái "Đang tìm gia sư".']);
                    return;
                }
                $conn->prepare("UPDATE classes SET status='pending' WHERE class_id=? AND status='finding'")->execute([$classId]);
                cls_redirect($back + ['msg' => 'Đã gỡ lớp ' . $cur['class_code'] . ' khỏi bảng tin, chuyển về Chờ duyệt.']);
                return;

            case 'cancel':
                if (!in_array($cur['status'], ['pending', 'finding'], true)) {
                    cls_redirect($back + ['type' => 'error', 'msg' => 'Chỉ hủy được lớp chưa giao cho gia sư.']);
                    return;
                }
                $conn->beginTransaction();
                $conn->prepare("UPDATE classes SET status='cancelled' WHERE class_id=?")->execute([$classId]);
                $conn->prepare("UPDATE class_matching SET status='rejected' WHERE class_id=? AND status IN ('applied','interviewing')")->execute([$classId]);
                $conn->commit();
                cls_redirect($back + ['msg' => 'Đã hủy lớp ' . $cur['class_code'] . '. Các hồ sơ ứng tuyển đã được đóng.']);
                return;

            case 'complete':
                if ($cur['status'] !== 'matched') {
                    cls_redirect($back + ['type' => 'error', 'msg' => 'Chỉ lớp đã giao gia sư mới có thể đánh dấu hoàn thành.']);
                    return;
                }
                $conn->prepare("UPDATE classes SET status='completed' WHERE class_id=? AND status='matched'")->execute([$classId]);
                cls_redirect($back + ['msg' => 'Lớp ' . $cur['class_code'] . ' đã được đánh dấu hoàn thành.']);
                return;

            case 'delete':
                $st = $conn->prepare('SELECT COUNT(*) FROM transactions WHERE class_id = ?');
                $st->execute([$classId]);
                $soGiaoDich = (int) $st->fetchColumn();
                if (!in_array($cur['status'], ['pending', 'cancelled'], true) || $soGiaoDich > 0) {
                    cls_redirect($back + ['type' => 'error', 'msg' => 'Không thể xóa ' . $cur['class_code'] . ': lớp đã phát sinh giao dịch hoặc đang hoạt động. Hãy dùng chức năng Hủy.']);
                    return;
                }
                $conn->prepare('DELETE FROM classes WHERE class_id = ?')->execute([$classId]);
                cls_redirect($back + ['msg' => 'Đã xóa lớp ' . $cur['class_code'] . '.']);
                return;
        }
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $cls_flash_now = ['type' => 'error', 'msg' => ve_loi_csdl($e, 'lưu thay đổi lớp học')];
    }
}

// ---------------------------------------------------------------
// TRUY VẤN DỮ LIỆU HIỂN THỊ
// ---------------------------------------------------------------
$cls_filter = $_GET['status'] ?? '';
if (!isset($cls_labels[$cls_filter])) $cls_filter = '';
$cls_q = trim((string) ($_GET['q'] ?? ''));

$classes = [];
$cls_counts = array_fill_keys(array_keys($cls_labels), 0);
$parents = [];
$error = null;

try {
    foreach ($conn->query('SELECT status, COUNT(*) AS n FROM classes GROUP BY status') as $row) {
        if (isset($cls_counts[$row['status']])) $cls_counts[$row['status']] = (int) $row['n'];
    }

    $where = [];
    $params = [];
    if ($cls_filter !== '') {
        $where[] = 'c.status = ?';
        $params[] = $cls_filter;
    }
    if ($cls_q !== '') {
        $where[] = '(c.class_code LIKE ? OR c.title LIKE ? OR c.subject LIKE ? OR c.location LIKE ? OR p.full_name LIKE ? OR p.phone LIKE ?)';
        $params = array_merge($params, array_fill(0, 6, '%' . $cls_q . '%'));
    }
    $sql = "SELECT c.*, p.full_name AS parent_name, p.phone AS parent_phone,
                   (SELECT COUNT(*) FROM class_matching m WHERE m.class_id = c.class_id AND m.status IN ('applied','interviewing')) AS applicants,
                   (SELECT t.full_name FROM class_matching m JOIN tutors t ON t.tutor_id = m.tutor_id
                     WHERE m.class_id = c.class_id AND m.status = 'matched' LIMIT 1) AS matched_tutor
            FROM classes c
            LEFT JOIN parents p ON c.parent_id = p.parent_id"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY FIELD(c.status, 'pending', 'finding', 'matched', 'completed', 'cancelled'), c.class_id DESC";
    $st = $conn->prepare($sql);
    $st->execute($params);
    $classes = $st->fetchAll(PDO::FETCH_ASSOC);

    // Phân trang 10 lớp / trang
    $pt = phan_trang($classes, 10);
    $classes = $pt['rows'];

    $parents = $conn->query('SELECT parent_id, full_name, phone FROM parents ORDER BY full_name')->fetchAll(PDO::FETCH_ASSOC);

    // Mở form sửa qua ?edit=ID
    if ($cls_form_mode === null && isset($_GET['edit'])) {
        $st = $conn->prepare('SELECT * FROM classes WHERE class_id = ?');
        $st->execute([(int) $_GET['edit']]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $cls_form_values = $row;
            $cls_form_mode = 'edit';
        }
    }
    if ($cls_form_mode === null && isset($_GET['new'])) {
        $cls_form_mode = 'create';
        $cls_form_values = [];
    }
} catch (PDOException $e) {
    $error = ve_loi_csdl($e, 'tải danh sách lớp học');
}

$cls_flash = $cls_flash_now;
if (!$cls_flash && isset($_GET['msg'])) {
    $cls_flash = ['type' => ($_GET['type'] ?? '') === 'error' ? 'error' : 'success', 'msg' => (string) $_GET['msg']];
}
$cls_qs = array_filter(['status' => $cls_filter, 'q' => $cls_q], 'strlen');
$cls_action_url = 'admin.php?' . http_build_query(['view' => 'classes'] + $cls_qs);
$fv = function ($k) use (&$cls_form_values) {
    return cls_e($cls_form_values[$k] ?? '');
};
$ferr = function ($k) use (&$cls_form_errors) {
    return isset($cls_form_errors[$k]) ? '<div class="ve-err">' . cls_e($cls_form_errors[$k]) . '</div>' : '';
};
?>
<style>
    .ve-flash{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px}
    .ve-flash.success{background:#EAF7ED;color:#2E7D32;border:1px solid #C8E6C9}
    .ve-flash.error{background:#FDECEA;color:#C62828;border:1px solid #F5C6CB}
    .ve-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px}
    .ve-kpi{background:#fff;border-radius:10px;padding:14px 16px;box-shadow:0 1px 3px rgba(0,0,0,.06);text-decoration:none;color:inherit;border:2px solid transparent}
    .ve-kpi.active{border-color:#2563eb}
    .ve-kpi .num{font-size:22px;font-weight:700}
    .ve-kpi .lbl{font-size:12px;color:#6b7280}
    .ve-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:14px}
    .ve-toolbar form{display:flex;gap:6px;flex-wrap:wrap}
    .ve-toolbar input[type=text]{padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;min-width:240px}
    .ve-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
    .ve-field label{display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#374151}
    .ve-field input,.ve-field select,.ve-field textarea{width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font:inherit;font-size:14px;box-sizing:border-box}
    .ve-field.full{grid-column:1/-1}
    .ve-err{color:#C62828;font-size:12px;margin-top:3px}
    .ve-btn{display:inline-flex;align-items:center;gap:5px;border:none;border-radius:5px;padding:6px 10px;font-size:12px;cursor:pointer;text-decoration:none;color:#fff;background:#2563eb;white-space:nowrap;font-family:inherit}
    .ve-btn.green{background:#27ae60}.ve-btn.red{background:#e74c3c}.ve-btn.gray{background:#6b7280}.ve-btn.orange{background:#f39c12}
    .ve-btn.outline{background:#fff;color:#2563eb;border:1px solid #2563eb}
    .ve-btn.lg{padding:8px 14px;font-size:13px}
    .ve-actions{display:flex;flex-wrap:wrap;gap:4px}
    .ve-actions form{margin:0}
    .ve-muted{color:#6b7280;font-size:12px}
    .ve-table-wrap{overflow-x:auto}
    .badge-info{background:#E8F1FD;color:#1565C0}.badge-danger{background:#FDECEA;color:#C62828}.badge-muted{background:#F1F3F5;color:#495057}
</style>

<section id="view-classes" class="content-view active">
    <div class="view-header">
        <h2>Phân hệ 3: Duyệt & Công Bố Lớp Học Mới</h2>
        <a class="ve-btn lg" href="admin.php?<?= cls_e(http_build_query(['view' => 'classes', 'new' => 1] + $cls_qs)) ?>"><i class="fa-solid fa-plus"></i> Thêm lớp mới</a>
    </div>

    <?php if ($error): ?><div class="ve-flash error"><?= cls_e($error) ?></div><?php endif; ?>
    <?php if ($cls_flash): ?><div class="ve-flash <?= $cls_flash['type'] ?>"><?= cls_e($cls_flash['msg']) ?></div><?php endif; ?>
    <?php if (!$cls_has_pending): ?>
        <div class="ve-flash error">CSDL chưa hỗ trợ trạng thái "Chờ duyệt" (không có quyền ALTER bảng classes). Lớp mới sẽ được công bố trực tiếp.</div>
    <?php endif; ?>

    <!-- Thống kê theo trạng thái (bấm để lọc) -->
    <div class="ve-kpis">
        <a class="ve-kpi <?= $cls_filter === '' ? 'active' : '' ?>" href="admin.php?<?= cls_e(http_build_query(array_filter(['view' => 'classes', 'q' => $cls_q], 'strlen'))) ?>">
            <div class="num"><?= array_sum($cls_counts) ?></div><div class="lbl">Tất cả lớp</div>
        </a>
        <?php foreach ($cls_labels as $key => $lb): ?>
            <a class="ve-kpi <?= $cls_filter === $key ? 'active' : '' ?>" href="admin.php?<?= cls_e(http_build_query(array_filter(['view' => 'classes', 'status' => $key, 'q' => $cls_q], 'strlen'))) ?>">
                <div class="num"><?= $cls_counts[$key] ?></div><div class="lbl"><?= $lb[0] ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($cls_form_mode): ?>
        <!-- FORM THÊM / SỬA LỚP -->
        <div class="data-table-card" id="cls-form">
            <h3><?= $cls_form_mode === 'create' ? 'Thêm lớp học mới' : 'Kiểm duyệt nội dung lớp ' . $fv('class_code') ?></h3>
            <?php if (isset($cls_form_errors['general'])): ?><div class="ve-flash error"><?= cls_e($cls_form_errors['general']) ?></div><?php endif; ?>
            <form method="post" action="<?= cls_e($cls_action_url) ?>">
                <input type="hidden" name="cls_action" value="<?= $cls_form_mode === 'create' ? 'create' : 'update' ?>">
                <input type="hidden" name="class_id" value="<?= (int) ($cls_form_values['class_id'] ?? 0) ?>">
                <div class="ve-form-grid">
                    <div class="ve-field full">
                        <label>Tiêu đề lớp *</label>
                        <input type="text" name="title" maxlength="150" required value="<?= $fv('title') ?>" placeholder="VD: Toán 9 ôn thi vào 10">
                        <?= $ferr('title') ?>
                    </div>
                    <div class="ve-field">
                        <label>Môn học *</label>
                        <input type="text" name="subject" maxlength="50" required value="<?= $fv('subject') ?>" placeholder="VD: Toán, Tiếng Anh">
                        <?= $ferr('subject') ?>
                    </div>
                    <div class="ve-field">
                        <label>Khối / Lớp *</label>
                        <input type="text" name="grade" maxlength="20" required value="<?= $fv('grade') ?>" placeholder="VD: 9">
                        <?= $ferr('grade') ?>
                    </div>
                    <div class="ve-field">
                        <label>Học phí / tháng (VNĐ)</label>
                        <input type="text" name="tuition_fee" inputmode="numeric" value="<?= (string) ($cls_form_values['tuition_fee'] ?? '') !== '' ? number_format((float) $cls_form_values['tuition_fee'], 0, ',', '.') : '' ?>" placeholder="VD: 2.400.000">
                        <?= $ferr('tuition_fee') ?>
                    </div>
                    <div class="ve-field">
                        <label>Khu vực</label>
                        <input type="text" name="location" maxlength="100" value="<?= $fv('location') ?>" placeholder="VD: Cầu Giấy">
                        <?= $ferr('location') ?>
                    </div>
                    <div class="ve-field">
                        <label>Lịch học</label>
                        <input type="text" name="schedule" maxlength="100" value="<?= $fv('schedule') ?>" placeholder="VD: 3 buổi/tuần (Tối T2-T4-T6)">
                        <?= $ferr('schedule') ?>
                    </div>
                    <div class="ve-field">
                        <label>Phụ huynh</label>
                        <select name="parent_id">
                            <option value="">-- Không gắn phụ huynh --</option>
                            <?php foreach ($parents as $p): ?>
                                <option value="<?= $p['parent_id'] ?>" <?= (string) ($cls_form_values['parent_id'] ?? '') === (string) $p['parent_id'] ? 'selected' : '' ?>>
                                    <?= cls_e($p['full_name'] . ' – ' . $p['phone']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $ferr('parent_id') ?>
                    </div>
                    <div class="ve-field full">
                        <label>Yêu cầu đối với gia sư</label>
                        <textarea name="requirements" rows="3" maxlength="2000" placeholder="VD: Sinh viên Sư phạm Toán, nữ, có kinh nghiệm"><?= $fv('requirements') ?></textarea>
                        <?= $ferr('requirements') ?>
                    </div>
                </div>
                <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;align-items:center">
                    <?php if ($cls_form_mode === 'create'): ?>
                        <?php if ($cls_has_pending): ?>
                            <button class="ve-btn lg gray" type="submit"><i class="fa-solid fa-floppy-disk"></i> Lưu (chờ duyệt)</button>
                        <?php endif; ?>
                        <button class="ve-btn lg green" type="submit" name="publish_now" value="1"><i class="fa-solid fa-bullhorn"></i> Lưu & công bố ngay</button>
                    <?php else: ?>
                        <button class="ve-btn lg" type="submit"><i class="fa-solid fa-floppy-disk"></i> Lưu thay đổi</button>
                    <?php endif; ?>
                    <a class="ve-btn lg outline" href="<?= cls_e($cls_action_url) ?>">Đóng</a>
                </div>
            </form>
        </div>
        <script>document.getElementById('cls-form').scrollIntoView({behavior: 'smooth', block: 'start'});</script>
    <?php endif; ?>

    <div class="data-table-card">
        <div class="ve-toolbar">
            <h3 style="margin:0">Danh Sách Lớp Học Trong Hệ Thống (<?= isset($pt) ? $pt['total'] : count($classes) ?>)</h3>
            <form method="get" action="admin.php">
                <input type="hidden" name="view" value="classes">
                <?php if ($cls_filter !== ''): ?><input type="hidden" name="status" value="<?= cls_e($cls_filter) ?>"><?php endif; ?>
                <input type="text" name="q" value="<?= cls_e($cls_q) ?>" placeholder="Tìm mã lớp, tiêu đề, môn, khu vực, phụ huynh...">
                <button class="ve-btn lg" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Tìm</button>
                <?php if ($cls_q !== ''): ?><a class="ve-btn lg outline" href="admin.php?<?= cls_e(http_build_query(array_filter(['view' => 'classes', 'status' => $cls_filter], 'strlen'))) ?>">Xóa lọc</a><?php endif; ?>
            </form>
        </div>
        <div class="ve-table-wrap">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Mã Lớp</th>
                    <th>Thông tin lớp</th>
                    <th>Môn & Khối</th>
                    <th>Học Phí</th>
                    <th>Phụ Huynh</th>
                    <th>Gia sư</th>
                    <th>Trạng Thái</th>
                    <th>Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($classes)): ?>
                    <?php foreach ($classes as $c):
                        $lb = $cls_labels[$c['status']] ?? [ucfirst((string) $c['status']), 'badge-muted'];
                        $missing = cls_publish_problems($c);
                        $gradeTxt = ctype_digit((string) $c['grade']) ? 'Lớp ' . $c['grade'] : $c['grade'];
                    ?>
                        <tr>
                            <td><strong><?= cls_e($c['class_code']) ?></strong><div class="ve-muted"><?= date('d/m/Y', strtotime($c['created_at'])) ?></div></td>
                            <td>
                                <strong><?= cls_e($c['title']) ?></strong>
                                <div class="ve-muted">
                                    <i class="fa-solid fa-location-dot"></i> <?= cls_e($c['location'] ?: '—') ?>
                                    · <i class="fa-regular fa-clock"></i> <?= cls_e($c['schedule'] ?: '—') ?>
                                </div>
                                <?php if ($c['requirements']): ?><div class="ve-muted"><i class="fa-solid fa-list-check"></i> <?= cls_e($c['requirements']) ?></div><?php endif; ?>
                                <?php if ($missing && in_array($c['status'], ['pending', 'cancelled'], true)): ?>
                                    <div class="ve-err"><i class="fa-solid fa-triangle-exclamation"></i> Thiếu: <?= cls_e(implode(', ', $missing)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= cls_e($c['subject']) ?><div class="ve-muted"><?= cls_e($gradeTxt) ?></div></td>
                            <td><?= $c['tuition_fee'] !== null ? number_format((float) $c['tuition_fee'], 0, ',', '.') . ' đ' : '<span class="ve-muted">Chưa có</span>' ?></td>
                            <td><?= cls_e($c['parent_name'] ?: '—') ?><?php if ($c['parent_phone']): ?><div class="ve-muted"><?= cls_e($c['parent_phone']) ?></div><?php endif; ?></td>
                            <td>
                                <?php if ($c['matched_tutor']): ?>
                                    <strong><?= cls_e($c['matched_tutor']) ?></strong>
                                <?php elseif ($c['status'] === 'finding'): ?>
                                    <span class="ve-muted"><?= (int) $c['applicants'] ?> hồ sơ ứng tuyển</span>
                                <?php else: ?>
                                    <span class="ve-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $lb[1] ?>"><?= $lb[0] ?></span></td>
                            <td>
                                <div class="ve-actions">
                                    <a class="ve-btn outline" title="Xem / sửa nội dung" href="admin.php?<?= cls_e(http_build_query(['view' => 'classes', 'edit' => $c['class_id']] + $cls_qs)) ?>"><i class="fa-solid fa-pen"></i> Sửa</a>
                                    <?php
                                    $btns = [];
                                    switch ($c['status']) {
                                        case 'pending':
                                            $btns[] = ['publish', 'green', 'fa-check', 'Duyệt & công bố', ''];
                                            $btns[] = ['cancel', 'red', 'fa-ban', 'Từ chối', 'Từ chối / hủy lớp này?'];
                                            $btns[] = ['delete', 'gray', 'fa-trash', 'Xóa', 'Xóa vĩnh viễn lớp này?'];
                                            break;
                                        case 'finding':
                                            if ($cls_has_pending) $btns[] = ['unpublish', 'orange', 'fa-eye-slash', 'Gỡ tin', 'Gỡ lớp khỏi bảng tin và chuyển về Chờ duyệt?'];
                                            $btns[] = ['cancel', 'red', 'fa-ban', 'Hủy', 'Hủy lớp này? Hồ sơ ứng tuyển sẽ bị đóng.'];
                                            break;
                                        case 'matched':
                                            $btns[] = ['complete', 'gray', 'fa-flag-checkered', 'Hoàn thành', 'Đánh dấu lớp đã hoàn thành?'];
                                            break;
                                        case 'cancelled':
                                            $btns[] = ['reopen', 'green', 'fa-rotate-left', 'Công bố lại', ''];
                                            $btns[] = ['delete', 'gray', 'fa-trash', 'Xóa', 'Xóa vĩnh viễn lớp này?'];
                                            break;
                                    }
                                    foreach ($btns as $b): ?>
                                        <form method="post" action="<?= cls_e($cls_action_url) ?>" <?= $b[4] ? 'onsubmit="return confirm(' . cls_e(json_encode($b[4])) . ')"' : '' ?>>
                                            <input type="hidden" name="cls_action" value="<?= $b[0] ?>">
                                            <input type="hidden" name="class_id" value="<?= (int) $c['class_id'] ?>">
                                            <button type="submit" class="ve-btn <?= $b[1] ?>"><i class="fa-solid <?= $b[2] ?>"></i> <?= $b[3] ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                    <?php if ($c['status'] === 'finding'): ?>
                                        <a class="ve-btn" href="admin.php?view=matching&amp;class_id=<?= (int) $c['class_id'] ?>"><i class="fa-solid fa-diagram-project"></i> Ghép gia sư</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align: center;">Không có lớp học nào phù hợp.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
        <?= isset($pt) ? phan_trang_html($pt, 'lớp học') : '' ?>
    </div>
</section>
