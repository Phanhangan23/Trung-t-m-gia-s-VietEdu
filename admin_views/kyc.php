<?php
$msg = '';
$msg_type = '';

// Kiểm tra danh sách cột thực tế trong DB để tránh lỗi Unknown column
$kycCols   = $conn->query("SHOW COLUMNS FROM kyc_profiles")->fetchAll(PDO::FETCH_COLUMN);
$tutorCols = $conn->query("SHOW COLUMNS FROM tutors")->fetchAll(PDO::FETCH_COLUMN);

$hasNote       = in_array('review_note', $kycCols); // cột ghi chú / lý do từ chối (gia sư xem được ở trang xác minh)
$hasUpdatedAt  = in_array('updated_at', $kycCols);
$hasCreatedAt  = in_array('created_at', $kycCols);
$hasUniversity = in_array('university', $tutorCols);
$hasMajor      = in_array('major', $tutorCols);
$hasVerified   = in_array('is_verified', $tutorCols);

/**
 * Đồng bộ tài khoản gia sư theo kết quả xác minh.
 * Cổng gia sư chỉ cho ứng tuyển/nhận lớp khi tutors.status = 'active', nên:
 *  - KYC được duyệt   -> kích hoạt tài khoản (active)
 *  - KYC chưa/không đạt -> đưa về chờ (pending)
 * Tài khoản đang bị khóa (locked) thì giữ nguyên.
 */
function kycSyncTutor(PDO $conn, int $tutorId, string $kycStatus): void
{
    $moi = $kycStatus === 'approved' ? 'active' : 'pending';
    $conn->prepare("UPDATE tutors SET status = ? WHERE tutor_id = ? AND status <> 'locked'")->execute([$moi, $tutorId]);
}

/** Ghi lại nhân viên đã xét duyệt hồ sơ. */
function kycReviewedBy(PDO $conn, int $kycId): void
{
    if (!empty($_SESSION['staff_id'])) {
        $conn->prepare('UPDATE kyc_profiles SET reviewed_by = ? WHERE kyc_id = ?')->execute([(int) $_SESSION['staff_id'], $kycId]);
    }
}

/**
 * Lưu ảnh minh chứng do nhân viên tải lên.
 * Dùng CHUNG thư mục được bảo vệ với cổng gia sư (gia-su/uploads/kyc/, có .htaccess chặn truy cập trực tiếp),
 * kiểm tra định dạng thật của file (không tin đuôi file), tên file ngẫu nhiên.
 * Trả về đường dẫn lưu CSDL dạng "uploads/kyc/xxx.png" (giống cổng gia sư) hoặc null.
 */
function uploadKycImage($fileInputName, $prefix) {
    $f = $_FILES[$fileInputName] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) {
        return null;
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($allowed[$mime])) {
        return null;
    }
    $dir = __DIR__ . '/../gia-su/uploads/kyc';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!is_file($dir . '/.htaccess')) file_put_contents($dir . '/.htaccess', "Require all denied\n");

    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    return move_uploaded_file($f['tmp_name'], $dir . '/' . $name) ? 'uploads/kyc/' . $name : null;
}

/** URL xem ảnh KYC qua trang kiểm tra quyền (ảnh gốc bị chặn truy cập trực tiếp). */
function kycImgUrl(array $row, string $loai): string
{
    return !empty($row[$loai]) ? 'xem-anh-kyc.php?id=' . (int) $row['kyc_id'] . '&loai=' . $loai : '';
}

// ------------------------------------------------------------------------------
// 1. XỬ LÝ CÁC THAO TÁC POST (THÊM / SỬA / PHÊ DUYỆT / TỪ CHỐI / XÓA)
// ------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        // A. THÊM MỚI HỒ SƠ XÁC MINH
        if ($action === 'create') {
            $tutor_id = intval($_POST['tutor_id']);
            $status   = $_POST['status'] ?? 'pending';
            $note     = trim($_POST['note'] ?? '');

            $frontPath   = uploadKycImage('id_card_front', 'front');
            $backPath    = uploadKycImage('id_card_back', 'back');
            $studentPath = uploadKycImage('student_card', 'student');
            $degreePath  = uploadKycImage('degree_cert', 'degree');

            $fields = ['tutor_id', 'id_card_front', 'id_card_back', 'student_card', 'degree_cert', 'status'];
            $placeholders = [':tutor_id', ':front', ':back', ':student', ':degree', ':status'];
            $params = [
                'tutor_id' => $tutor_id,
                'front'    => $frontPath,
                'back'     => $backPath,
                'student'  => $studentPath,
                'degree'   => $degreePath,
                'status'   => $status
            ];

            if ($hasNote) {
                $fields[] = 'review_note';
                $placeholders[] = ':note';
                $params['note'] = $note;
            }
            if ($hasCreatedAt) {
                $fields[] = 'created_at';
                $placeholders[] = 'NOW()';
            }

            $sql = "INSERT INTO kyc_profiles (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $conn->prepare($sql)->execute($params);

            if ($status === 'approved' && $hasVerified) {
                $conn->prepare("UPDATE tutors SET is_verified = 1 WHERE tutor_id = ?")->execute([$tutor_id]);
            }
            kycSyncTutor($conn, $tutor_id, $status);
            if ($status !== 'pending') kycReviewedBy($conn, (int) $conn->lastInsertId());

            $msg = "Đã thêm mới hồ sơ xác minh thành công!";
            $msg_type = "success";
        }

        // B. SỬA / CẬP NHẬT HỒ SƠ XÁC MINH
        elseif ($action === 'update') {
            $kyc_id   = intval($_POST['kyc_id']);
            $tutor_id = intval($_POST['tutor_id']);
            $status   = $_POST['status'] ?? 'pending';
            $note     = trim($_POST['note'] ?? '');

            // Ảnh cũ lấy từ CSDL (không tin giá trị ẩn trên form); có ảnh mới thì thay và xóa file cũ
            $stOld = $conn->prepare('SELECT id_card_front, id_card_back, student_card, degree_cert FROM kyc_profiles WHERE kyc_id = ?');
            $stOld->execute([$kyc_id]);
            $old = $stOld->fetch(PDO::FETCH_ASSOC) ?: ['id_card_front' => null, 'id_card_back' => null, 'student_card' => null, 'degree_cert' => null];
            $thayAnh = function ($field, $prefix) use ($old) {
                $moi = uploadKycImage($field, $prefix);
                if ($moi && !empty($old[$field]) && strpos($old[$field], 'uploads/kyc/') === 0) {
                    @unlink(__DIR__ . '/../gia-su/' . $old[$field]);
                }
                return $moi ?: $old[$field];
            };
            $frontPath   = $thayAnh('id_card_front', 'front');
            $backPath    = $thayAnh('id_card_back', 'back');
            $studentPath = $thayAnh('student_card', 'student');
            $degreePath  = $thayAnh('degree_cert', 'degree');

            $sets = [
                'id_card_front = :front',
                'id_card_back = :back',
                'student_card = :student',
                'degree_cert = :degree',
                'status = :status'
            ];
            $params = [
                'front'   => $frontPath,
                'back'    => $backPath,
                'student' => $studentPath,
                'degree'  => $degreePath,
                'status'  => $status,
                'kyc_id'  => $kyc_id
            ];

            if ($hasNote) {
                $sets[] = 'review_note = :note';
                $params['note'] = $note;
            }
            if ($hasUpdatedAt) {
                $sets[] = 'updated_at = NOW()';
            }

            $sql = "UPDATE kyc_profiles SET " . implode(', ', $sets) . " WHERE kyc_id = :kyc_id";
            $conn->prepare($sql)->execute($params);

            if ($hasVerified) {
                $isVerified = ($status === 'approved') ? 1 : 0;
                $conn->prepare("UPDATE tutors SET is_verified = ? WHERE tutor_id = ?")->execute([$isVerified, $tutor_id]);
            }
            kycSyncTutor($conn, $tutor_id, $status);
            if ($status !== 'pending') kycReviewedBy($conn, $kyc_id);

            $msg = "Đã cập nhật hồ sơ xác minh #{$kyc_id} thành công!";
            $msg_type = "success";
        }

        // C. PHÊ DUYỆT HỒ SƠ
        elseif ($action === 'approve') {
            $kyc_id   = intval($_POST['kyc_id']);
            $tutor_id = intval($_POST['tutor_id']);

            $sql = "UPDATE kyc_profiles SET status = 'approved'" . ($hasNote ? ", review_note = NULL" : "") . ($hasUpdatedAt ? ", updated_at = NOW()" : "") . " WHERE kyc_id = ?";
            $conn->prepare($sql)->execute([$kyc_id]);

            if ($hasVerified) {
                $conn->prepare("UPDATE tutors SET is_verified = 1 WHERE tutor_id = ?")->execute([$tutor_id]);
            }
            kycSyncTutor($conn, $tutor_id, 'approved');
            kycReviewedBy($conn, $kyc_id);

            $msg = "Đã phê duyệt hồ sơ xác minh #{$kyc_id} và kích hoạt tài khoản gia sư!";
            $msg_type = "success";
        }

        // D. TỪ CHỐI HỒ SƠ
        elseif ($action === 'reject') {
            $kyc_id   = intval($_POST['kyc_id']);
            $tutor_id = intval($_POST['tutor_id']);
            $reason   = trim($_POST['reject_reason'] ?? '') ?: 'Ảnh giấy tờ chưa đạt yêu cầu, vui lòng tải lại.';

            $sets = ["status = 'rejected'"];
            $params = [];

            if ($hasNote) {
                $sets[] = "review_note = ?";
                $params[] = $reason;
            }
            if ($hasUpdatedAt) {
                $sets[] = "updated_at = NOW()";
            }
            $params[] = $kyc_id;

            $conn->prepare("UPDATE kyc_profiles SET " . implode(', ', $sets) . " WHERE kyc_id = ?")->execute($params);

            if ($hasVerified) {
                $conn->prepare("UPDATE tutors SET is_verified = 0 WHERE tutor_id = ?")->execute([$tutor_id]);
            }
            kycSyncTutor($conn, $tutor_id, 'rejected');
            kycReviewedBy($conn, $kyc_id);

            $msg = "Đã từ chối hồ sơ xác minh #{$kyc_id}.";
            $msg_type = "warning";
        }

        // E. XÓA HỒ SƠ XÁC MINH
        elseif ($action === 'delete') {
            $kyc_id = intval($_POST['kyc_id']);
            $conn->prepare("DELETE FROM kyc_profiles WHERE kyc_id = ?")->execute([$kyc_id]);

            $msg = "Đã xóa hồ sơ xác minh #{$kyc_id}.";
            $msg_type = "error";
        }

    } catch (PDOException $e) {
        $msg = ve_loi_csdl($e, 'cập nhật hồ sơ xác minh');
        $msg_type = "error";
    }
}

// ------------------------------------------------------------------------------
// 2. TRUY VẤN DỮ LIỆU HIỂN THỊ (GET)
// ------------------------------------------------------------------------------
$status_filter = $_GET['status'] ?? 'pending';
$search_kw     = trim($_GET['search'] ?? '');
$kyc_list      = [];
$tutors_list   = [];
$counts        = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];

try {
    $stmtCount = $conn->query("SELECT status, COUNT(*) as total FROM kyc_profiles GROUP BY status");
    foreach ($stmtCount->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $counts[$c['status']] = (int)$c['total'];
        $counts['all'] += (int)$c['total'];
    }

    $selectNote = $hasNote ? "k.review_note AS note" : "'' AS note";
    $selectUni  = $hasUniversity ? "t.university" : "'' AS university";
    $selectMaj  = $hasMajor ? "t.major" : "'' AS major";
    $orderBy    = $hasCreatedAt ? "k.created_at DESC" : "k.kyc_id DESC";

    $sql = "SELECT k.kyc_id, k.tutor_id, k.id_card_front, k.id_card_back, k.student_card, k.degree_cert, k.submitted_at,
                   k.status, {$selectNote}, 
                   t.full_name, t.phone, {$selectUni}, {$selectMaj} 
            FROM kyc_profiles k 
            JOIN tutors t ON k.tutor_id = t.tutor_id 
            WHERE 1=1";
    $params = [];

    if ($status_filter !== 'all') {
        $sql .= " AND k.status = :status";
        $params['status'] = $status_filter;
    }

    if ($search_kw !== '') {
        $sql .= " AND (t.full_name LIKE :kw OR t.phone LIKE :kw OR k.kyc_id LIKE :kw)";
        $params['kw'] = "%{$search_kw}%";
    }

    $sql .= " ORDER BY {$orderBy}";
    $stmtList = $conn->prepare($sql);
    $stmtList->execute($params);
    $kyc_list = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    // Phân trang 10 hồ sơ / trang
    $pt = phan_trang($kyc_list, 10);
    $kyc_list = $pt['rows'];

    $tutors_list = $conn->query("SELECT tutor_id, full_name, phone FROM tutors ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $msg = ve_loi_csdl($e, 'tải danh sách hồ sơ xác minh');
    $msg_type = "error";
}
?>

<!-- ============================================================================== -->
<!-- PHẦN GIAO DIỆN HIỂN THỊ (HTML)                                                 -->
<!-- ============================================================================== -->
<div class="kyc-header-bar">
    <h2>Phân hệ 2: Soi & Phê Duyệt Ảnh CCCD / Bằng Cấp</h2>
    <button type="button" class="kyc-btn kyc-btn-primary" data-open-modal="kycAddModal">
        <i class="fa-solid fa-plus"></i> Thêm Hồ Sơ Xác Minh
    </button>
</div>

<?php if ($msg): ?>
    <div class="ph3-alert ph3-alert-<?= htmlspecialchars($msg_type) ?>">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<!-- Thanh Tab Trạng Thái & Tìm Kiếm -->
<div class="kyc-toolbar">
    <div class="kyc-tabs">
        <a href="admin.php?view=kyc&status=pending" class="kyc-tab <?= $status_filter === 'pending' ? 'active' : '' ?>">
            Chờ duyệt <span><?= $counts['pending'] ?></span>
        </a>
        <a href="admin.php?view=kyc&status=approved" class="kyc-tab <?= $status_filter === 'approved' ? 'active' : '' ?>">
            Đã duyệt <span><?= $counts['approved'] ?></span>
        </a>
        <a href="admin.php?view=kyc&status=rejected" class="kyc-tab <?= $status_filter === 'rejected' ? 'active' : '' ?>">
            Từ chối <span><?= $counts['rejected'] ?></span>
        </a>
        <a href="admin.php?view=kyc&status=all" class="kyc-tab <?= $status_filter === 'all' ? 'active' : '' ?>">
            Tất cả <span><?= $counts['all'] ?></span>
        </a>
    </div>

    <form method="GET" action="admin.php" class="kyc-search-form">
        <input type="hidden" name="view" value="kyc">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
        <input type="text" name="search" placeholder="Tìm mã hồ sơ, tên gia sư, SĐT..." value="<?= htmlspecialchars($search_kw) ?>">
        <button type="submit" class="kyc-btn kyc-btn-outline"><i class="fa-solid fa-magnifying-glass"></i> Tìm</button>
    </form>
</div>

<!-- Bảng Danh Sách Hồ Sơ Xác Minh -->
<div class="kyc-card">
    <div class="kyc-card-title">Danh Sách Hồ Sơ Chờ Xác Thực Danh Tính</div>

    <div class="kyc-table-wrap">
        <table class="kyc-table">
            <thead>
                <tr>
                    <th>Mã hồ sơ</th>
                    <th>Họ Tên Gia Sư</th>
                    <th>Số Điện Thoại</th>
                    <th class="kyc-text-center">CCCD Mặt Trước / Sau</th>
                    <th class="kyc-text-center">Thẻ SV / Bằng Cấp</th>
                    <th>Trạng Thái</th>
                    <th class="kyc-text-center">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($kyc_list) > 0): ?>
                    <?php foreach ($kyc_list as $row): 
                        $placeholder = 'https://placehold.co/150x100?text=Chua+co+anh';
                        // Ảnh hiển thị qua xem-anh-kyc.php (kiểm tra quyền), không dùng đường dẫn file trực tiếp
                        $frontImg   = kycImgUrl($row, 'id_card_front') ?: $placeholder;
                        $backImg    = kycImgUrl($row, 'id_card_back')  ?: $placeholder;
                        $studentImg = kycImgUrl($row, 'student_card')  ?: $placeholder;
                        $degreeImg  = kycImgUrl($row, 'degree_cert')   ?: $placeholder;
                        $rowJs = $row;
                        $rowJs['id_card_front'] = kycImgUrl($row, 'id_card_front');
                        $rowJs['id_card_back']  = kycImgUrl($row, 'id_card_back');
                        $rowJs['student_card']  = kycImgUrl($row, 'student_card');
                        $rowJs['degree_cert']   = kycImgUrl($row, 'degree_cert');
                        $jsonRow    = htmlspecialchars(json_encode($rowJs), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr>
                            <td><strong>#<?= $row['kyc_id'] ?></strong></td>
                            <td>
                                <strong class="kyc-tutor-name"><?= htmlspecialchars($row['full_name']) ?></strong>
                                <?php if (!empty($row['university'])): ?>
                                    <div class="kyc-tutor-uni"><?= htmlspecialchars($row['university']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['phone']) ?></td>

                            <td class="kyc-text-center">
                                <div class="kyc-thumb-group">
                                    <img src="<?= htmlspecialchars($frontImg) ?>" class="kyc-thumb" alt="CCCD Trước" title="Bấm để xem phóng to" data-zoom-img="<?= htmlspecialchars($frontImg) ?>" onerror="this.src='<?= $placeholder ?>'">
                                    <img src="<?= htmlspecialchars($backImg) ?>" class="kyc-thumb" alt="CCCD Sau" title="Bấm để xem phóng to" data-zoom-img="<?= htmlspecialchars($backImg) ?>" onerror="this.src='<?= $placeholder ?>'">
                                </div>
                            </td>

                            <td class="kyc-text-center">
                                <div class="kyc-thumb-group">
                                    <img src="<?= htmlspecialchars($studentImg) ?>" class="kyc-thumb" alt="Thẻ sinh viên" title="Thẻ sinh viên – bấm để xem phóng to" data-zoom-img="<?= htmlspecialchars($studentImg) ?>" onerror="this.src='<?= $placeholder ?>'">
                                    <img src="<?= htmlspecialchars($degreeImg) ?>" class="kyc-thumb" alt="Bằng cấp / chứng chỉ" title="Bằng cấp / chứng chỉ – bấm để xem phóng to" data-zoom-img="<?= htmlspecialchars($degreeImg) ?>" onerror="this.src='<?= $placeholder ?>'">
                                </div>
                            </td>

                            <td>
                                <?php if ($row['status'] === 'pending'): ?>
                                    <span class="badge badge-warning">Chờ duyệt</span>
                                <?php elseif ($row['status'] === 'approved'): ?>
                                    <span class="badge badge-success">Đã duyệt</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Từ chối</span>
                                <?php endif; ?>
                            </td>

                            <td class="kyc-text-center">
                                <div class="kyc-action-group">
                                    <button type="button" class="kyc-btn kyc-btn-primary" data-open-modal="kycReviewModal" data-row="<?= $jsonRow ?>">
                                        <i class="fa-solid fa-eye"></i> Soi & Duyệt
                                    </button>

                                    <button type="button" class="kyc-btn kyc-btn-warning" data-open-modal="kycEditModal" data-row="<?= $jsonRow ?>">
                                        <i class="fa-solid fa-pen"></i> Sửa
                                    </button>

                                    <form method="POST" action="admin.php?view=kyc&status=<?= urlencode($status_filter) ?>" onsubmit="return confirm('Xác nhận XÓA hồ sơ xác minh #<?= $row['kyc_id'] ?>?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="kyc_id" value="<?= $row['kyc_id'] ?>">
                                        <button type="submit" class="kyc-btn kyc-btn-danger">
                                            <i class="fa-solid fa-trash"></i> Xóa
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="kyc-empty-cell">Không có hồ sơ xác minh nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= isset($pt) ? phan_trang_html($pt, 'hồ sơ xác minh') : '' ?>
</div>

<!-- MODAL 1: SOI CHI TIẾT ẢNH & PHÊ DUYỆT / TỪ CHỐI -->
<div id="kycReviewModal" class="kyc-modal">
    <div class="kyc-modal-box">
        <div class="kyc-modal-head">
            <h3><i class="fa-solid fa-id-card"></i> Soi & Thẩm Định Hồ Sơ Xác Minh #<span data-fill="kyc_id"></span></h3>
            <button type="button" class="kyc-close-btn" data-close-modal>&times;</button>
        </div>

        <div class="kyc-info-banner">
            <div>Gia sư: <strong class="kyc-tutor-name" data-fill="full_name"></strong></div>
            <div>SĐT: <strong data-fill="phone"></strong></div>
            <div>Trường ĐH: <strong data-fill="university"></strong></div>
        </div>

        <div class="kyc-preview-grid">
            <div class="kyc-preview-item">
                <img src="" alt="CCCD Mặt trước" data-img="id_card_front" data-zoom-self>
                <p>CCCD - Mặt Trước</p>
            </div>
            <div class="kyc-preview-item">
                <img src="" alt="CCCD Mặt sau" data-img="id_card_back" data-zoom-self>
                <p>CCCD - Mặt Sau</p>
            </div>
            <div class="kyc-preview-item">
                <img src="" alt="Thẻ sinh viên" data-img="student_card" data-zoom-self>
                <p>Thẻ Sinh Viên</p>
            </div>
            <div class="kyc-preview-item">
                <img src="" alt="Bằng cấp / chứng chỉ" data-img="degree_cert" data-zoom-self>
                <p>Bằng Cấp / Chứng Chỉ</p>
            </div>
        </div>

        <form method="POST" action="admin.php?view=kyc&status=<?= urlencode($status_filter) ?>">
            <input type="hidden" name="kyc_id" data-fill="kyc_id">
            <input type="hidden" name="tutor_id" data-fill="tutor_id">

            <div class="kyc-form-group">
                <label>Ghi chú thẩm định / Lý do từ chối (nếu từ chối):</label>
                <textarea name="reject_reason" id="rejectReasonInput" rows="2" data-fill="note" placeholder="Nhập lý do từ chối hoặc chọn nhanh bên dưới..."></textarea>
                <div class="kyc-chips">
                    <button type="button" class="kyc-chip">Ảnh CCCD bị mờ, lóa sáng</button>
                    <button type="button" class="kyc-chip">Ảnh bị cắt góc, thiếu thông tin</button>
                    <button type="button" class="kyc-chip">Họ tên không trùng khớp giấy tờ</button>
                    <button type="button" class="kyc-chip">Thẻ sinh viên / Bằng cấp không hợp lệ</button>
                </div>
            </div>

            <div class="kyc-modal-foot">
                <button type="button" class="kyc-btn kyc-btn-outline" data-close-modal>Đóng</button>
                <button type="submit" name="action" value="reject" class="kyc-btn kyc-btn-danger" onclick="return confirm('Xác nhận TỪ CHỐI hồ sơ xác minh này?');">
                    <i class="fa-solid fa-xmark"></i> Từ Chối
                </button>
                <button type="submit" name="action" value="approve" class="kyc-btn kyc-btn-success" onclick="return confirm('Xác nhận PHÊ DUYỆT và kích hoạt tài khoản gia sư này?');">
                    <i class="fa-solid fa-check"></i> Phê Duyệt Kích Hoạt
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: THÊM MỚI HỒ SƠ XÁC MINH -->
<div id="kycAddModal" class="kyc-modal">
    <div class="kyc-modal-box kyc-modal-sm">
        <div class="kyc-modal-head">
            <h3><i class="fa-solid fa-folder-plus"></i> Thêm Mới Hồ Sơ Xác Minh</h3>
            <button type="button" class="kyc-close-btn" data-close-modal>&times;</button>
        </div>

        <form method="POST" action="admin.php?view=kyc&status=<?= urlencode($status_filter) ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">

            <div class="kyc-form-group">
                <label>Chọn Gia Sư (*):</label>
                <select name="tutor_id" required>
                    <option value="">-- Chọn gia sư trong hệ thống --</option>
                    <?php foreach ($tutors_list as $t): ?>
                        <option value="<?= $t['tutor_id'] ?>">
                            #<?= $t['tutor_id'] ?> - <?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['phone']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="kyc-form-group">
                <label>Ảnh CCCD Mặt Trước:</label>
                <input type="file" name="id_card_front" accept="image/*" required>
            </div>

            <div class="kyc-form-group">
                <label>Ảnh CCCD Mặt Sau:</label>
                <input type="file" name="id_card_back" accept="image/*" required>
            </div>

            <div class="kyc-form-group">
                <label>Ảnh Thẻ Sinh Viên:</label>
                <input type="file" name="student_card" accept="image/*" required>
            </div>

            <div class="kyc-form-group">
                <label>Ảnh Bằng Cấp / Chứng Chỉ (không bắt buộc):</label>
                <input type="file" name="degree_cert" accept="image/*">
            </div>

            <div class="kyc-form-group">
                <label>Trạng Thái Ban Đầu:</label>
                <select name="status">
                    <option value="pending">Chờ duyệt</option>
                    <option value="approved">Duyệt luôn</option>
                </select>
            </div>

            <div class="kyc-form-group">
                <label>Ghi chú:</label>
                <textarea name="note" rows="2" placeholder="Ghi chú thêm (nếu có)..."></textarea>
            </div>

            <div class="kyc-modal-foot">
                <button type="button" class="kyc-btn kyc-btn-outline" data-close-modal>Hủy</button>
                <button type="submit" class="kyc-btn kyc-btn-primary"><i class="fa-solid fa-floppy-disk"></i> Lưu Hồ Sơ</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 3: SỬA HỒ SƠ XÁC MINH -->
<div id="kycEditModal" class="kyc-modal">
    <div class="kyc-modal-box kyc-modal-sm">
        <div class="kyc-modal-head">
            <h3><i class="fa-solid fa-pen-to-square"></i> Sửa Hồ Sơ Xác Minh #<span data-fill="kyc_id"></span></h3>
            <button type="button" class="kyc-close-btn" data-close-modal>&times;</button>
        </div>

        <form method="POST" action="admin.php?view=kyc&status=<?= urlencode($status_filter) ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="kyc_id" data-fill="kyc_id">
            <input type="hidden" name="tutor_id" data-fill="tutor_id">

            <div class="kyc-form-group">
                <label>Gia Sư:</label>
                <input type="text" class="kyc-readonly" data-fill="full_name" readonly>
            </div>

            <div class="kyc-form-group">
                <label>Thay Ảnh CCCD Mặt Trước (để trống nếu giữ nguyên):</label>
                <input type="file" name="id_card_front" accept="image/*">
            </div>

            <div class="kyc-form-group">
                <label>Thay Ảnh CCCD Mặt Sau (để trống nếu giữ nguyên):</label>
                <input type="file" name="id_card_back" accept="image/*">
            </div>

            <div class="kyc-form-group">
                <label>Thay Ảnh Thẻ Sinh Viên (để trống nếu giữ nguyên):</label>
                <input type="file" name="student_card" accept="image/*">
            </div>

            <div class="kyc-form-group">
                <label>Thay Ảnh Bằng Cấp / Chứng Chỉ (để trống nếu giữ nguyên):</label>
                <input type="file" name="degree_cert" accept="image/*">
            </div>

            <div class="kyc-form-group">
                <label>Trạng Thái:</label>
                <select name="status" data-fill="status">
                    <option value="pending">Chờ duyệt</option>
                    <option value="approved">Đã duyệt</option>
                    <option value="rejected">Từ chối</option>
                </select>
            </div>

            <div class="kyc-form-group">
                <label>Ghi chú / Lý do:</label>
                <textarea name="note" rows="2" data-fill="note"></textarea>
            </div>

            <div class="kyc-modal-foot">
                <button type="button" class="kyc-btn kyc-btn-outline" data-close-modal>Hủy</button>
                <button type="submit" class="kyc-btn kyc-btn-warning"><i class="fa-solid fa-check"></i> Cập Nhật</button>
            </div>
        </form>
    </div>
</div>