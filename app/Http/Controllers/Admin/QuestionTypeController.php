<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionTypeSetting;
use App\Support\QuestionTypes\Engines;
use App\Support\QuestionTypes\QuestionType;
use App\Support\QuestionTypes\Registry;
use App\Support\QuestionTypes\Scoring;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Thư viện dạng bài (Quản trị hệ thống › Thư viện dạng bài) — chỉ chủ hệ thống (users.id = 1).
 * Xem từng mẫu, làm thử, xem đáp án / JSON / lược đồ / cách chấm, bật / tắt, chỉnh cách tính điểm.
 */
class QuestionTypeController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['lang', 'skill', 'standard', 'engine', 'enabled', 'q']);
        $types = Registry::filter($filters)->groupBy(fn (QuestionType $t) => $t->standard);
        $all = collect(Registry::all());

        return view('admin.question-types.index', [
            'groups' => collect(QuestionType::STANDARDS)->keys()->filter(fn ($s) => $types->has($s))->mapWithKeys(fn ($s) => [$s => $types[$s]]),
            'filters' => $filters,
            'total' => $all->count(),
            'shown' => $types->flatten()->count(),
            'disabled' => $all->filter(fn ($t) => ! Registry::enabled($t->code))->count(),
            'engines' => Engines::all(),
            'tabs' => SystemController::TABS,
        ]);
    }

    public function show(Request $request, string $code): View
    {
        $type = $this->type($code);
        $content = $type->sample;
        $errors = [];

        // Thử với JSON tự soạn (giống dữ liệu AI / giáo viên sẽ đưa vào).
        if ($request->isMethod('post')) {
            $decoded = json_decode((string) $request->input('content'), true);
            if (! is_array($decoded)) {
                $errors = ['JSON không hợp lệ: '.json_last_error_msg()];
            } elseif ($errors = $type->engine()->validate($decoded, $type)) {
                // giữ mẫu cũ để trang vẫn hiển thị, báo lỗi ở ô JSON
            } else {
                $content = $decoded;
            }
        }

        return view('admin.question-types.show', [
            'type' => $type,
            'engine' => $type->engine(),
            'content' => $content,
            'json' => $request->isMethod('post') ? (string) $request->input('content') : json_encode($type->sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'jsonErrors' => $errors,
            'custom' => $content !== $type->sample,
            'schema' => json_encode($type->engine()->schema($type), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'perfect' => $type->engine()->perfectAnswer($content, $type),
            'scoring' => Registry::scoring($type),
            'override' => QuestionTypeSetting::find($code)?->settings ?? [],
            'enabled' => Registry::enabled($code),
            'tabs' => SystemController::TABS,
        ]);
    }

    public function grade(Request $request, string $code): JsonResponse
    {
        $type = $this->type($code);
        $content = $type->sample;
        if ($request->filled('content')) {
            $decoded = json_decode((string) $request->input('content'), true);
            if (! is_array($decoded) || $type->engine()->validate($decoded, $type)) {
                return response()->json(['ok' => false, 'message' => 'Nội dung JSON không hợp lệ.'], 422);
            }
            $content = $decoded;
        }
        $answer = $request->input('answer', []);

        return response()->json(['ok' => true] + Registry::grade($type, $content, is_array($answer) ? $answer : []));
    }

    public function toggle(Request $request, string $code): RedirectResponse
    {
        $type = $this->type($code);
        $on = ! Registry::enabled($code);
        Registry::setEnabled($code, $on);

        return back()->with('status', ($on ? 'Đã bật' : 'Đã tắt').' dạng bài "'.$type->name.'".');
    }

    public function scoring(Request $request, string $code): RedirectResponse
    {
        $type = $this->type($code);
        if ($request->boolean('reset')) {
            QuestionTypeSetting::updateOrCreate(['code' => $code], ['settings' => null]);
        } else {
            $data = $request->validate([
                'points' => ['required', 'numeric', 'min:0.05', 'max:100'],
                'partial' => ['nullable', 'boolean'],
                'penalty' => ['required', 'numeric', 'min:0', 'max:100'],
                'scale' => ['required', Rule::in(array_keys(Scoring::SCALES))],
            ]);
            $data['partial'] = $request->boolean('partial');
            $data['points'] = (float) $data['points'];
            $data['penalty'] = (float) $data['penalty'];
            QuestionTypeSetting::updateOrCreate(['code' => $code], ['settings' => $data]);
        }
        Registry::flush();

        return back()->with('status', 'Đã lưu cách tính điểm cho "'.$type->name.'".');
    }

    private function type(string $code): QuestionType
    {
        return Registry::find($code) ?? abort(404, 'Không có dạng bài này.');
    }
}
