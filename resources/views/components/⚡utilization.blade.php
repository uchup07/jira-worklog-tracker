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
        $start = Carbon::parse($this->month.'-01')->startOfMonth();
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

<div style="display:flex; flex-direction:column; gap:14px;">

    {{-- Filter bar --}}
    <div style="display:flex; align-items:center; gap:10px; padding:10px 14px;
                background:var(--surface); border:1px solid var(--border);
                border-radius:var(--radius); flex-wrap:wrap;">

        <select wire:model.live="month"
                style="background:var(--surface-2); border:1px solid var(--border);
                       border-radius:var(--radius); padding:5px 9px; font-size:12.5px;
                       font-family:'Geist',sans-serif; color:var(--text); outline:none; cursor:pointer;">
            @foreach($months as $m)
                <option value="{{ $m['value'] }}">{{ $m['label'] }}</option>
            @endforeach
        </select>

        <span style="font-size:12px; color:var(--text-muted);">
            {{ $workingDays }} working days &middot; {{ $targetHours }}h target
        </span>
    </div>

    {{-- Table --}}
    @if(empty($rows))
        <div class="card" style="padding:40px; text-align:center;">
            <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                 stroke-width="1.2" style="color:var(--text-subtle); margin:0 auto 10px; display:block;">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
            </svg>
            <p style="font-size:13px; color:var(--text-muted);">No team members found. Try syncing the project first.</p>
        </div>
    @else
        <div class="card" style="overflow:hidden;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Worklog</th>
                        <th>Target</th>
                        <th>Utilization</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        @php
                            $color = match($row['color_band']) {
                                'green'  => 'var(--green)',
                                'yellow' => 'var(--accent)',
                                default  => 'var(--red)',
                            };
                            $dimColor = match($row['color_band']) {
                                'green'  => 'oklch(0.750 0.170 142 / 0.10)',
                                'yellow' => 'var(--accent-dim)',
                                default  => 'oklch(0.650 0.220 25 / 0.10)',
                            };
                            $barWidth = $row['utilization_pct'] !== null
                                ? min((float) $row['utilization_pct'], 100)
                                : 0;

                            $h = floor($row['actual_seconds'] / 3600);
                            $m = floor(($row['actual_seconds'] % 3600) / 60);
                            $actualFormatted = $h > 0
                                ? "{$h}h" . ($m > 0 ? " {$m}m" : '')
                                : "{$m}m";
                        @endphp
                        <tr>
                            <td style="font-size:13px; font-weight:500; color:var(--text);">
                                {{ $row['display_name'] }}
                            </td>
                            <td>
                                <span style="font-size:14px; font-weight:700; letter-spacing:-0.03em;
                                             color:var(--text);">{{ $actualFormatted }}</span>
                            </td>
                            <td>
                                <span style="font-size:13px; color:var(--text-muted);">
                                    {{ $row['target_hours'] }}h
                                </span>
                            </td>
                            <td style="position:relative; min-width:120px;">
                                {{-- Progress bar background --}}
                                <div style="position:absolute; inset:0; width:{{ $barWidth }}%;
                                            background:{{ $dimColor }}; z-index:0;
                                            transition:width 300ms ease;"></div>
                                {{-- Text --}}
                                <span style="position:relative; z-index:1; font-size:14px;
                                             font-weight:700; letter-spacing:-0.03em;
                                             color:{{ $color }};">
                                    @if($row['utilization_pct'] !== null)
                                        {{ $row['utilization_pct'] }}%
                                    @else
                                        &mdash;
                                    @endif
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
