<?php
/**
 * PHÂN TRANG DÙNG CHUNG CHO KHU QUẢN TRỊ
 *
 * Cách dùng trong một view:
 *     $pt   = phan_trang($danhSach, 10);            // cắt mảng theo ?page= (và ?per= nếu người dùng đổi số dòng)
 *     $rows = $pt['rows'];                           // các dòng của trang hiện tại
 *     ...
 *     <?= phan_trang_html($pt, 'gia sư') ?>         // thanh phân trang, giữ nguyên bộ lọc trên URL
 *
 * Thanh phân trang gồm: tổng số bản ghi, chọn số dòng/trang, các nút trang, ô "Đi tới trang".
 * admin-script.js giữ nguyên vị trí cuộn khi chuyển trang nên bảng không bị nhảy lên đầu.
 */

const PT_SO_DONG_CHO_CHON = [10, 20, 50, 100];

/** Cắt mảng $rows theo trang hiện tại lấy từ $_GET. */
function phan_trang(array $rows, int $soDong = 10, string $thamSo = 'page'): array
{
    $chon = (int) ($_GET['per'] ?? 0);
    if (in_array($chon, PT_SO_DONG_CHO_CHON, true)) $soDong = $chon;

    $tong    = count($rows);
    $soTrang = max(1, (int) ceil($tong / $soDong));
    $trang   = min(max(1, (int) ($_GET[$thamSo] ?? 1)), $soTrang);
    $batDau  = ($trang - 1) * $soDong;

    return [
        'rows'  => array_slice($rows, $batDau, $soDong),
        'page'  => $trang,
        'pages' => $soTrang,
        'total' => $tong,
        'per'   => $soDong,
        'from'  => $tong ? $batDau + 1 : 0,
        'to'    => min($tong, $batDau + $soDong),
        'param' => $thamSo,
    ];
}

/** Tham số URL hiện tại, bỏ các thông báo chỉ hiện 1 lần. */
function phan_trang_query(array $doi = [], array $bo = []): array
{
    $q = $_GET;
    foreach (array_merge(['msg', 'type'], $bo) as $k) unset($q[$k]);
    return array_merge($q, $doi);
}

function phan_trang_link(array $pt, int $n): string
{
    return htmlspecialchars(basename($_SERVER['PHP_SELF']) . '?' . http_build_query(phan_trang_query([$pt['param'] => $n])), ENT_QUOTES, 'UTF-8');
}

/** HTML thanh phân trang. $donVi: tên đối tượng hiển thị, ví dụ "gia sư", "lớp học". */
function phan_trang_html(array $pt, string $donVi = 'bản ghi'): string
{
    $e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
    $p = $pt['page'];
    $n = $pt['pages'];

    // --- Bên trái: tổng số + chọn số dòng ---
    if ($pt['total'] === 0) {
        $info = 'Không có ' . $e($donVi) . ' nào';
    } else {
        $info = 'Đang xem <b>' . $pt['from'] . '–' . $pt['to'] . '</b> trong tổng số <b>' . $pt['total'] . '</b> ' . $e($donVi);
    }

    $an = '';   // giữ lại bộ lọc hiện tại khi gửi form chọn số dòng / đi tới trang
    foreach (phan_trang_query([], [$pt['param'], 'per']) as $k => $v) {
        if (is_array($v)) continue;
        $an .= '<input type="hidden" name="' . $e($k) . '" value="' . $e($v) . '">';
    }
    $tuyChon = '';
    foreach (PT_SO_DONG_CHO_CHON as $so) {
        $tuyChon .= '<option value="' . $so . '"' . ($so === $pt['per'] ? ' selected' : '') . '>' . $so . '</option>';
    }
    if (!in_array($pt['per'], PT_SO_DONG_CHO_CHON, true)) {
        $tuyChon = '<option value="' . $pt['per'] . '" selected>' . $pt['per'] . '</option>' . $tuyChon;
    }

    $html  = '<div class="vt-pager" data-vt-pager>';
    $html .= '<div class="vt-pager-left"><span class="vt-pager-info">' . $info . '</span>';
    $html .= '<form method="get" action="' . $e(basename($_SERVER['PHP_SELF'])) . '" class="vt-pager-form">' . $an
           . '<input type="hidden" name="' . $e($pt['param']) . '" value="1">'
           . '<label>Hiển thị <select name="per" data-vt-autosubmit aria-label="Số dòng mỗi trang">' . $tuyChon . '</select> / trang</label>'
           . '<noscript><button type="submit" class="vt-pager-btn">Áp dụng</button></noscript></form></div>';

    // --- Giữa: các nút trang ---
    $nut = function ($trang, $nhan, $tieuDe, $tat = false, $dangXem = false) use ($pt) {
        if ($dangXem) return '<span class="vt-pager-btn active" aria-current="page">' . $nhan . '</span>';
        if ($tat)     return '<span class="vt-pager-btn disabled" aria-disabled="true" title="' . $tieuDe . '">' . $nhan . '</span>';
        return '<a class="vt-pager-btn" href="' . phan_trang_link($pt, $trang) . '" title="' . $tieuDe . '" data-vt-page>' . $nhan . '</a>';
    };
    $html .= '<nav class="vt-pager-nav" aria-label="Phân trang">';
    $html .= $nut($p - 1, '<i class="fa-solid fa-chevron-left"></i><span class="vt-hide-sm"> Trước</span>', 'Trang trước', $p === 1);
    $hien = array_filter(array_unique([1, 2, $n - 1, $n, $p - 1, $p, $p + 1]), function ($i) use ($n) { return $i >= 1 && $i <= $n; });
    sort($hien);
    $truoc = 0;
    foreach ($hien as $i) {
        if ($i - $truoc > 1) $html .= '<span class="vt-pager-gap" aria-hidden="true">…</span>';
        $html .= $nut($i, (string) $i, 'Trang ' . $i, false, $i === $p);
        $truoc = $i;
    }
    $html .= $nut($p + 1, '<span class="vt-hide-sm">Sau </span><i class="fa-solid fa-chevron-right"></i>', 'Trang sau', $p === $n);
    $html .= '</nav>';

    // --- Bên phải: đi tới trang (chỉ khi có từ 4 trang trở lên) ---
    if ($n >= 4) {
        $html .= '<form method="get" action="' . $e(basename($_SERVER['PHP_SELF'])) . '" class="vt-pager-form vt-pager-jump">'
               . $an . '<input type="hidden" name="per" value="' . $pt['per'] . '">'
               . '<label>Trang <input type="number" name="' . $e($pt['param']) . '" min="1" max="' . $n . '" value="' . $p
               . '" aria-label="Số trang muốn tới"> / ' . $n . '</label>'
               . '<button type="submit" class="vt-pager-btn">Đi</button></form>';
    } else {
        $html .= '<span class="vt-pager-page">Trang <b>' . $p . '</b> / ' . $n . '</span>';
    }
    return $html . '</div>';
}

/** Escape HTML và tô vàng (<mark>) các chỗ khớp từ khóa tìm kiếm. Dùng chung cho các bảng admin. */
function to_dam_tu_khoa($text, string $tuKhoa): string
{
    $text = (string) $text;
    if ($tuKhoa === '') return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $parts = preg_split('/(' . preg_quote($tuKhoa, '/') . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $out = '';
    foreach ($parts as $i => $p) {
        $out .= $i % 2 ? '<mark>' . htmlspecialchars($p, ENT_QUOTES, 'UTF-8') . '</mark>' : htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
    }
    return $out;
}
