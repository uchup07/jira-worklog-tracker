<?php

use App\Mail\WorklogReminderMail;
use App\Models\JiraProjectUser;
use App\Models\JiraWorklog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Native\Desktop\Facades\Settings;

new class extends Component
{
    public string $selectedProject = '';

    public int $lookbackDays = 7;

    public ?string $sendingTo = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->selectedProject = Settings::get('selected_project_key', '');
        $this->lookbackDays = (int) Settings::get('missing_worklog_days', 7);
    }

    public function sendReminder(string $accountId): void
    {
        $user = JiraProjectUser::where('account_id', $accountId)->first();

        if (! $user || ! $user->email) {
            $this->errorMessage = 'No email address on record for this user.';
            $this->successMessage = null;

            return;
        }

        $missing = collect($this->computeMissingUsers())
            ->firstWhere('account_id', $accountId);

        if (! $missing || empty($missing['missing_days'])) {
            return;
        }

        $this->sendingTo = $accountId;

        $encryption = Settings::get('smtp_encryption') ?: null;

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', Settings::get('smtp_host', 'localhost'));
        Config::set('mail.mailers.smtp.port', (int) Settings::get('smtp_port', 1025));
        Config::set('mail.mailers.smtp.username', Settings::get('smtp_username') ?: null);
        Config::set('mail.mailers.smtp.password', Settings::get('smtp_password') ?: null);
        Config::set('mail.mailers.smtp.encryption', $encryption);
        Config::set('mail.from.address', Settings::get('smtp_from_address', 'hello@example.com'));
        Config::set('mail.from.name', Settings::get('smtp_from_name', 'Worklog Tracker'));
        Mail::purge('smtp');

        try {
            Mail::to($user->email)->send(
                new WorklogReminderMail($user->display_name, $missing['missing_days'])
            );
            $this->successMessage = "Reminder sent to {$user->display_name}.";
            $this->errorMessage = null;
        } catch (Throwable $e) {
            $this->errorMessage = 'Failed to send email: '.$e->getMessage();
            $this->successMessage = null;
        } finally {
            $this->sendingTo = null;
        }
    }

    public function with(): array
    {
        return ['missingUsers' => $this->computeMissingUsers()];
    }

    public function computeMissingUsers(): array
    {
        $projectKey = $this->selectedProject;

        if (! $projectKey) {
            return [];
        }

        $workingDays = [];
        for ($i = 1; $i <= $this->lookbackDays; $i++) {
            $date = Carbon::today()->subDays($i);
            if ($date->isWeekday()) {
                $workingDays[] = $date->toDateString();
            }
        }

        if (empty($workingDays)) {
            return [];
        }

        $users = JiraProjectUser::forProject($projectKey)
            ->where('active', true)
            ->get();

        $loggedDays = JiraWorklog::forProject($projectKey)
            ->selectRaw('author_account_id, DATE(jira_worklogs.started_at) as log_date')
            ->whereIn(DB::raw('DATE(jira_worklogs.started_at)'), $workingDays)
            ->groupBy('author_account_id', DB::raw('DATE(jira_worklogs.started_at)'))
            ->get()
            ->groupBy('author_account_id')
            ->map(fn ($logs) => $logs->pluck('log_date')->toArray());

        return $users->map(function ($user) use ($workingDays, $loggedDays) {
            $logged = $loggedDays->get($user->account_id, []);
            $missing = array_values(array_diff($workingDays, $logged));
            sort($missing);

            return [
                'account_id' => $user->account_id,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'missing_days' => $missing,
                'count' => count($missing),
            ];
        })
            ->filter(fn ($u) => $u['count'] > 0)
            ->sortByDesc('count')
            ->values()
            ->toArray();
    }
};
?>

<div style="display:flex; flex-direction:column; gap:14px;">

    @if($successMessage)
        <div x-data x-init="setTimeout(() => $el.remove(), 4000)"
             style="padding:9px 13px; background:oklch(0.75 0.17 142 / 0.08); border:1px solid oklch(0.75 0.17 142 / 0.2); border-radius:var(--radius); font-size:12.5px; color:var(--green); display:flex; align-items:center; gap:8px;">
            {{ $successMessage }}
        </div>
    @endif

    @if($errorMessage)
        <div style="padding:9px 13px; background:oklch(0.65 0.22 25 / 0.08); border:1px solid oklch(0.65 0.22 25 / 0.2); border-radius:var(--radius); font-size:12.5px; color:var(--red);">
            {{ $errorMessage }}
        </div>
    @endif

    @if(empty($missingUsers))
        <div class="card" style="padding:40px; text-align:center;">
            <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.2"
                 style="color:var(--text-subtle); margin:0 auto 10px; display:block;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p style="font-size:13px; color:var(--text-muted);">All team members have logged time in the last {{ $lookbackDays }} days.</p>
        </div>
    @else
        <div class="card" style="overflow:hidden;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Missing Days</th>
                        <th style="width:130px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($missingUsers as $u)
                        <tr>
                            <td style="font-size:13px; color:var(--text); font-weight:500;">{{ $u['display_name'] }}</td>
                            <td>
                                <span style="font-size:14px; font-weight:700; color:var(--red);">{{ $u['count'] }}</span>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:3px;">
                                    {{ implode(', ', array_map(fn ($d) => \Carbon\Carbon::parse($d)->format('M j'), $u['missing_days'])) }}
                                </div>
                            </td>
                            <td>
                                @if($u['email'])
                                    <button type="button"
                                            wire:click="sendReminder('{{ $u['account_id'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="sendReminder('{{ $u['account_id'] }}')"
                                            style="font-size:12px; padding:5px 12px; border-radius:5px; cursor:pointer;
                                                   border:1px solid var(--border); background:transparent; color:var(--text-muted);
                                                   transition:all 120ms;"
                                            onmouseover="this.style.borderColor='var(--accent)'; this.style.color='var(--accent)'"
                                            onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text-muted)'">
                                        <span wire:loading.remove wire:target="sendReminder('{{ $u['account_id'] }}')">Send Email</span>
                                        <span wire:loading wire:target="sendReminder('{{ $u['account_id'] }}')">Sending…</span>
                                    </button>
                                @else
                                    <span style="font-size:11.5px; color:var(--text-subtle); font-style:italic;"
                                          title="No email on record">No email</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
