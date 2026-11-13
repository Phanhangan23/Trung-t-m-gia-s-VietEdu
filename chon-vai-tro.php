<?php
$pageTitle = 'Chọn Vai Trò Đăng Ký - VietEdu';
require __DIR__ . '/header.php';

// Nếu đã đăng nhập thì đẩy về trang chủ
if (isset($_SESSION['user_type'], $_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>

<style>
    .role-wrap { max-width: 850px; margin: 60px auto; padding: 0 16px; text-align: center; }
    .role-wrap h1 { color: var(--primary-dark); font-size: 28px; margin-bottom: 10px; }
    .role-wrap .lead { color: var(--text-muted); font-size: 15px; margin-bottom: 40px; }
    
    .role-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
    
    .role-card {
        background: #fff; border: 2px solid var(--border-color); border-radius: 12px;
        padding: 40px 24px; transition: all 0.3s ease; cursor: pointer; text-decoration: none;
        display: flex; flex-direction: column; align-items: center; color: var(--text-main);
    }
    .role-card:hover { 
        border-color: var(--primary-red); 
        box-shadow: 0 8px 24px rgba(128, 0, 32, 0.12); 
        transform: translateY(-5px); 
    }
    
    .role-icon { font-size: 56px; color: var(--primary-red); margin-bottom: 20px; transition: 0.3s; }
    .role-card:hover .role-icon { transform: scale(1.1); }
    
    .role-title { font-size: 20px; font-weight: 700; margin-bottom: 12px; color: var(--primary-dark); }
    .role-desc { font-size: 14px; color: var(--text-muted); margin-bottom: 30px; line-height: 1.6; }
    
    .role-btn {
        background: var(--primary-red); color: #fff; padding: 12px 28px;
        border-radius: 6px; font-weight: 600; font-size: 14px; transition: 0.3s;
    }
    .role-card:hover .role-btn { background: var(--primary-dark); }
    
    @media (max-width: 650px) { 
        .role-grid { grid-template-columns: 1fr; gap: 20px; } 
        .role-card { padding: 30px 20px; }
    }
</style>

<div class="role-wrap">
    <h1>Tham gia cùng VietEdu</h1>
    <p class="lead">Vui lòng chọn vai trò của bạn để tiếp tục đăng ký tài khoản</p>
    
    <div class="role-grid">
        <!-- Lựa chọn Phụ huynh -->
        <a href="dang-ky-phu-huynh.php" class="role-card">
            <i class="fa-solid fa-users role-icon"></i>
            <div class="role-title">Phụ Huynh / Học Viên</div>
            <div class="role-desc">Tìm kiếm gia sư chất lượng cao, theo dõi tiến độ học tập và quản lý thanh toán học phí dễ dàng.</div>
            <div class="role-btn">Đăng ký Phụ huynh</div>
        </a>
        
        <!-- Lựa chọn Gia sư -->
        <a href="dang-ky-gia-su.php" class="role-card">
            <i class="fa-solid fa-chalkboard-user role-icon"></i>
            <div class="role-title">Trở thành Gia Sư</div>
            <div class="role-desc">Tiếp cận hàng ngàn lớp học, nhận lớp nhanh chóng và xây dựng hồ sơ năng lực cá nhân uy tín.</div>
            <div class="role-btn">Đăng ký Gia sư</div>
        </a>
    </div>
    
    <div style="margin-top: 30px; font-size: 14px; color: var(--text-muted);">
        Đã có tài khoản? <a href="dang-nhap-nguoi-dung.php" style="color: var(--primary-red); font-weight: 600;">Đăng nhập tại đây</a>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>