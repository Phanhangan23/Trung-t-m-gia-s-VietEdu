<?php
// ==============================================================================
// PHẦN 1: XỬ LÝ NGHIỆP VỤ & LOGIC DATABASE (BACKEND PHP)
// Tệp: admin_views/tutors.php
// ==============================================================================

$msg = '';
$msg_type = '';

// Kiểm tra cấu trúc cột thực tế của bảng tutors để tránh lỗi thiếu cột / NOT NULL
$tutorColsInfo = $conn->query("SHOW COLUMNS FROM tutors")->fetchAll(PDO::FETCH_ASSOC);
$tutorCols     = array_column($tutorColsInfo, 'Field');

$hasEmail      = in_array('email', $tutorCols);
$hasPassword   = in_array('password_hash', $tutorCols);
$hasExp        = in_array('teaching_experience', $tutorCols);
$hasVerified   = in_array('is_verified', $tutorCols);
$hasCreatedAt  = in_array('created_at', $tutorCols);

// ------------------------------------------------------------------------------
// 1. XỬ LÝ CÁC THAO TÁC POST (THÊM / SỬA / XÓA GIA SƯ)
// ------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        // A. THÊM MỚI GIA SƯ
        if ($action === 'create') {
            $full_name  = trim($_POST['full_name'] ?? '');
            $phone      = trim($_POST['phone'] ?? '');
            $email      = trim($_POST['email'] ?? '');
            $university = trim($_POST['university'] ?? '');
            $major      = trim($_POST['major'] ?? '');
            $rating     = max(0, min(5, floatval($_POST['rating'] ?? 5.0)));
            $status     = in_array($_POST['status'] ?? '', ['pending', 'active', 'locked'], true) ? $_POST['status'] : 'pending';
            $exp        = trim($_POST['teaching_experience'] ?? '');

            $fields       = ['full_name', 'phone', 'university', 'major', 'rating', 'status'];
            $placeholders = [':full_name', ':phone', ':university', ':major', ':rating', ':status'];
            $params       = [
                'full_name'  => $full_name,
                'phone'      => $phone,
                'university' => $university,
                'major'      => $major,
                'rating'     => $rating,
                'status'     => $status
            ];

            if ($hasEmail) {
                $fields[]       = 'email';
                $placeholders[] = ':email';
                $params['email'] = $email !== '' ? $email : ('gs_' . time() . '@vietedu.vn');
            }
            $matKhauTam = '';
            if ($hasPassword) {
                // Mật khẩu tạm ngẫu nhiên đạt chuẩn bảo mật (không dùng mật khẩu mặc định chung)
                $matKhauTam = ve_tao_mat_khau_ngau_nhien();
                $fields[]          = 'password_hash';
                $placeholders[]    = ':password';
                $params['password'] = password_hash($matKhauTam, PASSWORD_DEFAULT);
            }
            if ($hasExp) {
                $fields[]     = 'teaching_experience';
                $placeholders[] = ':exp';
                $params['exp'] = $exp;
            }
            if ($hasVerified) {
                $fields[]          = 'is_verified';
                $placeholders[]    = ':verified';
                $params['verified'] = ($status === 'active') ? 1 : 0;
            }
            if ($hasCreatedAt) {
                $fields[]       = 'created_at';
                $placeholders[] = 'NOW()';
            }

            $sql = "INSERT INTO tutors (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $conn->prepare($sql)->execute($params);

            $msg = "Đã thêm hồ sơ gia sư \"{$full_name}\" thành công!"
                 . ($matKhauTam !== '' ? " Mật khẩu tạm: {$matKhauTam} (chỉ hiển thị 1 lần — hãy gửi cho gia sư và yêu cầu đổi mật khẩu khi đăng nhập)." : '');
            $msg_type = "success";
        }

        // B. SỬA / CẬP NHẬT THÔNG TIN GIA SƯ
        elseif ($action === 'update') {
            $tutor_id   = intval($_POST['tutor_id']);
            $full_name  = trim($_POST['full_name'] ?? '');
            $phone      = trim($_POST['phone'] ?? '');
            $email      = trim($_POST['email'] ?? '');
            $university = trim($_POST['university'] ?? '');
            $major      = trim($_POST['major'] ?? '');
            $rating     = max(0, min(5, floatval($_POST['rating'] ?? 5.0)));
            $status     = in_array($_POST['status'] ?? '', ['pending', 'active', 'locked'], true) ? $_POST['status'] : 'active';
            $exp        = trim($_POST['teaching_experience'] ?? '');

            $sets = [
                'full_name = :full_name',
                'phone = :phone',
                'university = :university',
                'major = :major',
                'rating = :rating',
                'status = :status'
            ];
            $params = [
                'full_name'  => $full_name,
                'phone'      => $phone,
                'university' => $university,
                'major'      => $major,
                'rating'     => $rating,
                'status'     => $status,
                'tutor_id'   => $tutor_id
            ];

            if ($hasEmail && $email !== '') {
                $sets[]          = 'email = :email';
                $params['email'] = $email;
            }
            if ($hasExp) {
                $sets[]        = 'teaching_experience = :exp';
                $params['exp'] = $exp;
            }
            if ($hasVerified) {
                $sets[]             = 'is_verified = :verified';
                $params['verified'] = ($status === 'active') ? 1 : 0;
            }

            $sql = "UPDATE tutors SET " . implode(', ', $sets) . " WHERE tutor_id = :tutor_id";
            $conn->prepare($sql)->execute($params);

            $msg = "Đã cập nhật hồ sơ gia sư #{$tutor_id} thành công!";
            $msg_type = "success";
        }

        // C. XÓA GIA SƯ
        elseif ($action === 'delete') {
            $tutor_id = intval($_POST['tutor_id']);

            // Ghi nhớ các file ảnh của gia sư (ảnh đại diện + ảnh KYC) để dọn sau khi xóa thành công
            $files = [];
            if (in_array('avatar', $tutorCols)) {
                $st = $conn->prepare('SELECT avatar FROM tutors WHERE tutor_id = ?');
                $st->execute([$tutor_id]);
                $files[] = $st->fetchColumn();
            }
            $st = $conn->prepare('SELECT id_card_front, id_card_back, student_card, degree_cert FROM kyc_profiles WHERE tutor_id = ?');
            $st->execute([$tutor_id]);
            foreach ($st->fetchAll(PDO::FETCH_NUM) as $r) $files = array_merge($files, $r);

            $del = $conn->prepare("DELETE FROM tutors WHERE tutor_id = ?");
            $del->execute([$tutor_id]);

            if ($del->rowCount() > 0) {
                foreach (array_filter($files) as $f) {
                    // Chỉ xóa file nằm trong thư mục upload của cổng gia sư
                    if (preg_match('#^uploads/(avatars|kyc)/[A-Za-z0-9_.-]+$#', $f)) @unlink(__DIR__ . '/../gia-su/' . $f);
                }
                $msg = "Đã xóa hồ sơ gia sư #{$tutor_id}.";
                $msg_type = "success";
            } else {
                $msg = "Không tìm thấy gia sư #{$tutor_id} (có thể đã bị xóa trước đó).";
                $msg_type = "error";
            }
        }

    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            $msg = "Không thể xóa gia sư này vì đang có hồ sơ xác minh, lớp học hoặc giao dịch liên kết. Hãy chuyển trạng thái sang Đã khóa.";
        } else {
            $msg = ve_loi_csdl($e, 'lưu hồ sơ gia sư');
        }
        $msg_type = "error";
    }
}

// ------------------------------------------------------------------------------
// 2. TRUY VẤN DỮ LIỆU HIỂN THỊ (GET)
// ------------------------------------------------------------------------------
$status_filter = $_GET['status'] ?? 'all';
$search_kw     = trim($_GET['search'] ?? '');
$tutors_list   = [];
$counts        = ['all' => 0, 'active' => 0, 'pending' => 0, 'locked' => 0];

try {
    // Đếm số lượng theo từng trạng thái
    $stmtCount = $conn->query("SELECT LOWER(status) AS st, COUNT(*) AS total FROM tutors GROUP BY LOWER(status)");
    foreach ($stmtCount->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $st = $c['st'] ?: 'pending';
        if (isset($counts[$st])) {
            $counts[$st] = (int)$c['total'];
        }
        $counts['all'] += (int)$c['total'];
    }

    $selectEmail = $hasEmail ? "email" : "'' AS email";
    $selectExp   = $hasExp ? "teaching_experience" : "'' AS teaching_experience";

    $selectAvatar = in_array('avatar', $tutorCols) ? "avatar" : "NULL AS avatar";

    $sql = "SELECT tutor_id, full_name, phone, {$selectEmail}, university, major, rating, status, {$selectExp}, {$selectAvatar}
            FROM tutors WHERE 1=1";
    $params = [];

    if ($status_filter !== 'all') {
        $sql .= " AND LOWER(status) = :status";
        $params['status'] = strtolower($status_filter);
    }

    if ($search_kw !== '') {
        $sql .= " AND (full_name LIKE :kw OR phone LIKE :kw OR university LIKE :kw OR major LIKE :kw)";
        $params['kw'] = "%{$search_kw}%";
    }

    $sql .= " ORDER BY tutor_id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $tutors_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Phân trang 10 gia sư / trang
    $pt = phan_trang($tutors_list, 10);
    $tutors_list = $pt['rows'];

} catch (PDOException $e) {
    $msg = ve_loi_csdl($e, 'tải danh sách gia sư');
    $msg_type = "error";
}
?>

<!-- ============================================================================== -->
<!-- PHẦN 2: GIAO DIỆN HIỂN THỊ (HTML)                                              -->
<!-- ============================================================================== -->

<div class="kyc-header-bar">
    <h2>Phân hệ 2: Quản Lý Hồ Sơ Gia Sư</h2>
    <button type="button" class="kyc-btn kyc-btn-primary" data-open-modal="tutorAddModal">
        <i class="fa-solid fa-plus"></i> Thêm Gia Sư
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
        <a href="admin.php?view=tutors&status=all" class="kyc-tab <?= $status_filter === 'all' ? 'active' : '' ?>">
            Tất cả <span><?= $counts['all'] ?></span>
        </a>
        <a href="admin.php?view=tutors&status=active" class="kyc-tab <?= $status_filter === 'active' ? 'active' : '' ?>">
            Đang hoạt động <span><?= $counts['active'] ?></span>
        </a>
        <a href="admin.php?view=tutors&status=pending" class="kyc-tab <?= $status_filter === 'pending' ? 'active' : '' ?>">
            Chờ duyệt <span><?= $counts['pending'] ?></span>
        </a>
        <a href="admin.php?view=tutors&status=locked" class="kyc-tab <?= $status_filter === 'locked' ? 'active' : '' ?>">
            Đã khóa <span><?= $counts['locked'] ?></span>
        </a>
    </div>

    <form method="GET" action="admin.php" class="kyc-search-form">
        <input type="hidden" name="view" value="tutors">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
        <input type="text" name="search" placeholder="Tìm tên, SĐT, trường ĐH, chuyên ngành..." value="<?= htmlspecialchars($search_kw) ?>">
        <button type="submit" class="kyc-btn kyc-btn-outline"><i class="fa-solid fa-magnifying-glass"></i> Tìm</button>
    </form>
</div>

<!-- Bảng Danh Sách Gia Sư Đã Đăng Ký -->
<div class="kyc-card">
    <div class="kyc-card-title">Danh Sách Gia Sư Đã Đăng Ký</div>

    <div class="kyc-table-wrap">
        <table class="kyc-table">
            <thead>
                <tr>
                    <th>Mã</th>
                    <th>Họ và Tên</th>
                    <th>Số Điện Thoại</th>
                    <th>Trường Đại Học</th>
                    <th>Chuyên Ngành</th>
                    <th>Đánh Giá</th>
                    <th>Trạng Thái</th>
                    <th class="kyc-text-center">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($tutors_list) > 0): ?>
                    <?php foreach ($tutors_list as $row): 
                        $stLower = strtolower($row['status'] ?? 'pending');
                        $ratingVal = number_format(floatval($row['rating']), 1);
                        $row['avatar_url'] = ve_url_anh_gia_su($row['avatar'] ?? null) ?? '';
                        $jsonRow = htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['tutor_id']) ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <?= ve_the_anh_gia_su($row['avatar'] ?? null, 38) ?>
                                    <strong class="kyc-tutor-name"><?= htmlspecialchars($row['full_name']) ?></strong>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($row['phone']) ?></td>
                            <td><?= htmlspecialchars($row['university']) ?></td>
                            <td><?= htmlspecialchars($row['major']) ?></td>
                            <td>
                                <i class="fa-solid fa-star" style="color: #D4AF37;"></i> <?= $ratingVal ?>
                            </td>
                            <td>
                                <?php if ($stLower === 'active'): ?>
                                    <span class="badge badge-success">Đang hoạt động</span>
                                <?php elseif ($stLower === 'locked'): ?>
                                    <span class="badge badge-danger">Đã khóa</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Chờ duyệt</span>
                                <?php endif; ?>
                            </td>
                            <td class="kyc-text-center">
                                <div class="kyc-action-group">
                                    <button type="button" class="kyc-btn kyc-btn-outline" data-open-modal="tutorViewModal" data-row="<?= $jsonRow ?>">
                                        <i class="fa-solid fa-eye"></i> Chi tiết
                                    </button>

                                    <button type="button" class="kyc-btn kyc-btn-warning" data-open-modal="tutorEditModal" data-row="<?= $jsonRow ?>">
                                        <i class="fa-solid fa-pen"></i> Sửa
                                    </button>

                                    <form method="POST" action="admin.php?view=tutors&status=<?= urlencode($status_filter) ?>" onsubmit="return confirm('Xác nhận XÓA hồ sơ gia sư #<?= $row['tutor_id'] ?> (<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>)?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="tutor_id" value="<?= $row['tutor_id'] ?>">
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
                        <td colspan="8" class="kyc-empty-cell">Không tìm thấy hồ sơ gia sư nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= isset($pt) ? phan_trang_html($pt, 'gia sư') : '' ?>
</div>

<!-- ============================================================================== -->
<!-- MODAL 1: XEM CHI TIẾT HỒ SƠ GIA SƯ                                             -->
<!-- ============================================================================== -->
<div id="tutorViewModal" class="kyc-modal">
    <div class="kyc-modal-box kyc-modal-sm">
        <div class="kyc-modal-head">
            <h3><i class="fa-solid fa-user-graduate"></i> Chi Tiết Hồ Sơ Gia Sư #<span data-fill="tutor_id"></span></h3>
            <button type="button" class="kyc-close-btn" data-close-modal>&times;</button>
        </div>

        <div style="text-align:center;margin-bottom:12px">
            <img data-img="avatar_url" alt="Ảnh đại diện gia sư"
                 style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:1px solid #e3e3e3">
        </div>
        <div class="kyc-info-banner" style="margin-bottom: 14px;">
            <div>Họ và tên: <strong class="kyc-tutor-name" data-fill="full_name"></strong></div>
            <div>Đánh giá: <strong><i class="fa-solid fa-star" style="color: #D4AF37;"></i> <span data-fill="rating"></span> / 5.0</strong></div>
        </div>

        <div class="kyc-form-group">
            <label>Số Điện Thoại:</label>
            <input type="text" class="kyc-readonly" data-fill="phone" readonly>
        </div>

        <?php if ($hasEmail): ?>
        <div class="kyc-form-group">
            <label>Email:</label>
            <input type="text" class="kyc-readonly" data-fill="email" readonly>
        </div>
        <?php endif; ?>

        <div class="kyc-form-group">
            <label>Trường Đại Học:</label>
            <input type="text" class="kyc-readonly" data-fill="university" readonly>
        </div>

        <div class="kyc-form-group">
            <label>Chuyên Ngành:</label>
            <input type="text" class="kyc-readonly" data-fill="major" readonly>
        </div>

        <?php if ($hasExp): ?>
        <div class="kyc-form-group">
            <label>Kinh Nghiệm Giảng Dạy:</label>
            <textarea class="kyc-readonly" rows="2" data-fill="teaching_experience" readonly></textarea>
        </div>
        <?php endif; ?>

        <div class="kyc-modal-foot">
            <button type="button" class="kyc-btn kyc-btn-outline" data-close-modal>Đóng</button>
        </div>
    </div>
</div>

<!-- ============================================================================== -->
<!-- MODAL 2: THÊM MỚI GIA SƯ                                                       -->
<!-- ============================================================================== -->
<div id="tutorAddModal" class="kyc-modal">
    <div class="kyc-modal-box kyc-modal-sm">
        <div class="kyc-modal-head">
            <h3><i class="fa-solid fa-user-plus"></i> Thêm Mới Hồ Sơ Gia Sư</h3>
            <button type="button" class="kyc-close-btn" data-close-modal>&times;</button>
        </div>

        <form method="POST" action="admin.php?view=tutors&status=<?= urlencode($status_filter) ?>">
            <input type="hidden" name="action" value="create">

            <div class="kyc-form-group">
                <label>Họ và Tên (*):</label>
                <input type="text" name="full_name" required placeholder="Ví dụ: Nguyễn Văn An">
            </div>

            <div class="kyc-form-group">
                <label>Số Điện Thoại (*):</label>
                <input type="text" name="phone" required placeholder="Ví dụ: 0988112233">
            </div>

            <?php if ($hasEmail): ?>
            <div class="kyc-form-group">
                <label>Email:</label>
                <input type="text" name="email" placeholder="Để trống hệ thống tự tạo email mặc định">
            </div>
            <?php endif; ?>

            <div class="kyc-form-group">
                <label>Trường Đại Học (*):</label>
                <input type="text" name="university" required placeholder="Ví dụ: ĐH Thương mại">
            </div>

            <div class="kyc-form-group">
                <label>Chuyên Ngành (*):</label>
                <input type="text" name="major" required placeholder="Ví dụ: Hệ thống thông tin quản lý">
            </div>

            <div class="kyc-form-group">
                <label>Điểm Đánh Giá (0.0 - 5.0):</label>
                <input type="text" name="rating" value="5.0">
            </div>

            <div class="kyc-form-group">
                <label>Trạng Thái:</label>
                <select name="status">
                    <option value="active">Đang hoạt động</option>
                    <option value="pending">Chờ duyệt</option>
                    <option value="locked">Đã khóa</option>
                </select>
            </div>

            <?php if ($hasExp): ?>
            <div class="kyc-form-group">
                <label>Kinh Nghiệm Giảng Dạy:</label>
                <textarea name="teaching_experience" rows="2" placeholder="Mô tả kinh nghiệm gia sư..."></textarea>
            </div>
            <?php endif; ?>

            <div class="kyc-modal-foot">
                <button type="button" class="kyc-btn kyc-btn-outline" data-close-modal>Hủy</button>
                <button type="submit" class="kyc-btn kyc-btn-primary"><i class="fa-solid fa-floppy-disk"></i> Lưu Gia Sư</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================================== -->
<!-- MODAL 3: SỬA HỒ SƠ GIA SƯ                                                      -->
<!-- ============================================================================== -->
<div id="tutorEditModal" class="kyc-modal">
    <div class="kyc-modal-box kyc-modal-sm">
        <div class="kyc-modal-head">
            <h3><i class="fa-solid fa-pen-to-square"></i> Sửa Hồ Sơ Gia Sư #<span data-fill="tutor_id"></span></h3>
            <button type="button" class="kyc-close-btn" data-close-modal>&times;</button>
        </div>

        <form method="POST" action="admin.php?view=tutors&status=<?= urlencode($status_filter) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="tutor_id" data-fill="tutor_id">

            <div class="kyc-form-group">
                <label>Họ và Tên (*):</label>
                <input type="text" name="full_name" data-fill="full_name" required>
            </div>

            <div class="kyc-form-group">
                <label>Số Điện Thoại (*):</label>
                <input type="text" name="phone" data-fill="phone" required>
            </div>

            <?php if ($hasEmail): ?>
            <div class="kyc-form-group">
                <label>Email:</label>
                <input type="text" name="email" data-fill="email">
            </div>
            <?php endif; ?>

            <div class="kyc-form-group">
                <label>Trường Đại Học (*):</label>
                <input type="text" name="university" data-fill="university" required>
            </div>

            <div class="kyc-form-group">
                <label>Chuyên Ngành (*):</label>
                <input type="text" name="major" data-fill="major" required>
            </div>

            <div class="kyc-form-group">
                <label>Điểm Đánh Giá (0.0 - 5.0):</label>
                <input type="text" name="rating" data-fill="rating">
            </div>

            <div class="kyc-form-group">
                <label>Trạng Thái:</label>
                <select name="status" data-fill="status">
                    <option value="active">Đang hoạt động</option>
                    <option value="pending">Chờ duyệt</option>
                    <option value="locked">Đã khóa</option>
                </select>
            </div>

            <?php if ($hasExp): ?>
            <div class="kyc-form-group">
                <label>Kinh Nghiệm Giảng Dạy:</label>
                <textarea name="teaching_experience" rows="2" data-fill="teaching_experience"></textarea>
            </div>
            <?php endif; ?>

            <div class="kyc-modal-foot">
                <button type="button" class="kyc-btn kyc-btn-outline" data-close-modal>Hủy</button>
                <button type="submit" class="kyc-btn kyc-btn-warning"><i class="fa-solid fa-check"></i> Cập Nhật</button>
            </div>
        </form>
    </div>
</div>