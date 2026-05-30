@extends('layouts.app')
@section('title', 'Log Time')

@section('content')

<div style="max-width:520px;">

    <div style="margin-bottom:22px;">
        <h1 class="display" style="font-size:22px; font-weight:800; font-style:italic; color:var(--text); line-height:1; letter-spacing:-0.02em;">Log Time</h1>
        <p style="font-size:12px; color:var(--text-muted); margin-top:3px;">Record time against a Jira issue</p>
    </div>

    <div class="card" style="padding:24px;">
        <form method="POST" action="{{ route('worklogs.store') }}" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf

            <div style="display:flex; flex-direction:column; gap:18px;">

                {{-- Issue --}}
                <div>
                    <label class="label" for="issue_key">Issue</label>
                    <select id="issue_key" name="issue_key"
                            style="width:100%; background:var(--surface-2); border:1px solid {{ $errors->has('issue_key') ? 'var(--red)' : 'var(--border)' }}; border-radius:7px; padding:9px 12px; font-size:13.5px; font-family:'IBM Plex Sans',sans-serif; color:var(--text); outline:none; cursor:pointer; transition:border-color 120ms;"
                            onfocus="this.style.borderColor='var(--border-2)'; this.style.boxShadow='0 0 0 3px var(--accent-ring)'"
                            onblur="this.style.borderColor=''; this.style.boxShadow=''">
                        <option value="">Select an issue…</option>
                        @foreach($issues as $issue)
                            <option value="{{ $issue->issue_key }}" {{ (old('issue_key', $selectedIssue) === $issue->issue_key) ? 'selected' : '' }}>
                                {{ $issue->issue_key }} — {{ $issue->summary }}
                            </option>
                        @endforeach
                    </select>
                    @error('issue_key')
                        <p style="font-size:11.5px; color:var(--red); margin-top:5px;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Time + Date row --}}
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                    <div>
                        <label class="label" for="time_spent">Time Spent</label>
                        <input type="text" id="time_spent" name="time_spent"
                               value="{{ old('time_spent') }}"
                               placeholder="1h 30m, 2h, 45m"
                               class="input {{ $errors->has('time_spent') ? 'error' : '' }}"
                               onfocus="this.style.borderColor='var(--border-2)'; this.style.boxShadow='0 0 0 3px var(--accent-ring)'"
                               onblur="this.style.borderColor=''; this.style.boxShadow=''">
                        @error('time_spent')
                            <p style="font-size:11.5px; color:var(--red); margin-top:5px;">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="label" for="started_at">Date</label>
                        <input type="date" id="started_at" name="started_at"
                               value="{{ old('started_at', now()->toDateString()) }}"
                               style="width:100%; background:var(--surface-2); border:1px solid {{ $errors->has('started_at') ? 'var(--red)' : 'var(--border)' }}; border-radius:7px; padding:9px 12px; font-size:13.5px; font-family:'IBM Plex Sans',sans-serif; color:var(--text); outline:none; color-scheme:dark; transition:border-color 120ms;"
                               onfocus="this.style.borderColor='var(--border-2)'; this.style.boxShadow='0 0 0 3px var(--accent-ring)'"
                               onblur="this.style.borderColor=''; this.style.boxShadow=''">
                        @error('started_at')
                            <p style="font-size:11.5px; color:var(--red); margin-top:5px;">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Comment --}}
                <div>
                    <label class="label" for="comment">
                        Comment
                        <span style="font-weight:400; color:var(--text-subtle); font-size:11px; margin-left:4px;">optional</span>
                    </label>
                    <textarea id="comment" name="comment" rows="3"
                              placeholder="What did you work on?"
                              style="width:100%; background:var(--surface-2); border:1px solid {{ $errors->has('comment') ? 'var(--red)' : 'var(--border)' }}; border-radius:7px; padding:9px 12px; font-size:13.5px; font-family:'IBM Plex Sans',sans-serif; color:var(--text); outline:none; resize:vertical; min-height:80px; transition:border-color 120ms;"
                              onfocus="this.style.borderColor='var(--border-2)'; this.style.boxShadow='0 0 0 3px var(--accent-ring)'"
                              onblur="this.style.borderColor=''; this.style.boxShadow=''">{{ old('comment') }}</textarea>
                    @error('comment')
                        <p style="font-size:11.5px; color:var(--red); margin-top:5px;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Actions --}}
                <div style="display:flex; align-items:center; gap:10px; padding-top:4px;">
                    <button type="submit" class="btn btn-primary" :disabled="submitting" :class="{ 'opacity-60': submitting }">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2" x-show="!submitting">
                            <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span x-text="submitting ? 'Logging…' : 'Log Time'"></span>
                    </button>
                    <a href="{{ route('worklogs.index') }}"
                       style="font-size:13px; color:var(--text-muted); text-decoration:none; padding:7px 12px; border-radius:7px; transition:all 120ms;"
                       onmouseover="this.style.color='var(--text)'; this.style.background='var(--surface-2)'"
                       onmouseout="this.style.color='var(--text-muted)'; this.style.background='transparent'">
                        Cancel
                    </a>
                </div>

            </div>
        </form>
    </div>

    {{-- Quick tips --}}
    <div style="margin-top:14px; padding:12px 14px; background:var(--surface); border:1px solid var(--border); border-radius:8px;">
        <p style="font-size:11px; font-weight:500; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:6px;">Time format tips</p>
        <div style="display:flex; gap:16px;">
            @foreach(['1h 30m', '2h', '45m', '90m'] as $ex)
                <code class="mono" style="font-size:11.5px; color:var(--text-muted); background:var(--surface-2); padding:2px 6px; border-radius:4px; border:1px solid var(--border);">{{ $ex }}</code>
            @endforeach
        </div>
    </div>

</div>

@endsection
