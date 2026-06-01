<?php

use App\Models\JiraProjectUser;
use App\Models\JiraWorklog;
use Livewire\Component;
use Livewire\WithPagination;
use Native\Desktop\Facades\Settings;

new class extends Component
{
    use WithPagination;

    public ?string $author = null;

    public ?string $from = null;

    public ?string $to = null;

    public bool $mine = false;

    public function mount(): void
    {
        $this->author = request('author');
        $this->from = request('from');
        $this->to = request('to');
        $this->mine = (bool) request('mine');
    }

    public function updatedAuthor(): void
    {
        $this->resetPage();
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->resetPage();
    }

    public function updatedMine(): void
    {
        $this->resetPage();
    }

    public function clear(): void
    {
        $this->author = null;
        $this->from = null;
        $this->to = null;
        $this->mine = false;
        $this->resetPage();
    }

    public function with(): array
    {
        $accountId = Settings::get('jira_account_id');
        $projectKey = Settings::get('selected_project_key');

        $query = JiraWorklog::query()
            ->leftJoin('jira_issues', 'jira_worklogs.issue_key', '=', 'jira_issues.issue_key')
            ->select([
                'jira_worklogs.id',
                'jira_worklogs.jira_worklog_id',
                'jira_worklogs.issue_key',
                'jira_worklogs.author_account_id',
                'jira_worklogs.author_display_name',
                'jira_worklogs.time_spent_seconds',
                'jira_worklogs.started_at',
                'jira_worklogs.comment',
                'jira_issues.summary',
                'jira_issues.issue_type',
                'jira_issues.status as issue_status',
                'jira_issues.sprint',
                'jira_issues.epic',
            ])
            ->forProject($projectKey);

        if ($this->mine && $accountId) {
            $query->forAuthor($accountId);
        } elseif ($this->author) {
            $query->forAuthor($this->author);
        }

        if ($this->from) {
            $query->where('jira_worklogs.started_at', '>=', $this->from);
        }
        if ($this->to) {
            $query->where('jira_worklogs.started_at', '<=', $this->to.' 23:59:59');
        }

        return [
            'worklogs' => $query->orderByDesc('jira_worklogs.started_at')->paginate(30),
            'authors' => JiraProjectUser::query()->forProject($projectKey)
                ->orderBy('display_name')
                ->get(),
            'accountId' => $accountId,
        ];
    }
};
?>

<div>
    {{-- Filter bar --}}
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:14px; padding:10px 14px; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); flex-wrap:wrap;">

        <select wire:model.live="author"
                style="background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius); padding:5px 9px; font-size:12.5px; font-family:'Geist',sans-serif; color:var(--text); outline:none; cursor:pointer;">
            <option value="">All users</option>
            @foreach($authors as $a)
                <option value="{{ $a->account_id }}">{{ $a->display_name }}</option>
            @endforeach
        </select>

        <div style="display:flex; align-items:center; gap:6px;">
            <input type="date" wire:model.live="from"
                   style="background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius); padding:5px 9px; font-size:12.5px; font-family:'Geist',sans-serif; color:var(--text); outline:none;">
            <span style="font-size:11px; color:var(--text-muted);">—</span>
            <input type="date" wire:model.live="to"
                   style="background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius); padding:5px 9px; font-size:12.5px; font-family:'Geist',sans-serif; color:var(--text); outline:none;">
        </div>

        @if($author || $from || $to || $mine)
            <button wire:click="clear"
                    style="font-size:11.5px; color:var(--text-muted); background:transparent; border:none; cursor:pointer; padding:4px 8px; border-radius:4px; transition:color 100ms;"
                    onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text-muted)'">
                Clear ×
            </button>
        @endif

        <label style="display:flex; align-items:center; gap:6px; margin-left:auto; font-size:12.5px; color:var(--text-muted); cursor:pointer;">
            <input type="checkbox" wire:model.live="mine"
                   style="accent-color:var(--accent); width:13px; height:13px;">
            Mine only
        </label>
    </div>

    {{-- Table --}}
    @if($worklogs->isEmpty())
        <div class="card" style="padding:40px; text-align:center;">
            <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.2" style="color:var(--text-subtle); margin:0 auto 10px; display:block;">
                <circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/>
            </svg>
            <p style="font-size:13px; color:var(--text-muted);">No worklogs found. Try adjusting filters or sync first.</p>
        </div>
    @else
        <div class="card" style="overflow:hidden;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Issue</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Sprint</th>
                        <th>Epic</th>
                        <th>Author</th>
                        <th>Date + started_at_time</th>
                        <th>Time</th>
                        <th>Comment</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($worklogs as $wl)
                        @php
                            $h  = floor($wl->time_spent_seconds / 3600);
                            $m  = floor(($wl->time_spent_seconds % 3600) / 60);
                            $t  = $h > 0 ? "{$h}h" . ($m > 0 ? " {$m}m" : '') : "{$m}m";
                            $me = $wl->author_account_id === $accountId;
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('issues.show', $wl->issue_key) }}"
                                   style="text-decoration:none;">
                                    <span class="badge-key" style="cursor:pointer;">{{ $wl->issue_key }}</span>
                                </a>
                                @if($wl->summary)
                                    <div style="font-size:11.5px; color:var(--text-muted); margin-top:2px; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $wl->summary }}</div>
                                @endif
                            </td>
                            <td style="font-size:11.5px; color:var(--text-muted);">{{ $wl->issue_type ?: '—' }}</td>
                            <td>
                                @if($wl->issue_status)
                                    <span class="badge-status">{{ $wl->issue_status }}</span>
                                @else
                                    <span style="font-size:11.5px; color:var(--text-muted);">—</span>
                                @endif
                            </td>
                            <td style="font-size:11.5px; color:var(--text-muted); max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $wl->sprint ?: '—' }}</td>
                            <td style="font-size:11.5px; color:var(--text-muted);">{{ $wl->epic ?: '—' }}</td>
                            <td style="font-size:13px; color:{{ $me ? 'var(--text)' : 'var(--text-muted)' }}; font-weight:{{ $me ? '500' : '400' }};">
                                {{ $wl->author_display_name }}
                                @if($me)<x-badge text="you" color="yellow" style="margin-left:4px;" />@endif
                            </td>
                            <td>
                                <div style="display:flex; flex-direction:column; gap:2px;">
                                    <span class="mono" style="font-size:12px; color:var(--text-muted);">{{ $wl->started_at?->format('M j, Y') }}</span>
                                    <span class="mono" style="font-size:11px; color:var(--text-subtle);">{{ $wl->started_at?->format('H:i') ?: '—' }}</span>
                                </div>
                            </td>
                            <td>
                                <span style="font-size:14px; font-weight:700; letter-spacing:-0.03em; color:var(--text);">{{ $t }}</span>
                            </td>
                            <td style="font-size:12.5px; color:var(--text-muted); max-width:200px;">
                                <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $wl->comment ?: '—' }}</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($worklogs->hasPages())
            <div style="margin-top:14px; display:flex; justify-content:center;">
                {{ $worklogs->links() }}
            </div>
        @endif
    @endif
</div>
