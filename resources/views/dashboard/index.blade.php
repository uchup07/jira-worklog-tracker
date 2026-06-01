<x-app-layout>
    {{-- Stat row --}}
    <div class="stagger" style="display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:14px;">

        {{-- Today --}}
        <div class="card" style="padding:18px 20px; position:relative; overflow:hidden;">
            <div style="font-size:10.5px; font-weight:500; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em; margin-bottom:8px;">Today</div>
            @php $tH = floor($todaySeconds/3600); $tM = floor(($todaySeconds%3600)/60); @endphp
            <div class="stat-num" style="font-size:42px; color:var(--accent);">
                {{ $tH }}<span style="font-size:20px; opacity:0.6; margin-left:1px;">h</span>@if($tM > 0) {{ $tM }}<span style="font-size:16px; opacity:0.6; margin-left:1px;">m</span>@endif
            </div>
            <div style="position:absolute; right:16px; bottom:14px; opacity:0.05;">
                <svg width="48" height="48" fill="currentColor" style="color:var(--accent)" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 2a8 8 0 100 16A8 8 0 0012 4zm0 2a1 1 0 011 1v4.586l2.707 2.707a1 1 0 01-1.414 1.414l-3-3A1 1 0 0111 12V9a1 1 0 011-1z" clip-rule="evenodd"/></svg>
            </div>
        </div>

        {{-- This Week --}}
        <div class="card" style="padding:18px 20px; position:relative; overflow:hidden;">
            <div style="font-size:10.5px; font-weight:500; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em; margin-bottom:8px;">This Week</div>
            @php $wH = floor($weekSeconds/3600); $wM = floor(($weekSeconds%3600)/60); @endphp
            <div class="stat-num" style="font-size:42px;">
                {{ $wH }}<span style="font-size:20px; opacity:0.5; margin-left:1px;">h</span>@if($wM > 0) {{ $wM }}<span style="font-size:16px; opacity:0.5; margin-left:1px;">m</span>@endif
            </div>
            <div style="position:absolute; right:16px; bottom:14px; opacity:0.04;">
                <svg width="48" height="48" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.103 0-2 .897-2 2v16c0 1.103.897 2 2 2h14c1.103 0 2-.897 2-2V5c0-1.103-.897-2-2-2zm-7 14l-4-4 1.414-1.414L12 14.172l6.586-6.586L20 9l-8 8z"/></svg>
            </div>
        </div>

        {{-- Open Issues --}}
        <div class="card" style="padding:18px 20px; position:relative; overflow:hidden;">
            <div style="font-size:10.5px; font-weight:500; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em; margin-bottom:8px;">Open Issues</div>
            <div class="stat-num" style="font-size:42px;">{{ $openIssues->count() }}</div>
            <div style="position:absolute; right:16px; bottom:14px; opacity:0.04;">
                <svg width="48" height="48" fill="currentColor" viewBox="0 0 24 24"><path d="M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2zm7 4a1 1 0 00-1 1v4a1 1 0 002 0V8a1 1 0 00-1-1zm0 8a1 1 0 100 2 1 1 0 000-2z"/></svg>
            </div>
        </div>
    </div>

    {{-- Mid grid --}}
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">

        {{-- Recent worklogs --}}
        <div class="card" style="overflow:hidden;">
            <div style="padding:13px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
                <span style="font-size:12.5px; font-weight:600; color:var(--text);">Recent Logs</span>
                <a href="{{ route('worklogs.index') }}" style="font-size:11px; color:var(--text-muted); text-decoration:none; display:flex; align-items:center; gap:3px;" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text-muted)'">
                    all <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>
            @if($recentWorklogs->isEmpty())
                <div style="padding:24px; text-align:center; font-size:12.5px; color:var(--text-muted);">No worklogs yet. Start logging time!</div>
            @else
                @foreach($recentWorklogs as $wl)
                    @php
                        $h = floor($wl->time_spent_seconds/3600);
                        $m = floor(($wl->time_spent_seconds%3600)/60);
                        $t = $h > 0 ? "{$h}h".($m > 0 ? " {$m}m" : '') : "{$m}m";
                    @endphp
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; border-bottom:1px solid var(--border); transition:background 100ms;" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                        <div>
                            <div style="display:flex; align-items:center; gap:7px; margin-bottom:3px;">
                                <span class="badge-key">{{ $wl->issue_key }}</span>
                                <span style="font-size:11.5px; color:var(--text-muted);">{{ $wl->started_at?->format('M j') }}</span>
                            </div>
                            @if($wl->comment)
                                <div style="font-size:11.5px; color:var(--text-muted); max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $wl->comment }}</div>
                            @endif
                        </div>
                        <div class="stat-num" style="font-size:18px;">{{ $t }}</div>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Team this week --}}
        <div class="card" style="overflow:hidden;">
            <div style="padding:13px 16px; border-bottom:1px solid var(--border);">
                <span style="font-size:12.5px; font-weight:600; color:var(--text);">Team This Week</span>
            </div>
            @if($teamWorklogs->isEmpty())
                <div style="padding:24px; text-align:center; font-size:12.5px; color:var(--text-muted);">No team activity this week.</div>
            @else
                @php $maxS = $teamWorklogs->max('total_seconds') ?: 1; @endphp
                <div style="padding:14px 16px; display:flex; flex-direction:column; gap:13px;">
                    @foreach($teamWorklogs as $m)
                        @php
                            $mH = floor($m->total_seconds/3600);
                            $mM = floor(($m->total_seconds%3600)/60);
                            $mT = $mH > 0 ? "{$mH}h".($mM > 0 ? " {$mM}m" : '') : "{$mM}m";
                            $pct = round(($m->total_seconds / $maxS) * 100);
                        @endphp
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:6px;">
                                <span style="font-size:12.5px; color:var(--text); max-width:130px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $m->author_display_name }}</span>
                                <span class="display" style="font-size:14px; font-weight:700; font-style:italic;">{{ $mT }}</span>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill" style="width:{{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Open issues --}}
    <div class="card" style="overflow:hidden;">
        <div style="padding:13px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
            <span style="font-size:12.5px; font-weight:600; color:var(--text);">My Open Issues</span>
            <a href="{{ route('issues.index') }}" style="font-size:11px; color:var(--text-muted); text-decoration:none; display:flex; align-items:center; gap:3px;" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text-muted)'">
                all <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>
        @if($openIssues->isEmpty())
            <div style="padding:24px; text-align:center; font-size:12.5px; color:var(--text-muted);">No open issues — you're all caught up!</div>
        @else
            <table class="data-table">
                <tbody>
                @foreach($openIssues as $issue)
                    <tr>
                        <td style="width:90px;"><span class="badge-key">{{ $issue->issue_key }}</span></td>
                        <td style="color:var(--text); font-size:13px; max-width:0;">
                            <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $issue->summary }}</div>
                        </td>
                        <td style="width:120px;"><span class="badge-status">{{ $issue->status }}</span></td>
                        <td style="width:70px; text-align:right;">
                            <a href="{{ route('worklogs.create', ['issue' => $issue->issue_key]) }}"
                               style="font-size:11px; font-weight:500; color:var(--accent); text-decoration:none; padding:3px 8px; border:1px solid rgba(237,217,76,0.25); border-radius:5px; transition:all 120ms; white-space:nowrap;"
                               onmouseover="this.style.background='var(--accent-dim)'; this.style.borderColor='rgba(237,217,76,0.4)'"
                               onmouseout="this.style.background='transparent'; this.style.borderColor='rgba(237,217,76,0.25)'">
                                Log
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-app-layout>
