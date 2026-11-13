-- ==========================================================
-- VIETEDU - FILE SQL ĐẦY ĐỦ (chạy 1 lần là có đủ dữ liệu)
--   = File tạo bảng + dữ liệu mẫu của nhóm
--   + sql/phan_he_3.sql (cột mới + dữ liệu demo Phân hệ 3)
-- Cách chạy: phpMyAdmin -> tab "Nhập" (Import) -> chọn file này -> Thực hiện.
-- CẢNH BÁO: file sẽ XÓA database vietedu cũ (nếu có) rồi tạo lại từ đầu.
-- ==========================================================

DROP DATABASE IF EXISTS vietedu;
CREATE DATABASE vietedu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vietedu;
SET NAMES utf8mb4;

-- ==========================================================
-- 1. TẠO CẤU TRÚC BẢNG (SCHEMA) - file gốc của nhóm
-- ==========================================================

CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    description VARCHAR(255)
);

CREATE TABLE staffs (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE SET NULL
);

CREATE TABLE support_tickets (
    ticket_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_type ENUM('parent', 'tutor') NOT NULL,
    sender_id INT NOT NULL,
    issue_title VARCHAR(150),
    content TEXT NOT NULL,
    status ENUM('open', 'processing', 'resolved') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE parents (
    parent_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) UNIQUE NOT NULL,
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tutors (
    tutor_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    university VARCHAR(150),
    major VARCHAR(100),
    rating DECIMAL(2,1) DEFAULT 5.0,
    status ENUM('pending', 'active', 'locked') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE kyc_profiles (
    kyc_id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    id_card_front VARCHAR(255),
    id_card_back VARCHAR(255),
    student_card VARCHAR(255),
    degree_cert VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reviewed_by INT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tutor_id) REFERENCES tutors(tutor_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES staffs(staff_id) ON DELETE SET NULL
);

CREATE TABLE classes (
    class_id INT AUTO_INCREMENT PRIMARY KEY,
    class_code VARCHAR(20) UNIQUE NOT NULL,
    title VARCHAR(150) NOT NULL, 
    subject VARCHAR(50) NOT NULL,
    grade VARCHAR(20) NOT NULL,
    location VARCHAR(100),
    schedule VARCHAR(100),
    requirements TEXT,
    tuition_fee DECIMAL(10,2),
    parent_id INT,
    status ENUM('finding', 'matched', 'completed', 'cancelled') DEFAULT 'finding',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES parents(parent_id) ON DELETE CASCADE
);

CREATE TABLE class_matching (
    match_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    tutor_id INT NOT NULL,
    apply_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    match_score DECIMAL(5,2),
    status ENUM('applied', 'interviewing', 'matched', 'rejected') DEFAULT 'applied',
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES tutors(tutor_id) ON DELETE CASCADE
);

CREATE TABLE lesson_reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    tutor_id INT NOT NULL,
    report_date DATE NOT NULL,
    content TEXT NOT NULL,
    attendance_status ENUM('present', 'absent', 'late') DEFAULT 'present',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES tutors(tutor_id) ON DELETE CASCADE
);

CREATE TABLE transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    tx_code VARCHAR(20) UNIQUE NOT NULL,
    class_id INT NOT NULL,
    tutor_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    tx_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('success', 'pending_24h', 'failed') DEFAULT 'pending_24h',
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES tutors(tutor_id) ON DELETE CASCADE
);

-- ==========================================================
-- 2. DỮ LIỆU MẪU CỦA NHÓM
-- ==========================================================

INSERT INTO roles (role_name, description) VALUES 
('Admin', 'Quản trị viên hệ thống toàn quyền'),
('Tư vấn viên', 'Nhân viên CSKH, đăng lớp và duyệt gia sư');

INSERT INTO staffs (full_name, username, password_hash, role_id) VALUES 
('Nguyễn Văn A', 'admin_a', 'hashed_pwd_123', 1),
('Trần Thị B', 'tuvan_b', 'hashed_pwd_456', 2);

INSERT INTO parents (full_name, phone, address) VALUES 
('Chị Bích Hồng', '0988123456', 'Cầu Giấy, Hà Nội'),('Anh Đinh Đức Vũ', '0912345678', 'Thanh Xuân, Hà Nội'),('Cô Lan Phương', '0909112233', 'Đống Đa, Hà Nội'),('Chú Quang Hải', '0978556677', 'Hai Bà Trưng, Hà Nội'),('Chị Ngọc Mai', '0933445566', 'Hoàng Mai, Hà Nội'),('Anh Tuấn Kiệt', '0888999111', 'Nam Từ Liêm, Hà Nội'),('Cô Thu Hà', '0866777888', 'Hà Đông, Hà Nội'),('Bác Văn Hùng', '0944111222', 'Tây Hồ, Hà Nội'),('Chị Tuyết nhung', '0911222333', 'Bắc Từ Liêm, Hà Nội'),('Anh Quốc Bảo', '0988777666', 'Long Biên, Hà Nội');
INSERT INTO tutors (full_name, phone, email, password_hash, university, major, rating, status) VALUES 
('Trần Văn Bình', '0901112223', 'binh.tran@email.com', 'pwd_123', 'ĐH Sư Phạm Hà Nội', 'Sư phạm Toán', 4.8, 'active'),('Lê Thị Hoa', '0902223334', 'hoa.le@email.com', 'pwd_123', 'ĐH Ngoại Thương', 'Kinh tế đối ngoại', 4.9, 'active'),
('Anh Hoàng', '0903334445', 'hoang.anh@email.com', 'pwd_123', 'ĐH Bách Khoa', 'Công nghệ thông tin', 5.0, 'active'),('Chị Thảo', '0904445556', 'thao.chi@email.com', 'pwd_123', 'ĐH Ngoại Thương', 'Tài chính', 4.7, 'active'),('Thầy Nam', '0905556667', 'nam.thay@email.com', 'pwd_123', 'Khoa Toán ĐHSP', 'Toán học', 5.0, 'active'),('Nguyễn Hải Đăng', '0911000111', 'dang.nguyen@email.com', 'pwd_123', 'ĐH Y Hà Nội', 'Y Đa Khoa', 4.6, 'active'),('Phạm Quỳnh Anh', '0922000222', 'anh.pham@email.com', 'pwd_123', 'ĐH Quốc Gia HN', 'Ngôn ngữ Anh', 4.9, 'active'),('Đoàn Thanh Tuấn', '0933000333', 'tuan.doan@email.com', 'pwd_123', 'Học viện Tài Chính', 'Kế toán', 4.5, 'active'),('Lương Ngọc Diệp', '0944000444', 'diep.luong@email.com', 'pwd_123', 'ĐH KHXH&NV', 'Văn học', 4.8, 'active'),('Hoàng Chí Bảo', '0955000555', 'bao.hoang@email.com', 'pwd_123', 'ĐH Khoa học Tự nhiên', 'Hóa học', 4.7, 'active'),('Vũ Minh Châu', '0966000666', 'chau.vu@email.com', 'pwd_123', 'ĐH Kinh tế Quốc dân', 'Quản trị kinh doanh', 4.0, 'pending'),('Trịnh Thu Phương', '0977000777', 'phuong.trinh@email.com', 'pwd_123', 'Học viện Báo chí', 'Truyền thông', 4.2, 'pending');
INSERT INTO classes (class_code, title, subject, grade, location, schedule, requirements, tuition_fee, parent_id, status) VALUES 
('VE-1024', 'Toán & Tiếng Việt Lớp 5', 'Toán, Tiếng Việt', '5', 'Cầu Giấy', '3 buổi/tuần (Tối T2-T4-T6)', 'Sinh viên SP Toán', 2400000.00, 1, 'matched'),('VE-1025', 'Tiếng Anh Ôn Thi Vào 10', 'Tiếng Anh', '9', 'Thanh Xuân', '2 buổi/tuần (Chiều T7-CN)', 'Ngoại Thương / ĐHNN', 3200000.00, 2, 'matched'),('VE-1026', 'Vật Lý Lớp 12 Ôn Thi ĐH', 'Vật Lý', '12', 'Đống Đa', '3 buổi/tuần (Tối T3-T5-T7)', 'ĐH Bách Khoa', 3000000.00, 3, 'finding'),('VE-1027', 'Toán 8 Nâng Cao', 'Toán', '8', 'Hai Bà Trưng', '2 buổi/tuần (Tối T4-CN)', 'Nam sinh viên Sư phạm', 2000000.00, 4, 'matched'),('VE-1028', 'Hóa Học 10 Mất Gốc', 'Hóa Học', '10', 'Hoàng Mai', '3 buổi/tuần', 'Nhiệt tình, kiên nhẫn', 2500000.00, 5, 'finding'),('VE-1029', 'IELTS 6.5 Cấp Tốc', 'Tiếng Anh', '12', 'Nam Từ Liêm', '4 buổi/tuần', 'Có chứng chỉ IELTS >7.5', 5000000.00, 6, 'matched'),('VE-1030', 'Ngữ Văn 9 Luyện Thi Chuyên', 'Ngữ Văn', '9', 'Hà Đông', '2 buổi/tuần', 'Sinh viên ĐH KHXH&NV', 2800000.00, 7, 'finding'),('VE-1031', 'Toán 6 Sách Kết Nối Tri Thức', 'Toán', '6', 'Tây Hồ', '2 buổi/tuần', 'Nữ gia sư', 1600000.00, 8, 'finding'),('VE-1032', 'Sinh Học Luyện Thi Y', 'Sinh Học', '12', 'Bắc Từ Liêm', '3 buổi/tuần', 'Sinh viên ĐH Y', 3600000.00, 9, 'matched'),('VE-1033', 'Tiếng Việt Lớp 1 Tập Đọc', 'Tiếng Việt', '1', 'Long Biên', '5 buổi/tuần', 'Giọng chuẩn Hà Nội', 4000000.00, 10, 'matched'),('VE-1034', 'Toán & Lý Lớp 11', 'Toán, Lý', '11', 'Cầu Giấy', '3 buổi/tuần', 'Bách Khoa', 3000000.00, 1, 'finding'),('VE-1035', 'Luyện Chữ Đẹp Lớp 2', 'Luyện Chữ', '2', 'Thanh Xuân', '2 buổi/tuần', 'Sư phạm Tiểu Học', 1500000.00, 2, 'finding'),('VE-1036', 'Tiếng Anh Giao Tiếp Cơ Bản', 'Tiếng Anh', 'Đại Học', 'Đống Đa', '3 buổi/tuần', 'Phát âm chuẩn', 2700000.00, 3, 'completed'),('VE-1037', 'Toán 12 Chống Liệt', 'Toán', '12', 'Hai Bà Trưng', '3 buổi/tuần', 'Nhiệt tình', 2500000.00, 4, 'finding'),('VE-1038', 'Vật Lý 9 Nâng Cao', 'Vật Lý', '9', 'Hoàng Mai', '2 buổi/tuần', 'Nam sinh viên', 2200000.00, 5, 'completed'),('VE-1039', 'Tiếng Anh Trẻ Em 7 Tuổi', 'Tiếng Anh', '2', 'Nam Từ Liêm', '2 buổi/tuần', 'Biết cách dỗ trẻ', 1800000.00, 6, 'matched'),('VE-1040', 'Toán Hình 10', 'Toán', '10', 'Hà Đông', '1 buổi/tuần', 'Giảng dễ hiểu', 1000000.00, 7, 'finding'),('VE-1041', 'Hóa Học Luyện Thi Đại Học', 'Hóa', '12', 'Tây Hồ', '3 buổi/tuần', 'Chuyên Hóa Tự Nhiên', 3200000.00, 8, 'matched'),('VE-1042', 'Ngữ Văn 12 Phân Tích Tác Phẩm', 'Ngữ Văn', '12', 'Bắc Từ Liêm', '2 buổi/tuần', 'Chữ đẹp, giảng hay', 2400000.00, 9, 'finding'),('VE-1043', 'Toán, Tiếng Việt, Tiếng Anh Lớp 3', 'Báo bài', '3', 'Long Biên', '4 buổi/tuần', 'Nữ gia sư', 3200000.00, 10, 'matched');
INSERT INTO class_matching (class_id, tutor_id, match_score, status) VALUES (1, 1, 95.5, 'matched'),(2, 2, 89.0, 'matched'),(4, 5, 92.0, 'matched'),(6, 7, 98.5, 'matched'),(9, 6, 91.0, 'matched'),(10, 9, 88.0, 'matched'),(16, 7, 85.0, 'matched'),(18, 10, 94.0, 'matched'),(20, 4, 87.5, 'matched'),(3, 3, 75.0, 'interviewing'),(5, 10, 80.0, 'applied');

INSERT INTO transactions (tx_code, class_id, tutor_id, amount, tx_time, status) VALUES 
('#TX-9081', 1, 1, 720000.00, '2026-10-27 10:15:00', 'success'),
('#TX-9082', 2, 2, 960000.00, '2026-10-27 09:30:00', 'success'),
('#TX-9083', 4, 5, 600000.00, '2026-10-28 14:20:00', 'success'),
('#TX-9084', 6, 7, 1500000.00, '2026-10-29 08:45:00', 'pending_24h'),
('#TX-9085', 9, 6, 1080000.00, '2026-10-29 11:30:00', 'success'),
('#TX-9086', 10, 9, 1200000.00, '2026-10-30 16:10:00', 'pending_24h'),
('#TX-9087', 16, 7, 540000.00, '2026-10-31 09:15:00', 'success'),
('#TX-9088', 18, 10, 960000.00, '2026-11-01 10:20:00', 'pending_24h'),
('#TX-9089', 20, 4, 960000.00, '2026-11-02 15:40:00', 'failed'),
('#TX-9090', 13, 2, 810000.00, '2026-11-03 13:00:00', 'success'),
('#TX-9091', 15, 3, 660000.00, '2026-11-04 18:20:00', 'success');


-- ==========================================================
-- 3. [PHÂN HỆ 3] BỔ SUNG CHO ĐIỀU HƯỚNG LỚP HỌC & NGHIỆP VỤ

-- ==========================================================
-- PHẦN 1. CẤU TRÚC (bắt buộc)
-- ==========================================================

-- 1.1 classes: thêm trạng thái kiểm duyệt 'pending' (chờ duyệt) và 'rejected' (từ chối).
--     Lớp mới phụ huynh gửi lên mặc định là 'pending' -> admin duyệt mới thành 'finding' (hiện trang chủ).
ALTER TABLE classes MODIFY status ENUM('pending','finding','matched','completed','cancelled','rejected') NOT NULL DEFAULT 'pending';
ALTER TABLE classes ADD COLUMN IF NOT EXISTS reject_reason TEXT NULL;
ALTER TABLE classes ADD COLUMN IF NOT EXISTS published_at DATETIME NULL;
ALTER TABLE classes ADD COLUMN IF NOT EXISTS assigned_tutor_id INT NULL;
ALTER TABLE classes ADD COLUMN IF NOT EXISTS sessions_per_week TINYINT NOT NULL DEFAULT 2;

-- 1.2 tutors: năng lực dùng cho thuật toán Match Score
ALTER TABLE tutors ADD COLUMN IF NOT EXISTS subjects VARCHAR(255) NULL COMMENT 'Các môn dạy, cách nhau dấu phẩy';
ALTER TABLE tutors ADD COLUMN IF NOT EXISTS teaching_areas VARCHAR(255) NULL COMMENT 'Khu vực dạy, cách nhau dấu phẩy';
ALTER TABLE tutors ADD COLUMN IF NOT EXISTS grade_min TINYINT NULL;
ALTER TABLE tutors ADD COLUMN IF NOT EXISTS grade_max TINYINT NULL;
ALTER TABLE tutors ADD COLUMN IF NOT EXISTS experience_years TINYINT NOT NULL DEFAULT 0;

-- 1.3 class_matching: thêm 'suggested' (admin gợi ý) và 'cancelled' (hủy giao lớp)
ALTER TABLE class_matching MODIFY status ENUM('applied','interviewing','suggested','matched','rejected','cancelled') NOT NULL DEFAULT 'applied';
ALTER TABLE class_matching ADD COLUMN IF NOT EXISTS note VARCHAR(255) NULL;

-- 1.4 lesson_reports: kết quả admin kiểm tra báo cáo
ALTER TABLE lesson_reports ADD COLUMN IF NOT EXISTS review_status ENUM('pending','approved','flagged') NOT NULL DEFAULT 'pending';
ALTER TABLE lesson_reports ADD COLUMN IF NOT EXISTS admin_note TEXT NULL;
ALTER TABLE lesson_reports ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL;

-- 1.5 Điền dữ liệu cho cột mới từ dữ liệu cũ
UPDATE classes c
JOIN class_matching m ON m.class_id = c.class_id AND m.status = 'matched'
SET c.assigned_tutor_id = m.tutor_id
WHERE c.assigned_tutor_id IS NULL;

-- Lấy số buổi/tuần từ cột schedule, VD "3 buổi/tuần (Tối T2-T4-T6)" -> 3
UPDATE classes SET sessions_per_week = CAST(LEFT(TRIM(schedule), 1) AS UNSIGNED)
WHERE TRIM(schedule) REGEXP '^[1-7] *buổi';

-- ==========================================================
-- PHẦN 2. DỮ LIỆU MẪU ĐỂ DEMO (tùy chọn - CHỈ CHẠY 1 LẦN)
-- ==========================================================

-- 2.1 Năng lực của 12 gia sư mẫu (theo tutor_id 1..12)
UPDATE tutors SET subjects='Toán, Tiếng Việt',            teaching_areas='Cầu Giấy, Thanh Xuân',            grade_min=1, grade_max=9,  experience_years=3 WHERE tutor_id=1;
UPDATE tutors SET subjects='Tiếng Anh',                   teaching_areas='Thanh Xuân, Đống Đa',             grade_min=6, grade_max=12, experience_years=2 WHERE tutor_id=2;
UPDATE tutors SET subjects='Toán, Vật Lý, Tin học',       teaching_areas='Đống Đa, Hai Bà Trưng, Cầu Giấy', grade_min=9, grade_max=12, experience_years=3 WHERE tutor_id=3;
UPDATE tutors SET subjects='Toán, Tiếng Việt, Tiếng Anh', teaching_areas='Long Biên, Hoàn Kiếm',            grade_min=1, grade_max=5,  experience_years=2 WHERE tutor_id=4;
UPDATE tutors SET subjects='Toán',                        teaching_areas='Hai Bà Trưng, Hà Đông, Hoàng Mai', grade_min=6, grade_max=12, experience_years=5 WHERE tutor_id=5;
UPDATE tutors SET subjects='Sinh Học, Hóa Học',           teaching_areas='Bắc Từ Liêm, Cầu Giấy',           grade_min=9, grade_max=12, experience_years=2 WHERE tutor_id=6;
UPDATE tutors SET subjects='Tiếng Anh, IELTS',            teaching_areas='Nam Từ Liêm, Thanh Xuân, Cầu Giấy', grade_min=1, grade_max=12, experience_years=4 WHERE tutor_id=7;
UPDATE tutors SET subjects='Toán',                        teaching_areas='Hoàng Mai, Hai Bà Trưng',         grade_min=1, grade_max=8,  experience_years=1 WHERE tutor_id=8;
UPDATE tutors SET subjects='Ngữ Văn, Tiếng Việt, Luyện Chữ', teaching_areas='Long Biên, Hà Đông, Thanh Xuân', grade_min=1, grade_max=12, experience_years=3 WHERE tutor_id=9;
UPDATE tutors SET subjects='Hóa Học',                     teaching_areas='Tây Hồ, Hoàng Mai, Cầu Giấy',     grade_min=8, grade_max=12, experience_years=3 WHERE tutor_id=10;
UPDATE tutors SET subjects='Toán, Tiếng Anh',             teaching_areas='Đống Đa',                         grade_min=1, grade_max=9,  experience_years=0 WHERE tutor_id=11;
UPDATE tutors SET subjects='Ngữ Văn',                     teaching_areas='Cầu Giấy',                        grade_min=6, grade_max=9,  experience_years=0 WHERE tutor_id=12;

-- 2.2 Vài hồ sơ KYC đã duyệt (cộng 5 điểm KYC khi matching)
INSERT INTO kyc_profiles (tutor_id, status) VALUES (1,'approved'), (3,'approved'), (5,'approved'), (7,'approved'), (9,'approved'), (10,'pending');

-- 2.3 Lớp mới phụ huynh gửi lên, đang CHỜ DUYỆT (để thử màn hình classes.php)
INSERT INTO classes (class_code, title, subject, grade, location, schedule, requirements, tuition_fee, parent_id, status, sessions_per_week) VALUES
('VE-1044', 'Toán 7 Củng Cố Kiến Thức', 'Toán', '7', 'Cầu Giấy', '2 buổi/tuần (Tối T3-T5)', 'Sinh viên sư phạm, kiên nhẫn', 1800000.00, 1, 'pending', 2),
('VE-1045', 'Tiếng Anh Lớp 4 Theo SGK', 'Tiếng Anh', '4', 'Thanh Xuân', '3 buổi/tuần', 'Gọi chị Hồng 0988123456 để trao đổi', 2100000.00, 2, 'pending', 3),
('VE-1046', 'Vật Lý 11 Chương Điện', 'Vật Lý', '11', 'Đống Đa', '', 'Ưu tiên sinh viên Bách Khoa', 2600000.00, 3, 'pending', 2),
('VE-1047', 'Hóa Học 9 Ôn Thi Vào 10', 'Hóa Học', '9', 'Hoàng Mai', '2 buổi/tuần (Chiều T7-CN)', 'Có kinh nghiệm luyện thi', 2200000.00, 5, 'pending', 2);

-- 2.4 Báo cáo buổi dạy của các lớp đã giao (ngày tính lùi từ hôm nay)
INSERT INTO lesson_reports (class_id, tutor_id, report_date, content, attendance_status) VALUES
(1, 1, CURDATE() - INTERVAL 1 DAY,  'Toán: ôn phép chia số thập phân, làm 12 bài tập SGK trang 65. Tiếng Việt: luyện viết đoạn văn tả cảnh.', 'present'),
(1, 1, CURDATE() - INTERVAL 3 DAY,  'Toán: giải toán chuyển động đều, con hiểu bài nhanh. Giao 5 bài về nhà.', 'present'),
(1, 1, CURDATE() - INTERVAL 5 DAY,  'Tiếng Việt: từ đồng nghĩa, trái nghĩa; chữa bài kiểm tra giữa kỳ.', 'present'),
(1, 1, CURDATE() - INTERVAL 8 DAY,  'Toán: tỉ số phần trăm, các dạng bài cơ bản.', 'late'),
(1, 1, CURDATE() - INTERVAL 10 DAY, 'Ôn tập tổng hợp chuẩn bị thi giữa kỳ I.', 'present'),
(1, 1, CURDATE() - INTERVAL 12 DAY, 'Toán: diện tích hình tam giác, hình thang.', 'present'),
(2, 2, CURDATE() - INTERVAL 2 DAY,  'Unit 5 - Reading & Grammar: câu bị động, làm đề thi thử vào 10 số 3.', 'present'),
(2, 2, CURDATE() - INTERVAL 6 DAY,  'Luyện nghe + viết lại câu. Học sinh còn yếu phần phát âm đuôi -ed.', 'present'),
(2, 2, CURDATE() - INTERVAL 9 DAY,  'Ôn tập', 'present'),
(4, 5, CURDATE() - INTERVAL 4 DAY,  'Hằng đẳng thức đáng nhớ nâng cao, bài toán chứng minh chia hết.', 'present'),
(4, 5, CURDATE() - INTERVAL 18 DAY, 'Phân tích đa thức thành nhân tử - phương pháp tách hạng tử.', 'present'),
(6, 7, CURDATE() - INTERVAL 1 DAY,  'IELTS Writing Task 2: opinion essay, chữa 2 bài viết của học viên.', 'present'),
(6, 7, CURDATE() - INTERVAL 2 DAY,  'Speaking Part 2: describe a place, luyện fluency.', 'present'),
(6, 7, CURDATE() - INTERVAL 4 DAY,  '', 'absent'),
(6, 7, CURDATE() - INTERVAL 6 DAY,  'Reading: True/False/Not Given, làm Cambridge 17 Test 2.', 'present'),
(9, 6, CURDATE() - INTERVAL 3 DAY,  'Di truyền học: quy luật Menđen, bài tập lai 2 cặp tính trạng.', 'present'),
(9, 6, CURDATE() - INTERVAL 5 DAY,  'Cơ chế nhân đôi ADN, phiên mã, dịch mã.', 'present'),
(20, 4, CURDATE() - INTERVAL 13 DAY, 'Kèm bài tập về nhà các môn, đọc chính tả.', 'present');

COMMIT;
