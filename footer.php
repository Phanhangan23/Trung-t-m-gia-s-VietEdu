<?php
// Dữ liệu các trang ở chân trang (dùng chung với trang.php) và tiền tố đường dẫn.
// Trang nằm trong thư mục /gia-su/ phải lùi ra một cấp (giống cách nạp script.js bên dưới).
$chanTrangDuLieu = require __DIR__ . '/trang_noi_dung.php';
$chanTrangBase   = strpos($_SERVER['PHP_SELF'], '/gia-su/') !== false ? '../' : '';
?>
<!-- FOOTER CHÂN TRANG (CÓ MINH CHỨNG PHÁP LÝ) -->
    <footer>
        <div class="footer-container">
            <div class="footer-col">
                <h3>TRUNG TÂM GIA SƯ VIETEDU</h3>
                <p>Hệ thống số hóa quy trình quản lý, kết nối gia sư uy tín hàng đầu tại Hà Nội. Đảm bảo minh bạch, tiện lợi và chất lượng giảng dạy vượt trội.</p>
                <div class="gov-badge">
                    <i class="fa-solid fa-shield-check"></i> ĐÃ THÔNG BÁO BỘ CÔNG THƯƠNG
                </div>
            </div>

            <?php /* Hai cột "Về VietEdu" và "Dành Cho Gia Sư": link lấy từ trang_noi_dung.php */ ?>
            <?php foreach ($chanTrangDuLieu['nhom'] as $chanTrangNhomKhoa => $chanTrangNhom): ?>
            <div class="footer-col">
                <h3><?= htmlspecialchars($chanTrangNhom['ten'], ENT_QUOTES, 'UTF-8') ?></h3>
                <?php foreach ($chanTrangDuLieu['trang'] as $chanTrangSlug => $chanTrangMuc): ?>
                    <?php if ($chanTrangMuc['nhom'] !== $chanTrangNhomKhoa) { continue; } ?>
                    <a href="<?= $chanTrangBase ?>trang.php?p=<?= urlencode($chanTrangSlug) ?>"><?= htmlspecialchars($chanTrangMuc['ten_menu'], ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <div class="footer-col">
                <h3>Thông Tin Liên Hệ</h3>
                <p><i class="fa-solid fa-phone"></i> Đường dây nóng: 0988.XXX.XXX</p>
                <p><i class="fa-solid fa-envelope"></i> Email: contact@vietedu.vn</p>
                <p><i class="fa-solid fa-map-marker-alt"></i> Cầu Giấy, Hà Nội</p>
                <p><i class="fa-solid fa-file-contract"></i> MST / GPĐKKD: 0106XXXXXX</p>
            </div>
        </div>
        <div class="copyright">
            &copy; 2026 Cổng thông tin VietEdu. Tất cả quyền được bảo lưu. Phát triển bởi Đội ngũ Kỹ thuật VietEdu.
        </div>
    </footer>

    <!-- NÚT LIÊN HỆ NỔI GÓC PHẢI (FLOATING WIDGET) -->
    <div class="floating-widget">
        <!-- Nút Cuộn Lên Đầu Trang -->
        <button class="float-btn btn-top" id="btnScrollTop" title="Cuộn lên đầu trang">
            <i class="fa-solid fa-chevron-up"></i>
        </button>
    </div>
    <script src="<?= $chanTrangBase ?>script.js"></script>
</body>
</html>
