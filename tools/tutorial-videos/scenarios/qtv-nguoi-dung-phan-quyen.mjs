// Video quản trị 7: Người dùng & phân quyền
export default (d) => ({
    slug: 'qtv-nguoi-dung-phan-quyen',
    title: 'Quản trị viên — Người dùng & phân quyền',
    scenes: [
        { caption: false, say: 'Xin chào! Video này hướng dẫn tạo tài khoản cho nhân viên và cấp quyền chi tiết theo từng chức năng, để mỗi người chỉ thao tác đúng phần việc của mình.',
            do: async (t) => { await t.card('Quản trị viên', 'Người dùng & phân quyền', 'Nhân viên · Quản trị viên · Quyền theo từng mục'); await t.goto('/admin/login'); } },
        { say: 'Hệ thống có ba cấp: chủ hệ thống là tài khoản đầu tiên, có toàn quyền kể cả cài đặt máy chủ; quản trị viên có mọi quyền nghiệp vụ; nhân viên chỉ có những quyền được cấp.',
            do: async (t) => { await t.hideCard(); await t.login('/admin/login', 'demo.admin' + d.domain, d.password); await t.nav('/admin/users'); } },
        { say: 'Mục Người dùng và phân quyền liệt kê tài khoản quản trị, vai trò và các quyền đã cấp. Bấm Sửa để thay đổi, hoặc Thêm tài khoản để tạo mới.',
            do: async (t) => { await t.point('a:has-text("Thêm tài khoản")', 900); await t.scroll(250, { ms: 900 }); await t.point('a:has-text("Sửa")', 800); } },
        { say: 'Tạo tài khoản: nhập họ tên, email đăng nhập và mật khẩu. Công tắc Quản trị viên cấp toàn quyền; chỉ chủ hệ thống mới bật được công tắc này.',
            do: async (t) => { await t.click('a:has-text("Thêm tài khoản")', { wait: 800 }); await t.type('input[name=name]', 'Lê Thị Mai', { delay: 45 }); await t.type('input[name=email]', 'mai.le@example.com', { delay: 30 }); await t.point('input[name=password]', 700); const adm = t.page.locator('input[name=is_admin][type=checkbox]'); if (await adm.count()) await t.point(adm.first(), 1000); else await t.point('text=Quản trị viên', 1000); } },
        { say: 'Khối Phân quyền theo từng mục: mỗi chức năng như Bài viết, Liên hệ, Đăng ký dự thi, Chứng chỉ, Khoá học và lớp học có các quyền xem, thêm, sửa, xoá, phản hồi riêng. Đánh dấu đúng những gì nhân viên cần.',
            do: async (t) => { await t.point('text=Phân quyền theo từng mục', 1000); await t.scroll(300, { ms: 1000 }); const boxes = t.page.locator('input[name="permissions[]"]'); const n = await boxes.count(); for (let i = 0; i < Math.min(4, n); i++) await t.click(boxes.nth(i), { wait: 200 }); } },
        { say: 'Nhân viên chỉ thấy menu và số liệu thuộc quyền của mình; vào đường dẫn ngoài quyền sẽ nhận thông báo Không có quyền truy cập. Bấm Lưu để tạo tài khoản và gửi thông tin đăng nhập cho nhân viên.',
            do: async (t) => { await t.scroll(400, { ms: 900 }); await t.point('button:has-text("Lưu")', 1200); } },
        { say: 'Lưu ý bảo mật: đăng nhập sai nhiều lần sẽ bị tạm khoá, mật khẩu học viên do hệ thống cấp phải đổi ở lần đầu, và mọi thao tác với tài khoản học viên đều có nhật ký. Hãy thu hồi quyền ngay khi nhân viên nghỉ việc.',
            do: async (t) => { await t.goto('/admin/users'); await t.scroll(200); } },
        { caption: false, say: 'Đó là cách tổ chức tài khoản và phân quyền. Chúc bạn vận hành hệ thống an toàn và hiệu quả!',
            do: async (t) => { await t.card('Cảm ơn bạn đã theo dõi', 'Người dùng & phân quyền', 'Hết bộ video dành cho Quản trị viên'); } },
    ],
});
