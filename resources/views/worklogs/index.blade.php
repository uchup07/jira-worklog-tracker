@extends('layouts.app')
@section('title', 'Worklogs')

@section('content')

<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
    <div>
        <h1 class="display" style="font-size:22px; font-weight:800; font-style:italic; color:var(--text); line-height:1; letter-spacing:-0.02em;">Worklogs</h1>
        <p style="font-size:12px; color:var(--text-muted); margin-top:3px;">All logged time for the project</p>
    </div>
    <a href="{{ route('worklogs.create') }}" class="btn btn-primary">
        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
            <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
        </svg>
        Log Time
    </a>
</div>

{{-- Filter bar --}}
<form method="GET" action="{{ route('worklogs.index') }}"
      style="display:flex; align-items:center; gap:8px; margin-bottom:14px; padding:10px 14px; background:var(--surface); border:1px solid var(--border); border-radius:10px;">

    <select name="author" style="background:var(--surface-2); border:1px solid var(--border); border-radius:6px; padding:6px 10px; font-size:12.5px; font-family:'IBM Plex Sans',sans-serif; color:var(--text); outline:none; cursor:pointer;">
        <option value="">All authors</option>
        @foreach($authors as $author)
            <option value="{{ $author->author_account_id }}" {{ request('author') === $author->author_account_id ? 'selected' : '' }}>
                {{ $author->author_display_name }}
            </option>
        @endforeach
    </select>

    <div style="display:flex; align-items:center; gap:6px;">
        <input type="date" name="from" value="{{ request('from') }}"
               style="background:var(--surface-2); border:1px solid var(--border); border-radius:6px; padding:6px 10px; font-size:12.5px; font-family:'IBM Plex Sans',sans-serif; color:var(--text); outline:none; color-scheme:dark;">
        <span style="font-size:11px; color:var(--text-muted);">—</span>
        <input type="date" name="to" value="{{ request('to') }}"
               style="background:var(--surface-2); border:1px solid var(--border); border-radius:6px; padding:6px 10px; font-size:12.5px; font-family:'IBM Plex Sans',sans-serif; color:var(--text); outline:none; color-scheme:dark;">
    </div>

    <button type="submit" class="btn btn-ghost btn-sm" style="font-size:12px;">Filter</button>

    @if(request()->hasAny(['author','from','to','mine']))
        <a href="{{ route('worklogs.index') }}" style="font-size:11.5px; color:var(--text-muted); text-decoration:none; padding:4px 8px; border-radius:5px; transition:color 120ms;" onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text-muted)'">
            Clear ×
        </a>
    @endif

    <label style="display:flex; align-items:center; gap:6px; margin-left:auto; font-size:12.5px; color:var(--text-muted); cursor:pointer;">
        <input type="checkbox" name="mine" value="1" {{ request()->boolean('mine') ? 'checked' : '' }}
               style="accent-color:var(--accent); width:13px; height:13px;">
        Mine only
    </label>
</form>

{{-- Table --}}
@if($worklogs->isEmpty())
    <div class="card" style="padding:40px; text-align:center;">
        <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.2" style="color:var(--text-subtle); margin:0 auto 10px;">
            <circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/>
        </svg>
        <p style="font-size:13px; color:var(--text-muted);">No worklogs found. Try adjusting filters or sync first.</p>
    </div>
@else
    <div class="card" style="overflow:hidden;">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="padding-left:16px;">Issue</th>
                    <th>Author</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>
                @foreach($worklogs as $wl)
                    @php
                        $h = floor($wl->time_spent_seconds/3600);
                        $m = floor(($wl->time_spent_seconds%3600)/60);
                        $t = $h > 0 ? "{$h}h".($m > 0 ? " {$m}m" : '') : "{$m}m";
                        $isMe = $wl->author_account_id === $accountId;
                    @endphp
                    <tr>
                        <td><span class="badge-key">{{ $wl->issue_key }}</span></td>
                        <td style="font-size:13px; color:{{ $isMe ? 'var(--text)' : 'var(--text-muted)' }}; font-weight:{{ $isMe ? '500' : '400' }};">
                            {{ $wl->author_display_name }}
                            @if($isMe)<span style="font-size:10px; color:var(--accent); margin-left:4px; opacity:0.8;">you</span>@endif
                        </td>
                        <td>
                            <span class="mono" style="font-size:12px; color:var(--text-muted);">
                                {{ $wl->started_at?->format('M j, Y') }}
                            </span>
                        </td>
                        <td>
                            <span class="display" style="font-size:15px; font-weight:700; font-style:italic; color:var(--text);">{{ $t }}</span>
                        </td>
                        <td style="font-size:12.5px; color:var(--text-muted); max-width:220px;">
                            <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $wl->comment ?: '—' }}</div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($worklogs->hasPages())
        <div style="margin-top:14px; display:flex; justify-content:center;">
            {{ $worklogs->appends(request()->query())->links() }}
        </div>
    @endif
@endif

@endsection
