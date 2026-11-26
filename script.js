/* ==========================================================================
   SCRIPT DÙNG CHUNG CHO TRANG NGƯỜI DÙNG (nạp ở footer.php)
   ========================================================================== */
document.addEventListener('DOMContentLoaded', function () {

    // 1. Nút cuộn lên đầu trang
    const btnScrollTop = document.getElementById('btnScrollTop');
    if (btnScrollTop) {
        btnScrollTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }

    // 2. Tự động ẩn thông báo .alert sau 4 giây
    function anThongBao(root) {
        const alerts = root.querySelectorAll('.alert');
        if (!alerts.length) return;
        setTimeout(() => {
            alerts.forEach(el => {
                el.style.transition = 'opacity 0.5s ease';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            });
        }, 4000);
    }
    anThongBao(document);

    // 3. Menu sidebar phụ huynh: tải nội dung bằng AJAX, không load lại trang
    const mainContent = document.getElementById('mainContent');
    const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
    if (!mainContent) return;

    sidebarLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (!href || !href.includes('index.php?sub=')) return;

        link.addEventListener('click', function (e) {
            e.preventDefault();
            mainContent.style.opacity = '0.4';

            fetch(href + '&ajax=1')
                .then(res => res.text())
                .then(html => {
                    mainContent.innerHTML = html;
                    mainContent.style.opacity = '1';
                    history.pushState({ path: href }, '', href);
                    sidebarLinks.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                    anThongBao(mainContent);
                })
                .catch(err => {
                    console.error('Lỗi tải trang:', err);
                    window.location.href = href; // lỗi mạng -> tải trang bình thường
                });
        });
    });

    // Nút Back / Forward của trình duyệt
    window.addEventListener('popstate', () => window.location.reload());
});

/* ==========================================================================
   Bảng kiểm tra độ mạnh mật khẩu (ô nhập có thuộc tính data-pw-policy)
   ========================================================================== */
document.addEventListener('DOMContentLoaded', function () {
    const quyTac = [
        ['Tối thiểu 8 ký tự', v => v.length >= 8 && v.length <= 72],
        ['Có chữ in hoa (A-Z)', v => /[A-Z]/.test(v)],
        ['Có chữ thường (a-z)', v => /[a-z]/.test(v)],
        ['Có chữ số (0-9)', v => /\d/.test(v)],
        ['Có ký tự đặc biệt (@ # $ % ! & * ...)', v => /[^A-Za-z0-9\s]/.test(v)],
        ['Không chứa khoảng trắng', v => v.length > 0 && !/\s/.test(v)],
    ];
    document.querySelectorAll('input[data-pw-policy]').forEach(input => {
        const ds = document.createElement('ul');
        ds.style.cssText = 'list-style:none;margin:6px 0 0;padding:0;font-size:12.5px;line-height:1.7';
        quyTac.forEach(([nhan]) => {
            const li = document.createElement('li');
            li.textContent = '○ ' + nhan;
            ds.appendChild(li);
        });
        input.insertAdjacentElement('afterend', ds);
        const capNhat = () => {
            quyTac.forEach(([nhan, kiemTra], i) => {
                const dat = kiemTra(input.value);
                ds.children[i].textContent = (dat ? '✓ ' : '○ ') + nhan;
                ds.children[i].style.color = dat ? '#2E7D32' : '#888';
            });
        };
        input.addEventListener('input', capNhat);
        capNhat();
    });
});
