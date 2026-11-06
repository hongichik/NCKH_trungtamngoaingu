#!/usr/bin/env bash
# Đẩy code V2 lên hosting qua FTP (chỉ code: không đụng .env, storage/, public/storage).
# Chạy từ thư mục gốc dự án:  bash scripts/deploy-v2.sh
# Mật khẩu FTP đọc từ dòng "# mk ..." trong mục # FTP của .env (không in ra màn hình).
set -u
cd "$(dirname "$0")/.."
PASS=$(sed -n 's/^# mk //p' .env | head -1)
[ -z "$PASS" ] && { echo "Không thấy mật khẩu FTP trong .env"; exit 1; }
ok=0; fail=0
while read -r f; do
  [ -f "$f" ] || continue
  if curl -s --ftp-create-dirs -T "$f" "ftp://cnttuhl.site/$f" --user "hongichik@cnttuhl.site:$PASS" --max-time 90; then
    ok=$((ok+1))
  else
    fail=$((fail+1)); echo "LỖI: $f"
  fi
done < docs/deploy-v2-files.txt
echo "Đã tải $ok file, lỗi $fail."
echo "Tiếp theo: Admin › Quản trị hệ thống › Lệnh hệ thống → 'Chạy migration' rồi 'Xoá toàn bộ cache'."
