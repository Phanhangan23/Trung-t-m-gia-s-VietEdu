<?php
// Mã bảo vệ form (tạo 1 lần cho mỗi phiên đăng nhập)
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

// Thông báo từ lần thêm trước (hiện 1 lần rồi xóa)
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Tìm kiếm theo họ tên / tên đăng nhập + lọc theo vai trò
$tuKhoa = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100, 'UTF-8');
$locRole = (int) ($_GET['role'] ?? 0);

try {
    $dk = [];
    $tso = [];
    if ($tuKhoa !== '') {
        $dk[] = '(s.full_name LIKE ? OR s.username LIKE ? OR r.role_name LIKE ?)';
        array_push($tso, "%$tuKhoa%", "%$tuKhoa%", "%$tuKhoa%");
    }
    if ($locRole > 0) {
        $dk[] = 's.role_id = ?';
        $tso[] = $locRole;
    }
    $stmt = $conn->prepare("SELECT s.staff_id, s.full_name, s.username, s.role_id, s.created_at, r.role_name
                            FROM staffs s LEFT JOIN roles r ON s.role_id = r.role_id"
                         . ($dk ? ' WHERE ' . implode(' AND ', $dk) : '') . " ORDER BY s.staff_id DESC");
    $stmt->execute($tso);
    $staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $tongNV = (int) $conn->query('SELECT COUNT(*) FROM staffs')->fetchColumn();

    // Phân trang 10 nhân viên / trang
    $pt = phan_trang($staffs, 10);
    $staffs = $pt['rows'];

    // Danh sách vai trò cho ô chọn trong form
    $dsRole = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_id")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log('Lỗi tải nhân viên: ' . $e->getMessage());
    $staffs = [];
    $dsRole = [];
    $flash = ['loai' => 'loi', 'nd' => 'Không tải được dữ liệu nhân viên.'];
}
?>

<section id="view-staff" class="content-view active">
    <div class="view-header">
        <h2>Phân hệ 1: Quản Lý & Phân Quyền Nhân Viên Tư Vấn</h2>
        <button type="button" class="btn-excel" style="background-color: var(--primary-red); color: #fff; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer;"
                onclick="document.getElementById('formThem').style.display = 'block'">
            <i class="fa-solid fa-user-plus"></i> Thêm Nhân Viên Mới
        </button>
    </div>

    <?php if ($flash): ?>
        <div style="padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;
                    background: <?= $flash['loai'] === 'ok' ? '#EAF7ED' : '#FDECEA' ?>;
                    color: <?= $flash['loai'] === 'ok' ? '#2E7D32' : '#B3261E' ?>;">
            <?= htmlspecialchars($flash['nd']) ?>
        </div>
    <?php endif; ?>

    <!-- FORM THÊM NHÂN VIÊN (ẩn sẵn, hiện khi bấm nút; hiện luôn nếu vừa bị lỗi) -->
    <div class="data-table-card" id="formThem" style="margin-bottom: 20px; display: <?= ($flash && $flash['loai'] === 'loi') ? 'block' : 'none' ?>;">
        <h3>Thêm nhân viên mới</h3>
        <form method="post" action="staff-them.php" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

            <label>Họ và tên<br>
                <input type="text" name="full_name" required maxlength="100" style="width:100%; padding:8px; border:1px solid var(--border-color); border-radius:4px;">
            </label>
            <label>Tên đăng nhập (không dấu)<br>
                <input type="text" name="username" required maxlength="50" style="width:100%; padding:8px; border:1px solid var(--border-color); border-radius:4px;">
            </label>
            <label>Mật khẩu<br>
                <input type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password"
                       pattern="<?= htmlspecialchars(VE_MK_PATTERN) ?>" title="<?= htmlspecialchars(VE_MK_MO_TA) ?>" data-pw-policy
                       style="width:100%; padding:8px; border:1px solid var(--border-color); border-radius:4px;">
                <small style="color: var(--text-muted);"><?= htmlspecialchars(VE_MK_MO_TA) ?></small>
            </label>
            <label>Vai trò<br>
                <select name="role_id" required style="width:100%; padding:8px; border:1px solid var(--border-color); border-radius:4px;">
                    <?php foreach ($dsRole as $r): ?>
                        <option value="<?= (int) $r['role_id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div style="grid-column: 1 / -1;">
                <button type="submit" style="background: var(--primary-red); color: #fff; padding: 10px 20px; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">Lưu nhân viên</button>
                <button type="button" style="padding: 10px 20px; border: 1px solid var(--border-color); background: #fff; border-radius: 4px; cursor: pointer;"
                        onclick="document.getElementById('formThem').style.display = 'none'">Hủy</button>
            </div>
        </form>
    </div>

        <!-- FORM SỬA NHÂN VIÊN (ẩn sẵn, bấm nút Sửa mới hiện) -->
    <div class="data-table-card" id="formSua" style="margin-bottom: 20px; display: none;">
        <h3>Sửa nhân viên: <span id="suaTenDangNhap"></span></h3>
        <form method="post" action="staff-sua.php" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            <input type="hidden" name="staff_id" id="suaId">

            <label>Họ và tên<br>
                <input type="text" name="full_name" id="suaTen" required maxlength="100" style="width:100%; padding:8px; border:1px solid var(--border-color); border-radius:4px;">
            </label>
            <label>Vai trò<br>
                <select name="role_id" id="suaRole" required style="width:100%; padding:8px; border:1px solid var(--border-color); border-radius:4px;">
                    <?php foreach ($dsRole as $r): ?>
                        <option value="<?= (int) $r['role_id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label style="grid-column: 1 / -1;">Mật khẩu mới (để trống nếu không đổi)<br>
                <input type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password"
                       pattern="<?= htmlspecialchars(VE_MK_PATTERN) ?>" title="<?= htmlspecialchars(VE_MK_MO_TA) ?>"
                       style="width:100%; padding:8px; border:1px solid var(--border-color); border-radius:4px;">
                <small style="color: var(--text-muted);"><?= htmlspecialchars(VE_MK_MO_TA) ?></small>
            </label>

            <div style="grid-column: 1 / -1;">
                <button type="submit" style="background: var(--primary-red); color: #fff; padding: 10px 20px; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">Lưu thay đổi</button>
                <button type="button" style="padding: 10px 20px; border: 1px solid var(--border-color); background: #fff; border-radius: 4px; cursor: pointer;"
                        onclick="document.getElementById('formSua').style.display = 'none'">Hủy</button>
            </div>
        </form>
    </div>

        <!-- Form ẩn: JavaScript sẽ điền ID rồi gửi đi khi người dùng xác nhận xóa -->
    <form method="post" action="staff-xoa.php" id="formXoa" style="display: none;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
        <input type="hidden" name="staff_id" id="xoaId">
    </form>

    <div class="data-table-card">
        <h3>Danh Sách Tài Khoản Nhân Viên & Phân Quyền</h3>

        <form method="get" action="admin.php" class="db-filter" role="search" style="margin-top:12px">
            <input type="hidden" name="view" value="staff">
            <div class="db-field db-field-search">
                <label for="nv-q">Tìm nhân viên</label>
                <div class="db-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" id="nv-q" name="q" value="<?= htmlspecialchars($tuKhoa) ?>" maxlength="100"
                           placeholder="Họ tên, tên đăng nhập hoặc tên vai trò…">
                </div>
            </div>
            <div class="db-field">
                <label for="nv-role">Vai trò</label>
                <select id="nv-role" name="role" onchange="this.form.submit()">
                    <option value="">Tất cả vai trò</option>
                    <?php foreach ($dsRole as $rl): ?>
                        <option value="<?= (int) $rl['role_id'] ?>" <?= $locRole === (int) $rl['role_id'] ? 'selected' : '' ?>><?= htmlspecialchars($rl['role_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="db-actions">
                <button type="submit" class="db-btn db-btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Tìm</button>
                <?php if ($tuKhoa !== '' || $locRole > 0): ?>
                    <a href="admin.php?view=staff" class="db-btn db-btn-ghost"><i class="fa-solid fa-rotate-left"></i> Xóa lọc</a>
                <?php endif; ?>
            </div>
        </form>
        <?php if (($tuKhoa !== '' || $locRole > 0) && isset($pt)): ?>
            <p class="db-period">Tìm thấy <b><?= $pt['total'] ?></b> / <?= $tongNV ?? 0 ?> nhân viên.</p>
        <?php endif; ?>

        <table class="custom-table">
            <thead>
                <tr>
                    <th>Mã</th>
                    <th>Họ và Tên</th>
                    <th>Tên đăng nhập</th>
                    <th>Vai trò</th>
                    <th>Ngày tạo</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($staffs)): ?>
                    <?php foreach ($staffs as $stf): ?>
                        <tr>
                            <td><?= htmlspecialchars($stf['staff_id']) ?></td>
                            <td><strong><?= to_dam_tu_khoa($stf['full_name'], $tuKhoa) ?></strong></td>
                            <td><?= to_dam_tu_khoa($stf['username'], $tuKhoa) ?></td>
                            <td><span class="badge badge-success"><?= to_dam_tu_khoa($stf['role_name'] ?? 'Chưa phân quyền', $tuKhoa) ?></span></td>
                            <td><?= date('d/m/Y H:i', strtotime($stf['created_at'])) ?></td>
                            <td>
                                <button type="button" class="btn-sm" style="padding: 4px 8px; background: #3498db; color: #fff; border:none; border-radius:3px; cursor:pointer;"
        data-id="<?= (int) $stf['staff_id'] ?>"
        data-ten="<?= htmlspecialchars($stf['full_name']) ?>"
        data-username="<?= htmlspecialchars($stf['username']) ?>"
        data-role="<?= (int) $stf['role_id'] ?>"
        onclick="moSua(this)"><i class="fa-solid fa-pen"></i> Sửa</button>
                            <?php 
// Lấy staff_id từ session nếu có, nếu không thì gán bằng 0
$current_staff_id = isset($_SESSION['staff_id']) ? (int)$_SESSION['staff_id'] : 0; 
if ((int) $stf['staff_id'] !== $current_staff_id): 
?>
    <button type="button" class="btn-sm" style="padding: 4px 8px; background: #e74c3c; color: #fff; border:none; border-radius:3px; cursor:pointer;"
            data-id="<?= (int) $stf['staff_id'] ?>"
            data-ten="<?= htmlspecialchars($stf['full_name']) ?>"
            onclick="xoaNV(this)"><i class="fa-solid fa-trash"></i> Xóa</button>
<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="db-empty"><i class="fa-regular fa-folder-open"></i>
                        <?php if ($tuKhoa !== '' || $locRole > 0): ?>Không tìm thấy nhân viên nào khớp. <a href="admin.php?view=staff">Xóa bộ lọc</a>
                        <?php else: ?>Chưa có nhân viên nào trong hệ thống.<?php endif; ?>
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?= isset($pt) ? phan_trang_html($pt, 'nhân viên') : '' ?>
    </div>
</section>

<script>
// Bấm nút Sửa: điền thông tin dòng đó vào form rồi hiện form ra
function moSua(btn) {
    document.getElementById('suaId').value = btn.dataset.id;
    document.getElementById('suaTen').value = btn.dataset.ten;
    document.getElementById('suaRole').value = btn.dataset.role;
    document.getElementById('suaTenDangNhap').textContent = btn.dataset.username;

    var form = document.getElementById('formSua');
    form.style.display = 'block';
    form.scrollIntoView({ behavior: 'smooth' });
}

// Bấm nút Xóa: hỏi xác nhận, đồng ý thì gửi form ẩn
function xoaNV(btn) {
    if (confirm('Xóa nhân viên "' + btn.dataset.ten + '"?\nThao tác này không thể hoàn tác.')) {
        document.getElementById('xoaId').value = btn.dataset.id;
        document.getElementById('formXoa').submit();
    }
}
</script>