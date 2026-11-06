<?php

namespace App\Support\Ai;

use App\Models\AiUsage;

/**
 * Hạn mức AI (cấu hình ở config/ai.php > limits, 0 = không giới hạn):
 *  - số lượt / người / ngày
 *  - số lượt / tháng toàn hệ thống
 *  - ngân sách USD / tháng toàn hệ thống
 */
class AiLimiter
{
    public function check(?string $actorType, ?int $actorId): void
    {
        $limits = config('ai.limits');

        if ($actorType && $actorId && ($max = (int) $limits['per_actor_daily']) > 0) {
            $used = AiUsage::where('actor_type', $actorType)->where('actor_id', $actorId)
                ->where('created_at', '>=', now()->startOfDay())->count();
            if ($used >= $max) {
                throw new AiLimitException("Bạn đã dùng hết {$max} lượt AI hôm nay. Vui lòng thử lại vào ngày mai.");
            }
        }

        $month = AiUsage::where('created_at', '>=', now()->startOfMonth());

        if (($max = (int) $limits['monthly_calls']) > 0 && (clone $month)->count() >= $max) {
            throw new AiLimitException("Hệ thống đã dùng hết {$max} lượt AI của tháng này. Liên hệ quản trị viên để tăng hạn mức.");
        }

        if (($budget = (float) $limits['monthly_budget_usd']) > 0 && (float) (clone $month)->sum('cost_usd') >= $budget) {
            throw new AiLimitException('Đã chạm ngân sách AI của tháng này ($'.number_format($budget, 2).'). Liên hệ quản trị viên để tăng ngân sách.');
        }
    }

    /**
     * Số liệu cho trang quản trị.
     */
    public function stats(): array
    {
        $month = AiUsage::where('created_at', '>=', now()->startOfMonth());

        return [
            'today_calls' => AiUsage::where('created_at', '>=', now()->startOfDay())->count(),
            'month_calls' => (clone $month)->count(),
            'month_failed' => (clone $month)->where('success', false)->count(),
            'month_cost' => (float) (clone $month)->sum('cost_usd'),
            'by_feature' => (clone $month)->selectRaw('feature, COUNT(*) calls, SUM(cost_usd) cost, SUM(input_tokens) input_tokens, SUM(output_tokens) output_tokens')
                ->groupBy('feature')->orderByDesc('calls')->get(),
            'recent_errors' => AiUsage::where('success', false)->latest('id')->limit(5)->get(['feature', 'model', 'error', 'created_at']),
        ];
    }
}
