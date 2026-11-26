<div class="card-grid">
    <?php
    require_once __DIR__ . '/../db.php';
    try {
        $stmt = $conn->prepare("SELECT * FROM classes WHERE status = 'finding' ORDER BY created_at DESC");
        $stmt->execute();
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($classes) > 0) {
            foreach ($classes as $class) {
                $fee = number_format($class['tuition_fee'], 0, ',', '.');
                echo '
                <div class="class-card">
                    <span class="card-tag">Đang tìm gia sư</span>
                    <div class="class-code">MÃ LỚP: ' . htmlspecialchars($class['class_code']) . '</div>
                    <div class="class-title">' . htmlspecialchars($class['title']) . '</div>
                    <div class="class-info"><i class="fa-solid fa-location-dot"></i> ' . htmlspecialchars($class['location']) . '</div>
                    <div class="class-info"><i class="fa-solid fa-clock"></i> ' . htmlspecialchars($class['schedule']) . '</div>
                    <div class="class-info"><i class="fa-solid fa-user-graduate"></i> Yêu cầu: ' . htmlspecialchars($class['requirements']) . '</div>
                    <div class="class-price">' . $fee . ' VNĐ / tháng</div>
                    <button class="btn-card-action"><i class="fa-solid fa-paper-plane"></i> Ứng Tuyển Nhanh</button>
                </div>';
            }
        } else {
            echo "<p style='padding: 20px;'>Hiện tại chưa có lớp học trống nào.</p>";
        }
    } catch(PDOException $e) {
        echo '<p>' . htmlspecialchars(ve_loi_csdl($e, 'tải danh sách lớp')) . '</p>';
    }
    ?>
</div>

<div class="pagination">
    <a href="#" class="page-link disabled">&laquo;</a>
    <a href="#" class="page-link active">1</a>
    <a href="#" class="page-link">2</a>
    <a href="#" class="page-link">3</a>
    <a href="#" class="page-link">&raquo;</a>
</div>