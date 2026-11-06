// Video quản trị 6: Giáo viên, lớp học, tài khoản học viên
export default (d) => {
    const cls = d.classrooms.en;
    return {
        slug: 'qtv-lop-hoc-tai-khoan',
        title: 'Quản trị viên — Lớp học, tài khoản học viên & giáo viên',
        scenes: [
            { caption: false, say: 'Xin chào! Video này hướng dẫn vận hành lớp học trực tuyến: tạo tài khoản giáo viên, mở lớp theo khoá với thời hạn học, phân quyền giáo viên trong lớp, và cấp tài khoản cho học viên.',
                do: async (t) => { await t.card('Quản trị viên', 'Lớp học · Tài khoản học viên · Giáo viên', 'Giáo viên · Mở lớp · Quyền trong lớp · Tài khoản hàng loạt'); await t.goto('/admin/login'); } },
            { say: 'Đăng nhập trang quản trị. Mục Giáo viên tạo tài khoản cho giáo viên: họ tên, email, điện thoại, mật khẩu và trạng thái hoạt động. Giáo viên đăng nhập ở cổng riêng và chỉ thấy lớp, khoá được giao.',
                do: async (t) => { await t.hideCard(); await t.login('/admin/login', 'demo.admin' + d.domain, d.password); await t.nav('/admin/teachers'); await t.click('a:has-text("Thêm giáo viên")', { wait: 800 }); await t.type('input[name=name]', 'Cô Trần Thu Hà', { delay: 45 }); await t.point('input[name=email]', 700); await t.point('input[name=is_active]', 800); } },
            { say: 'Mục Lớp học liệt kê các lớp: thuộc khoá nào, thời gian học, giáo viên, sĩ số và trạng thái sắp mở, đang học hay đã kết thúc. Lọc theo khoá hoặc trạng thái.',
                do: async (t) => { await t.nav('/admin/classrooms'); await t.point('select[name=course_id]', 800); await t.point('select[name=status]', 800); await t.point(`text=${cls.name}`, 1000); } },
            { say: 'Tạo lớp mới: tên lớp, chọn khoá học, ngày bắt đầu và kết thúc. Sau giờ kết thúc, học viên không vào học nữa nhưng vẫn xem được điểm cũ. Tuỳ chọn cho phép học viên tự vào lớp bằng liên kết mời và bảng xếp hạng.',
                do: async (t) => { await t.goto('/admin/classrooms/create'); await t.type('input[name=name]', 'Giao tiếp A2 — sáng thứ 7', { delay: 40 }); await t.point('select[name=course_id]', 700); await t.point('input[name=starts_at]', 700); await t.point('input[name=ends_at]', 700); await t.point('text=Sau khi lớp kết thúc', 900); await t.point('input[name=allow_join]', 700); await t.point('input[name=show_leaderboard]', 700); } },
            { say: 'Khối Giáo viên của lớp và quyền: một lớp có thể có nhiều giáo viên. Với mỗi người, bật riêng quyền tuỳ chỉnh bài tập, thêm học viên, và cấp lại mật khẩu. Bấm Lưu để tạo lớp.',
                do: async (t) => { await t.point('.card-title:has-text("Giáo viên của lớp")', 1000); const tt = t.page.locator('input[name^="teachers["][name$="[id]"]'); if (await tt.count()) { await t.point(tt.first(), 800); await t.point('input[name^="teachers["][name$="[can_customize]"]', 800); } await t.scroll(250); await t.point('button:has-text("Lưu")', 800); } },
            { say: 'Trang chi tiết lớp: giáo viên và quyền, danh sách học viên với lần đăng nhập gần nhất, và Nhật ký tài khoản của lớp ghi lại mọi thao tác cấp, khoá, đổi mật khẩu.',
                do: async (t) => { await t.goto(`/admin/classrooms/${cls.id}`); await t.point('text=Giáo viên', 800); await t.scroll(350, { ms: 1000 }); await t.point('text=Nhật ký tài khoản của lớp', 1200); } },
            { say: 'Khối Lịch mở bài học cho phép đặt ngày mở từng bài với lớp này, hoặc ẩn bài không dạy. Học viên chỉ thấy bài đã mở, phù hợp dạy theo tuần.',
                do: async (t) => { await t.scrollTop(); const c = t.page.locator('text=Lịch mở bài học').first(); if (await c.count()) { await t.point(c, 1000); await t.point('button:has-text("Lưu lịch")', 900); } } },
            { say: 'Thêm học viên vào lớp theo hai cách: tìm học viên đã có tài khoản rồi bấm Thêm vào lớp, hoặc tạo tài khoản hàng loạt từ file Excel theo mẫu và thêm luôn vào lớp.',
                do: async (t) => { const s = t.page.locator('text=Thêm học viên đã có tài khoản').first(); if (await s.count()) { await t.point(s, 900); await t.type('#ss-q', 'Trần', { delay: 70 }); await t.point('#ss-add', 800); } const i = t.page.locator('text=Tạo tài khoản hàng loạt & thêm vào lớp').first(); if (await i.count()) { await t.point(i, 900); await t.point('a:has-text("Tải file mẫu")', 800); } } },
            { say: 'Mục Tài khoản học viên quản lý mọi tài khoản học trực tuyến: tìm theo tên, email; lọc theo lớp hoặc theo trạng thái chưa có mật khẩu, bị khoá.',
                do: async (t) => { await t.nav('/admin/student-accounts'); await t.point('input[name=q]', 800); await t.point('select[name=scope]', 800); await t.point('select[name=classroom_id]', 800); } },
            { say: 'Tạo một tài khoản: tên, email, điện thoại, ngày sinh, và lớp nếu có. Hệ thống sinh mật khẩu tạm, hiện ngay trên màn hình để gửi cho học viên; học viên phải đổi mật khẩu ở lần đăng nhập đầu.',
                do: async (t) => { await t.point('text=Tạo một tài khoản', 900); await t.type('input[name=name]', 'Nguyễn Hoàng Long', { delay: 40 }); await t.type('input[name=email]', 'hoanglong@example.com', { delay: 30 }); await t.point('button:has-text("Tạo")', 900); } },
            { say: 'Tạo hàng loạt: dán danh sách mỗi dòng một học viên, hoặc tải file Excel theo mẫu, bấm Xem trước để kiểm tra dòng lỗi, trùng email, rồi mới lưu. Có thể in hoặc tải bảng tài khoản và mật khẩu tạm.',
                do: async (t) => { await t.point('text=Tạo tài khoản hàng loạt', 900); await t.point('textarea[name=rows]', 900); await t.point('input[name=file]', 700); await t.point('button:has-text("Xem trước")', 900); } },
            { say: 'Bấm Quản lý ở một tài khoản: sửa thông tin, xem các lớp đang học, thêm vào lớp khác, cấp lại mật khẩu, khoá hoặc mở khoá đăng nhập, và xem nhật ký thao tác.',
                do: async (t) => { const m = t.page.locator('a:has-text("Quản lý")').first(); if (await m.count()) { await t.click(m, { wait: 900 }); await t.point('text=Đăng nhập', 900); await t.scroll(350, { ms: 1000 }); await t.point('text=Nhật ký tài khoản', 900); } } },
            { caption: false, say: 'Đó là toàn bộ quy trình mở lớp và quản lý tài khoản. Video tiếp theo: tài khoản quản trị và phân quyền nhân viên.',
                do: async (t) => { await t.card('Cảm ơn bạn đã theo dõi', 'Lớp học · Tài khoản học viên · Giáo viên', 'Tiếp theo: Người dùng & phân quyền'); } },
        ],
    };
};
