<?php
// Xử lý khi gửi form đăng ký tìm gia sư
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_tutor_request') {
    $subject       = trim($_POST['subject'] ?? '');
    $grade         = trim($_POST['grade'] ?? '');
    $learning_type = trim($_POST['learning_type'] ?? 'offline');
    $location      = trim($_POST['location'] ?? '');
    $schedule      = trim($_POST['schedule'] ?? '');
    $sessions      = trim($_POST['sessions_per_week'] ?? '');
    $tuition_fee   = preg_replace('/[^\d]/', '', $_POST['tuition_fee'] ?? '0');
    $requirements  = trim($_POST['requirements'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $parent_id     = (int) ($_SESSION['parent_id'] ?? 0);

    if ($subject === '' || $grade === '' || $phone === '') {
        echo '<div class="alert alert-error">Vui lòng nhập đầy đủ Môn học, Lớp học và Số điện thoại.</div>';
    } else {
        $max_num = (int)$conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(class_code, 4) AS UNSIGNED)), 1000) FROM classes")->fetchColumn();
        $class_code = 'VE-' . ($max_num + 1);
        $hinhThuc = $learning_type === 'online' ? 'học trực tuyến' : 'học tại nhà';
        $lopSo = preg_replace('/^\s*lớp\s*/iu', '', $grade);   // tránh "Lớp lớp 9" khi người dùng gõ sẵn chữ "lớp"
        $title_full = "$subject - Lớp $lopSo ($hinhThuc)";
        $schedule_full = $sessions ? "$sessions buổi/tuần ($schedule)" : $schedule;

        $stmt = $conn->prepare("INSERT INTO classes (class_code, title, subject, grade, location, schedule, requirements, tuition_fee, parent_id, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'finding')");
        $stmt->execute([$class_code, $title_full, $subject, $grade, $location, $schedule_full, $requirements, $tuition_fee, $parent_id]);
        
        echo '<div class="alert alert-success">Gửi yêu cầu tìm gia sư thành công! Mã lớp: <strong>' . $class_code . '</strong></div>';
    }
}
?>
<div class="section-title"><span>1. Biểu Mẫu Đăng Ký Nhu Cầu Tìm Gia Sư</span></div>
<form method="POST" action="index.php?sub=register_form">
    <input type="hidden" name="action" value="submit_tutor_request">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
        <div style="margin-bottom: 15px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Môn học cần tìm (*)</label>
            <input type="text" name="subject" class="search-input" style="width:100%; padding:9px; border:1px solid #ddd; border-radius:6px;" placeholder="Toán, Tiếng Anh..." required>
        </div>
        <div style="margin-bottom: 15px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Lớp học (*)</label>
            <input type="text" name="grade" class="search-input" style="width:100%; padding:9px; border:1px solid #ddd; border-radius:6px;" placeholder="Lớp 9, Lớp 12..." required>
        </div>
        <div style="margin-bottom: 15px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Hình thức học</label>
            <select name="learning_type" class="search-input" style="width:100%; padding:9px; border:1px solid #ddd; border-radius:6px;">
                <option value="offline">Học trực tiếp tại nhà</option>
                <option value="online">Học trực tuyến</option>
            </select>
        </div>
        <div style="margin-bottom: 15px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Số điện thoại liên hệ (*)</label>
            <input type="text" name="phone" class="search-input" style="width:100%; padding:9px; border:1px solid #ddd; border-radius:6px;" placeholder="0988xxxxxx" required>
        </div>
        <div style="grid-column: 1/-1; margin-bottom: 15px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Địa chỉ học chi tiết</label>
            <input type="text" name="location" class="search-input" style="width:100%; padding:9px; border:1px solid #ddd; border-radius:6px;" placeholder="Số nhà, đường, Quận/Huyện...">
        </div>
        <div style="margin-bottom: 15px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Số buổi học mỗi tuần</label>
            <input type="number" name="sessions_per_week" class="search-input" style="width:100%; padding:9px; border:1px solid #ddd; border-radius:6px;" min="1" max="7" value="3">
        </div>
        <div style="margin-bottom: 15px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Lịch học mong muốn</label>
            <input type="text" name="schedule" class="search-input" style="width:100%; padding:9px; border:1px solid #ddd; border-radius:6px;" placeholder="Ví dụ: Tối T2 - T4 - T6">
        </div>
        <div style="grid-column: 1/-1; margin-bottom: 15px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Học phí dự kiến (VNĐ / tháng)</label>
            <input type="number" name="tuition_fee" class="search-input" style="width:100%; padding:9px; border:1px solid #ddd; border-radius:6px;" placeholder="2500000">
        </div>
        <div style="grid-column: 1/-1; margin-bottom: 15px;">
            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Yêu cầu đối với gia sư</label>
            <textarea name="requirements" class="search-input" style="width:100%; padding:9px; border:1px solid #ddd; border-radius:6px;" rows="3" placeholder="Sinh viên Sư phạm, Bách Khoa..."></textarea>
        </div>
    </div>
    <button type="submit" class="btn-search" style="padding: 10px 20px; cursor: pointer;"><i class="fa-solid fa-paper-plane"></i> Gửi Yêu Cầu Tìm Gia Sư</button>
</form>