<?php
/**
 * NỘI DUNG CÁC TRANG Ở CHÂN TRANG (footer)
 *
 * File này chỉ chứa DỮ LIỆU, được dùng bởi:
 *   - trang.php  : hiển thị nội dung trang  (trang.php?p=<khóa-trang>)
 *   - footer.php : tự sinh các link ở chân trang
 *
 * Muốn đổi chữ        -> sửa trực tiếp ở đây.
 * Muốn thêm trang mới -> thêm một mục vào mảng 'trang' (chép từ mục có sẵn), link ở footer tự xuất hiện.
 *
 * Mỗi trang có danh sách 'khoi' (các khối nội dung), mỗi khối có 'loai':
 *   heading : tiêu đề phụ            ['loai' => 'heading', 'text' => '...']
 *   text    : đoạn văn               ['loai' => 'text',    'text' => '...']
 *   list    : danh sách gạch đầu     ['loai' => 'list',    'items' => ['...', '...']]
 *   steps   : các bước đánh số       ['loai' => 'steps',   'items' => [['Tên bước', 'Mô tả'], ...]]
 *   note    : khung lưu ý nổi bật    ['loai' => 'note',    'tieu_de' => '...', 'text' => '...']
 * Chỉ viết văn bản thường, KHÔNG viết thẻ HTML (trang.php tự escape).
 */
return [

    // Nhóm hiển thị ở sidebar và làm tiêu đề cột ở footer (thứ tự = thứ tự hiển thị)
    'nhom' => [
        've-vietedu'      => ['ten' => 'Về VietEdu',       'icon' => 'fa-circle-info'],
        'danh-cho-gia-su' => ['ten' => 'Dành Cho Gia Sư',  'icon' => 'fa-user-graduate'],
    ],

    'trang' => [

        /* ============================================================ */
        'gioi-thieu' => [
            'nhom'     => 've-vietedu',
            'ten_menu' => 'Giới thiệu trung tâm',
            'icon'     => 'fa-building',
            'tieu_de'  => 'Giới thiệu trung tâm VietEdu',
            'mo_ta'    => 'Trung tâm Gia sư VietEdu được thành lập từ năm 2012, có hơn 14 năm kinh nghiệm tư vấn, môi giới và kết nối gia sư, đồng hành cùng hàng vạn học sinh tại Hà Nội.',
            'lien_quan' => ['quy-dinh', 'huong-dan-nhan-lop'],
            'khoi' => [
                ['loai' => 'heading', 'text' => 'Chúng tôi làm gì'],
                ['loai' => 'text', 'text' => 'VietEdu kết nối học sinh với gia sư phù hợp theo môn học, trình độ, địa điểm và hình thức học. Mỗi lớp học đều được nhân viên tư vấn kiểm tra trước khi công bố, và mỗi gia sư đều được xác minh hồ sơ trước khi nhận lớp.'],

                ['loai' => 'heading', 'text' => 'Cổng thông tin VietEdu'],
                ['loai' => 'text', 'text' => 'Cổng thông tin số hóa toàn bộ quy trình vận hành của trung tâm: tiếp nhận nhu cầu của phụ huynh, ghép nối gia sư với học sinh, thu phí nhận lớp, theo dõi lịch học và báo cáo sau từng buổi dạy. Dữ liệu được lưu tập trung, thay cho sổ sách, file Excel rời rạc và tin nhắn trao đổi thủ công.'],

                ['loai' => 'heading', 'text' => 'Ai tham gia vào hệ thống'],
                ['loai' => 'list', 'items' => [
                    'Phụ huynh / Học viên: gửi nhu cầu tìm gia sư, xác nhận lớp, theo dõi tiến độ trên trang cá nhân và đánh giá chất lượng dịch vụ.',
                    'Gia sư: cung cấp hồ sơ năng lực, đăng ký nhận lớp, đóng phí nhận lớp và báo cáo tình hình học tập sau mỗi buổi dạy.',
                    'Nhân viên tư vấn: tiếp nhận thông tin, xét duyệt năng lực gia sư, xử lý sự cố và ghi nhận các khoản thu phí.',
                    'Quản lý (Giám đốc): điều hành tổng thể, quyết định chính sách phí và giám sát hiệu suất vận hành qua báo cáo.',
                ]],

                ['loai' => 'heading', 'text' => 'Một lớp học đi qua những bước nào'],
                ['loai' => 'steps', 'items' => [
                    ['Phụ huynh gửi nhu cầu', 'Phụ huynh hoặc học viên điền biểu mẫu trên website, hoặc cung cấp thông tin cho nhân viên tư vấn qua đường dây nóng. Hệ thống lưu hồ sơ tập trung và cấp mã lớp để theo dõi.'],
                    ['Kiểm tra và công bố lớp', 'Nhân viên tư vấn kiểm tra thông tin rồi công bố lớp vào danh sách lớp trống. Số điện thoại và địa chỉ của phụ huynh được tự động ẩn.'],
                    ['Gia sư ứng tuyển', 'Gia sư lọc lớp theo môn, cấp lớp, khu vực và bấm “Ứng tuyển nhanh” bằng hồ sơ năng lực đã lưu sẵn.'],
                    ['Gợi ý và chọn gia sư', 'Hệ thống chấm điểm mức độ phù hợp của các ứng viên theo môn học, trình độ và khu vực. Nhân viên tư vấn xem gợi ý và chọn gia sư phù hợp nhất.'],
                    ['Gia sư đóng phí nhận lớp', 'Gia sư quét mã VietQR động và chuyển khoản trong vòng 24 giờ.'],
                    ['Xác nhận và giao lớp', 'Khi ngân hàng báo giao dịch thành công, hệ thống tự động ghi nhận khoản thu, chuyển lớp sang “Đã giao” và hiển thị thông tin liên hệ của phụ huynh cho đúng gia sư đó.'],
                    ['Theo dõi tiến độ', 'Sau mỗi buổi dạy, gia sư nộp báo cáo điện tử. Phụ huynh xem lịch học và tiến độ trên trang cá nhân, và gửi Ticket khi cần hỗ trợ hoặc khiếu nại.'],
                ]],

                ['loai' => 'heading', 'text' => 'Cam kết của VietEdu'],
                ['loai' => 'list', 'items' => [
                    'Phí nhận lớp công khai, nằm trong khoảng 30% đến 40% lương tháng đầu tiên.',
                    'Thông tin liên hệ của phụ huynh được bảo vệ cho đến khi gia sư hoàn thành nghĩa vụ đóng phí.',
                    'Hoàn trả phí hoặc đổi lớp miễn phí khi phụ huynh hủy lớp trong 2 buổi dạy thử đầu tiên, không do lỗi của gia sư.',
                    'Khi đổi gia sư, toàn bộ lịch sử học tập và báo cáo cũ được giữ nguyên để gia sư mới tiếp quản.',
                ]],
            ],
        ],

        /* ============================================================ */
        'quy-dinh' => [
            'nhom'     => 've-vietedu',
            'ten_menu' => 'Quy định & Chính sách',
            'icon'     => 'fa-scale-balanced',
            'tieu_de'  => 'Quy định và chính sách',
            'mo_ta'    => 'Các quy định dưới đây áp dụng cho phụ huynh, học viên và gia sư, nhằm đảm bảo chất lượng giảng dạy và tính công bằng của hoạt động kết nối.',
            'lien_quan' => ['hoan-phi', 'bao-mat', 'thanh-toan-vietqr'],
            'khoi' => [
                ['loai' => 'heading', 'text' => 'Quy định về nhận lớp'],
                ['loai' => 'list', 'items' => [
                    'Tại một thời điểm, mỗi lớp học chỉ được giao cho đúng một gia sư.',
                    'Gia sư phải tải lên ảnh căn cước công dân và thẻ sinh viên hoặc bằng cấp hợp lệ thì tài khoản mới được phê duyệt để nhận lớp.',
                    'Lớp ở trạng thái “Đã giao” hoặc “Đã hủy” được tự động ẩn khỏi danh sách lớp trống của gia sư.',
                ]],

                ['loai' => 'heading', 'text' => 'Quy định về phí nhận lớp'],
                ['loai' => 'list', 'items' => [
                    'Phí nhận lớp bằng 30% đến 40% mức lương tháng đầu tiên, tùy theo môn học và cấp độ lớp do trung tâm quy định.',
                    'Gia sư phải hoàn thành đóng phí trong vòng 24 giờ kể từ khi được duyệt giao lớp.',
                    'Quá thời hạn, hệ thống tự động gỡ trạng thái giữ lớp để nhường cho ứng viên khác.',
                ]],

                ['loai' => 'heading', 'text' => 'Quy định về thông tin liên lạc'],
                ['loai' => 'text', 'text' => 'Số điện thoại và địa chỉ cụ thể của phụ huynh tuyệt đối được che giấu, không hiển thị trên các trang công khai, cho đến khi gia sư được giao lớp và hoàn thành nghĩa vụ đóng phí.'],

                ['loai' => 'heading', 'text' => 'Quy định về chất lượng giảng dạy'],
                ['loai' => 'list', 'items' => [
                    'Sau mỗi buổi dạy, gia sư phải nộp báo cáo điện tử về tình hình học tập của học sinh.',
                    'Gia sư phải hoàn thành báo cáo của các buổi học trước thì mới được ghi nhận hoàn thành buổi dạy và đăng ký lớp mới.',
                    'Khi đổi gia sư, hệ thống giữ nguyên toàn bộ lịch sử học tập và báo cáo cũ để gia sư mới tiếp quản.',
                ]],

                ['loai' => 'heading', 'text' => 'Hoàn phí'],
                ['loai' => 'text', 'text' => 'Trong 2 buổi dạy thử đầu tiên, nếu phụ huynh hủy lớp không do lỗi của gia sư, trung tâm hoàn trả 100% phí nhận lớp hoặc hỗ trợ đổi lớp mới miễn phí. Chi tiết ở trang Chính sách hoàn phí.'],
            ],
        ],

        /* ============================================================ */
        'hoan-phi' => [
            'nhom'     => 've-vietedu',
            'ten_menu' => 'Chính sách hoàn phí 100%',
            'icon'     => 'fa-rotate-left',
            'tieu_de'  => 'Chính sách hoàn phí nhận lớp',
            'mo_ta'    => 'Trong 2 buổi dạy thử đầu tiên, nếu phụ huynh hủy lớp không do lỗi của gia sư, trung tâm hoàn trả 100% phí nhận lớp cho gia sư hoặc hỗ trợ đổi lớp mới miễn phí.',
            'lien_quan' => ['quy-dinh', 'thanh-toan-vietqr'],
            'khoi' => [
                ['loai' => 'heading', 'text' => 'Điều kiện áp dụng'],
                ['loai' => 'text', 'text' => 'Chính sách hoàn phí 100% áp dụng khi đáp ứng đủ cả ba điều kiện:'],
                ['loai' => 'list', 'items' => [
                    'Lớp đã được giao và gia sư đã đóng phí nhận lớp thành công.',
                    'Phụ huynh hủy lớp trong thời gian 2 buổi dạy thử đầu tiên.',
                    'Việc hủy lớp không do lỗi của gia sư.',
                ]],

                ['loai' => 'heading', 'text' => 'Hình thức xử lý'],
                ['loai' => 'list', 'items' => [
                    'Hoàn trả 100% phí nhận lớp mà gia sư đã đóng, hoặc',
                    'Hỗ trợ gia sư nhận một lớp mới, miễn phí.',
                ]],

                ['loai' => 'heading', 'text' => 'Các bước yêu cầu hoàn phí'],
                ['loai' => 'steps', 'items' => [
                    ['Liên hệ nhân viên tư vấn', 'Phụ huynh hoặc gia sư gửi Ticket trên hệ thống, hoặc liên hệ đường dây nóng của trung tâm, nêu rõ mã lớp và lý do hủy lớp.'],
                    ['Nhân viên kiểm tra', 'Nhân viên tư vấn kiểm tra số buổi dạy thử đã diễn ra và nguyên nhân hủy lớp.'],
                    ['Ghi nhận vào hệ thống', 'Nếu đủ điều kiện, nhân viên ghi nhận yêu cầu hoàn phí. Lớp chuyển sang trạng thái “Đã hủy”, còn lịch sử báo cáo buổi học vẫn được giữ lại.'],
                    ['Hoàn phí hoặc đổi lớp', 'Trung tâm thực hiện hoàn trả phí nhận lớp hoặc hỗ trợ gia sư nhận lớp mới theo hình thức đã thống nhất.'],
                ]],

                ['loai' => 'note', 'tieu_de' => 'Lưu ý', 'text' => 'Từ buổi dạy thứ 3 trở đi, phí nhận lớp không còn thuộc diện hoàn trả theo chính sách này. Chính sách cũng chỉ áp dụng khi việc hủy lớp không do lỗi của gia sư.'],
            ],
        ],

        /* ============================================================ */
        'bao-mat' => [
            'nhom'     => 've-vietedu',
            'ten_menu' => 'Quy trình bảo mật thông tin',
            'icon'     => 'fa-lock',
            'tieu_de'  => 'Quy trình bảo mật thông tin',
            'mo_ta'    => 'VietEdu chỉ thu thập những thông tin cần thiết cho việc kết nối gia sư và học sinh, đồng thời kiểm soát chặt việc ai được xem thông tin nào.',
            'lien_quan' => ['xac-minh-ho-so', 'quy-dinh'],
            'khoi' => [
                ['loai' => 'heading', 'text' => 'Thông tin liên lạc của phụ huynh'],
                ['loai' => 'list', 'items' => [
                    'Số điện thoại và địa chỉ cụ thể của phụ huynh không hiển thị trên các trang công khai.',
                    'Gia sư chỉ nhìn thấy thông tin này sau khi được giao lớp và đóng phí nhận lớp thành công.',
                    'Thông tin được mở khóa trên tài khoản của đúng gia sư được giao lớp, không hiển thị cho gia sư khác.',
                ]],

                ['loai' => 'heading', 'text' => 'Mật khẩu tài khoản'],
                ['loai' => 'list', 'items' => [
                    'Hệ thống không lưu mật khẩu dưới dạng văn bản có thể đọc trực tiếp.',
                    'Mật khẩu cần tối thiểu 8 ký tự, gồm chữ hoa, chữ thường, chữ số và ký tự đặc biệt, không chứa khoảng trắng.',
                ]],

                ['loai' => 'heading', 'text' => 'Giấy tờ xác minh của gia sư'],
                ['loai' => 'text', 'text' => 'Ảnh căn cước công dân, thẻ sinh viên hoặc bằng cấp mà gia sư tải lên chỉ dùng để xác minh danh tính. Chỉ nhân viên được phân quyền mới xem được các giấy tờ này.'],

                ['loai' => 'heading', 'text' => 'Phân quyền nội bộ'],
                ['loai' => 'list', 'items' => [
                    'Mỗi nhân viên chỉ thao tác trong phạm vi quyền được cấp.',
                    'Báo cáo doanh thu tổng chỉ dành cho nhóm tài khoản Quản lý.',
                ]],

                ['loai' => 'heading', 'text' => 'Dữ liệu thanh toán'],
                ['loai' => 'text', 'text' => 'Phí nhận lớp được thanh toán bằng cách quét mã VietQR và chuyển khoản qua ứng dụng ngân hàng của chính gia sư. Hệ thống ghi nhận kết quả giao dịch do ngân hàng báo về, không yêu cầu nhập thông tin thẻ trên website.'],

                ['loai' => 'note', 'tieu_de' => 'Nghi ngờ thông tin bị sử dụng sai mục đích?', 'text' => 'Hãy gửi Ticket trên hệ thống hoặc liên hệ nhân viên tư vấn qua thông tin ở chân trang để được kiểm tra.'],
            ],
        ],

        /* ============================================================ */
        'huong-dan-nhan-lop' => [
            'nhom'     => 'danh-cho-gia-su',
            'ten_menu' => 'Hướng dẫn nhận lớp',
            'icon'     => 'fa-clipboard-list',
            'tieu_de'  => 'Hướng dẫn nhận lớp dành cho gia sư',
            'mo_ta'    => 'Từ lúc tạo hồ sơ đến lúc nhận được thông tin liên hệ của phụ huynh, gia sư thực hiện toàn bộ trên cổng thông tin, không cần điền lại biểu mẫu dài cho mỗi lớp.',
            'lien_quan' => ['xac-minh-ho-so', 'thanh-toan-vietqr'],
            'khoi' => [
                ['loai' => 'heading', 'text' => 'Các bước nhận lớp'],
                ['loai' => 'steps', 'items' => [
                    ['Tạo hồ sơ năng lực', 'Đăng ký tài khoản và điền hồ sơ một lần duy nhất: trường đại học, chuyên ngành, thành tích và kinh nghiệm giảng dạy.'],
                    ['Xác minh hồ sơ', 'Tải lên ảnh căn cước công dân và thẻ sinh viên hoặc bằng cấp. Tài khoản được kích hoạt sau khi nhân viên phê duyệt.'],
                    ['Tìm lớp trống', 'Đăng nhập và dùng bộ lọc theo môn học, cấp lớp và khu vực để tìm lớp phù hợp.'],
                    ['Ứng tuyển nhanh', 'Bấm “Ứng tuyển nhanh”. Hệ thống dùng hồ sơ năng lực đã lưu để đưa bạn vào danh sách ứng viên của lớp.'],
                    ['Chờ xét chọn', 'Hệ thống chấm điểm mức độ phù hợp giữa bạn và yêu cầu của lớp. Nhân viên tư vấn xem danh sách gợi ý và chọn gia sư phù hợp.'],
                    ['Đóng phí nhận lớp', 'Nếu được chọn, lớp chuyển sang “Chờ thu phí”. Bạn quét mã VietQR và chuyển khoản trong vòng 24 giờ.'],
                    ['Nhận thông tin và dạy thử', 'Sau khi đóng phí thành công, số điện thoại và địa chỉ của phụ huynh hiện trên tài khoản của bạn để hẹn lịch dạy thử.'],
                ]],

                ['loai' => 'heading', 'text' => 'Những điều cần nhớ'],
                ['loai' => 'list', 'items' => [
                    'Phí nhận lớp bằng 30% đến 40% lương tháng đầu tiên, tùy môn học và cấp độ lớp.',
                    'Quá 24 giờ chưa đóng phí, lớp được gỡ khỏi bạn để nhường cho ứng viên khác.',
                    'Sau mỗi buổi dạy, bạn phải nộp báo cáo điện tử. Chưa nộp báo cáo các buổi trước thì buổi dạy chưa được ghi nhận hoàn thành và bạn không đăng ký được lớp mới.',
                    'Nếu phụ huynh hủy lớp trong 2 buổi dạy thử đầu tiên mà không do lỗi của bạn, bạn được hoàn phí hoặc đổi lớp miễn phí.',
                ]],
            ],
        ],

        /* ============================================================ */
        'thanh-toan-vietqr' => [
            'nhom'     => 'danh-cho-gia-su',
            'ten_menu' => 'Thanh toán VietQR tự động',
            'icon'     => 'fa-qrcode',
            'tieu_de'  => 'Thanh toán phí nhận lớp bằng VietQR',
            'mo_ta'    => 'Gia sư đóng phí nhận lớp bằng cách quét mã VietQR động. Khi ngân hàng báo tiền về, hệ thống tự động ghi nhận, không cần gửi ảnh biên lai.',
            'lien_quan' => ['huong-dan-nhan-lop', 'hoan-phi'],
            'khoi' => [
                ['loai' => 'heading', 'text' => 'Phí nhận lớp là bao nhiêu'],
                ['loai' => 'text', 'text' => 'Phí nhận lớp bằng 30% đến 40% mức lương tháng đầu tiên, tùy theo môn học và cấp độ lớp. Số tiền chính xác của từng lớp đã được gắn sẵn trong mã QR.'],

                ['loai' => 'heading', 'text' => 'Cách thanh toán'],
                ['loai' => 'steps', 'items' => [
                    ['Nhận mã QR', 'Khi bạn được chọn nhận lớp, lớp chuyển sang “Chờ thu phí” và hệ thống tạo mã VietQR riêng cho lớp đó.'],
                    ['Quét mã bằng ứng dụng ngân hàng', 'Mở ứng dụng ngân hàng, chọn chức năng quét QR và quét mã trên màn hình.'],
                    ['Kiểm tra thông tin', 'Đối chiếu số tiền và nội dung chuyển khoản. Giữ nguyên nội dung có mã giao dịch, không tự sửa, vì hệ thống dựa vào mã này để biết khoản tiền thuộc lớp nào.'],
                    ['Xác nhận chuyển khoản', 'Hoàn tất chuyển khoản trong ứng dụng ngân hàng của bạn.'],
                    ['Hệ thống tự gạch nợ', 'Khi ngân hàng báo giao dịch thành công, hệ thống ghi nhận khoản thu, chuyển lớp sang “Đã giao” và mở khóa số điện thoại, địa chỉ của phụ huynh trên tài khoản của bạn.'],
                ]],

                ['loai' => 'heading', 'text' => 'Thời hạn 24 giờ'],
                ['loai' => 'text', 'text' => 'Bạn có 24 giờ kể từ khi được duyệt giao lớp để chuyển khoản. Quá thời hạn mà chưa ghi nhận thanh toán, hệ thống tự động hủy trạng thái chờ và nhường lớp cho ứng viên khác.'],

                ['loai' => 'heading', 'text' => 'Khi chuyển khoản gặp vấn đề'],
                ['loai' => 'list', 'items' => [
                    'Chuyển thiếu tiền hoặc sửa mất mã giao dịch trong nội dung: hệ thống có thể không tự gạch nợ được. Hãy liên hệ nhân viên tư vấn kèm ảnh biên lai chuyển khoản.',
                    'Đã chuyển khoản nhưng lớp đã bị gỡ vì quá 24 giờ: hãy liên hệ nhân viên tư vấn để được kiểm tra.',
                ]],

                ['loai' => 'note', 'tieu_de' => 'Quyền lợi khi phụ huynh hủy lớp', 'text' => 'Nếu phụ huynh hủy lớp trong 2 buổi dạy thử đầu tiên mà không do lỗi của gia sư, phí đã đóng được hoàn 100% hoặc đổi sang lớp mới miễn phí.'],
            ],
        ],

        /* ============================================================ */
        'xac-minh-ho-so' => [
            'nhom'     => 'danh-cho-gia-su',
            'ten_menu' => 'Xác minh hồ sơ gia sư',
            'icon'     => 'fa-id-card',
            'tieu_de'  => 'Xác minh hồ sơ gia sư',
            'mo_ta'    => 'Mọi gia sư phải được xác minh danh tính và năng lực trước khi nhận lớp. Đây là cách VietEdu giúp phụ huynh yên tâm khi giao việc dạy kèm cho con.',
            'lien_quan' => ['huong-dan-nhan-lop', 'bao-mat'],
            'khoi' => [
                ['loai' => 'heading', 'text' => 'Giấy tờ cần chuẩn bị'],
                ['loai' => 'list', 'items' => [
                    'Ảnh căn cước công dân, cả mặt trước và mặt sau.',
                    'Ảnh thẻ sinh viên hoặc bằng cấp hợp lệ.',
                ]],

                ['loai' => 'heading', 'text' => 'Quy trình xác minh'],
                ['loai' => 'steps', 'items' => [
                    ['Tải giấy tờ lên', 'Đăng nhập tài khoản gia sư và tải ảnh giấy tờ vào mục hồ sơ. Tài khoản ở trạng thái chờ duyệt.'],
                    ['Nhân viên kiểm tra', 'Nhân viên tư vấn xem ảnh căn cước và thẻ sinh viên hoặc bằng cấp, đối chiếu với thông tin trong hồ sơ của bạn.'],
                    ['Nhận kết quả', 'Nếu hợp lệ, hồ sơ được phê duyệt và tài khoản được kích hoạt để ứng tuyển và nhận lớp. Nếu chưa hợp lệ, hồ sơ bị từ chối và bạn cần liên hệ nhân viên tư vấn để bổ sung.'],
                ]],

                ['loai' => 'heading', 'text' => 'Mẹo để hồ sơ được duyệt nhanh'],
                ['loai' => 'list', 'items' => [
                    'Chụp ảnh rõ nét, đủ bốn góc, không bị lóa hoặc che mất chữ và số.',
                    'Dùng giấy tờ của chính bạn, thông tin trùng với họ tên và trường đã khai trong hồ sơ.',
                    'Tải đủ giấy tờ ngay từ lần đầu để tránh phải bổ sung nhiều lần.',
                ]],

                ['loai' => 'note', 'tieu_de' => 'Vì sao bắt buộc xác minh', 'text' => 'Tài khoản chỉ được phê duyệt để tham gia nhận lớp khi đã có ảnh căn cước và thẻ sinh viên hoặc bằng cấp hợp lệ. Giấy tờ của bạn chỉ dùng để xác minh và chỉ nhân viên được phân quyền mới xem được.'],
            ],
        ],

    ],
];
