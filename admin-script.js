document.addEventListener('DOMContentLoaded', () => {

    // 2. NÚT THU GỌN SIDEBAR (Giữ nguyên tính năng thu gọn menu)
    const btnToggleSidebar = document.getElementById('btnToggleSidebar');
    const adminSidebar = document.getElementById('adminSidebar');
    const adminMain = document.querySelector('.admin-main');

    if (btnToggleSidebar) {
        btnToggleSidebar.addEventListener('click', () => {
            if (adminSidebar.style.display === 'none') {
                adminSidebar.style.display = 'block';
                adminMain.style.marginLeft = '260px';
                adminMain.style.width = 'calc(100% - 260px)';
            } else {
                adminSidebar.style.display = 'none';
                adminMain.style.marginLeft = '0';
                adminMain.style.width = '100%';
            }
        });
    }
});
/* ============================================================
   [PHÂN HỆ 3] THÊM MỚI - Modal, xác nhận thao tác, chọn tất cả
   Dùng cho admin_views/classes.php, matching.php, reports.php
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    // Mở modal và đổ dữ liệu dòng (JSON trong data-ph3-row) vào các phần tử [data-field]
    document.querySelectorAll('[data-ph3-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.dataset.ph3Modal);
            if (!modal) return;
            let row = {};
            try { row = JSON.parse(btn.dataset.ph3Row || '{}'); } catch (e) {}
            modal.querySelectorAll('[data-field]').forEach(el => {
                const v = row[el.dataset.field] ?? '';
                if (el.type === 'radio') el.checked = (String(el.value) === String(v));
                else if ('value' in el && el.tagName !== 'DIV' && el.tagName !== 'SPAN' && el.tagName !== 'STRONG') el.value = v;
                else el.textContent = v;
                if (el.hasAttribute('data-hide-empty')) el.style.display = v ? '' : 'none';
            });
            modal.querySelectorAll('[data-show-if]').forEach(el => {
                el.style.display = Number(row[el.dataset.showIf]) ? '' : 'none';
            });
            modal.querySelectorAll('textarea:not([data-field])').forEach(t => t.value = '');
            modal.classList.add('open');
        });
    });

    const closeModal = m => m && m.classList.remove('open');
    document.querySelectorAll('[data-ph3-close]').forEach(b => b.addEventListener('click', () => closeModal(b.closest('.ph3-modal'))));
    document.querySelectorAll('.ph3-modal').forEach(m => m.addEventListener('click', e => { if (e.target === m) closeModal(m); }));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.ph3-modal.open').forEach(closeModal); });

    // Chip chọn nhanh lý do -> điền vào textarea
    document.querySelectorAll('.ph3-chips').forEach(box => {
        const target = document.querySelector(box.dataset.target);
        box.querySelectorAll('button').forEach(b => b.addEventListener('click', () => { if (target) target.value = b.textContent.trim(); }));
    });

    // Form có data-confirm: hỏi lại trước khi gửi; form thao tác hàng loạt phải có ít nhất 1 ô được tick
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', e => {
            if (form.id) { // form hàng loạt (bulkPublishForm, bulkApproveForm)
                const boxes = document.querySelectorAll('input[type=checkbox][form="' + form.id + '"]');
                if (!Array.from(boxes).some(b => b.checked)) {
                    e.preventDefault(); alert('Vui lòng tick chọn ít nhất 1 dòng.'); return;
                }
            }
            if (!confirm(form.dataset.confirm)) { e.preventDefault(); return; }
            if (form.dataset.prompt) {
                const ans = prompt(form.dataset.promptText || 'Ghi chú:', '');
                if (ans === null) { e.preventDefault(); return; }
                const input = form.querySelector('[name="' + form.dataset.prompt + '"]');
                if (input) input.value = ans;
            }
        });
    });

    // Ô "chọn tất cả" ở đầu bảng
    document.querySelectorAll('[data-ph3-checkall]').forEach(master => {
        master.addEventListener('change', () => {
            const table = master.closest('table');
            table.querySelectorAll('input[type=checkbox][name="' + master.dataset.ph3Checkall + '"]').forEach(b => b.checked = master.checked);
        });
    });
});
/* ============================================================
   [PHÂN HỆ 2] THÊM MỚI - Modal Soi/Duyệt, Thêm, Sửa, Phóng to ảnh KYC
   Dùng cho admin_views/kyc_view.php
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    const fallbackImg = 'https://placehold.co/400x250?text=Chua+co+anh';

    document.addEventListener('click', (e) => {
        // 1. Mở Modal (Soi & Duyệt / Thêm mới / Sửa) và đổ dữ liệu dòng
        const openBtn = e.target.closest('[data-open-modal]');
        if (openBtn) {
            const modalId = openBtn.getAttribute('data-open-modal');
            const modal = document.getElementById(modalId);
            if (!modal) return;

            let rowData = {};
            try {
                rowData = JSON.parse(openBtn.getAttribute('data-row') || '{}');
            } catch (err) {}

            modal.querySelectorAll('[data-fill]').forEach(el => {
                const key = el.getAttribute('data-fill');
                const val = rowData[key] ?? '';
                if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') {
                    el.value = val;
                } else {
                    el.textContent = val;
                }
            });

            modal.querySelectorAll('[data-img]').forEach(img => {
                const key = img.getAttribute('data-img');
                img.src = rowData[key] ? rowData[key] : fallbackImg;
            });

            modal.classList.add('show');
        }

        // 2. Đóng Modal khi bấm nút X, nút Hủy hoặc click ra vùng nền tối
        if (e.target.closest('[data-close-modal]') || e.target.classList.contains('kyc-modal')) {
            document.querySelectorAll('.kyc-modal.show').forEach(m => m.classList.remove('show'));
        }

        // 3. Click chọn nhanh lý do từ chối (Chips)
        const chip = e.target.closest('.kyc-chip');
        if (chip) {
            const textarea = document.getElementById('rejectReasonInput');
            if (textarea) textarea.value = chip.textContent.trim();
        }

        // 4. Click vào ảnh thu nhỏ hoặc ảnh trong modal để mở tab mới soi rõ nét
        const zoomImg = e.target.closest('[data-zoom-img]');
        if (zoomImg) {
            window.open(zoomImg.getAttribute('data-zoom-img'), '_blank');
        }
        if (e.target.hasAttribute('data-zoom-self') && e.target.src) {
            window.open(e.target.src, '_blank');
        }
    });

    // Nhấn phím ESC để đóng Modal KYC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.kyc-modal.show').forEach(m => m.classList.remove('show'));
        }
    });
});