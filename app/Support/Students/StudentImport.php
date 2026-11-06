<?php

namespace App\Support\Students;

use App\Models\AccountLog;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * Nhập danh sách học viên (dán từ Excel, file .csv hoặc .xlsx) → xem trước → tạo tài khoản + thêm vào lớp.
 *
 * Trạng thái từng dòng khi xem trước:
 *  - new       tạo tài khoản mới
 *  - existing  đã có tài khoản (trùng email hoặc số điện thoại) → không tạo lại, chỉ thêm vào lớp
 *  - in_class  đã ở trong lớp → bỏ qua
 *  - error     thiếu họ tên / email, email sai định dạng, trùng dòng trong file
 */
class StudentImport
{
    public const STATUSES = [
        'new' => ['Tạo mới', '🟢'], 'existing' => ['Đã có tài khoản', '🔵'],
        'in_class' => ['Đã ở trong lớp', '⚪'], 'error' => ['Lỗi', '🔴'],
    ];

    public const MAX_ROWS = 1000;

    public const TEMPLATE_HEADER = ['Họ tên', 'Email', 'Số điện thoại', 'Ngày sinh (dd/mm/yyyy)'];

    // ---------------------------------------------------------------- đọc dữ liệu

    /** @return list<array{name: string, email: string, phone: string, dob: string, line: int}> */
    public function parse(?string $text, ?UploadedFile $file = null): array
    {
        if ($file) {
            $ext = strtolower($file->getClientOriginalExtension());
            $table = $ext === 'xlsx' ? $this->readXlsx($file->getRealPath()) : $this->readDelimited((string) file_get_contents($file->getRealPath()));
        } else {
            $table = $this->readDelimited((string) $text);
        }

        $rows = [];
        foreach ($table as $i => $cells) {
            $cells = array_map(fn ($c) => trim((string) $c), $cells);
            if (! array_filter($cells, 'strlen')) {
                continue;
            }
            // Bỏ dòng tiêu đề.
            if ($i === array_key_first($table) && preg_match('/(họ|tên|name|email)/iu', implode(' ', $cells)) && ! filter_var($cells[1] ?? '', FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $rows[] = ['name' => $cells[0] ?? '', 'email' => mb_strtolower($cells[1] ?? ''), 'phone' => $cells[2] ?? '', 'dob' => $cells[3] ?? '', 'line' => $i + 1];
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }

        return $rows;
    }

    /** Dán từ Excel (tab) hoặc CSV (dấu phẩy / chấm phẩy). */
    public function readDelimited(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', str_replace(["\r\n", "\r"], "\n", $text));
        $lines = explode("\n", $text);
        $sample = implode("\n", array_slice($lines, 0, 5));
        $delim = str_contains($sample, "\t") ? "\t" : (substr_count($sample, ';') > substr_count($sample, ',') ? ';' : ',');

        return array_map(fn ($l) => str_getcsv($l, $delim), $lines);
    }

    /** Đọc sheet đầu tiên của file .xlsx (không cần thư viện ngoài). */
    public function readXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = simplexml_load_string($xml);
            foreach ($sx->si as $si) {
                $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map('strval', iterator_to_array($si->xpath('.//*[local-name()="t"]'), false)));
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false) {
            return [];
        }
        $rows = [];
        foreach (simplexml_load_string($sheet)->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = $this->colIndex(preg_replace('/\d+/', '', (string) $c['r']));
                $v = (string) ($c->v ?? '');
                $type = (string) $c['t'];
                $cells[$col] = match ($type) {
                    's' => $shared[(int) $v] ?? '',
                    'inlineStr' => (string) ($c->is->t ?? ''),
                    default => $v,
                };
                // Ô ngày kiểu số của Excel ở cột ngày sinh.
                if ($col === 3 && $type === '' && is_numeric($v) && $v > 1000) {
                    $cells[$col] = Carbon::create(1899, 12, 30)->addDays((int) $v)->format('d/m/Y');
                }
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $rows[] = array_map(fn ($k) => $cells[$k] ?? '', range(0, $max));
            }
        }

        return $rows;
    }

    private function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    public static function normalizePhone(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($d, '84') && strlen($d) >= 11) {
            $d = '0'.substr($d, 2);
        }
        // Excel bỏ số 0 đầu của số di động: 912345678 → 0912345678.
        if (strlen($d) === 9 && str_contains('35789', $d[0])) {
            $d = '0'.$d;
        }

        return $d;
    }

    public static function parseDob(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        foreach (['d/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'Y-m-d', 'd.m.Y', 'j.n.Y'] as $f) {
            try {
                $d = Carbon::createFromFormat('!'.$f, $s);
            } catch (\Throwable) {
                continue;
            }
            if ($d && $d->format($f) === $s) {
                return $d->year > 1900 && $d->isPast() ? $d->toDateString() : null;
            }
        }

        return null;
    }

    // ---------------------------------------------------------------- xem trước

    /**
     * @return list<array{name, email, phone, dob, line, status: string, reason: ?string, student_id: ?int, student_name: ?string}>
     */
    public function preview(array $rows, ?Classroom $classroom = null): array
    {
        $emails = array_filter(array_column($rows, 'email'));
        $phones = array_filter(array_map(fn ($r) => self::normalizePhone($r['phone']), $rows));
        $byEmail = Student::whereIn(DB::raw('LOWER(email)'), $emails ?: ['-'])->get()->keyBy(fn ($s) => mb_strtolower($s->email));
        $byPhone = Student::whereNotNull('phone')->where('phone', '!=', '')->get(['id', 'phone', 'email', 'first_name', 'last_name'])
            ->filter(fn ($s) => in_array(self::normalizePhone($s->phone), $phones, true))->keyBy(fn ($s) => self::normalizePhone($s->phone));
        $inClass = $classroom ? $classroom->students()->pluck('students.id')->all() : [];

        $seenEmail = [];
        $seenPhone = [];
        $out = [];
        foreach ($rows as $r) {
            $phone = self::normalizePhone($r['phone']);
            $reason = match (true) {
                $r['name'] === '' => 'Thiếu họ tên',
                $r['email'] === '' => 'Thiếu email',
                ! filter_var($r['email'], FILTER_VALIDATE_EMAIL) => 'Email sai định dạng',
                isset($seenEmail[$r['email']]) => 'Trùng email với dòng '.$seenEmail[$r['email']],
                $phone !== '' && isset($seenPhone[$phone]) => 'Trùng số điện thoại với dòng '.$seenPhone[$phone],
                $phone !== '' && (strlen($phone) < 9 || strlen($phone) > 11) => 'Số điện thoại sai',
                $r['dob'] !== '' && ! self::parseDob($r['dob']) => 'Ngày sinh sai (dùng dd/mm/yyyy)',
                default => null,
            };
            if (! $reason) {
                $seenEmail[$r['email']] = $r['line'];
                if ($phone !== '') {
                    $seenPhone[$phone] = $r['line'];
                }
            }
            $existing = $reason ? null : ($byEmail[$r['email']] ?? ($phone !== '' ? ($byPhone[$phone] ?? null) : null));
            $status = $reason ? 'error' : ($existing ? (in_array($existing->id, $inClass, true) ? 'in_class' : 'existing') : 'new');
            $note = $existing && mb_strtolower((string) $existing->email) !== $r['email'] ? 'Trùng số điện thoại với tài khoản '.$existing->email : null;

            $out[] = $r + ['phone_normalized' => $phone, 'status' => $status, 'reason' => $reason ?? $note,
                'student_id' => $existing?->id, 'student_name' => $existing?->full_name];
        }

        return $out;
    }

    public static function counts(array $preview): array
    {
        $c = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach ($preview as $r) {
            $c[$r['status']]++;
        }

        return $c;
    }

    // ---------------------------------------------------------------- lưu

    /**
     * Tạo tài khoản mới + thêm vào lớp. Tính lại trạng thái ngay lúc lưu (không tin dữ liệu gửi lên).
     *
     * @param  string  $passwordMode  random | phone
     * @return array{created: int, added: int, skipped: int, errors: int, credentials: list<array{name, email, phone, password}>}
     */
    public function commit(array $rows, ?Classroom $classroom = null, string $passwordMode = 'random'): array
    {
        $preview = $this->preview($rows, $classroom);
        $result = ['created' => 0, 'added' => 0, 'skipped' => 0, 'errors' => 0, 'credentials' => []];

        DB::transaction(function () use ($preview, $classroom, $passwordMode, &$result) {
            foreach ($preview as $r) {
                if ($r['status'] === 'error') {
                    $result['errors']++;

                    continue;
                }
                if ($r['status'] === 'in_class' || ($r['status'] === 'existing' && ! $classroom)) {
                    $result['skipped']++;

                    continue;
                }
                $studentId = $r['student_id'];
                if ($r['status'] === 'new') {
                    $password = $passwordMode === 'phone' && strlen($r['phone_normalized']) >= 6 ? $r['phone_normalized'] : self::randomPassword();
                    $student = Student::forceCreate([
                        'last_name' => $r['name'], 'email' => $r['email'], 'phone' => $r['phone_normalized'] ?: null,
                        'dob' => self::parseDob($r['dob']), 'password' => $password, 'must_change_password' => true, 'status' => 'published',
                    ]);
                    $studentId = $student->id;
                    $result['created']++;
                    $result['credentials'][] = ['name' => $r['name'], 'email' => $r['email'], 'phone' => $r['phone_normalized'], 'password' => $password];
                    AccountLog::record('create', $studentId, $classroom?->id, ['source' => 'import']);
                }
                if ($classroom) {
                    $classroom->students()->syncWithoutDetaching([$studentId]);
                    AccountLog::record('add_to_class', $studentId, $classroom->id);
                    $result['added']++;
                }
            }
        });

        return $result;
    }

    /** Mật khẩu dễ đọc, không có ký tự dễ nhầm (0/O, 1/l). */
    public static function randomPassword(int $length = 8): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyz23456789';

        return collect(range(1, $length))->map(fn () => $chars[random_int(0, strlen($chars) - 1)])->implode('');
    }

    /** Cấp lại mật khẩu: mật khẩu mới ngẫu nhiên, bắt đổi khi đăng nhập. */
    public function resetPassword(Student $student, ?Classroom $classroom = null): string
    {
        $password = self::randomPassword();
        $student->forceFill(['password' => $password, 'must_change_password' => true])->save();
        AccountLog::record('reset_password', $student->id, $classroom?->id);

        return $password;
    }

    /**
     * Tìm học viên (mọi hồ sơ: tài khoản học trực tuyến và hồ sơ thí sinh) theo họ tên, email, số điện thoại, CCCD.
     *
     * @return Collection<int, Student>
     */
    public static function search(string $q, int $limit = 20)
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return collect();
        }
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
        $phone = self::normalizePhone($q);

        return Student::query()
            ->where(function ($w) use ($like, $phone) {
                $w->where('last_name', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('email', 'like', $like)
                    ->orWhere('identity_number', 'like', $like);
                if (strlen($phone) >= 4) {
                    $w->orWhere('phone', 'like', '%'.$phone.'%');
                }
            })
            ->orderByRaw('CASE WHEN password IS NULL THEN 1 ELSE 0 END')->orderBy('last_name')
            ->limit($limit)->get();
    }

    /** Hồ sơ có email nhưng chưa có mật khẩu → cấp mật khẩu (bắt đổi khi đăng nhập). Trả về mật khẩu mới hoặc null. */
    public function ensureLogin(Student $student, ?Classroom $classroom = null): ?string
    {
        if ($student->password) {
            return null;
        }
        $password = self::randomPassword();
        $student->forceFill(['password' => $password, 'must_change_password' => true])->save();
        AccountLog::record('create', $student->id, $classroom?->id, ['source' => 'existing_record']);

        return $password;
    }

    /** File CSV (UTF-8 có BOM để Excel đọc đúng tiếng Việt). */
    public static function csv(array $header, array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $header);
        foreach ($rows as $r) {
            fputcsv($fh, array_values($r));
        }
        rewind($fh);

        return stream_get_contents($fh);
    }
}
