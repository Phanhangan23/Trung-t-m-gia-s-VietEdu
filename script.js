document.addEventListener('DOMContentLoaded', () => {
    
    // 1. CHUYỂN TAB TRÊN NAVIGATION BAR
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            navLinks.forEach(item => item.classList.remove('active'));
            link.classList.add('active');
            
            const pageName = link.getAttribute('data-page');
            console.log(`Chuyển đến trang Header: ${pageName}`);
        });
    });

    // 2. CHUYỂN TAB TRÊN SIDEBAR
    const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            sidebarLinks.forEach(item => item.classList.remove('active'));
            link.classList.add('active');

            const pageName = link.getAttribute('data-page');
            console.log(`Chuyển đến trang Sidebar: ${pageName}`);
        });
    });

    // 3. SỰ KIỆN NÚT ỨNG TUYỂN NHANH TRÊN THẺ LỚP HỌC
    const applyButtons = document.querySelectorAll('.btn-card-action');
    applyButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const card = e.target.closest('.class-card');
            const classCode = card.querySelector('.class-code').textContent;
            const classTitle = card.querySelector('.class-title').textContent;
            
            alert(`Bạn đã chọn ứng tuyển lớp:\n- ${classCode}\n- Môn: ${classTitle}\n\nHệ thống sẽ chuyển hướng bạn tới form xác nhận hồ sơ năng lực.`);
        });
    });

    // 4. SỰ KIỆN TÌM KIẾM LỚP HỌC
    const btnSearch = document.getElementById('btnSearch');
    if (btnSearch) {
        btnSearch.addEventListener('click', () => {
            const keyword = document.getElementById('searchKeyword').value;
            const grade = document.getElementById('searchGrade').value;
            const location = document.getElementById('searchLocation').value;

            alert(`Kết quả lọc lớp:\n- Môn học: ${keyword || 'Tất cả'}\n- Khối lớp: ${grade || 'Tất cả'}\n- Khu vực: ${location || 'Tất cả'}`);
        });
    }

    // 5. NÚT ĐĂNG NHẬP / ĐĂNG KÝ
    document.getElementById('btnLogin').addEventListener('click', () => {
        alert('Hiển thị Modal Đăng nhập (Admin / Gia sư / Phụ huynh)');
    });

    document.getElementById('btnRegister').addEventListener('click', () => {
        alert('Hiển thị Modal Đăng ký tài khoản mới');
    });

    // 6. NÚT CUỘN LÊN ĐẦU TRANG (SCROLL TO TOP)
    const btnScrollTop = document.getElementById('btnScrollTop');
    if (btnScrollTop) {
        btnScrollTop.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
});

/* ==========================================================================
   SCRIPT XỬ LÝ TƯƠNG TÁC GIAO DIỆN PHỤ HUYNH & TRANG CHỦ
   ========================================================================== */
document.addEventListener('DOMContentLoaded', function () {
    // 1. Tự động ẩn thông báo Flash sau 4 giây
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(() => {
            alerts.forEach(el => {
                el.style.transition = 'opacity 0.5s ease';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            });
        }, 4000);
    }

    // 2. Xử lý nút tìm kiếm nhanh ở trang chủ (index.php)
    const btnSearch = document.getElementById('btnSearch');
    if (btnSearch) {
        btnSearch.addEventListener('click', function () {
            const kw = document.getElementById('searchKeyword')?.value.trim() || '';
            const grade = document.getElementById('searchGrade')?.value || '';
            const loc = document.getElementById('searchLocation')?.value || '';
            const price = document.getElementById('searchPrice')?.value || '';

            const params = new URLSearchParams();
            if (kw) params.set('q', kw);
            if (grade) params.set('grade', grade);
            if (loc) params.set('location', loc);
            if (price) params.set('price', price);

            window.location.href = 'index.php?' + params.toString() + '#mainContent';
        });
    }
});

// 3. Mở Modal chỉnh sửa yêu cầu tìm gia sư
function openEditModal(data) {
    document.getElementById('edit_class_id').value = data.class_id || '';
    document.getElementById('edit_subject').value = data.subject || '';
    document.getElementById('edit_grade').value = data.grade || '';
    document.getElementById('edit_location').value = data.location || '';
    document.getElementById('edit_schedule').value = data.schedule || '';
    document.getElementById('edit_tuition').value = data.tuition_fee || '';
    document.getElementById('edit_req').value = data.requirements || '';

    const modal = document.getElementById('editModal');
    if (modal) modal.style.display = 'flex';
}

// 4. Mở Modal xem hồ sơ chi tiết gia sư
function openTutorProfileModal(tutor) {
    document.getElementById('tp_name').textContent = tutor.full_name || 'Đang cập nhật';
    document.getElementById('tp_uni').textContent = (tutor.university || '') + (tutor.major ? ' - ' + tutor.major : '');
    document.getElementById('tp_rating').textContent = (tutor.rating || '5.0') + ' / 5.0 ⭐';
    document.getElementById('tp_phone').textContent = tutor.phone || 'Ẩn đến khi nhận lớp';
    document.getElementById('tp_score').textContent = (tutor.match_score || '90') + '%';

    const modal = document.getElementById('tutorProfileModal');
    if (modal) modal.style.display = 'flex';
}

// 5. Đóng Modal chung
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}
document.addEventListener('DOMContentLoaded', function () {
    const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
    const mainContent = document.getElementById('mainContent');

    sidebarLinks.forEach(link => {
        const href = link.getAttribute('href');
        // Chỉ bắt các link điều hướng nội bộ có chứa ?sub=
        if (href && href.includes('index.php?sub=')) {
            link.addEventListener('click', function (e) {
                e.preventDefault(); // Chặn việc load lại trang của trình duyệt

                // Thêm tham số &ajax=1 vào URL để PHP nhận diện
                const ajaxUrl = href + '&ajax=1';

                // Hiệu ứng mờ nhẹ khi đang tải dữ liệu
                mainContent.style.opacity = '0.4';

                fetch(ajaxUrl)
                    .then(response => response.text())
                    .then(html => {
                        mainContent.innerHTML = html;
                        mainContent.style.opacity = '1';

                        // Thay đổi URL trên thanh địa chỉ trình duyệt mà không reload
                        history.pushState({ path: href }, '', href);

                        // Cập nhật class active cho menu được chọn
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

    // Hỗ trợ nút Back / Forward trên trình duyệt
    window.addEventListener('popstate', function () {
        window.location.reload();
    });
});
document.addEventListener('DOMContentLoaded', function () {
    const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
    const mainContent = document.getElementById('mainContent');

    if (!mainContent) return; // Nếu không tìm thấy vùng hiển thị thì dừng

    sidebarLinks.forEach(link => {
        const href = link.getAttribute('href');
        
        // Chỉ bắt những link có chứa ?sub= (chức năng phụ huynh)
        if (href && href.includes('index.php?sub=')) {
            link.addEventListener('click', function (e) {
                e.preventDefault(); // CHẶN LẬP TỨC VIỆC LOAD LẠI TRANG

                // Tạo đường dẫn gọi ngầm kèm tham số ajax=1
                const ajaxUrl = href + '&ajax=1';

                // Làm mờ nhẹ vùng nội dung để báo hiệu đang tải
                mainContent.style.opacity = '0.4';

                fetch(ajaxUrl)
                    .then(response => response.text())
                    .then(html => {
                        mainContent.innerHTML = html;
                        mainContent.style.opacity = '1';

                        // Cập nhật lại URL trên thanh địa chỉ mà không reload trang
                        history.pushState({ path: href }, '', href);

                        // Đổi màu active cho menu được chọn
                        sidebarLinks.forEach(l => l.classList.remove('active'));
                        this.classList.add('active');
                    })
                    .catch(error => {
                        console.error('Lỗi tải AJAX:', error);
                        mainContent.style.opacity = '1';
                    });
            });
        }
    });
});