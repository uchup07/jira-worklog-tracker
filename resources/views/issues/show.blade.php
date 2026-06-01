<x-app-layout>
    @php
        $hours = floor($totalLoggedSeconds / 3600);
        $minutes = floor(($totalLoggedSeconds % 3600) / 60);
        $totalLogged = $hours > 0 ? "{$hours}h".($minutes > 0 ? " {$minutes}m" : '') : "{$minutes}m";
    @endphp

    <div style="display:flex; flex-direction:column; gap:16px;">

        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px;">
            <div style="min-width:0;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px; flex-wrap:wrap;">
                    <span class="badge-key" style="font-size:12px;">{{ $issue->issue_key }}</span>
                    <span class="badge-status">{{ $issue->status }}</span>
                    <span style="font-size:11.5px; color:var(--text-muted);">{{ $issue->issue_type }}</span>
                    <span style="font-size:11.5px; color:var(--text-muted);">{{ $issue->priority ?? 'No priority' }}</span>
                </div>
                <h1 style="font-size:24px; font-weight:800; color:var(--text); line-height:1.1; letter-spacing:-0.03em;">
                    {{ $issue->summary }}
                </h1>
                <p style="font-size:12px; color:var(--text-muted); margin-top:6px;">
                    Assigned to {{ $issue->assignee_display_name ?? 'Unassigned' }} • synced {{ $issue->synced_at?->diffForHumans() ?? 'never' }}
                </p>
            </div>

            <div style="display:flex; gap:8px; flex-shrink:0;">
                <a href="{{ route('issues.index') }}" class="btn btn-ghost btn-sm">All Issues</a>
                <a href="{{ route('worklogs.create', ['issue' => $issue->issue_key]) }}" class="btn btn-ghost btn-sm">Standalone Form</a>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:minmax(0, 1.4fr) minmax(320px, 0.9fr); gap:16px; align-items:start;">

            <div class="card" style="overflow:hidden;">
                <div style="padding:14px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
                    <div>
                        <div style="font-size:12.5px; font-weight:600; color:var(--text);">Worklogs</div>
                        <div style="font-size:11.5px; color:var(--text-subtle); margin-top:2px;">
                            {{ $worklogs->count() }} log{{ $worklogs->count() !== 1 ? 's' : '' }} • {{ $totalLogged }} total
                        </div>
                    </div>
                </div>

                @if($worklogs->isEmpty())
                    <div style="padding:28px 20px; text-align:center;">
                        <p style="font-size:13px; color:var(--text-muted);">No worklogs recorded for this issue yet.</p>
                        <p style="font-size:11.5px; color:var(--text-subtle); margin-top:4px;">Use the form on the right to log the first entry.</p>
                    </div>
                @else
                    <div style="display:flex; flex-direction:column;">
                        @foreach($worklogs as $worklog)
                            @php
                                $h = floor($worklog->time_spent_seconds / 3600);
                                $m = floor(($worklog->time_spent_seconds % 3600) / 60);
                                $t = $h > 0 ? "{$h}h".($m > 0 ? " {$m}m" : '') : "{$m}m";
                            @endphp
                            <div style="padding:14px 16px; border-bottom:1px solid var(--border); display:flex; align-items:flex-start; justify-content:space-between; gap:12px;">
                                <div style="min-width:0;">
                                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:5px;">
                                        <span style="font-size:12.5px; color:var(--text); font-weight:600;">{{ $worklog->author_display_name }}</span>
                                        <span style="font-size:11.5px; color:var(--text-subtle);">{{ $worklog->started_at?->format('M j, Y') ?? 'No date' }}</span>
                                    </div>
                                    <div style="font-size:12px; color:var(--text-muted); line-height:1.45; white-space:pre-wrap; word-break:break-word;">
                                        {{ $worklog->comment ?: 'No comment' }}
                                    </div>
                                </div>
                                <div class="stat-num" style="font-size:18px; flex-shrink:0;">{{ $t }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card" style="padding:18px;">
                <div style="margin-bottom:14px;">
                    <h2 style="font-size:15px; font-weight:700; color:var(--text); line-height:1;">Log Time</h2>
                    <p style="font-size:11.5px; color:var(--text-muted); margin-top:4px;">Create a worklog directly on {{ $issue->issue_key }}</p>
                </div>

                <form method="POST" action="{{ route('worklogs.store') }}"
                      x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <input type="hidden" name="issue_key" value="{{ $issue->issue_key }}">
                    <input type="hidden" name="return_to_issue" value="1">

                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <div>
                            <label style="display:block; font-size:12px; font-weight:500; color:var(--text-muted); margin-bottom:5px;">Issue</label>
                            <div style="padding:9px 11px; border:1px solid var(--border); border-radius:var(--radius); background:var(--surface-2); display:flex; align-items:center; gap:8px;">
                                <span class="badge-key">{{ $issue->issue_key }}</span>
                                <span style="font-size:12px; color:var(--text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $issue->summary }}</span>
                            </div>
                            @error('issue_key')
                            <p style="font-size:11.5px; color:var(--red); margin-top:4px;">{{ $message }}</p>
                            @enderror
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <x-input label="Time Spent"
                                     name="time_spent"
                                     placeholder="1h 30m, 2h, 45m"
                                     :value="old('time_spent')"
                                     :error="$errors->first('time_spent')" />

                            <x-input label="Date"
                                     name="started_at"
                                     type="date"
                                     :value="old('started_at', now()->toDateString())"
                                     :error="$errors->first('started_at')" />
                        </div>

                        <x-textarea label="Comment"
                                    name="comment"
                                    placeholder="What did you work on?"
                                    :value="old('comment')"
                                    :error="$errors->first('comment')"
                                    hint="optional" />

                        <div style="display:flex; align-items:center; gap:10px;">
                            <x-button type="submit"
                                      color="primary"
                                      :loading="false"
                                      x-bind:loading="submitting">
                                Log Time
                            </x-button>
                            <x-button href="{{ route('worklogs.monitoring') }}"
                                      color="secondary"
                                      light>
                                Cancel
                            </x-button>
                        </div>
                    </div>
                </form>

                <div style="margin-top:14px; padding:11px 12px; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);">
                    <p style="font-size:11px; font-weight:600; color:var(--text-subtle); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:6px;">Time formats</p>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        @foreach(['1h 30m', '2h', '45m', '90m'] as $example)
                            <code class="mono" style="font-size:11.5px; color:var(--text-muted); background:var(--surface-2); padding:2px 6px; border-radius:4px; border:1px solid var(--border);">{{ $example }}</code>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>

    </div>
</x-app-layout>
