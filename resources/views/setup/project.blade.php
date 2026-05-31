<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Project — Worklog Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body style="background:var(--bg); min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:24px; -webkit-app-region:drag;">

    <div class="titlebar-no-drag" style="width:100%; max-width:460px;">

        <div style="text-align:center; margin-bottom:28px;">
            <div style="width:36px; height:36px; border-radius:9px; background:var(--accent); display:flex; align-items:center; justify-content:center; margin:0 auto 14px;">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="oklch(0.109 0 0)" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                    <path stroke-linecap="round" d="M9 12l2 2 4-4"/>
                </svg>
            </div>
            <h1 style="font-size:18px; font-weight:700; color:var(--text); letter-spacing:-0.025em; line-height:1;">
                Select a Project
            </h1>
            <p style="font-size:12.5px; color:var(--text-muted); margin-top:5px;">
                Choose the Jira project to track worklogs for
            </p>
        </div>

        <div class="card" style="padding:20px;">

            @if(empty($projects))
                <div style="padding:24px; text-align:center; border:1px dashed var(--border-2); border-radius:var(--radius);">
                    <p style="font-size:13px; color:var(--text-muted);">No projects found.</p>
                    <p style="font-size:11.5px; color:var(--text-subtle); margin-top:4px;">Ensure your API token has project access.</p>
                </div>
                <a href="{{ route('setup') }}"
                   style="display:block; text-align:center; margin-top:16px; font-size:12.5px; color:var(--text-muted); text-decoration:none; transition:color 100ms;"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text-muted)'">
                    ← Back to credentials
                </a>
            @else
                <form method="POST" action="{{ route('setup.project.store') }}"
                      x-data="{ submitting: false, selected: '{{ $projects[0]['key'] ?? '' }}' }"
                      @submit="submitting = true">
                    @csrf

                    <div style="display:flex; flex-direction:column; gap:5px; max-height:300px; overflow-y:auto; margin-bottom:16px;">
                        @foreach($projects as $project)
                            <label x-bind:style="selected === '{{ $project['key'] }}' ? 'border-color:oklch(0.857 0.168 87.5 / 0.35); background:var(--accent-dim);' : ''"
                                   style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:var(--radius); background:var(--surface-2); border:1px solid var(--border); cursor:pointer; transition:all 100ms;">
                                <input type="radio" name="project_key" value="{{ $project['key'] }}"
                                       {{ $loop->first ? 'checked' : '' }}
                                       @change="selected = '{{ $project['key'] }}'"
                                       style="accent-color:var(--accent); width:13px; height:13px; flex-shrink:0;">
                                <span class="mono" style="font-size:11px; font-weight:500; color:var(--accent); background:var(--accent-dim); padding:2px 6px; border-radius:3px; flex-shrink:0;">
                                    {{ $project['key'] }}
                                </span>
                                <span style="font-size:13px; color:var(--text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    {{ $project['name'] }}
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @error('project_key')
                        <p style="font-size:11.5px; color:var(--red); margin-bottom:12px;">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="btn btn-primary"
                            :disabled="submitting" :class="{ 'opacity-50': submitting }"
                            style="width:100%; justify-content:center; padding:8px 12px;">
                        <svg width="13" height="13" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24" stroke-width="2.2" x-show="!submitting">
                            <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span x-text="submitting ? 'Starting sync…' : 'Start Tracking'"></span>
                    </button>

                </form>

                <div style="text-align:center; margin-top:12px;">
                    <a href="{{ route('setup') }}"
                       style="font-size:11.5px; color:var(--text-subtle); text-decoration:none; transition:color 100ms;"
                       onmouseover="this.style.color='var(--text-muted)'" onmouseout="this.style.color='var(--text-subtle)'">
                        ← Back to credentials
                    </a>
                </div>
            @endif

        </div>

    </div>

</body>
</html>
