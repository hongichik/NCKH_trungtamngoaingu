<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AccountLog;
use App\Models\Classroom;
use App\Models\Student;
use App\Support\Students\StudentImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Luồng nhập học viên dùng chung cho trang quản trị và cổng giáo viên:
 * dán / tải file → xem trước (trạng thái từng dòng) → lưu → danh sách tài khoản + mật khẩu (tải CSV).
 * Lớp dùng trait khai báo: importLayout(), importRoute($name, $classroom).
 */
trait ImportsStudents
{
    abstract protected function importLayout(): string;

    abstract protected function importRoute(string $name, Classroom $classroom): string;

    protected function importPreview(Request $request, Classroom $classroom): View
    {
        $request->validate([
            'rows' => ['nullable', 'string', 'max:300000'],
            'file' => ['nullable', 'file', 'max:5120', 'mimes:csv,txt,xlsx'],
        ]);
        $import = app(StudentImport::class);
        $rows = $import->parse($request->input('rows'), $request->file('file'));
        $preview = $import->preview($rows, $classroom);

        return view('student-import.preview', [
            'layout' => $this->importLayout(), 'classroom' => $classroom, 'preview' => $preview,
            'counts' => StudentImport::counts($preview), 'rowsJson' => json_encode($rows, JSON_UNESCAPED_UNICODE),
            'commitUrl' => $this->importRoute('commit', $classroom), 'backUrl' => $this->importRoute('back', $classroom),
        ]);
    }

    protected function importCommit(Request $request, Classroom $classroom): View
    {
        $data = $request->validate([
            'rows' => ['required', 'string', 'max:500000'],
            'password_mode' => ['required', 'in:random,phone'],
        ]);
        $rows = array_slice(array_values(array_filter((array) json_decode($data['rows'], true), 'is_array')), 0, StudentImport::MAX_ROWS);
        $rows = array_map(fn ($r) => [
            'name' => trim((string) ($r['name'] ?? '')), 'email' => mb_strtolower(trim((string) ($r['email'] ?? ''))),
            'phone' => trim((string) ($r['phone'] ?? '')), 'dob' => trim((string) ($r['dob'] ?? '')), 'line' => (int) ($r['line'] ?? 0),
        ], $rows);
        $result = app(StudentImport::class)->commit($rows, $classroom, $data['password_mode']);
        session()->put('import_credentials.'.$classroom->id, $result['credentials']);

        return view('student-import.result', [
            'layout' => $this->importLayout(), 'classroom' => $classroom, 'result' => $result,
            'downloadUrl' => $this->importRoute('credentials', $classroom), 'backUrl' => $this->importRoute('back', $classroom),
        ]);
    }

    /** CSV tài khoản + mật khẩu vừa tạo (chỉ trong phiên làm việc vừa nhập). */
    protected function importCredentials(Classroom $classroom): Response
    {
        $rows = session('import_credentials.'.$classroom->id, []);
        abort_if(! $rows, 404, 'Không còn danh sách mật khẩu (đã hết phiên). Hãy cấp lại mật khẩu cho học viên cần thiết.');

        return response(StudentImport::csv(['Họ tên', 'Email', 'Số điện thoại', 'Mật khẩu'], $rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tai-khoan-'.$classroom->code.'.csv"',
        ]);
    }

    protected function importTemplate(): Response
    {
        return response(StudentImport::csv(StudentImport::TEMPLATE_HEADER, [['Nguyễn Văn An', 'an@example.com', '0912345678', '15/08/2008']]), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="mau-danh-sach-hoc-vien.csv"',
        ]);
    }

    protected function resetStudentPassword(Classroom $classroom, Student $student)
    {
        abort_unless($classroom->students()->whereKey($student->id)->exists(), 404);
        $password = app(StudentImport::class)->resetPassword($student, $classroom);

        return back()->with('status', "Mật khẩu mới của {$student->full_name} ({$student->email}): {$password} — học viên sẽ phải đổi mật khẩu khi đăng nhập.");
    }

    /** Tìm học viên đã có (JSON) để thêm vào lớp. */
    protected function searchForClass(Request $request, Classroom $classroom): JsonResponse
    {
        $inClass = $classroom->students()->pluck('students.id')->all();
        $rows = StudentImport::search((string) $request->query('q'))->map(fn (Student $s) => [
            'id' => $s->id, 'name' => $s->full_name ?: '(chưa có tên)', 'email' => $s->email, 'phone' => $s->phone,
            'dob' => $s->dob?->format('d/m/Y'), 'has_login' => (bool) $s->password, 'can_add' => filled($s->email),
            'in_class' => in_array($s->id, $inClass, true),
        ]);

        return response()->json(['ok' => true, 'results' => $rows]);
    }

    /** Thêm học viên đã có vào lớp; hồ sơ chưa có mật khẩu được cấp mật khẩu (hiện một lần). */
    protected function addExistingToClass(Request $request, Classroom $classroom): RedirectResponse
    {
        $ids = array_map('intval', (array) $request->validate(['student_ids' => ['required', 'array', 'max:200'], 'student_ids.*' => ['integer']])['student_ids']);
        $import = app(StudentImport::class);
        $added = 0;
        $issued = [];
        $skipped = [];
        foreach (Student::whereIn('id', $ids)->get() as $s) {
            if (blank($s->email)) {
                $skipped[] = $s->full_name;

                continue;
            }
            if ($pw = $import->ensureLogin($s, $classroom)) {
                $issued[] = "{$s->full_name} ({$s->email}): {$pw}";
            }
            if (! $classroom->students()->whereKey($s->id)->exists()) {
                $classroom->students()->attach($s->id);
                AccountLog::record('add_to_class', $s->id, $classroom->id);
                $added++;
            }
        }
        $msg = "Đã thêm {$added} học viên vào lớp.";
        if ($issued) {
            $msg .= ' Cấp mật khẩu cho hồ sơ chưa có tài khoản — '.implode('; ', $issued).' (bắt đổi khi đăng nhập).';
        }
        if ($skipped) {
            $msg .= ' Bỏ qua (chưa có email): '.implode(', ', $skipped).'.';
        }

        return back()->with('status', $msg);
    }
}
