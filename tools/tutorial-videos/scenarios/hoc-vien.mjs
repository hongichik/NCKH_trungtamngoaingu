// Video hướng dẫn: Học viên
export default (d) => {
    const en = d.courses.en, cls = d.classrooms.en, lesson = en.lessons[1], ex = lesson.exercises[0];
    return {
        slug: 'hoc-vien',
        title: 'Hướng dẫn dành cho Học viên',
        scenes: [
            { caption: false, say: 'Xin chào các bạn học viên! Video này hướng dẫn cách vào lớp, học bài, làm bài tập, ôn từ vựng và theo dõi tiến độ học tập của mình.',
                do: async (t) => { await t.card('Hướng dẫn sử dụng', 'Học viên', 'Vào lớp · Học bài · Làm bài tập · Ôn từ vựng · Xem tiến độ'); await t.goto('/dang-ky-chung-chi'); } },
            { say: 'Mở trang đăng nhập, chọn thẻ Đã có tài khoản, nhập email và mật khẩu trung tâm cấp. Ở lần đăng nhập đầu tiên, hệ thống sẽ yêu cầu bạn đổi mật khẩu.',
                do: async (t) => { await t.hideCard(); await t.click('#login-tab', { wait: 400 }); await t.type('#login-pane input[name=email]', 'demo.hocvien' + d.domain, { delay: 35 }); await t.type('#login-pane input[name=password]', d.password, { delay: 45 }); await t.click('#login-pane button:has-text("Đăng nhập")', { wait: 900 }); } },
            { say: 'Trang chủ hiện chuỗi ngày học liên tiếp, điểm kinh nghiệm, các lớp bạn đang học, và danh sách Bài cần làm. Bấm Làm bài ở bất kỳ dòng nào để vào thẳng bài học đó.',
                do: async (t) => { await t.point('text=ngày liên tiếp', 1000); await t.point('main >> text=Lớp học của tôi', 1000); await t.point('text=Bài cần làm', 1500); } },
            { say: 'Vào mục Lớp học của tôi, chọn lớp. Lớp gồm danh sách bài học theo thứ tự, kèm bảng xếp hạng điểm kinh nghiệm bảy ngày gần nhất.',
                do: async (t) => { await t.nav('/thi-sinh/lop-hoc'); await t.click(`text=${cls.name}`); await t.scroll(400, { ms: 1400 }); await t.scrollTop(); } },
            { say: 'Mở một bài học. Bạn xem video hướng dẫn, đọc ghi chú từ vựng, ngữ pháp và hội thoại mẫu, tải tài liệu, rồi làm bài tập cuối bài.',
                do: async (t) => { await t.goto(`/thi-sinh/lop-hoc/${cls.id}/bai-hoc/${lesson.id}`); await t.scroll(450, { ms: 1800 }); } },
            { say: 'Ở cuối bài có danh sách từ vựng của bài, kèm nút Học thẻ từ để ôn ngay những từ vừa học.',
                do: async (t) => { await t.point('text=Từ vựng của bài', 1500); } },
            { say: 'Bấm Làm bài để bắt đầu. Bài tập gồm nhiều phần: đọc hiểu, nghe, nói, viết, từ vựng và ngữ pháp.',
                do: async (t) => { const b = t.page.locator('button:has-text("Làm bài"), button:has-text("Làm tiếp")').first(); await t.click(b, { wait: 1200 }); } },
            { say: 'Với câu trắc nghiệm, bấm chọn đáp án. Với câu điền từ, gõ câu trả lời vào ô trống. Bài làm được lưu tự động, lỡ thoát ra vẫn làm tiếp được.',
                do: async (t) => {
                    const radios = t.page.locator('.qt input[type=radio]');
                    for (let i = 0; i < Math.min(3, await radios.count()); i += 1) { const r = radios.nth(i * 2); if (await r.isVisible().catch(() => false)) await t.click(r, { wait: 250 }); }
                    const gap = t.page.locator('.qt input[type=text]:visible, .qt input.qt-input:visible').first();
                    if (await gap.count()) await t.type(gap, 'gets up', { delay: 80 });
                } },
            { say: 'Ở phần nghe, bấm nút phát để nghe bài. Giọng đọc được tạo bằng AI, rõ ràng và tự nhiên. Bạn có thể nghe lại nhiều lần.',
                do: async (t) => { const a = t.page.locator('audio').first(); if (await a.count()) { await t.point(a, 600); await a.evaluate((x) => { x.volume = 0.0; x.play(); }).catch(() => {}); } await t.scroll(400, { ms: 1500 }); } },
            { say: 'Phần nói, bạn bấm ghi âm và trả lời bằng giọng của mình. Phần viết, bạn gõ đoạn văn theo yêu cầu. AI sẽ chấm và nhận xét chi tiết cho hai phần này.',
                do: async (t) => { await t.scroll(700, { ms: 2200 }); } },
            { say: 'Làm xong, bấm Nộp bài. Kết quả hiện ngay với điểm từng phần, đáp án đúng và lời giải thích.',
                do: async (t) => { await t.point('button:has-text("Nộp bài")', 1500); } },
            { say: 'Mục Ôn từ vựng có thẻ ghi nhớ theo từng bài học. Bạn xem từ, nhớ nghĩa, lật thẻ, rồi tự đánh giá Quên, Khó, Nhớ hay Dễ. Hệ thống nhắc ôn lại đúng lúc bạn sắp quên. Bài tiếng Trung có thêm luyện thanh điệu và tập viết chữ Hán.',
                do: async (t) => {
                    await t.nav('/thi-sinh/on-tu-vung'); await t.point('text=Học thẻ', 900);
                    await t.click(t.page.locator('a:has-text("Học thẻ")').first(), { wait: 900 });
                    const card = t.page.locator('#fc-word').first();
                    if (await card.count()) await t.click(card, { wait: 900 }); else await t.page.keyboard.press('Space');
                    await t.point('button:has-text("Nhớ")', 900);
                } },
            { say: 'Mục Sổ lỗi sai gom lại những câu bạn từng làm sai, để bạn luyện lại cho đến khi nắm chắc.',
                do: async (t) => { await t.nav('/thi-sinh/so-loi-sai'); await t.scroll(250); } },
            { say: 'Mục Tiến độ và huy hiệu cho thấy điểm kinh nghiệm theo ngày, mục tiêu hằng ngày, huy hiệu đạt được, và bản đồ năng lực: điểm từng kỹ năng đọc, nghe, nói, viết, từ vựng theo từng bài học. Ô màu đỏ hoặc vàng là kỹ năng cần luyện thêm.',
                do: async (t) => { await t.nav('/thi-sinh/tien-do'); await t.scroll(350, { ms: 1200 }); await t.point('text=Bản đồ năng lực', 1600); } },
            { caption: false, say: 'Chúc các bạn học tập chăm chỉ và tiến bộ mỗi ngày!',
                do: async (t) => { await t.card('Chúc bạn học tốt!', 'Học viên', 'Mỗi ngày một bài — tiến bộ đều đặn'); } },
        ],
    };
};
