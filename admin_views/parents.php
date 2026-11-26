<?php

$per_page = 15;

// Hàm hỗ trợ chống XSS và định dạng tiền
function h($v) { 
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); 
}
function money($n) { 
    return number_format((float)$n, 0, ',', '.') . ' VNĐ'; 
}

/* ============================================================
 * 1. XỬ LÝ POST REQUEST (THÊM / SỬA / XÓA PHỤ HUYNH & YÊU CẦU LỚP)
 * ============================================================ */
$flash_ok  = '';
$flash_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $parent_id = (int)($_POST['parent_id'] ?? 0);

    try {
        // A. THÊM PHỤ HUYNH MỚI
        if ($action === 'add_parent') {
            $full_name = trim($_POST['full_name'] ?? '');
            $phone     = trim($_POST['phone'] ?? '');
            $address   = trim($_POST['address'] ?? '');

            if ($full_name === '' || $phone === '') {
                $flash_err = "Vui lòng nhập đầy đủ họ tên và số điện thoại.";
            } else {
                // Kiểm tra trùng SĐT
                $chk = $conn->prepare("SELECT COUNT(*) FROM parents WHERE phone = ?");
                $chk->execute([$phone]);
                if ($chk->fetchColumn() > 0) {
                    $flash_err = "Số điện thoại này đã được đăng ký trên hệ thống.";
                } else {
                    $stmt = $conn->prepare("INSERT INTO parents (full_name, phone, address) VALUES (?, ?, ?)");
                    $stmt->execute([$full_name, $phone, $address]);
                    $new_id = $conn->lastInsertId();
                    $flash_ok = "Đã thêm phụ huynh mới thành công.";
                    header("Location: admin.php?view=parents&parent_id=" . $new_id . "&ok=parent_added");
                    exit;
                }
            }
        }

        // B. SỬA THÔNG TIN PHỤ HUYNH
        elseif ($action === 'edit_parent') {
            $full_name = trim($_POST['full_name'] ?? '');
            $phone     = trim($_POST['phone'] ?? '');
            $address   = trim($_POST['address'] ?? '');

            $chk = $conn->prepare("SELECT COUNT(*) FROM parents WHERE phone = ? AND parent_id <> ?");
            $chk->execute([$phone, $parent_id]);
            if ($chk->fetchColumn() > 0) {
                $flash_err = "Số điện thoại bị trùng với một phụ huynh khác.";
            } else {
                $stmt = $conn->prepare("UPDATE parents SET full_name = ?, phone = ?, address = ? WHERE parent_id = ?");
                $stmt->execute([$full_name, $phone, $address, $parent_id]);
                $flash_ok = "Đã cập nhật thông tin phụ huynh.";
            }
        }

        // C. XÓA PHỤ HUYNH (Chỉ cho phép xóa khi chưa có lớp hoặc khiếu nại)
        elseif ($action === 'delete_parent') {
            $chk_class = $conn->prepare("SELECT COUNT(*) FROM classes WHERE parent_id = ?");
            $chk_class->execute([$parent_id]);
            
            $chk_ticket = $conn->prepare("SELECT COUNT(*) FROM support_tickets WHERE sender_type = 'parent' AND sender_id = ?");
            $chk_ticket->execute([$parent_id]);

            if ($chk_class->fetchColumn() > 0 || $chk_ticket->fetchColumn() > 0) {
                $flash_err = "Không thể xóa phụ huynh này vì đã có lớp học hoặc khiếu nại trong cơ sở dữ liệu.";
            } else {
                $del = $conn->prepare("DELETE FROM parents WHERE parent_id = ?");
                $del->execute([$parent_id]);
                $flash_ok = "Đã xóa hồ sơ phụ huynh thành công.";
            }
        }

        // D. THÊM YÊU CẦU TÌM GIA SƯ (TẠO LỚP HỌC MỚI)
        elseif ($action === 'add_request') {
            $title        = trim($_POST['title'] ?? '');
            $subject      = trim($_POST['subject'] ?? '');
            $grade        = trim($_POST['grade'] ?? '');
            $location     = trim($_POST['location'] ?? '');
            $schedule     = trim($_POST['schedule'] ?? '');
            $requirements = trim($_POST['requirements'] ?? '');
            $tuition_fee  = (float)($_POST['tuition_fee'] ?? 0);

            // Sinh mã lớp tự động (VD: VE-1044)
            $max_num = (int)$conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(class_code, 4) AS UNSIGNED)), 1000) FROM classes")->fetchColumn();
            $class_code = 'VE-' . ($max_num + 1);

            $ins = $conn->prepare("INSERT INTO classes (class_code, title, subject, grade, location, schedule, requirements, tuition_fee, parent_id, status)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'finding')");
            $ins->execute([$class_code, $title, $subject, $grade, $location, $schedule, $requirements, $tuition_fee, $parent_id]);
            $flash_ok = "Đã ghi nhận yêu cầu tìm gia sư mới ($class_code).";
        }

        // E. SỬA YÊU CẦU TÌM GIA SƯ
        elseif ($action === 'edit_request') {
            $class_id     = (int)($_POST['class_id'] ?? 0);
            $title        = trim($_POST['title'] ?? '');
            $subject      = trim($_POST['subject'] ?? '');
            $grade        = trim($_POST['grade'] ?? '');
            $location     = trim($_POST['location'] ?? '');
            $schedule     = trim($_POST['schedule'] ?? '');
            $requirements = trim($_POST['requirements'] ?? '');
            $tuition_fee  = (float)($_POST['tuition_fee'] ?? 0);

            // Chỉ sửa được yêu cầu đang tìm gia sư của đúng phụ huynh này
            $chk = $conn->prepare("SELECT status FROM classes WHERE class_id = ? AND parent_id = ?");
            $chk->execute([$class_id, $parent_id]);
            $trangThai = $chk->fetchColumn();

            if ($title === '' || $subject === '' || $grade === '' || $location === '' || $tuition_fee <= 0) {
                $flash_err = "Vui lòng nhập đầy đủ tiêu đề, môn học, khối lớp, khu vực và học phí.";
            } elseif ($trangThai === false) {
                $flash_err = "Không tìm thấy yêu cầu này.";
            } elseif ($trangThai !== 'finding') {
                $flash_err = "Chỉ sửa được yêu cầu đang ở trạng thái tìm gia sư.";
            } else {
                $up = $conn->prepare("UPDATE classes
                                      SET title = ?, subject = ?, grade = ?, location = ?, schedule = ?, requirements = ?, tuition_fee = ?
                                      WHERE class_id = ? AND parent_id = ? AND status = 'finding'");
                $up->execute([$title, $subject, $grade, $location, $schedule, $requirements, $tuition_fee, $class_id, $parent_id]);
                $flash_ok = "Đã cập nhật yêu cầu tìm gia sư.";
            }
        }

        // F. HỦY YÊU CẦU TÌM GIA SƯ
        elseif ($action === 'cancel_request') {
            $class_id = (int)($_POST['class_id'] ?? 0);
            $conn->beginTransaction();

            // Đổi trạng thái lớp -> cancelled
            $conn->prepare("UPDATE classes SET status = 'cancelled' WHERE class_id = ? AND parent_id = ?")->execute([$class_id, $parent_id]);
            // Từ chối các ứng viên đang chờ
            $conn->prepare("UPDATE class_matching SET status = 'rejected' WHERE class_id = ? AND status IN ('applied','interviewing')")->execute([$class_id]);
            // Hủy các mã giao dịch QR chờ thanh toán
            $conn->prepare("UPDATE transactions SET status = 'failed' WHERE class_id = ? AND status = 'pending_24h'")->execute([$class_id]);

            $conn->commit();
            $flash_ok = "Đã hủy yêu cầu tìm gia sư.";
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $flash_err = ve_loi_csdl($e, 'cập nhật phụ huynh');
    }
}

/* ============================================================
 * 2. TRUY VẤN DỮ LIỆU HIỂN THỊ
 * ============================================================ */
$parent_id  = (int)($_GET['parent_id'] ?? 0);
$kw         = trim($_GET['q'] ?? '');
$req_filter = trim($_GET['req'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));

$detail   = null; 
$requests = []; 
$tickets  = [];
$parents  = []; 
$pages    = 1;

$class_labels  = ['pending' => 'Chờ duyệt', 'rejected' => 'Bị từ chối', 'finding' => 'Đang tìm gia sư', 'matched' => 'Đã ghép', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy'];
$class_badges  = ['finding' => 'badge-warning', 'matched' => 'badge-primary', 'completed' => 'badge-success', 'cancelled' => 'badge-danger'];
$ticket_labels = VE_NHAN_TICKET;
$ticket_badges = ['open' => 'badge-danger', 'processing' => 'badge-warning', 'resolved' => 'badge-success'];

// THỦ TỤC 1: XEM CHI TIẾT 1 PHỤ HUYNH
if ($parent_id > 0) {
    $st = $conn->prepare("SELECT * FROM parents WHERE parent_id = ?");
    $st->execute([$parent_id]);
    $detail = $st->fetch(PDO::FETCH_ASSOC);

    if ($detail) {
        // Lấy danh sách lớp phụ huynh đã đăng
        $st_req = $conn->prepare("SELECT c.*, 
                                        (SELECT tu.full_name FROM class_matching m JOIN tutors tu ON tu.tutor_id = m.tutor_id WHERE m.class_id = c.class_id AND m.status = 'matched' LIMIT 1) AS matched_tutor,
                                        (SELECT COUNT(*) FROM class_matching m WHERE m.class_id = c.class_id AND m.status IN ('applied','interviewing')) AS applicants
                                  FROM classes c 
                                  WHERE c.parent_id = ? 
                                  ORDER BY c.class_id DESC");
        $st_req->execute([$parent_id]);
        $requests = $st_req->fetchAll(PDO::FETCH_ASSOC);

        // Lấy danh sách khiếu nại
        $st_tick = $conn->prepare("SELECT * FROM support_tickets WHERE sender_type = 'parent' AND sender_id = ? ORDER BY created_at DESC");
        $st_tick->execute([$parent_id]);
        $tickets = $st_tick->fetchAll(PDO::FETCH_ASSOC);
    }
}

// THỦ TỤC 2: XEM DANH SÁCH PHỤ HUYNH & KPI THỐNG KÊ (NẾU KHÔNG XEM CHI TIẾT)
if (!$detail) {
    $stats = $conn->query("SELECT
                (SELECT COUNT(*) FROM parents) AS total_parents,
                (SELECT COUNT(*) FROM classes WHERE status = 'finding') AS req_finding,
                (SELECT COUNT(*) FROM classes WHERE status IN ('matched','completed')) AS req_done,
                (SELECT COUNT(*) FROM classes) AS req_total")->fetch(PDO::FETCH_ASSOC);

    $sql = "SELECT p.*,
                   COUNT(c.class_id) AS total_req,
                   SUM(CASE WHEN c.status = 'finding' THEN 1 ELSE 0 END) AS cnt_finding,
                   SUM(CASE WHEN c.status = 'matched' THEN 1 ELSE 0 END) AS cnt_matched,
                   SUM(CASE WHEN c.status = 'completed' THEN 1 ELSE 0 END) AS cnt_completed,
                   SUM(CASE WHEN c.status = 'cancelled' THEN 1 ELSE 0 END) AS cnt_cancelled,
                   (SELECT COUNT(*) FROM support_tickets s WHERE s.sender_type = 'parent' AND s.sender_id = p.parent_id AND s.status <> 'resolved') AS open_tickets
            FROM parents p
            LEFT JOIN classes c ON c.parent_id = p.parent_id
            WHERE 1=1";
    $params = [];

    if ($kw !== '') {
        $sql .= " AND (p.full_name LIKE ? OR p.phone LIKE ? OR p.address LIKE ?)";
        $params[] = "%$kw%"; $params[] = "%$kw%"; $params[] = "%$kw%";
    }
    
    $sql .= " GROUP BY p.parent_id";

    // Thêm điều kiện HAVING theo bộ lọc trạng thái lớp
    if ($req_filter === 'finding')   $sql .= " HAVING cnt_finding > 0";
    if ($req_filter === 'matched')   $sql .= " HAVING cnt_matched > 0";
    if ($req_filter === 'completed') $sql .= " HAVING cnt_completed > 0";
    if ($req_filter === 'cancelled') $sql .= " HAVING cnt_cancelled > 0";
    if ($req_filter === 'none')      $sql .= " HAVING total_req = 0";

    $sql .= " ORDER BY p.parent_id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $all_parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Phân trang đơn giản bằng array_slice
    $pt         = phan_trang($all_parents, $per_page);
    $total_rows = $pt['total'];
    $pages      = $pt['pages'];
    $page       = $pt['page'];
    $parents    = $pt['rows'];
}
?>

<section id="view-parents" class="content-view active">
    <div class="view-header">
        <h2>Phân hệ 4: Quản Lý Nhu Cầu Phụ Huynh</h2>
    </div>

    <!-- THÔNG BÁO FLASH -->
    <?php if ($flash_ok || isset($_GET['ok'])): ?>
        <div class="p4-alert ok"><i class="fa-solid fa-check-circle"></i> <?= h($flash_ok ?: "Thao tác thành công.") ?></div>
    <?php endif; ?>
    <?php if ($flash_err): ?>
        <div class="p4-alert err"><i class="fa-solid fa-exclamation-triangle"></i> <?= h($flash_err) ?></div>
    <?php endif; ?>

    <?php if ($detail): ?>
    <!-- ==================== TRANG CHI TIẾT 1 PHỤ HUYNH ==================== -->
    <div style="margin-bottom: 15px;">
        <a href="admin.php?view=parents" style="color:#800020; font-weight:bold; text-decoration:none;">
            <i class="fa-solid fa-arrow-left"></i> Quay lại danh sách phụ huynh
        </a>
    </div>

    <!-- THÔNG TIN PHỤ HUYNH -->
    <div class="data-table-card" style="margin-bottom: 20px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:15px;">
            <div>
                <h3 style="margin:0 0 10px 0; font-size:20px; color:var(--primary-dark);"><?= h($detail['full_name']) ?></h3>
                <p style="margin:4px 0;"><i class="fa-solid fa-phone" style="width:20px; color:#666;"></i> <strong><?= h($detail['phone']) ?></strong></p>
                <p style="margin:4px 0;"><i class="fa-solid fa-location-dot" style="width:20px; color:#666;"></i> <?= h($detail['address'] ?: 'Chưa cập nhật địa chỉ') ?></p>
                <p style="margin:4px 0; color:#777; font-size:13px;"><i class="fa-regular fa-calendar" style="width:20px;"></i> Ngày tham gia: <?= date('d/m/Y', strtotime($detail['created_at'])) ?></p>
            </div>
            <div style="display:flex; gap:8px;">
                <button class="p4-btn p4-btn-ghost" onclick="editParentProfile(<?= h(json_encode(array_intersect_key($detail, array_flip(['parent_id', 'full_name', 'phone', 'address'])))) ?>)"><i class="fa-solid fa-pen"></i> Sửa thông tin</button>
                <button class="p4-btn p4-btn-red" onclick="openAddRequestModal()"><i class="fa-solid fa-plus"></i> Thêm yêu cầu tìm gia sư</button>
            </div>
        </div>
    </div>

    <!-- DANH SÁCH LỚP HỌC YÊU CẦU -->
    <div class="data-table-card" style="margin-bottom: 20px;">
        <h3>Danh Sách Yêu Cầu Tìm Gia Sư (<?= count($requests) ?>)</h3>
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Mã Lớp</th>
                    <th>Tiêu Đề</th>
                    <th>Môn & Khối</th>
                    <th>Địa Điểm</th>
                    <th>Học Phí</th>
                    <th>Gia Sư Ghép</th>
                    <th>Trạng Thái</th>
                    <th>Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($requests)): ?>
                    <?php foreach ($requests as $r): ?>
                        <tr>
                            <td><strong><?= h($r['class_code']) ?></strong></td>
                            <td><?= h($r['title']) ?></td>
                            <td><?= h($r['subject']) ?> (Lớp <?= h($r['grade']) ?>)</td>
                            <td><?= h($r['location']) ?></td>
                            <td style="font-weight:bold; color:var(--primary-red);"><?= money($r['tuition_fee']) ?></td>
                            <td>
                                <?php if ($r['matched_tutor']): ?>
                                    <strong style="color:#27ae60;"><?= h($r['matched_tutor']) ?></strong>
                                <?php else: ?>
                                    <span style="color:#777; font-size:13px;"><?= (int)$r['applicants'] ?> ứng viên</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?= $class_badges[$r['status']] ?? 'badge-primary' ?>"><?= h($class_labels[$r['status']] ?? $r['status']) ?></span></td>
                            <td>
                                <?php if ($r['status'] === 'finding'): ?>
                                    <div style="display:flex; gap:5px;">
                                        <button class="p4-btn p4-btn-blue" onclick="editRequest(<?= h(json_encode($r)) ?>)"><i class="fa-solid fa-pen"></i> Sửa</button>
                                        <form method="POST" action="admin.php?view=parents&parent_id=<?= $detail['parent_id'] ?>" onsubmit="return confirm('Hủy yêu cầu tìm gia sư cho lớp này?');">
                                            <input type="hidden" name="action" value="cancel_request">
                                            <input type="hidden" name="parent_id" value="<?= $detail['parent_id'] ?>">
                                            <input type="hidden" name="class_id" value="<?= $r['class_id'] ?>">
                                            <button type="submit" class="p4-btn p4-btn-red"><i class="fa-solid fa-ban"></i> Hủy</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#999; font-size:12px;"><i class="fa-solid fa-lock"></i> Đã đóng</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;">Phụ huynh chưa đăng yêu cầu tìm gia sư nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- DANH SÁCH TICKET KHIẾU NẠI -->
    <div class="data-table-card">
        <h3>Lịch Sử Khiếu Nại (<?= count($tickets) ?>)</h3>
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Tiêu Đề</th>
                    <th>Nội Dung</th>
                    <th>Trạng Thái</th>
                    <th>Ngày Gửi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($tickets)): ?>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td><strong><?= h($t['issue_title']) ?></strong></td>
                            <td><?= h(mb_strimwidth($t['content'], 0, 100, '...')) ?></td>
                            <td><span class="badge <?= $ticket_badges[$t['status']] ?? 'badge-primary' ?>"><?= h($ticket_labels[$t['status']] ?? $t['status']) ?></span></td>
                            <td><?= date('H:i - d/m/Y', strtotime($t['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center;">Chưa có khiếu nại nào từ phụ huynh này.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <!-- ==================== TRANG DANH SÁCH TỔNG HỢP PHỤ HUYNH ==================== -->
    <div class="p4-stats">
        <div class="p4-stat">
            <div>Tổng phụ huynh</div>
            <div class="val"><?= (int)($stats['total_parents'] ?? 0) ?></div>
            <small>Trong hệ thống</small>
        </div>
        <div class="p4-stat orange">
            <div>Lớp đang tìm gia sư</div>
            <div class="val"><?= (int)($stats['req_finding'] ?? 0) ?></div>
            <small>Đang chờ ghép nối</small>
        </div>
        <div class="p4-stat green">
            <div>Lớp đã ghép / Hoàn thành</div>
            <div class="val"><?= (int)($stats['req_done'] ?? 0) ?></div>
            <small>Trên tổng <?= (int)($stats['req_total'] ?? 0) ?> yêu cầu</small>
        </div>
    </div>

    <div class="data-table-card">
        <h3>Danh Sách Hồ Sơ Phụ Huynh</h3>

        <!-- BỘ LỌC TÌM KIẾM -->
        <form method="GET" action="admin.php" style="margin-bottom: 15px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <input type="hidden" name="view" value="parents">
            <input type="text" name="q" value="<?= h($kw) ?>" class="p4-input" placeholder="Tìm theo tên, SĐT, địa chỉ..." style="min-width:250px;">
            <select name="req" class="p4-input">
                <option value="">Tất cả tình trạng yêu cầu</option>
                <option value="finding" <?= $req_filter === 'finding' ? 'selected' : '' ?>>Có lớp đang tìm gia sư</option>
                <option value="matched" <?= $req_filter === 'matched' ? 'selected' : '' ?>>Có lớp đã ghép</option>
                <option value="completed" <?= $req_filter === 'completed' ? 'selected' : '' ?>>Có lớp đã hoàn thành</option>
                <option value="none" <?= $req_filter === 'none' ? 'selected' : '' ?>>Chưa có yêu cầu nào</option>
            </select>
            <button type="submit" class="p4-btn p4-btn-blue"><i class="fa-solid fa-search"></i> Lọc</button>
            <div style="margin-left:auto;">
                <button type="button" class="p4-btn p4-btn-red" onclick="openAddParentModal()"><i class="fa-solid fa-user-plus"></i> Thêm Phụ Huynh</button>
            </div>
        </form>

        <!-- BẢNG DỮ LIỆU -->
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Mã</th>
                    <th>Họ và Tên</th>
                    <th>Số Điện Thoại</th>
                    <th>Địa Chỉ</th>
                    <th>Tình Trạng Lớp Đăng</th>
                    <th>Khiếu Nại Chờ Xử Lý</th>
                    <th>Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($parents)): ?>
                    <?php foreach ($parents as $p): ?>
                        <tr>
                            <td><?= $p['parent_id'] ?></td>
                            <td><strong><?= h($p['full_name']) ?></strong></td>
                            <td><a href="tel:<?= h($p['phone']) ?>"><i class="fa-solid fa-phone"></i> <?= h($p['phone']) ?></a></td>
                            <td><?= h($p['address'] ?: '-') ?></td>
                            <td>
                                <?php if ((int)$p['total_req'] === 0): ?>
                                    <span style="color:#888; font-size:12px;">Chưa có yêu cầu</span>
                                <?php else: ?>
                                    <?php if ((int)$p['cnt_finding'] > 0): ?><span class="p4-chip f"><?= $p['cnt_finding'] ?> đang tìm</span><?php endif; ?>
                                    <?php if ((int)$p['cnt_matched'] > 0): ?><span class="p4-chip m"><?= $p['cnt_matched'] ?> đã ghép</span><?php endif; ?>
                                    <?php if ((int)$p['cnt_completed'] > 0): ?><span class="p4-chip c"><?= $p['cnt_completed'] ?> xong</span><?php endif; ?>
                                    <?php if ((int)$p['cnt_cancelled'] > 0): ?><span class="p4-chip x"><?= $p['cnt_cancelled'] ?> hủy</span><?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$p['open_tickets'] > 0): ?>
                                    <span class="badge badge-danger"><?= $p['open_tickets'] ?> ticket</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:5px;">
                                    <a href="admin.php?view=parents&parent_id=<?= $p['parent_id'] ?>" class="p4-btn p4-btn-blue"><i class="fa-solid fa-folder-open"></i> Chi tiết</a>
                                    <button class="p4-btn p4-btn-ghost" onclick="editParentProfile(<?= h(json_encode(array_intersect_key($p, array_flip(['parent_id', 'full_name', 'phone', 'address'])))) ?>)"><i class="fa-solid fa-pen"></i> Sửa</button>
                                    
                                    <?php if ((int)$p['total_req'] === 0 && (int)$p['open_tickets'] === 0): ?>
                                        <form method="POST" action="admin.php?view=parents" onsubmit="return confirm('Bạn chắc chắn muốn xóa hồ sơ phụ huynh này?');">
                                            <input type="hidden" name="action" value="delete_parent">
                                            <input type="hidden" name="parent_id" value="<?= $p['parent_id'] ?>">
                                            <button type="submit" class="p4-btn p4-btn-red"><i class="fa-solid fa-trash"></i> Xóa</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center;">Không tìm thấy thông tin phụ huynh nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- PHÂN TRANG -->
        <?= isset($pt) ? phan_trang_html($pt, 'phụ huynh') : '' ?>
    </div>
    <?php endif; ?>
</section>

<!-- ==================== POPUP MODAL THÊM / SỬA PHỤ HUYNH ==================== -->
<div class="p4-modal" id="parentModal" style="display:none;">
    <div class="p4-modal-box">
        <button class="p4-modal-close" onclick="closeModal('parentModal')">&times;</button>
        <h3 id="parentModalTitle">Thêm Phụ Huynh Mới</h3>
        <form method="POST" action="admin.php?view=parents">
            <input type="hidden" name="action" id="parentAction" value="add_parent">
            <input type="hidden" name="parent_id" id="parentId" value="">
            
            <div class="p4-field">
                <label>Họ và tên *</label>
                <input type="text" name="full_name" id="pName" class="p4-input" required>
            </div>
            <div class="p4-field">
                <label>Số điện thoại *</label>
                <input type="text" name="phone" id="pPhone" class="p4-input" placeholder="0988123456" required>
            </div>
            <div class="p4-field">
                <label>Địa chỉ</label>
                <textarea name="address" id="pAddress" class="p4-input" rows="3" placeholder="Quận/Huyện, Tỉnh/Thành phố..."></textarea>
            </div>

            <div style="text-align:right; margin-top:15px;">
                <button type="button" class="p4-btn p4-btn-ghost" onclick="closeModal('parentModal')">Đóng</button>
                <button type="submit" class="p4-btn p4-btn-red">Lưu thông tin</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== POPUP MODAL THÊM / SỬA YÊU CẦU LỚP HỌC ==================== -->
<?php if ($detail): ?>
<div class="p4-modal" id="requestModal" style="display:none;">
    <div class="p4-modal-box">
        <button class="p4-modal-close" onclick="closeModal('requestModal')">&times;</button>
        <h3 id="reqModalTitle">Thêm Yêu Cầu Tìm Gia Sư</h3>
        <form method="POST" action="admin.php?view=parents&parent_id=<?= $detail['parent_id'] ?>">
            <input type="hidden" name="action" id="reqAction" value="add_request">
            <input type="hidden" name="parent_id" value="<?= $detail['parent_id'] ?>">
            <input type="hidden" name="class_id" id="reqClassId" value="">

            <div class="p4-field">
                <label>Tiêu đề lớp học *</label>
                <input type="text" name="title" id="rTitle" class="p4-input" placeholder="Ví dụ: Toán 8 nâng cao" required>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div class="p4-field">
                    <label>Môn học *</label>
                    <input type="text" name="subject" id="rSubject" class="p4-input" placeholder="Toán, Tiếng Anh..." required>
                </div>
                <div class="p4-field">
                    <label>Khối lớp *</label>
                    <input type="text" name="grade" id="rGrade" class="p4-input" placeholder="8, 9, 12..." required>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div class="p4-field">
                    <label>Khu vực (Quận/Huyện) *</label>
                    <input type="text" name="location" id="rLocation" class="p4-input" placeholder="Cầu Giấy, Thanh Xuân..." required>
                </div>
                <div class="p4-field">
                    <label>Học phí / tháng (VNĐ) *</label>
                    <input type="number" name="tuition_fee" id="rFee" class="p4-input" placeholder="2500000" required>
                </div>
            </div>
            <div class="p4-field">
                <label>Lịch học dự kiến</label>
                <input type="text" name="schedule" id="rSchedule" class="p4-input" placeholder="3 buổi/tuần (Tối T2-T4-T6)">
            </div>
            <div class="p4-field">
                <label>Yêu cầu đối với gia sư</label>
                <textarea name="requirements" id="rRequirements" class="p4-input" rows="2" placeholder="Ví dụ: Sinh viên Bách Khoa, nhiệt tình..."></textarea>
            </div>

            <div style="text-align:right; margin-top:15px;">
                <button type="button" class="p4-btn p4-btn-ghost" onclick="closeModal('requestModal')">Đóng</button>
                <button type="submit" class="p4-btn p4-btn-red">Lưu yêu cầu</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// ===== Popup phụ huynh & yêu cầu tìm gia sư =====
function openModal(id) {
    const m = document.getElementById(id);
    if (m) m.style.display = 'flex';
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.style.display = 'none';
}
function setVal(id, v) {
    const el = document.getElementById(id);
    if (el) el.value = (v === null || v === undefined) ? '' : v;
}

// Thêm phụ huynh mới
function openAddParentModal() {
    document.getElementById('parentModalTitle').textContent = 'Thêm Phụ Huynh Mới';
    setVal('parentAction', 'add_parent');
    setVal('parentId', '');
    setVal('pName', ''); setVal('pPhone', ''); setVal('pAddress', '');
    openModal('parentModal');
}

// Sửa thông tin phụ huynh
function editParentProfile(p) {
    document.getElementById('parentModalTitle').textContent = 'Sửa Thông Tin Phụ Huynh';
    setVal('parentAction', 'edit_parent');
    setVal('parentId', p.parent_id);
    setVal('pName', p.full_name); setVal('pPhone', p.phone); setVal('pAddress', p.address);
    openModal('parentModal');
}

// Thêm yêu cầu tìm gia sư
function openAddRequestModal() {
    document.getElementById('reqModalTitle').textContent = 'Thêm Yêu Cầu Tìm Gia Sư';
    setVal('reqAction', 'add_request');
    setVal('reqClassId', '');
    ['rTitle', 'rSubject', 'rGrade', 'rLocation', 'rFee', 'rSchedule', 'rRequirements'].forEach(id => setVal(id, ''));
    openModal('requestModal');
}

// Sửa yêu cầu tìm gia sư
function editRequest(r) {
    document.getElementById('reqModalTitle').textContent = 'Sửa Yêu Cầu ' + (r.class_code || '');
    setVal('reqAction', 'edit_request');
    setVal('reqClassId', r.class_id);
    setVal('rTitle', r.title);
    setVal('rSubject', r.subject);
    setVal('rGrade', r.grade);
    setVal('rLocation', r.location);
    setVal('rFee', r.tuition_fee !== null && r.tuition_fee !== undefined ? Math.round(parseFloat(r.tuition_fee)) : '');
    setVal('rSchedule', r.schedule);
    setVal('rRequirements', r.requirements);
    openModal('requestModal');
}

// Bấm ra ngoài khung hoặc nhấn Esc để đóng popup
document.querySelectorAll('.p4-modal').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; }));
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.p4-modal').forEach(m => m.style.display = 'none'); });
</script>