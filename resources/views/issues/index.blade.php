<x-app-layout>
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <div>
            <h1 class="display" style="font-size:22px; font-weight:800; font-style:italic; color:var(--text); line-height:1; letter-spacing:-0.02em;">My Issues</h1>
            <p style="font-size:12px; color:var(--text-muted); margin-top:3px;">Issues assigned to you in the project</p>
        </div>

        <div style="display:flex; gap:6px;">
            <a href="{{ route('issues.index') }}"
               style="font-size:12px; font-weight:500; padding:6px 14px; border-radius:6px; text-decoration:none; transition:all 120ms; border:1px solid;
                  {{ !request('status') ? 'color:var(--accent); background:var(--accent-dim); border-color:rgba(237,217,76,0.3);' : 'color:var(--text-muted); background:transparent; border-color:var(--border);' }}">
                All
            </a>
            <a href="{{ route('issues.index', ['status' => 'open']) }}"
               style="font-size:12px; font-weight:500; padding:6px 14px; border-radius:6px; text-decoration:none; transition:all 120ms; border:1px solid;
                  {{ request('status') === 'open' ? 'color:var(--accent); background:var(--accent-dim); border-color:rgba(237,217,76,0.3);' : 'color:var(--text-muted); background:transparent; border-color:var(--border);' }}">
                Open
            </a>
        </div>
    </div>

    @if($issues->isEmpty())
        <div class="card" style="padding:48px; text-align:center;">
            <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.2" style="color:var(--text-subtle); margin:0 auto 12px; display:block;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p style="font-size:13.5px; color:var(--text-muted);">No issues assigned to you.</p>
            <p style="font-size:12px; color:var(--text-subtle); margin-top:4px;">Try syncing to pull the latest data from Jira.</p>
        </div>
    @else
        <div class="card" style="overflow:hidden;">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Key</th>
                    <th>Summary</th>
                    <th style="width:80px;">Type</th>
                    <th style="width:80px;">Priority</th>
                    <th style="width:130px;">Status</th>
                    <th style="width:70px;"></th>
                </tr>
                </thead>
                <tbody>
                @foreach($issues as $issue)
                    <tr>
                        <td style="width:90px;">
                            <a href="{{ route('issues.show', $issue) }}" style="text-decoration:none;">
                                <span class="badge-key">{{ $issue->issue_key }}</span>
                            </a>
                        </td>
                        <td style="color:var(--text); font-size:13px; max-width:0;">
                            <a href="{{ route('issues.show', $issue) }}"
                               style="display:block; color:inherit; text-decoration:none; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                {{ $issue->summary }}
                            </a>
                        </td>
                        <td style="font-size:12px; color:var(--text-muted);">{{ $issue->issue_type }}</td>
                        <td style="font-size:12px; color:var(--text-muted);">{{ $issue->priority ?? '—' }}</td>
                        <td><span class="badge-status">{{ $issue->status }}</span></td>
                        <td style="text-align:right;">
                            <a href="{{ route('worklogs.create', ['issue' => $issue->issue_key]) }}"
                               style="font-size:11px; font-weight:500; color:var(--accent); text-decoration:none; padding:4px 9px; border:1px solid rgba(237,217,76,0.25); border-radius:5px; white-space:nowrap; transition:all 120ms; display:inline-block;"
                               onmouseover="this.style.background='var(--accent-dim)'; this.style.borderColor='rgba(237,217,76,0.4)'"
                               onmouseout="this.style.background='transparent'; this.style.borderColor='rgba(237,217,76,0.25)'">
                                Log
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p style="font-size:11.5px; color:var(--text-muted); margin-top:10px; text-align:right;">
            {{ $issues->count() }} issue{{ $issues->count() !== 1 ? 's' : '' }}
        </p>
    @endif
</x-app-layout>
