# Video hướng dẫn tự động

Tự quay 12 video hướng dẫn bằng Playwright, thuyết minh bằng giọng nam của OpenAI TTS, ghép thành MP4 bằng ffmpeg:

- `quan-tri-vien` — tổng quan cho quản trị viên
- `qtv-noi-dung-website`, `qtv-giao-dien-website`, `qtv-hoc-vien-chung-chi`, `qtv-ky-thi`, `qtv-khoa-hoc-bai-hoc`, `qtv-lop-hoc-tai-khoan`, `qtv-nguoi-dung-phan-quyen` — từng nhóm chức năng quản trị
- `nhan-vien` — tài khoản quản trị bị giới hạn quyền (cần tài khoản `demo.nhanvien@tutorial.local`, xem bên dưới)
- `giao-vien`, `hoc-vien`, `thi-sinh`

## Chạy

```bash
# 1. Dữ liệu demo: 2 khoá học (Tiếng Anh giao tiếp A2, Tiếng Trung HSK 1) có bài học + bài tập do AI tạo, lớp, tài khoản @tutorial.local
php artisan tutorial:demo setup

# 2. Quay (máy chủ phải đang chạy: php artisan serve)
cd tools/tutorial-videos
npm install
node record.mjs                 # tất cả video
node record.mjs giao-vien       # một hoặc vài video theo slug ở trên
OUT_DIR=../../docs/huong-dan node record.mjs   # xuất thẳng vào docs/huong-dan (bộ video bàn giao)

# 3. Xong thì xoá dữ liệu demo (khoá học, lớp, tài khoản demo)
php artisan tutorial:demo cleanup
```

Video ra ở `storage/app/public/huong-dan/*.mp4` (xem tại `/storage/huong-dan/<tên>.mp4`); bộ bàn giao nằm ở `docs/huong-dan/`.

### Tài khoản nhân viên cho video `nhan-vien`

`tutorial:demo setup` không tạo tài khoản này (và `cleanup` xoá mọi email `@tutorial.local`), nên tạo riêng trước khi quay:

```bash
php artisan tinker --execute='
$pw = \App\Console\Commands\TutorialDemo::PASSWORD; $dom = \App\Console\Commands\TutorialDemo::DOMAIN;
$u = \App\Models\User::firstOrCreate(["email" => "demo.nhanvien".$dom], ["name" => "Phạm Thu Trang", "password" => $pw, "is_admin" => false]);
$u->permissions()->sync(\App\Models\Permission::whereIn("module", ["posts","categories","contacts","exam_registrations","certificates","students","galleries"])->whereNotIn("action", ["delete"])->pluck("id"));
\App\Models\Contact::firstOrCreate(["email" => "khach.demo".$dom], ["name" => "Nguyễn Minh Anh", "phone" => "0988123456", "subject" => "Hỏi lịch khai giảng", "content" => "Cho em hỏi lớp tiếng Anh giao tiếp A2 khi nào khai giảng và học phí bao nhiêu ạ?", "status" => "unread"]);
'
```

### Kiểm tra sau khi quay

Trong log, dòng `! cảnh N lỗi: locator.boundingBox: Timeout` nghĩa là một bộ chọn không tìm thấy phần tử và cảnh đó bị kéo dài 30 giây — sửa bộ chọn trong kịch bản rồi quay lại video đó.

**Đồng bộ hình – tiếng.** Script không dùng `recordVideo` của Playwright (bộ ghi đó nhân đôi khung hình khi trang chuyển động nhanh, video dài hơn thời gian thực 4–11 giây nên tiếng chạy trước hình). Thay vào đó nó nhận khung hình qua CDP `Page.startScreencast` kèm mốc thời gian thật, ghép bằng ffmpeg concat; lời đọc đặt theo cùng đồng hồ đó. Mốc từng cảnh được ghi vào `.cache/timeline/<slug>.json` — khi nghi ngờ, so sánh với khung hình (`ffmpeg -ss <at> -i video.mp4 -frames:v 1 x.jpg`): tại `at` của cảnh 2 banner phải vừa biến mất, tại `at` của cảnh cuối banner phải vừa hiện.

**Banner mở đầu.** Trong cảnh đầu gọi `t.card(...)` **trước** `t.goto(...)`: thẻ được gắn bằng init script nên giữ nguyên qua lần chuyển trang, giao diện web không "nháy" ra trước banner.

## Tuỳ chỉnh

- Lời thuyết minh và thao tác: `scenarios/<tên>.mjs` — mỗi cảnh gồm `say` (lời đọc, cũng là phụ đề) và `do` (thao tác: `t.nav`, `t.click`, `t.point`, `t.type`, `t.scroll`, `t.card`…). Cảnh kéo dài đúng bằng lời đọc.
- Giọng: `TTS_VOICE=ash node record.mjs` (mặc định `onyx` — nam, trầm). Model lấy từ `AI_MODEL_TTS` trong `.env`.
- Địa chỉ web: `BASE_URL=http://127.0.0.1:8000`.
- Lời đọc được lưu đệm ở `.cache/tts` theo nội dung — sửa câu nào thì chỉ câu đó gọi lại OpenAI.
