<?php

namespace App\Support\QuestionTypes;

use App\Models\QuestionTypeSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Sổ đăng ký dạng bài: gom định nghĩa từ Catalog/*, kèm trạng thái bật / tắt lưu CSDL.
 * Mọi nguồn bài tập (sinh từ giáo trình, giáo viên soạn, AI đọc file) đều tra dạng bài ở đây.
 */
class Registry
{
    public const CATALOGS = [
        Catalog\General::class,
        Catalog\Ielts::class,
        Catalog\Toeic::class,
        Catalog\CambridgeVstep::class,
        Catalog\Chinese::class,
        Catalog\Legacy::class,
    ];

    /** @var array<string, QuestionType>|null */
    private static ?array $types = null;

    private static ?array $settings = null;

    /** @return array<string, QuestionType> */
    public static function all(): array
    {
        if (self::$types === null) {
            self::$types = [];
            foreach (self::CATALOGS as $catalog) {
                foreach ($catalog::types() as $d) {
                    $t = QuestionType::make($d);
                    if (isset(self::$types[$t->code])) {
                        throw new InvalidArgumentException("Trùng mã dạng bài: {$t->code}");
                    }
                    self::$types[$t->code] = $t;
                }
            }
        }

        return self::$types;
    }

    public static function find(string $code): ?QuestionType
    {
        return self::all()[$code] ?? null;
    }

    public static function get(string $code): QuestionType
    {
        return self::find($code) ?? throw new InvalidArgumentException("Không có dạng bài: {$code}");
    }

    /** Dạng bài tương ứng với loại câu hỏi V1 (bảng questions.type). */
    public static function forLegacy(string $v1Type): ?QuestionType
    {
        return collect(self::all())->first(fn (QuestionType $t) => $t->legacy === $v1Type);
    }

    public static function settings(): array
    {
        if (self::$settings === null) {
            self::$settings = Schema::hasTable('question_types')
                ? QuestionTypeSetting::all()->keyBy('code')->map(fn ($s) => ['enabled' => $s->enabled, 'settings' => $s->settings ?? []])->all()
                : [];
        }

        return self::$settings;
    }

    public static function enabled(string $code): bool
    {
        return self::settings()[$code]['enabled'] ?? true;
    }

    public static function setEnabled(string $code, bool $enabled): void
    {
        self::get($code);
        QuestionTypeSetting::updateOrCreate(['code' => $code], ['enabled' => $enabled]);
        self::$settings = null;
    }

    /** Cấu hình tính điểm hiệu lực = mặc định + của dạng bài + chủ hệ thống ghi đè + bài cụ thể ghi đè. */
    public static function scoring(QuestionType $type, array $override = []): array
    {
        return Scoring::config($type, array_merge(self::settings()[$type->code]['settings'] ?? [], $override));
    }

    /** Lọc danh sách: ['lang' => 'en', 'skill' => 'reading', 'standard' => 'ielts', 'q' => 'tfng', 'enabled' => '1'|'0'] */
    public static function filter(array $f): Collection
    {
        return collect(self::all())->filter(function (QuestionType $t) use ($f) {
            if (! empty($f['lang']) && $t->lang !== $f['lang'] && ! ($t->lang === 'any' && $f['lang'] !== 'any')) {
                return false;
            }
            foreach (['skill', 'standard', 'engine'] as $k) {
                if (! empty($f[$k]) && $t->{$k} !== $f[$k]) {
                    return false;
                }
            }
            if (isset($f['enabled']) && $f['enabled'] !== '' && self::enabled($t->code) !== (bool) $f['enabled']) {
                return false;
            }
            if (! empty($f['q'])) {
                $q = mb_strtolower($f['q']);

                return str_contains(mb_strtolower($t->code.' '.$t->name.' '.$t->description), $q);
            }

            return true;
        });
    }

    /**
     * Chấm một bài theo dạng: kiểm tra → chấm → tính điểm.
     *
     * @return array{parts: list<array>, pending: bool, score: array}
     */
    public static function grade(QuestionType $type, array $content, array $answer, array $scoring = []): array
    {
        $graded = $type->engine()->grade($content, $answer, $type);
        $graded['score'] = Scoring::apply($graded, self::scoring($type, $scoring));

        return $graded;
    }

    public static function flush(): void
    {
        self::$types = null;
        self::$settings = null;
    }
}
