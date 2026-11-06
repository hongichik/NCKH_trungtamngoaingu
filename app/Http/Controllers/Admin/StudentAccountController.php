<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountLog;
use App\Models\Classroom;
use App\Models\Student;
use App\Support\Students\StudentImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tài khoản học viên (thí sinh) dùng cổng học trực tuyến: tìm, tạo (từng người / hàng loạt), cấp lại mật khẩu,
 * khoá / mở đăng nhập, thêm / bớt lớp, nhật ký. Hồ sơ thí sinh chưa có tài khoản cũng tìm được để cấp tài khoản.
 */
class StudentAccountController extends Controller
{
    public const SCOPES = [
        'accounts' => 'Có tài khoản đăng nhập', 'no_class' => 'Chưa vào lớp nào', 'pending' => 'Chưa đổi mật khẩu lần đầu',
        'locked' => 'Đang bị khoá', 'records' => 'Hồ sơ thí sinh chưa có tài khoản',
    ];

    public function index(Request $request): View
    {
        $scope = array_key_exists($request->query('scope'), self::SCOPES) ? $request->query('scope') : 'accounts';
        $q = Student::query()->withCount('classrooms')->with('classrooms:id,name');
        match ($scope) {
            'records' => $q->whereNull('password'),
            'no_class' => $q->whereNotNull('password')->doesntHave('classrooms'),
            'pending' => $q->whereNotNull('password')->where('must_change_password', true),
            'locked' => $q->where('login_locked', true),
            default => $q->whereNotNull('password'),
        };
        if ($request->filled('classroom_id')) {
            $q->whereHas('classrooms', fn ($w) => $w->whereKey($request->integer('classroom_id')));
        }
        if ($request->filled('q')) {
            $ids = StudentImport::search((string) $request->q, 500)->pluck('id');
            $q->whereIn('id', $ids);
        }

        return view('admin.student-accounts.index', [
            'students' => $q->orderByDesc('id')->paginate(30)->withQueryString(),
            'scope' => $scope, 'scopes' => self::SCOPES,
            'classrooms' => Classroom::orderBy('name')->get(['id', 'name']),
            'counts' => [
                'accounts' => Student::whereNotNull('password')->count(),
                'locked' => Student::where('login_locked', true)->count(),
            ],
        ]);
    }

    public function show(Student $student): View
    {
        return view('admin.student-accounts.show', [
            'student' => $student->load('classrooms.course'),
            'classrooms' => Classroom::whereNotIn('id', $student->classrooms->pluck('id'))->orderBy('name')->get(['id', 'name']),
            'logs' => AccountLog::where('student_id', $student->id)->with('classroom:id,name')->latest('id')->limit(50)->get(),
        ]);
    }

    /** Tạo một tài khoản (hoặc cấp tài khoản cho hồ sơ thí sinh đã có email). */
    public function store(Request $request, StudentImport $import): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('students', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'dob' => ['nullable', 'date'],
            'classroom_id' => ['nullable', Rule::exists('classrooms', 'id')],
        ], ['email.unique' => 'Email đã có tài khoản / hồ sơ. Tìm học viên đó trong danh sách để cấp mật khẩu hoặc thêm vào lớp.']);
        $password = StudentImport::randomPassword();
        $s = Student::forceCreate(['last_name' => $data['name'], 'email' => mb_strtolower($data['email']), 'phone' => StudentImport::normalizePhone((string) ($data['phone'] ?? '')) ?: null,
            'dob' => $data['dob'] ?? null, 'password' => $password, 'must_change_password' => true, 'status' => 'published']);
        AccountLog::record('create', $s->id, $data['classroom_id'] ?? null, ['source' => 'admin']);
        if (! empty($data['classroom_id'])) {
            $s->classrooms()->attach($data['classroom_id']);
            AccountLog::record('add_to_class', $s->id, $data['classroom_id']);
        }

        return redirect()->route('admin.student-accounts.show', $s)->with('status', "Đã tạo tài khoản {$s->email} — mật khẩu: {$password} (bắt đổi khi đăng nhập lần đầu).");
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('students', 'email')->ignore($student->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'dob' => ['nullable', 'date'],
        ]);
        $student->forceFill(['last_name' => $data['name'], 'first_name' => $student->first_name && $student->last_name !== $data['name'] ? null : $student->first_name,
            'email' => $data['email'] ? mb_strtolower($data['email']) : null, 'phone' => $data['phone'] ?: null, 'dob' => $data['dob'] ?? null])->save();

        return back()->with('status', 'Đã lưu thông tin học viên.');
    }

    /** Cấp lại mật khẩu (hoặc cấp lần đầu cho hồ sơ thí sinh có email). */
    public function resetPassword(Student $student, StudentImport $import): RedirectResponse
    {
        abort_if(blank($student->email), 422, 'Học viên chưa có email — nhập email trước khi cấp mật khẩu.');
        $password = $import->resetPassword($student);

        return back()->with('status', "Mật khẩu mới của {$student->full_name} ({$student->email}): {$password} — học viên phải đổi mật khẩu khi đăng nhập.");
    }

    public function toggleLock(Student $student): RedirectResponse
    {
        $student->forceFill(['login_locked' => ! $student->login_locked])->save();
        AccountLog::record($student->login_locked ? 'lock' : 'unlock', $student->id);

        return back()->with('status', $student->login_locked ? 'Đã khoá đăng nhập của học viên.' : 'Đã mở khoá đăng nhập.');
    }

    public function addToClass(Request $request, Student $student, StudentImport $import): RedirectResponse
    {
        $classroom = Classroom::findOrFail($request->validate(['classroom_id' => ['required', 'integer']])['classroom_id']);
        abort_if(blank($student->email), 422, 'Học viên chưa có email nên chưa đăng nhập được — nhập email trước.');
        $pw = $import->ensureLogin($student, $classroom);
        $classroom->students()->syncWithoutDetaching([$student->id]);
        AccountLog::record('add_to_class', $student->id, $classroom->id);

        return back()->with('status', "Đã thêm vào lớp {$classroom->name}.".($pw ? " Đã cấp mật khẩu: {$pw} (bắt đổi khi đăng nhập)." : ''));
    }

    public function removeFromClass(Student $student, Classroom $classroom): RedirectResponse
    {
        $classroom->students()->detach($student->id);
        AccountLog::record('remove_from_class', $student->id, $classroom->id);

        return back()->with('status', "Đã xoá khỏi lớp {$classroom->name} (kết quả bài làm vẫn giữ).");
    }

    // ---------------------------------------------------------------- tạo hàng loạt (có hoặc không kèm lớp)

    public function import(Request $request, StudentImport $import): View
    {
        $request->validate(['rows' => ['nullable', 'string', 'max:300000'], 'file' => ['nullable', 'file', 'max:5120', 'mimes:csv,txt,xlsx'], 'classroom_id' => ['nullable', Rule::exists('classrooms', 'id')]]);
        $classroom = $request->filled('classroom_id') ? Classroom::find($request->integer('classroom_id')) : null;
        $rows = $import->parse($request->input('rows'), $request->file('file'));
        $preview = $import->preview($rows, $classroom);

        return view('student-import.preview', [
            'layout' => 'layouts.admin', 'classroom' => $classroom, 'preview' => $preview, 'counts' => StudentImport::counts($preview),
            'rowsJson' => json_encode($rows, JSON_UNESCAPED_UNICODE),
            'commitUrl' => route('admin.student-accounts.import.commit', ['classroom_id' => $classroom?->id]), 'backUrl' => route('admin.student-accounts.index'),
        ]);
    }

    public function importSave(Request $request, StudentImport $import): View
    {
        $data = $request->validate(['rows' => ['required', 'string', 'max:500000'], 'password_mode' => ['required', 'in:random,phone']]);
        $classroom = $request->filled('classroom_id') ? Classroom::find($request->integer('classroom_id')) : null;
        $rows = array_map(fn ($r) => ['name' => trim((string) ($r['name'] ?? '')), 'email' => mb_strtolower(trim((string) ($r['email'] ?? ''))),
            'phone' => trim((string) ($r['phone'] ?? '')), 'dob' => trim((string) ($r['dob'] ?? '')), 'line' => (int) ($r['line'] ?? 0)],
            array_slice(array_values(array_filter((array) json_decode($data['rows'], true), 'is_array')), 0, StudentImport::MAX_ROWS));
        $result = $import->commit($rows, $classroom, $data['password_mode']);
        session()->put('import_credentials.accounts', $result['credentials']);

        return view('student-import.result', ['layout' => 'layouts.admin', 'classroom' => $classroom, 'result' => $result,
            'downloadUrl' => route('admin.student-accounts.import.credentials'), 'backUrl' => route('admin.student-accounts.index')]);
    }

    public function credentials(): Response
    {
        $rows = session('import_credentials.accounts', []);
        abort_if(! $rows, 404, 'Không còn danh sách mật khẩu (đã hết phiên).');

        return response(StudentImport::csv(['Họ tên', 'Email', 'Số điện thoại', 'Mật khẩu'], $rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="tai-khoan-hoc-vien.csv"',
        ]);
    }
}
