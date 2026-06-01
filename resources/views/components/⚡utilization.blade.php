<?php

use App\Models\JiraProjectUser;
use App\Models\JiraWorklog;
use Carbon\Carbon;
use Livewire\Component;
use Native\Desktop\Facades\Settings;

new class extends Component
{
    public string $month = '';

    public string $selectedProject = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->selectedProject = Settings::get('selected_project_key', '');
    }

    public function updatedMonth(): void
    {
        // reactive — with() re-runs automatically
    }

    public function with(): array
    {
        $months = collect(range(0, 11))->map(function (int $i) {
            $date = now()->subMonths($i);

            return [
                'value' => $date->format('Y-m'),
                'label' => $date->format('F Y'),
            ];
        })->toArray();

        [$workingDays, $targetSeconds, $monthStart, $monthEnd] = $this->calculateMonthMeta();

        $actualByUser = JiraWorklog::whereBetween('started_at', [$monthStart, $monthEnd])
            ->selectRaw('author_account_id, SUM(time_spent_seconds) as total_seconds')
            ->groupBy('author_account_id')
            ->pluck('total_seconds', 'author_account_id');

        $users = JiraProjectUser::where('project_key', $this->selectedProject)
            ->where('active', true)
            ->get();

        $rows = $users->map(function ($user) use ($actualByUser, $targetSeconds) {
            $actualSeconds = (int) ($actualByUser->get($user->account_id, 0));
            $actualHours = round($actualSeconds / 3600, 1);
            $targetHours = round($targetSeconds / 3600, 1);

            if ($targetSeconds === 0) {
                $utilizationPct = null;
                $colorBand = 'red';
            } else {
                $utilizationPct = round($actualSeconds / $targetSeconds * 100, 1);
                $colorBand = $utilizationPct >= 90 ? 'green'
                    : ($utilizationPct >= 70 ? 'yellow' : 'red');
            }

            return [
                'account_id' => $user->account_id,
                'display_name' => $user->display_name,
                'actual_seconds' => $actualSeconds,
                'actual_hours' => $actualHours,
                'target_hours' => $targetHours,
                'utilization_pct' => $utilizationPct,
                'color_band' => $colorBand,
            ];
        })
            ->sortByDesc('utilization_pct')
            ->values()
            ->toArray();

        return [
            'rows' => $rows,
            'months' => $months,
            'targetHours' => round($targetSeconds / 3600, 1),
            'workingDays' => $workingDays,
        ];
    }

    private function calculateMonthMeta(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $workingDays = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if ($cursor->isWeekday()) {
                $workingDays++;
            }
            $cursor->addDay();
        }

        $targetSeconds = $workingDays * 8 * 3600;

        return [$workingDays, $targetSeconds, $start->startOfDay(), $end->endOfDay()];
    }
};
?>

<div>
    {{-- placeholder --}}
</div>
