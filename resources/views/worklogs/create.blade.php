@extends('layouts.app')
@section('title', 'Log Time')

@section('content')

<div style="max-width:520px;">

    <div style="margin-bottom:20px;">
        <h1 style="font-size:18px; font-weight:700; color:var(--text); letter-spacing:-0.025em; line-height:1;">Log Time</h1>
        <p style="font-size:12px; color:var(--text-muted); margin-top:3px;">Record time against a Jira issue</p>
    </div>

    <x-card class="dark:bg-dark-800 dark:border-dark-700" style="padding:22px;">
        <form method="POST" action="{{ route('worklogs.store') }}"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf

            <div style="display:flex; flex-direction:column; gap:16px;">

                {{-- Issue select --}}
                <div>
                    <label style="display:block; font-size:12px; font-weight:500; color:var(--text-muted); margin-bottom:5px;">Issue</label>
                    <select name="issue_key"
                            style="width:100%; background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius); padding:7px 10px; font-size:13px; font-family:'Geist',sans-serif; color:var(--text); outline:none;">
                        <option value="">Select an issue…</option>
                        @foreach($issues as $issue)
                            <option value="{{ $issue->issue_key }}"
                                    @selected(old('issue_key', $selectedIssue) === $issue->issue_key)>
                                {{ $issue->issue_key }} — {{ $issue->summary }}
                            </option>
                        @endforeach
                    </select>
                    @error('issue_key')
                        <p style="font-size:11.5px; color:var(--red); margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Time + Date --}}
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

                {{-- Comment --}}
                <x-textarea label="Comment"
                            name="comment"
                            placeholder="What did you work on?"
                            :value="old('comment')"
                            :error="$errors->first('comment')"
                            hint="optional" />

                {{-- Actions --}}
                <div style="display:flex; align-items:center; gap:10px; padding-top:2px;">
                    <x-button type="submit"
                              color="primary"
                              :loading="false"
                              x-bind:loading="submitting">
                        Log Time
                    </x-button>
                    <x-button href="{{ route('worklogs.index') }}"
                              color="secondary"
                              light>
                        Cancel
                    </x-button>
                </div>

            </div>
        </form>
    </x-card>

    {{-- Time format hints --}}
    <div style="margin-top:12px; padding:11px 14px; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);">
        <p style="font-size:11px; font-weight:600; color:var(--text-subtle); text-transform:uppercase; letter-spacing:0.07em; margin-bottom:6px;">Time formats</p>
        <div style="display:flex; gap:12px;">
            @foreach(['1h 30m', '2h', '45m', '90m'] as $ex)
                <code class="mono" style="font-size:11.5px; color:var(--text-muted); background:var(--surface-2); padding:2px 6px; border-radius:4px; border:1px solid var(--border);">{{ $ex }}</code>
            @endforeach
        </div>
    </div>

</div>

@endsection
