<?php
/**
 * PHÂN HỆ 3 – THUẬT TOÁN MATCHING GHÉP LỚP
 *
 * Điểm phù hợp (0–100) = Chuyên môn (40) + Yêu cầu phụ huynh (20) + Đánh giá (25) + Khối lượng dạy hiện tại (15)
 *  - Chuyên môn : so khớp môn học của lớp với chuyên ngành / trường của gia sư (đúng ngành = 100%, ngành liên quan = 60%).
 *  - Yêu cầu    : từ khoá trong yêu cầu của phụ huynh (Sư phạm, Bách Khoa, IELTS, ĐH Y...) có khớp hồ sơ gia sư không.
 *  - Đánh giá   : rating / 5 × 25.
 *  - Khối lượng : gia sư đang dạy càng ít lớp thì càng có thời gian -> 0 lớp = 15, 1 = 10, 2 = 5, ≥3 = 0.
 */

if (!function_exists('mt_e')) {
    function mt_e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    function mt_redirect(array $params)
    {
        $url = 'admin.php?' . http_build_query(array_merge(['view' => 'matching'], array_filter($params, 'strlen')));
        echo '<script>location.replace(' . json_encode($url) . ');</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . mt_e($url) . '"></noscript>';
    }

    /** Chữ thường, bỏ dấu tiếng Việt, chỉ giữ chữ-số; có khoảng trắng 2 đầu để so khớp nguyên từ. */
    function mt_norm($s)
    {
        $s = function_exists('mb_strtolower') ? mb_strtolower((string) $s, 'UTF-8') : strtolower((string) $s);
        $map = [
            'a' => 'àáạảãâầấậẩẫăằắặẳẵ', 'e' => 'èéẹẻẽêềếệểễ', 'i' => 'ìíịỉĩ',
            'o' => 'òóọỏõôồốộổỗơờớợởỡ', 'u' => 'ùúụủũưừứựửữ', 'y' => 'ỳýỵỷỹ', 'd' => 'đ',
        ];
        foreach ($map as $to => $chars) {
            $s = preg_replace('/[' . $chars . ']/u', $to, $s);
        }
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        return ' ' . trim($s) . ' ';
    }

    function mt_has($hay, $kw)
    {
        return strpos($hay, ' ' . $kw . ' ') !== false;
    }

    function mt_has_any($hay, array $kws)
    {
        foreach ($kws as $k) {
            if (mt_has($hay, $k)) return true;
        }
        return false;
    }

    /** Tính điểm phù hợp giữa 1 lớp và 1 gia sư. Trả về tổng + chi tiết từng tiêu chí. */
    function mt_score(array $class, array $tutor, $activeLoad)
    {
        static $subjectRules = [
            ['t' => ['toan'], 's' => ['toan', 'toan hoc', 'su pham toan'],
             'r' => ['bach khoa', 'cong nghe thong tin', 'ke toan', 'tai chinh', 'kinh te', 'khoa hoc tu nhien', 'vat ly']],
            ['t' => ['ly', 'vat ly'], 's' => ['vat ly', 'ly'],
             'r' => ['bach khoa', 'cong nghe thong tin', 'ky thuat', 'khoa hoc tu nhien', 'toan', 'toan hoc']],
            ['t' => ['hoa', 'hoa hoc'], 's' => ['hoa', 'hoa hoc'],
             'r' => ['khoa hoc tu nhien', 'y', 'duoc', 'y da khoa']],
            ['t' => ['sinh', 'sinh hoc'], 's' => ['sinh', 'sinh hoc', 'y', 'y da khoa'],
             'r' => ['duoc', 'khoa hoc tu nhien', 'hoa hoc']],
            ['t' => ['anh', 'tieng anh', 'ielts', 'toeic'], 's' => ['ngon ngu anh', 'tieng anh', 'su pham anh'],
             'r' => ['ngoai thuong', 'kinh te doi ngoai', 'ngoai ngu', 'quoc te']],
            ['t' => ['van', 'ngu van', 'tieng viet'], 's' => ['van hoc', 'ngu van', 'van'],
             'r' => ['khxh', 'nhan van', 'xa hoi', 'su pham', 'bao chi', 'truyen thong', 'tieu hoc']],
            ['t' => ['luyen chu', 'bao bai'], 's' => ['tieu hoc', 'su pham tieu hoc'],
             'r' => ['su pham', 'van hoc']],
        ];
        static $reqAlias = [
            'su pham' => ['su pham', 'dhsp'], 'sp' => ['su pham', 'dhsp'], 'dhsp' => ['su pham', 'dhsp'],
            'bach khoa' => ['bach khoa'], 'ngoai thuong' => ['ngoai thuong'],
            'dhnn' => ['ngon ngu', 'ngoai ngu'], 'ngoai ngu' => ['ngon ngu', 'ngoai ngu'],
            'y' => ['y', 'y da khoa'], 'khxh' => ['khxh', 'nhan van', 'xa hoi'], 'nhan van' => ['khxh', 'nhan van'],
            'tu nhien' => ['tu nhien'], 'toan' => ['toan', 'toan hoc'], 'hoa' => ['hoa', 'hoa hoc'],
            'tieu hoc' => ['tieu hoc'], 'tai chinh' => ['tai chinh'], 'kinh te' => ['kinh te'],
            'ielts' => ['ngon ngu anh', 'tieng anh'], 'phat am' => ['ngon ngu anh', 'tieng anh', 'ngoai thuong'],
            'cong nghe' => ['cong nghe thong tin'],
        ];

        $profile = mt_norm($tutor['major'] . ' ' . $tutor['university']);

        // 1. Chuyên môn (40)
        $parts = preg_split('/\s*(?:,|&|\/|\+|\bvà\b)\s*/u', (string) $class['subject'], -1, PREG_SPLIT_NO_EMPTY);
        $sum = 0;
        $n = 0;
        $why = [];
        foreach ($parts as $part) {
            $pn = mt_norm($part);
            $best = null;
            foreach ($subjectRules as $rule) {
                if (!mt_has_any($pn, $rule['t'])) continue;
                $v = mt_has_any($profile, $rule['s']) ? 1.0 : (mt_has_any($profile, $rule['r']) ? 0.6 : 0.0);
                $best = $best === null ? $v : max($best, $v);
            }
            if ($best === null) $best = 0.5; // môn chưa có trong bộ luật -> trung lập
            $sum += $best;
            $n++;
        }
        $subjectPts = $n ? round($sum / $n * 40, 2) : 20;

        // 2. Yêu cầu phụ huynh (20)
        $req = mt_norm($class['requirements']);
        $found = 0;
        $hit = 0;
        foreach ($reqAlias as $kw => $targets) {
            if (!mt_has($req, $kw)) continue;
            $found++;
            if (mt_has_any($profile, $targets)) $hit++;
        }
        $reqPts = $found ? round($hit / $found * 20, 2) : 14; // không có từ khoá xác minh được -> 70%
        if (mt_has_any($req, ['nam', 'nu'])) $why[] = 'Yêu cầu giới tính – cần xác minh thủ công';

        // 3. Đánh giá (25)
        $rating = max(0, min(5, (float) $tutor['rating']));
        $ratingPts = round($rating / 5 * 25, 2);

        // 4. Khối lượng (15)
        $loadPts = [15, 10, 5][$activeLoad] ?? 0;

        return [
            'total'  => round($subjectPts + $reqPts + $ratingPts + $loadPts, 2),
            'parts'  => ['CM' => [$subjectPts, 40], 'YC' => [$reqPts, 20], 'ĐG' => [$ratingPts, 25], 'TL' => [$loadPts, 15]],
            'load'   => $activeLoad,
            'notes'  => $why,
        ];
    }

    /** Số lớp gia sư đang dạy (đã giao, chưa kết thúc). */
    function mt_loads(PDO $conn)
    {
        return $conn->query("SELECT m.tutor_id, COUNT(*) FROM class_matching m JOIN classes c ON c.class_id = m.class_id
                             WHERE m.status = 'matched' AND c.status = 'matched' GROUP BY m.tutor_id")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    function mt_bar($v)
    {
        $c = $v >= 80 ? '#27ae60' : ($v >= 60 ? '#f39c12' : '#e74c3c');
        return '<div class="mt-score"><strong style="color:' . $c . '">' . number_format((float) $v, 1) . '%</strong>'
             . '<div class="mt-bar"><span style="width:' . max(0, min(100, (float) $v)) . '%;background:' . $c . '"></span></div></div>';
    }

    function mt_parts(array $s)
    {
        $o = [];
        foreach ($s['parts'] as $k => $p) $o[] = $k . ' ' . rtrim(rtrim(number_format($p[0], 1), '0'), '.') . '/' . $p[1];
        return implode(' · ', $o);
    }
}

$mt_labels = [
    'applied'      => ['Đã ứng tuyển', 'badge-warning'],
    'interviewing' => ['Đang phỏng vấn', 'badge-info'],
    'matched'      => ['Đã giao lớp', 'badge-success'],
    'rejected'     => ['Từ chối', 'badge-danger'],
];
$mt_class_labels = [
    'pending' => 'Chờ duyệt', 'finding' => 'Đang tìm gia sư', 'matched' => 'Đã giao lớp',
    'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy',
];

$mt_class_id = (int) ($_GET['class_id'] ?? 0);
$mt_mstatus  = isset($mt_labels[$_GET['mstatus'] ?? '']) ? $_GET['mstatus'] : '';
$mt_flash    = null;

// ---------------------------------------------------------------
// XỬ LÝ THAO TÁC (POST)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mt_action'])) {
    $act  = $_POST['mt_action'];
    $back = ['class_id' => $mt_class_id ?: '', 'mstatus' => $mt_mstatus];
    $fail = function ($msg) use (&$back) {
        mt_redirect($back + ['type' => 'error', 'msg' => $msg]);
    };

    try {
        $loadClass = function ($id) use ($conn) {
            $st = $conn->prepare('SELECT * FROM classes WHERE class_id = ?');
            $st->execute([(int) $id]);
            return $st->fetch(PDO::FETCH_ASSOC);
        };
        $loadTutor = function ($id) use ($conn) {
            $st = $conn->prepare('SELECT * FROM tutors WHERE tutor_id = ?');
            $st->execute([(int) $id]);
            return $st->fetch(PDO::FETCH_ASSOC);
        };

        // Các thao tác trên 1 bản ghi matching
        $match = null;
        $cls = null;
        if (isset($_POST['match_id'])) {
            $st = $conn->prepare('SELECT * FROM class_matching WHERE match_id = ?');
            $st->execute([(int) $_POST['match_id']]);
            $match = $st->fetch(PDO::FETCH_ASSOC);
            if (!$match) { $fail('Không tìm thấy bản ghi ghép nối.'); return; }
            $cls = $loadClass($match['class_id']);
            $back['class_id'] = $back['class_id'] ?: $match['class_id'];
        }

        switch ($act) {
            case 'add':
            case 'add_top':
                $cls = $loadClass($_POST['class_id'] ?? 0);
                if (!$cls) { $fail('Không tìm thấy lớp học.'); return; }
                if ($cls['status'] !== 'finding') { $fail('Chỉ thêm ứng viên cho lớp đang ở trạng thái "Đang tìm gia sư".'); return; }
                $back['class_id'] = $cls['class_id'];
                $loads = mt_loads($conn);

                $existing = $conn->prepare('SELECT tutor_id, match_id, status FROM class_matching WHERE class_id = ?');
                $existing->execute([$cls['class_id']]);
                $exist = [];
                foreach ($existing->fetchAll(PDO::FETCH_ASSOC) as $r) $exist[$r['tutor_id']] = $r;

                if ($act === 'add') {
                    $tutor = $loadTutor($_POST['tutor_id'] ?? 0);
                    if (!$tutor || $tutor['status'] !== 'active') { $fail('Gia sư không tồn tại hoặc chưa được kích hoạt.'); return; }
                    $candidates = [$tutor];
                } else {
                    $candidates = $conn->query("SELECT * FROM tutors WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
                }

                $scored = [];
                foreach ($candidates as $t) {
                    if (isset($exist[$t['tutor_id']]) && $exist[$t['tutor_id']]['status'] !== 'rejected') continue;
                    $scored[] = [$t, mt_score($cls, $t, (int) ($loads[$t['tutor_id']] ?? 0))['total']];
                }
                if (!$scored) { $fail('Gia sư đã có trong danh sách ứng tuyển của lớp này.'); return; }
                usort($scored, function ($a, $b) { return $b[1] <=> $a[1]; });
                if ($act === 'add_top') $scored = array_slice($scored, 0, 3);

                $ins = $conn->prepare("INSERT INTO class_matching (class_id, tutor_id, match_score, status) VALUES (?, ?, ?, 'applied')");
                $upd = $conn->prepare("UPDATE class_matching SET status = 'applied', match_score = ?, apply_date = CURRENT_TIMESTAMP WHERE match_id = ?");
                $names = [];
                $conn->beginTransaction();
                foreach ($scored as $s) {
                    list($t, $score) = $s;
                    if (isset($exist[$t['tutor_id']])) $upd->execute([$score, $exist[$t['tutor_id']]['match_id']]);
                    else $ins->execute([$cls['class_id'], $t['tutor_id'], $score]);
                    $names[] = $t['full_name'] . ' (' . $score . '%)';
                }
                $conn->commit();
                mt_redirect($back + ['msg' => 'Đã thêm vào danh sách ứng tuyển lớp ' . $cls['class_code'] . ': ' . implode(', ', $names) . '.']);
                return;

            case 'interview':
                if ($match['status'] !== 'applied') { $fail('Chỉ chuyển phỏng vấn cho hồ sơ đang ở trạng thái "Đã ứng tuyển".'); return; }
                if ($cls['status'] !== 'finding') { $fail('Lớp không còn ở trạng thái tìm gia sư.'); return; }
                $conn->prepare("UPDATE class_matching SET status = 'interviewing' WHERE match_id = ?")->execute([$match['match_id']]);
                mt_redirect($back + ['msg' => 'Đã chuyển hồ sơ sang vòng phỏng vấn.']);
                return;

            case 'assign':
                if (!in_array($match['status'], ['applied', 'interviewing'], true)) { $fail('Hồ sơ này không thể giao lớp.'); return; }
                if ($cls['status'] !== 'finding') { $fail('Lớp ' . $cls['class_code'] . ' không ở trạng thái "Đang tìm gia sư" (có thể đã giao cho gia sư khác).'); return; }
                $tutor = $loadTutor($match['tutor_id']);
                if (!$tutor || $tutor['status'] !== 'active') { $fail('Gia sư không còn hoạt động, không thể giao lớp.'); return; }

                $conn->beginTransaction();
                $st = $conn->prepare("UPDATE classes SET status = 'matched' WHERE class_id = ? AND status = 'finding'");
                $st->execute([$cls['class_id']]);
                if ($st->rowCount() !== 1) { $conn->rollBack(); $fail('Lớp vừa được cập nhật bởi người khác, vui lòng tải lại.'); return; }
                $conn->prepare("UPDATE class_matching SET status = 'matched' WHERE match_id = ?")->execute([$match['match_id']]);
                $conn->prepare("UPDATE class_matching SET status = 'rejected' WHERE class_id = ? AND match_id <> ? AND status IN ('applied','interviewing')")
                     ->execute([$cls['class_id'], $match['match_id']]);
                $conn->commit();
                mt_redirect($back + ['msg' => 'Đã giao lớp ' . $cls['class_code'] . ' cho gia sư ' . $tutor['full_name'] . '. Các hồ sơ còn lại đã được đóng.']);
                return;

            case 'unassign':
                if ($match['status'] !== 'matched' || $cls['status'] !== 'matched') { $fail('Chỉ hủy giao được lớp đang ở trạng thái "Đã giao lớp".'); return; }

                // Gia sư đã đóng phí nhận lớp thì không hủy giao được (tránh giữ tiền mà không có lớp) -> hoàn phí trước
                $st = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE class_id = ? AND tutor_id = ? AND status = 'success'");
                $st->execute([$cls['class_id'], $match['tutor_id']]);
                if ((int) $st->fetchColumn() > 0) { $fail('Gia sư đã thanh toán phí nhận lớp nên không thể hủy giao. Hãy xử lý hoàn phí cho gia sư trước.'); return; }

                $conn->beginTransaction();
                $conn->prepare("UPDATE class_matching SET status = 'rejected' WHERE match_id = ?")->execute([$match['match_id']]);
                $conn->prepare("UPDATE classes SET status = 'finding' WHERE class_id = ?")->execute([$cls['class_id']]);
                // Vô hiệu mã QR đang chờ của gia sư này để không ai chuyển khoản vào mã cũ
                $conn->prepare("UPDATE transactions SET status = 'failed' WHERE class_id = ? AND tutor_id = ? AND status = 'pending_24h'")
                     ->execute([$cls['class_id'], $match['tutor_id']]);
                $conn->commit();
                mt_redirect($back + ['msg' => 'Đã hủy giao lớp ' . $cls['class_code'] . '. Lớp được mở lại để tìm gia sư mới.']);
                return;

            case 'reject':
                if (!in_array($match['status'], ['applied', 'interviewing'], true)) { $fail('Hồ sơ này không thể từ chối.'); return; }
                $conn->prepare("UPDATE class_matching SET status = 'rejected' WHERE match_id = ?")->execute([$match['match_id']]);
                mt_redirect($back + ['msg' => 'Đã từ chối hồ sơ ứng tuyển.']);
                return;

            case 'restore':
                if ($match['status'] !== 'rejected' || $cls['status'] !== 'finding') { $fail('Chỉ khôi phục hồ sơ bị từ chối của lớp đang tìm gia sư.'); return; }
                $conn->prepare("UPDATE class_matching SET status = 'applied' WHERE match_id = ?")->execute([$match['match_id']]);
                mt_redirect($back + ['msg' => 'Đã khôi phục hồ sơ về trạng thái "Đã ứng tuyển".']);
                return;

            case 'rescore':
                $cls = $loadClass($_POST['class_id'] ?? 0);
                if (!$cls) { $fail('Không tìm thấy lớp học.'); return; }
                $back['class_id'] = $cls['class_id'];
                $loads = mt_loads($conn);
                $st = $conn->prepare('SELECT m.match_id, m.status, t.* FROM class_matching m JOIN tutors t ON t.tutor_id = m.tutor_id WHERE m.class_id = ?');
                $st->execute([$cls['class_id']]);
                $upd = $conn->prepare('UPDATE class_matching SET match_score = ? WHERE match_id = ?');
                $cnt = 0;
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $load = (int) ($loads[$row['tutor_id']] ?? 0);
                    if ($row['status'] === 'matched' && $cls['status'] === 'matched') $load = max(0, $load - 1); // không tính chính lớp này
                    $upd->execute([mt_score($cls, $row, $load)['total'], $row['match_id']]);
                    $cnt++;
                }
                mt_redirect($back + ['msg' => "Đã tính lại Điểm phù hợp cho $cnt hồ sơ của lớp " . $cls['class_code'] . '.']);
                return;

            case 'delete':
                if ($match['status'] === 'matched') { $fail('Không thể xóa hồ sơ đã giao lớp. Hãy hủy giao trước.'); return; }
                $conn->prepare('DELETE FROM class_matching WHERE match_id = ?')->execute([$match['match_id']]);
                mt_redirect($back + ['msg' => 'Đã xóa hồ sơ ứng tuyển.']);
                return;
        }
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $mt_flash = ['type' => 'error', 'msg' => ve_loi_csdl($e, 'cập nhật ghép lớp')];
    }
}

// ---------------------------------------------------------------
// TRUY VẤN DỮ LIỆU HIỂN THỊ
// ---------------------------------------------------------------
$error = null;
$mt_classes = [];
$mt_class = null;
$mt_applicants = [];
$mt_suggest = [];
$matches = [];
$mt_counts = array_fill_keys(array_keys($mt_labels), 0);

try {
    $loads = mt_loads($conn);

    $mt_classes = $conn->query("SELECT c.class_id, c.class_code, c.title, c.subject, c.grade, c.status, c.location, c.tuition_fee,
                                       SUM(m.status IN ('applied','interviewing')) AS open_apps
                                FROM classes c LEFT JOIN class_matching m ON m.class_id = c.class_id
                                WHERE c.status IN ('finding','matched')
                                GROUP BY c.class_id
                                ORDER BY FIELD(c.status,'finding','matched'), c.class_id DESC")->fetchAll(PDO::FETCH_ASSOC);

    if ($mt_class_id) {
        $st = $conn->prepare('SELECT c.*, p.full_name AS parent_name, p.phone AS parent_phone FROM classes c LEFT JOIN parents p ON p.parent_id = c.parent_id WHERE c.class_id = ?');
        $st->execute([$mt_class_id]);
        $mt_class = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($mt_class) {
        $st = $conn->prepare("SELECT m.*, t.full_name, t.phone, t.university, t.major, t.rating, t.status AS tutor_status
                              FROM class_matching m JOIN tutors t ON t.tutor_id = m.tutor_id
                              WHERE m.class_id = ?
                              ORDER BY FIELD(m.status,'matched','interviewing','applied','rejected'), m.match_score DESC");
        $st->execute([$mt_class['class_id']]);
        $taken = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $load = (int) ($loads[$a['tutor_id']] ?? 0);
            if ($a['status'] === 'matched' && $mt_class['status'] === 'matched') $load = max(0, $load - 1);
            $a['live'] = mt_score($mt_class, $a, $load);
            $mt_applicants[] = $a;
            if ($a['status'] !== 'rejected') $taken[$a['tutor_id']] = true;
        }

        if ($mt_class['status'] === 'finding') {
            foreach ($conn->query("SELECT * FROM tutors WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC) as $t) {
                if (isset($taken[$t['tutor_id']])) continue;
                $t['score'] = mt_score($mt_class, $t, (int) ($loads[$t['tutor_id']] ?? 0));
                $mt_suggest[] = $t;
            }
            usort($mt_suggest, function ($a, $b) {
                return $b['score']['total'] <=> $a['score']['total'] ?: (float) $b['rating'] <=> (float) $a['rating'];
            });
            $mt_suggest = array_slice($mt_suggest, 0, 10);
        }
    }

    foreach ($conn->query('SELECT status, COUNT(*) FROM class_matching GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
        if (isset($mt_counts[$k])) $mt_counts[$k] = (int) $v;
    }
    $sql = 'SELECT m.*, c.class_code, c.title, c.status AS class_status, t.full_name AS tutor_name, t.major, t.university
            FROM class_matching m JOIN classes c ON m.class_id = c.class_id JOIN tutors t ON m.tutor_id = t.tutor_id';
    if ($mt_mstatus !== '') {
        $st = $conn->prepare($sql . ' WHERE m.status = ? ORDER BY m.match_score DESC');
        $st->execute([$mt_mstatus]);
    } else {
        $st = $conn->query($sql . " ORDER BY FIELD(m.status,'interviewing','applied','matched','rejected'), m.match_score DESC");
    }
    $matches = $st->fetchAll(PDO::FETCH_ASSOC);

    // Phân trang 10 ghép nối / trang
    $pt = phan_trang($matches, 10);
    $matches = $pt['rows'];
} catch (PDOException $e) {
    $error = ve_loi_csdl($e, 'tải dữ liệu ghép lớp');
}

if (!$mt_flash && isset($_GET['msg'])) {
    $mt_flash = ['type' => ($_GET['type'] ?? '') === 'error' ? 'error' : 'success', 'msg' => (string) $_GET['msg']];
}
$mt_url = 'admin.php?' . http_build_query(array_filter(['view' => 'matching', 'class_id' => $mt_class_id ?: '', 'mstatus' => $mt_mstatus], 'strlen'));

/** In 1 nút thao tác dạng form POST. */
$mt_btn = function ($action, array $hidden, $cls, $icon, $label, $confirm = '') use ($mt_url) {
    $h = '<form method="post" action="' . mt_e($mt_url) . '"' . ($confirm ? ' onsubmit="return confirm(' . mt_e(json_encode($confirm)) . ')"' : '') . '>';
    $h .= '<input type="hidden" name="mt_action" value="' . mt_e($action) . '">';
    foreach ($hidden as $k => $v) $h .= '<input type="hidden" name="' . mt_e($k) . '" value="' . mt_e($v) . '">';
    $h .= '<button type="submit" class="ve-btn ' . $cls . '"><i class="fa-solid ' . $icon . '"></i> ' . $label . '</button></form>';
    return $h;
};
?>
<style>
    .ve-flash{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px}
    .ve-flash.success{background:#EAF7ED;color:#2E7D32;border:1px solid #C8E6C9}
    .ve-flash.error{background:#FDECEA;color:#C62828;border:1px solid #F5C6CB}
    .ve-btn{display:inline-flex;align-items:center;gap:5px;border:none;border-radius:5px;padding:6px 10px;font-size:12px;cursor:pointer;text-decoration:none;color:#fff;background:#2563eb;white-space:nowrap;font-family:inherit}
    .ve-btn.green{background:#27ae60}.ve-btn.red{background:#e74c3c}.ve-btn.gray{background:#6b7280}.ve-btn.orange{background:#f39c12}
    .ve-btn.outline{background:#fff;color:#2563eb;border:1px solid #2563eb}
    .ve-btn.lg{padding:8px 14px;font-size:13px}
    .ve-actions{display:flex;flex-wrap:wrap;gap:4px}
    .ve-actions form{margin:0}
    .ve-muted{color:#6b7280;font-size:12px}
    .ve-table-wrap{overflow-x:auto}
    .ve-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:14px}
    .ve-toolbar select{padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;min-width:280px;font:inherit;font-size:14px}
    .ve-tabs{display:flex;flex-wrap:wrap;gap:6px}
    .ve-tab{padding:6px 12px;border-radius:16px;background:#f1f3f5;color:#495057;text-decoration:none;font-size:13px}
    .ve-tab.active{background:#2563eb;color:#fff}
    .badge-info{background:#E8F1FD;color:#1565C0}.badge-danger{background:#FDECEA;color:#C62828}.badge-muted{background:#F1F3F5;color:#495057}
    .mt-score{min-width:90px}
    .mt-bar{height:6px;background:#eef0f3;border-radius:3px;overflow:hidden;margin-top:4px}
    .mt-bar span{display:block;height:100%}
    .mt-info{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px 20px;font-size:14px}
    .mt-info div span{display:block;font-size:12px;color:#6b7280}
    .mt-legend{font-size:12px;color:#6b7280;background:#f8fafc;border-radius:8px;padding:10px 12px;margin-bottom:12px;line-height:1.6}
    .mt-rank{display:inline-flex;width:24px;height:24px;border-radius:50%;align-items:center;justify-content:center;background:#eef2ff;color:#3730a3;font-weight:700;font-size:12px}
    .mt-rank.top{background:#fde68a;color:#92400e}
</style>

<section id="view-matching" class="content-view active">
    <div class="view-header">
        <h2>Phân hệ 3: Ghép Lớp Theo Thuật Toán</h2>
    </div>

    <?php if ($error): ?><div class="ve-flash error"><?= mt_e($error) ?></div><?php endif; ?>
    <?php if ($mt_flash): ?><div class="ve-flash <?= $mt_flash['type'] ?>"><?= mt_e($mt_flash['msg']) ?></div><?php endif; ?>

    <!-- CHỌN LỚP CẦN GHÉP -->
    <div class="data-table-card">
        <div class="ve-toolbar">
            <h3 style="margin:0">Chọn lớp cần ghép gia sư</h3>
            <form method="get" action="admin.php" style="display:flex;gap:6px;flex-wrap:wrap">
                <input type="hidden" name="view" value="matching">
                <select name="class_id" onchange="this.form.submit()">
                    <option value="">-- Chọn lớp (đang tìm / đã giao) --</option>
                    <?php foreach ($mt_classes as $c): ?>
                        <option value="<?= $c['class_id'] ?>" <?= $mt_class_id === (int) $c['class_id'] ? 'selected' : '' ?>>
                            <?= mt_e($c['class_code'] . ' – ' . $c['title'] . ' [' . ($mt_class_labels[$c['status']] ?? $c['status']) . ']') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript><button class="ve-btn lg" type="submit">Xem</button></noscript>
            </form>
        </div>

        <?php if (!$mt_class): ?>
            <?php if ($mt_class_id): ?><div class="ve-flash error">Không tìm thấy lớp học đã chọn.</div><?php endif; ?>
            <div class="ve-table-wrap">
            <table class="custom-table">
                <thead><tr><th>Mã Lớp</th><th>Tên Lớp</th><th>Môn / Khối</th><th>Khu vực</th><th>Hồ sơ đang mở</th><th>Trạng thái</th><th></th></tr></thead>
                <tbody>
                <?php $finding = array_filter($mt_classes, function ($c) { return $c['status'] === 'finding'; }); ?>
                <?php if ($finding): foreach ($finding as $c): ?>
                    <tr>
                        <td><strong><?= mt_e($c['class_code']) ?></strong></td>
                        <td><?= mt_e($c['title']) ?></td>
                        <td><?= mt_e($c['subject']) ?> <span class="ve-muted">(<?= mt_e($c['grade']) ?>)</span></td>
                        <td><?= mt_e($c['location']) ?></td>
                        <td><?= (int) $c['open_apps'] ?></td>
                        <td><span class="badge badge-info">Đang tìm gia sư</span></td>
                        <td><a class="ve-btn" href="admin.php?view=matching&amp;class_id=<?= (int) $c['class_id'] ?>"><i class="fa-solid fa-wand-magic-sparkles"></i> Ghép gia sư</a></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7" style="text-align:center">Không có lớp nào đang tìm gia sư. Hãy duyệt & công bố lớp ở màn "Duyệt & Công Bố Lớp Học".</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="mt-info">
                <div><span>Mã lớp</span><strong><?= mt_e($mt_class['class_code']) ?></strong> – <?= mt_e($mt_class['title']) ?></div>
                <div><span>Môn / Khối</span><?= mt_e($mt_class['subject']) ?> · <?= mt_e(ctype_digit((string) $mt_class['grade']) ? 'Lớp ' . $mt_class['grade'] : $mt_class['grade']) ?></div>
                <div><span>Khu vực / Lịch</span><?= mt_e($mt_class['location'] ?: '—') ?> · <?= mt_e($mt_class['schedule'] ?: '—') ?></div>
                <div><span>Học phí</span><?= $mt_class['tuition_fee'] !== null ? number_format((float) $mt_class['tuition_fee'], 0, ',', '.') . ' đ/tháng' : '—' ?></div>
                <div><span>Yêu cầu phụ huynh</span><?= mt_e($mt_class['requirements'] ?: 'Không có') ?></div>
                <div><span>Phụ huynh</span><?= mt_e(($mt_class['parent_name'] ?: '—') . ($mt_class['parent_phone'] ? ' – ' . $mt_class['parent_phone'] : '')) ?></div>
                <div><span>Trạng thái lớp</span><strong><?= mt_e($mt_class_labels[$mt_class['status']] ?? $mt_class['status']) ?></strong></div>
            </div>
            <div style="margin-top:12px"><a class="ve-btn outline" href="admin.php?view=matching"><i class="fa-solid fa-arrow-left"></i> Chọn lớp khác</a></div>
        <?php endif; ?>
    </div>

    <?php if ($mt_class): ?>
        <!-- HỒ SƠ ỨNG TUYỂN CỦA LỚP -->
        <div class="data-table-card">
            <div class="ve-toolbar">
                <h3 style="margin:0">Hồ sơ ứng tuyển lớp <?= mt_e($mt_class['class_code']) ?> (<?= count($mt_applicants) ?>)</h3>
                <?php if ($mt_applicants): ?>
                    <div class="ve-actions"><?= $mt_btn('rescore', ['class_id' => $mt_class['class_id']], 'outline', 'fa-calculator', 'Tính lại điểm tất cả') ?></div>
                <?php endif; ?>
            </div>
            <div class="ve-table-wrap">
            <table class="custom-table">
                <thead><tr><th>Gia Sư</th><th>Trường / Chuyên ngành</th><th>Đánh giá</th><th>Điểm phù hợp</th><th>Ngày ứng tuyển</th><th>Trạng Thái</th><th>Thao Tác</th></tr></thead>
                <tbody>
                <?php if ($mt_applicants): foreach ($mt_applicants as $a):
                    $lb = $mt_labels[$a['status']] ?? [$a['status'], 'badge-muted'];
                    $diff = abs((float) $a['match_score'] - $a['live']['total']) >= 0.01;
                ?>
                    <tr>
                        <td><strong><?= mt_e($a['full_name']) ?></strong><div class="ve-muted"><?= mt_e($a['phone']) ?><?= $a['tutor_status'] !== 'active' ? ' · <span style="color:#C62828">Tài khoản ' . mt_e($a['tutor_status']) . '</span>' : '' ?></div></td>
                        <td><?= mt_e($a['university']) ?><div class="ve-muted"><?= mt_e($a['major']) ?></div></td>
                        <td>⭐ <?= mt_e($a['rating']) ?></td>
                        <td>
                            <?= mt_bar($a['match_score'] !== null ? $a['match_score'] : $a['live']['total']) ?>
                            <div class="ve-muted"><?= mt_e(mt_parts($a['live'])) ?></div>
                            <?php if ($diff): ?><div class="ve-muted" style="color:#B78103">Điểm hiện tại: <?= number_format($a['live']['total'], 1) ?>% (bấm "Tính lại")</div><?php endif; ?>
                        </td>
                        <td><?= $a['apply_date'] ? date('d/m/Y H:i', strtotime($a['apply_date'])) : '—' ?></td>
                        <td><span class="badge <?= $lb[1] ?>"><?= $lb[0] ?></span></td>
                        <td><div class="ve-actions">
                            <?php
                            $h = ['match_id' => $a['match_id']];
                            if ($mt_class['status'] === 'finding') {
                                if ($a['status'] === 'applied') echo $mt_btn('interview', $h, 'orange', 'fa-comments', 'Phỏng vấn');
                                if (in_array($a['status'], ['applied', 'interviewing'], true)) {
                                    echo $mt_btn('assign', $h, 'green', 'fa-handshake', 'Giao lớp', 'Giao lớp ' . $mt_class['class_code'] . ' cho ' . $a['full_name'] . '? Các hồ sơ khác sẽ bị từ chối.');
                                    echo $mt_btn('reject', $h, 'red', 'fa-xmark', 'Từ chối');
                                }
                                if ($a['status'] === 'rejected') echo $mt_btn('restore', $h, 'gray', 'fa-rotate-left', 'Khôi phục');
                            }
                            if ($a['status'] === 'matched' && $mt_class['status'] === 'matched') {
                                echo $mt_btn('unassign', $h, 'red', 'fa-user-xmark', 'Hủy giao', 'Hủy giao lớp cho gia sư này và mở lại lớp để tìm gia sư mới?');
                            }
                            if ($a['status'] !== 'matched') echo $mt_btn('delete', $h, 'gray', 'fa-trash', 'Xóa', 'Xóa hồ sơ ứng tuyển này?');
                            ?>
                        </div></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7" style="text-align:center">Chưa có gia sư nào ứng tuyển. Xem gợi ý bên dưới.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <?php if ($mt_class['status'] === 'finding'): ?>
            <!-- GỢI Ý THUẬT TOÁN -->
            <div class="data-table-card">
                <div class="ve-toolbar">
                    <h3 style="margin:0"><i class="fa-solid fa-wand-magic-sparkles"></i> Top gia sư phù hợp nhất (thuật toán gợi ý)</h3>
                    <?php if ($mt_suggest): ?>
                        <div class="ve-actions"><?= $mt_btn('add_top', ['class_id' => $mt_class['class_id']], 'green', 'fa-list-check', 'Thêm Top 3 vào danh sách') ?></div>
                    <?php endif; ?>
                </div>
                <div class="mt-legend">
                    <strong>Cách tính điểm phù hợp (100 điểm):</strong>
                    CM – Chuyên môn khớp môn học (40) · YC – Khớp yêu cầu phụ huynh (20) · ĐG – Đánh giá gia sư (25) · TL – Thời gian trống theo số lớp đang dạy (15).
                    Chỉ xét gia sư đã kích hoạt (active).
                </div>
                <div class="ve-table-wrap">
                <table class="custom-table">
                    <thead><tr><th>#</th><th>Gia Sư</th><th>Trường / Chuyên ngành</th><th>Đánh giá</th><th>Đang dạy</th><th>Điểm phù hợp</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($mt_suggest): foreach ($mt_suggest as $i => $t): ?>
                        <tr>
                            <td><span class="mt-rank <?= $i < 3 ? 'top' : '' ?>"><?= $i + 1 ?></span></td>
                            <td><strong><?= mt_e($t['full_name']) ?></strong><div class="ve-muted"><?= mt_e($t['phone']) ?></div></td>
                            <td><?= mt_e($t['university']) ?><div class="ve-muted"><?= mt_e($t['major']) ?></div></td>
                            <td>⭐ <?= mt_e($t['rating']) ?></td>
                            <td><?= (int) $t['score']['load'] ?> lớp</td>
                            <td>
                                <?= mt_bar($t['score']['total']) ?>
                                <div class="ve-muted"><?= mt_e(mt_parts($t['score'])) ?></div>
                                <?php foreach ($t['score']['notes'] as $note): ?><div class="ve-muted" style="color:#B78103"><?= mt_e($note) ?></div><?php endforeach; ?>
                            </td>
                            <td><div class="ve-actions"><?= $mt_btn('add', ['class_id' => $mt_class['class_id'], 'tutor_id' => $t['tutor_id']], '', 'fa-user-plus', 'Thêm ứng viên') ?></div></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7" style="text-align:center">Không còn gia sư active nào để gợi ý.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- TOÀN BỘ GHÉP NỐI -->
    <div class="data-table-card">
        <div class="ve-toolbar">
            <h3 style="margin:0">Danh Sách Ghép Nối Gia Sư & Lớp Học</h3>
            <div class="ve-tabs">
                <?php $base = array_filter(['view' => 'matching', 'class_id' => $mt_class_id ?: ''], 'strlen'); ?>
                <a class="ve-tab <?= $mt_mstatus === '' ? 'active' : '' ?>" href="admin.php?<?= mt_e(http_build_query($base)) ?>">Tất cả (<?= array_sum($mt_counts) ?>)</a>
                <?php foreach ($mt_labels as $k => $lb): ?>
                    <a class="ve-tab <?= $mt_mstatus === $k ? 'active' : '' ?>" href="admin.php?<?= mt_e(http_build_query($base + ['mstatus' => $k])) ?>"><?= $lb[0] ?> (<?= $mt_counts[$k] ?>)</a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="ve-table-wrap">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Mã Lớp</th>
                    <th>Tên Lớp Học</th>
                    <th>Gia Sư Ứng Tuyển</th>
                    <th>Điểm phù hợp (%)</th>
                    <th>Trạng Thái</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($matches)): ?>
                    <?php foreach ($matches as $m): $lb = $mt_labels[$m['status']] ?? [$m['status'], 'badge-muted']; ?>
                        <tr>
                            <td><strong><?= mt_e($m['class_code']) ?></strong><div class="ve-muted"><?= mt_e($mt_class_labels[$m['class_status']] ?? $m['class_status']) ?></div></td>
                            <td><?= mt_e($m['title']) ?></td>
                            <td><?= mt_e($m['tutor_name']) ?><div class="ve-muted"><?= mt_e($m['major']) ?></div></td>
                            <td><?= $m['match_score'] !== null ? mt_bar($m['match_score']) : '<span class="ve-muted">Chưa tính</span>' ?></td>
                            <td><span class="badge <?= $lb[1] ?>"><?= $lb[0] ?></span></td>
                            <td><a class="ve-btn outline" href="admin.php?<?= mt_e(http_build_query(array_filter(['view' => 'matching', 'class_id' => $m['class_id'], 'mstatus' => $mt_mstatus], 'strlen'))) ?>">Chi tiết</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center;">Chưa có dữ liệu ghép lớp.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
        <?= isset($pt) ? phan_trang_html($pt, 'lượt ghép nối') : '' ?>
    </div>
</section>
