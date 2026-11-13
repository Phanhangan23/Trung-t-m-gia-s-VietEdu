<?php
require_once 'db.php';

// 1. XỬ LÝ AJAX: Phải đặt trên cùng TRƯỚC khi include header.php
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $sub = $_GET['sub'] ?? 'home';
    // Lọc tên file an toàn để tránh lỗi bảo mật
    $sub = preg_replace('/[^a-zA-Z0-9_-]/', '', $sub);
    $target_file = "index_views/" . $sub . ".php";
    
    if (file_exists($target_file)) {
        include $target_file;
    } else {
        echo '<div class="alert alert-error">Không tìm thấy chức năng này.</div>';
    }
    exit;
}

include 'header.php';
?>

<!-- HERO BANNER TÌM KIẾM NHANH -->
<section class="hero-banner">
    <h1>HỆ THỐNG QUẢN LÝ GIA SƯ VIETEDU</h1>
    <p>Kết nối Tri thức - Nâng tầm Học vấn cho Học sinh & Gia sư</p>
    
    <div class="search-box">
        <input type="text" class="search-input" id="searchKeyword" placeholder="Nhập môn học (Toán, Tiếng Anh...)...">
        <select class="search-input" id="searchGrade">
            <option value="">-- Chọn Khối Lớp --</option>
            <option value="1">Tiểu học (Lớp 1-5)</option>
            <option value="2">THCS (Lớp 6-9)</option>
            <option value="3">THPT (Lớp 10-12)</option>
        </select>
        <select class="search-input" id="searchLocation">
            <option value="">-- Chọn Khu Vực --</option>
            <option value="cau-giay">Cầu Giấy</option>
            <option value="thanh-xuan">Thanh Xuân</option>
            <option value="dong-da">Đống Đa</option>
        </select>
        <button class="btn-search" id="btnSearch"><i class="fa-solid fa-magnifying-glass"></i> Tìm Lớp</button>
    </div>
</section>

<!-- BỐ CỤC CHÍNH (SIDEBAR + CONTENT AREA) -->
<div class="main-layout">
    
    <!-- SIDEBAR BÊN TRÁI (Dành cho Phụ huynh / Học viên) -->
    <aside class="sidebar">
        <div class="sidebar-title">
            <i class="fa-solid fa-bars-staggered"></i> DÀNH CHO PHỤ HUYNH
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php?sub=home" class="<?= (!isset($_GET['sub']) || $_GET['sub'] === 'home') ? 'active' : '' ?>"><i class="fa-solid fa-border-all"></i> Bảng Tin Lớp Trống</a></li>
            <li><a href="index.php?sub=register_form" class="<?= ($_GET['sub'] ?? '') === 'register_form' ? 'active' : '' ?>"><i class="fa-solid fa-user-pen"></i> 1. Đăng Ký Tìm Gia Sư</a></li>
            <li><a href="index.php?sub=my_requests" class="<?= ($_GET['sub'] ?? '') === 'my_requests' ? 'active' : '' ?>"><i class="fa-solid fa-list-check"></i> 2. Yêu Cầu Của Tôi</a></li>
            <li><a href="index.php?sub=suggested_tutors" class="<?= ($_GET['sub'] ?? '') === 'suggested_tutors' ? 'active' : '' ?>"><i class="fa-solid fa-user-graduate"></i> 3. Gia Sư Đề Xuất</a></li>
            <li><a href="index.php?sub=my_classes" class="<?= ($_GET['sub'] ?? '') === 'my_classes' ? 'active' : '' ?>"><i class="fa-solid fa-book-open"></i> 4. Lớp Học Của Tôi</a></li>
            <li><a href="index.php?sub=schedule" class="<?= ($_GET['sub'] ?? '') === 'schedule' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-days"></i> 5. Lịch Học</a></li>
            <li><a href="index.php?sub=reports" class="<?= ($_GET['sub'] ?? '') === 'reports' ? 'active' : '' ?>"><i class="fa-solid fa-file-signature"></i> 6. Báo Cáo Buổi Học</a></li>
            <li><a href="index.php?sub=payments" class="<?= ($_GET['sub'] ?? '') === 'payments' ? 'active' : '' ?>"><i class="fa-solid fa-qrcode"></i> 7. Thanh Toán VietQR</a></li>
            <li><a href="index.php?sub=support" class="<?= ($_GET['sub'] ?? '') === 'support' ? 'active' : '' ?>"><i class="fa-solid fa-headset"></i> 8. Hỗ Trợ / Khiếu Nại</a></li>
        </ul>
    </aside>

    <!-- KHU VỰC HIỂN THỊ NỘI DUNG CHÍNH -->
    <main class="content-area" id="mainContent">
        <?php
        $sub = $_GET['sub'] ?? 'home';
        $sub = preg_replace('/[^a-zA-Z0-9_-]/', '', $sub);
        $target_file = "index_views/" . $sub . ".php";

        if (file_exists($target_file)) {
            include $target_file;
        } else {
            include "index_views/home.php";
        }
        ?>
    </main>

</div> <!-- THẺ ĐÓNG QUAN TRỌNG CỦA .main-layout GIÚP CÁC PHẦN DƯỚI KHÔNG BỊ VỠ CỘT -->

<!-- KHỐI CON SỐ THÀNH TỰU & UY TÍN -->
<section class="trust-stats-section">
    <div class="stats-container">
        <div class="stat-item">
            <i class="fa-solid fa-award"></i>
            <div class="stat-number">14+</div>
            <div class="stat-label">Năm Kinh Nghiệm</div>
        </div>
        <div class="stat-item">
            <i class="fa-solid fa-user-graduate"></i>
            <div class="stat-number">10.000+</div>
            <div class="stat-label">Học Sinh Đỗ NV1</div>
        </div>
        <div class="stat-item">
            <i class="fa-solid fa-id-card-clip"></i>
            <div class="stat-number">5.000+</div>
            <div class="stat-label">Gia Sư Xác Minh KYC</div>
        </div>
        <div class="stat-item">
            <i class="fa-solid fa-heart"></i>
            <div class="stat-number">98%</div>
            <div class="stat-label">Phụ Huynh Hài Lòng</div>
        </div>
    </div>
</section>

<!-- KHỐI VINH DANH THÀNH TÍCH HỌC SINH -->
<section class="achievements-section">
    <div class="section-title-center">
        <h2>Bảng Vàng Thành Tích Học Sinh VietEdu</h2>
        <p>Minh chứng cho chất lượng giảng dạy và sự nỗ lực vượt bậc của Thầy & Trò</p>
    </div>
    
    <div class="achievement-grid">
        <div class="achievement-card">
            <div class="student-badge"><i class="fa-solid fa-trophy"></i> Thủ Khoa Khối A</div>
            <div class="student-avatar"><i class="fa-solid fa-user-graduate"></i></div>
            <h3 class="student-name">Nguyễn Minh Anh</h3>
            <p class="student-school">Cựu HS Chuyên KHTN &rarr; ĐH Bách Khoa</p>
            <div class="score-tag">29.15 Điểm (Toán 9.8 - Lý 9.75 - Hóa 9.6)</div>
            <p class="tutor-ref"><i class="fa-solid fa-chalkboard-user"></i> Gia sư: Anh Hoàng (ĐH Bách Khoa)</p>
        </div>

        <div class="achievement-card">
            <div class="student-badge"><i class="fa-solid fa-star"></i> IELTS 8.0 Overall</div>
            <div class="student-avatar"><i class="fa-solid fa-user-graduate"></i></div>
            <h3 class="student-name">Trần Đức Hòa</h3>
            <p class="student-school">Học sinh THPT Kim Liên</p>
            <div class="score-tag">Listening 8.5 - Reading 8.5 - Speaking 7.5</div>
            <p class="tutor-ref"><i class="fa-solid fa-chalkboard-user"></i> Gia sư: Chị Thảo (ĐH Ngoại Thương)</p>
        </div>

        <div class="achievement-card">
            <div class="student-badge"><i class="fa-solid fa-medal"></i> Đỗ Chuyên Sư Phạm</div>
            <div class="student-avatar"><i class="fa-solid fa-user-graduate"></i></div>
            <h3 class="student-name">Lê Phương Thảo</h3>
            <p class="student-school">Cựu HS THCS Cầu Giấy</p>
            <div class="score-tag">Điểm chuyên Toán: 9.0 (Tăng 3 điểm sau 4 tháng)</div>
            <p class="tutor-ref"><i class="fa-solid fa-chalkboard-user"></i> Gia sư: Thầy Nam (Khoa Toán ĐHSP)</p>
        </div>
    </div>
</section>

<!-- KHỐI CẢM NHẬN PHỤ HUYNH -->
<section class="testimonials-section">
    <div class="section-title-center">
        <h2>Phụ Huynh Nói Gì Về VietEdu</h2>
        <p>Sự tín nhiệm của Phụ huynh là niềm tự hào lớn nhất của chúng tôi</p>
    </div>

    <div class="testimonial-grid">
        <div class="testimonial-card">
            <div class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
            <p class="comment">"Tôi rất ấn tượng với tính năng Dashboard theo dõi tiến độ. Sau mỗi buổi học gia sư đều cập nhật báo cáo rõ ràng nên tôi hoàn toàn yên tâm dù đi làm bận rộn."</p>
            <div class="parent-info">
                <strong>Chị Bích Hồng</strong>
                <span>Phụ huynh cháu Minh Đức (Cầu Giấy)</span>
            </div>
        </div>

        <div class="testimonial-card">
            <div class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
            <p class="comment">"Gia sư do VietEdu ghép nối rất nghiêm túc, có giấy tờ xác minh đầy đủ. Con tôi từ sợ môn Vật lý giờ đã tự tin làm bài thi vào lớp 10."</p>
            <div class="parent-info">
                <strong>Anh Đinh Đức Vũ</strong>
                <span>Phụ huynh cháu Hoàng Anh (Thanh Xuân)</span>
            </div>
        </div>
    </div>
</section>

<!-- SCRIPT ĐIỀU HƯỚNG KHÔNG LOAD LẠI TRANG -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
    const mainContent = document.getElementById('mainContent');

    if (!mainContent) return;

    sidebarLinks.forEach(link => {
        const href = link.getAttribute('href');
        
        if (href && href.includes('index.php?sub=')) {
            link.addEventListener('click', function (e) {
                e.preventDefault();

                const ajaxUrl = href + '&ajax=1';
                mainContent.style.opacity = '0.4';

                fetch(ajaxUrl)
                    .then(response => response.text())
                    .then(html => {
                        mainContent.innerHTML = html;
                        mainContent.style.opacity = '1';
                        history.pushState({ path: href }, '', href);

                        sidebarLinks.forEach(l => l.classList.remove('active'));
                        this.classList.add('active');
                    })
                    .catch(error => {
                        console.error('Lỗi tải trang:', error);
                        mainContent.style.opacity = '1';
                    });
            });
        }
    });
});
</script>

<?php include 'footer.php'; ?>

/* =====================================================================
 * PHẦN BỔ SUNG: BẢNG TIN LỚP TRỐNG (bộ lọc môn / khối / khu vực + phân trang
 * thật + nút "Xem chi tiết" sang gia-su/lop-hoc-chi-tiet.php).
 * Toàn bộ phần trên giữ nguyên bản gốc. Khi tổng hợp chỉ cần dán khối này
 * vào CUỐI index.php (cần kèm khối bổ sung ở cuối header.php).
 *
 * Cách hoạt động: header.php bật bộ đệm đầu ra khi thấy hàm
 * vietedu_index_patch(); tới đây trang đã in xong, khối này thay 3 vùng
 * của bản gốc (ô tìm kiếm ở banner, menu sidebar, khu vực bảng tin)
 * bằng phiên bản mới rồi mới gửi về trình duyệt.
 * ===================================================================== */
<?php 
function vietedu_index_patch($html)
{
    global $conn;
    $h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
    $gradeText = function ($g) { return ctype_digit((string) $g) ? 'Lớp ' . $g : (string) $g; };

    /* ---------- Bộ lọc & phân trang ---------- */
    $fMon  = mb_substr(trim((string) ($_GET['mon'] ?? '')), 0, 50);
    $fKhoi = (string) ($_GET['khoi'] ?? '');
    $fKv   = mb_substr(trim((string) ($_GET['khu_vuc'] ?? '')), 0, 100);
    $perPage = 9;
    $page = max(1, (int) ($_GET['trang'] ?? 1));

    $khoiOptions = ['tieu-hoc' => 'Tiểu học (Lớp 1-5)', 'thcs' => 'THCS (Lớp 6-9)', 'thpt' => 'THPT (Lớp 10-12)', 'khac' => 'Đại học / Người đi làm'];
    for ($g = 1; $g <= 12; $g++) $khoiOptions[(string) $g] = 'Lớp ' . $g;
    if (!isset($khoiOptions[$fKhoi])) $fKhoi = '';

    $where = ["status = 'finding'"];
    $params = [];
    if ($fMon !== '') {
        $where[] = '(subject LIKE ? OR title LIKE ?)';
        $like = '%' . addcslashes($fMon, '%_\\') . '%';
        array_push($params, $like, $like);
    }
    if ($fKv !== '') {
        $where[] = 'location = ?';
        $params[] = $fKv;
    }
    $ranges = ['tieu-hoc' => [1, 5], 'thcs' => [6, 9], 'thpt' => [10, 12]];
    if (isset($ranges[$fKhoi])) {
        $where[] = "grade REGEXP '^[0-9]+$' AND CAST(grade AS UNSIGNED) BETWEEN ? AND ?";
        array_push($params, $ranges[$fKhoi][0], $ranges[$fKhoi][1]);
    } elseif ($fKhoi === 'khac') {
        $where[] = "grade NOT REGEXP '^[0-9]+$'";
    } elseif ($fKhoi !== '') {
        $where[] = 'grade = ?';
        $params[] = $fKhoi;
    }
    $whereSql = implode(' AND ', $where);

    $classes = [];
    $locations = [];
    $total = 0;
    $pages = 1;
    $loadError = false;
    try {
        $locations = $conn->query("SELECT DISTINCT location FROM classes WHERE status = 'finding' AND location <> '' ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);
        $st = $conn->prepare("SELECT COUNT(*) FROM classes WHERE $whereSql");
        $st->execute($params);
        $total = (int) $st->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $st = $conn->prepare("SELECT * FROM classes WHERE $whereSql ORDER BY created_at DESC, class_id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage));
        $st->execute($params);
        $classes = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Bảng tin lớp: ' . $e->getMessage());
        $loadError = true;
    }
    $filterQs = array_filter(['mon' => $fMon, 'khoi' => $fKhoi, 'khu_vuc' => $fKv], 'strlen');
    $pageUrl = function ($p) use ($filterQs) {
        return 'index.php?' . http_build_query($filterQs + ['trang' => $p]) . '#bang-tin';
    };
    $locOptions = function () use ($locations, $fKv, $h) {
        $o = '';
        foreach ($locations as $loc) $o .= '<option value="' . $h($loc) . '"' . ($fKv === $loc ? ' selected' : '') . '>' . $h($loc) . '</option>';
        return $o;
    };

    /* ---------- 1. Ô tìm kiếm ở banner ---------- */
    ob_start(); ?>
        <form class="search-box" method="get" action="index.php#bang-tin">
            <input type="text" class="search-input" name="mon" value="<?= $h($fMon) ?>" placeholder="Nhập môn học (Toán, Tiếng Anh...)...">
            <select class="search-input" name="khoi">
                <option value="">-- Chọn Khối Lớp --</option>
                <?php foreach (['tieu-hoc', 'thcs', 'thpt'] as $k): ?>
                    <option value="<?= $k ?>" <?= $fKhoi === $k ? 'selected' : '' ?>><?= $khoiOptions[$k] ?></option>
                <?php endforeach; ?>
            </select>
            <select class="search-input" name="khu_vuc">
                <option value="">-- Chọn Khu Vực --</option>
                <?= $locOptions() ?>
            </select>
            <button class="btn-search" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Tìm Lớp</button>
        </form>
    <?php $searchBox = ob_get_clean();

    /* ---------- 2. Menu sidebar ---------- */
    ob_start(); ?>
            <ul class="sidebar-menu">
                <li><a href="index.php#bang-tin" class="active"><i class="fa-solid fa-border-all"></i> Bảng Tin Lớp Trống</a></li>
                <li><a href="yeu-cau-tim-gia-su.php"><i class="fa-solid fa-user-pen"></i> Đăng Ký Tìm Gia Sư</a></li>
                <li><a href="gia-su/kyc.php"><i class="fa-solid fa-id-card"></i> Hồ Sơ Gia Sư & KYC</a></li>
                <li><a href="gia-su/bao-cao.php"><i class="fa-solid fa-file-signature"></i> Báo Cáo Buổi Học</a></li>
                <li><a href="gia-su/ho-tro.php"><i class="fa-solid fa-ticket"></i> Khiếu Nại / Support</a></li>
                <li><a href="admin.php"><i class="fa-solid fa-chart-line"></i> Dashboard Quản Lý</a></li>
            </ul>
    <?php $sidebar = ob_get_clean();

    /* ---------- 3. Khu vực bảng tin ---------- */
    ob_start(); ?>
        <style>
            .class-card .class-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
            .class-card .class-meta span { background: #F8ECEE; color: var(--primary-dark); font-size: 12px; font-weight: 600; padding: 2px 8px; border-radius: 4px; }
            .class-card .class-info.req { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
            .class-card .btn-card-action { display: block; text-align: center; margin-top: auto; }
            .page-link.disabled { opacity: .4; pointer-events: none; }
            .board-filter { display: grid; grid-template-columns: 1.4fr 1fr 1fr auto auto; gap: 10px; margin-bottom: 18px; }
            .board-filter .search-input { padding: 9px 12px; }
            .board-empty { grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 40px 0; }
            @media (max-width: 900px) { .board-filter { grid-template-columns: 1fr; } }
        </style>
        <main class="content-area" id="bang-tin">
            <div class="section-header">
                <h2 class="section-title">Lớp Mới Cần Gia Sư</h2>
                <span style="color: var(--text-muted); font-size: 14px;">Tìm thấy <strong><?= $total ?></strong> lớp</span>
            </div>

            <!-- BỘ LỌC: MÔN HỌC / KHỐI LỚP / KHU VỰC -->
            <form class="board-filter" method="get" action="index.php#bang-tin">
                <input type="text" class="search-input" name="mon" value="<?= $h($fMon) ?>" placeholder="Môn học hoặc tên lớp...">
                <select class="search-input" name="khoi">
                    <option value="">Tất cả khối lớp</option>
                    <?php foreach ($khoiOptions as $k => $lb): ?>
                        <option value="<?= $h($k) ?>" <?= $fKhoi === (string) $k ? 'selected' : '' ?>><?= $h($lb) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="search-input" name="khu_vuc">
                    <option value="">Tất cả khu vực</option>
                    <?= $locOptions() ?>
                </select>
                <button class="btn-search" type="submit"><i class="fa-solid fa-filter"></i> Lọc</button>
                <?php if ($filterQs): ?><a class="page-link" href="index.php#bang-tin" style="text-align:center">Xóa lọc</a><?php endif; ?>
            </form>

            <!-- DANH SÁCH THẺ LỚP HỌC -->
            <div class="card-grid">
                <?php if ($loadError): ?>
                    <p class="board-empty">Hiện chưa tải được danh sách lớp. Vui lòng thử lại sau.</p>
                <?php elseif (!$classes): ?>
                    <p class="board-empty">Không có lớp nào phù hợp<?= $filterQs ? ' với bộ lọc. <a href="index.php#bang-tin" style="color:var(--primary-red)">Xóa bộ lọc</a>' : '.' ?></p>
                <?php endif; ?>
                <?php foreach ($classes as $class): ?>
                    <div class="class-card">
                        <span class="card-tag"><?= strtotime($class['created_at']) > strtotime('-3 days') ? 'Mới' : 'Đang tìm gia sư' ?></span>
                        <div class="class-code">MÃ LỚP: <?= $h($class['class_code']) ?></div>
                        <div class="class-title"><?= $h($class['title']) ?></div>
                        <div class="class-meta"><span><?= $h($class['subject']) ?></span><span><?= $h($gradeText($class['grade'])) ?></span></div>
                        <div class="class-info"><i class="fa-solid fa-location-dot"></i> <?= $h($class['location'] ?: 'Thỏa thuận') ?></div>
                        <div class="class-info"><i class="fa-solid fa-clock"></i> <?= $h($class['schedule'] ?: 'Thỏa thuận') ?></div>
                        <div class="class-info req"><i class="fa-solid fa-user-graduate"></i> Yêu cầu: <?= $h($class['requirements'] ?: 'Không có') ?></div>
                        <div class="class-price"><?= $class['tuition_fee'] !== null ? number_format((float) $class['tuition_fee'], 0, ',', '.') . ' VNĐ / tháng' : 'Học phí thỏa thuận' ?></div>
                        <a class="btn-card-action" href="gia-su/lop-hoc-chi-tiet.php?id=<?= (int) $class['class_id'] ?>"><i class="fa-solid fa-eye"></i> Xem Chi Tiết</a>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- PHÂN TRANG -->
            <?php if ($pages > 1): ?>
            <div class="pagination">
                <a href="<?= $h($pageUrl($page - 1)) ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">&laquo;</a>
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <?php if ($p === 1 || $p === $pages || abs($p - $page) <= 2): ?>
                        <a href="<?= $h($pageUrl($p)) ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php elseif (abs($p - $page) === 3): ?>
                        <span class="page-link disabled">…</span>
                    <?php endif; ?>
                <?php endfor; ?>
                <a href="<?= $h($pageUrl($page + 1)) ?>" class="page-link <?= $page >= $pages ? 'disabled' : '' ?>">&raquo;</a>
            </div>
            <?php endif; ?>
        </main>
    <?php $board = ob_get_clean();

    // Thay 3 vùng của bản gốc (nếu bản gốc đổi cấu trúc mà không tìm thấy thì giữ nguyên vùng đó)
    $html = preg_replace('#<div class="search-box">.*?</div>#s', $searchBox, $html, 1);
    $html = preg_replace('#<ul class="sidebar-menu">.*?</ul>#s', $sidebar, $html, 1);
    $html = preg_replace('#<main class="content-area"[^>]*>.*?</main>#s', $board, $html, 1);
    return $html;
}

// Trang đã in xong -> lấy nội dung từ bộ đệm (do header.php bật), thay các vùng rồi gửi ra trình duyệt
if (defined('VIETEDU_INDEX_OB') && ob_get_level() === VIETEDU_INDEX_OB) {
    echo vietedu_index_patch(ob_get_clean());
}
