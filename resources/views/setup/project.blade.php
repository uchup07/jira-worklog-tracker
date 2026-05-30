<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Project — Worklog Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body style="background:var(--bg); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px;">

    <div style="width:100%; max-width:480px;">

        {{-- Logo mark --}}
        <div style="text-align:center; margin-bottom:32px;">
            <div class="display" style="font-size:28px; font-weight:800; font-style:italic; color:var(--text); letter-spacing:-0.03em; line-height:1;">
                Worklog
            </div>
            <div style="font-size:10px; font-weight:500; color:var(--text-muted); margin-top:4px; letter-spacing:0.1em; text-transform:uppercase;">
                Jira Tracker
            </div>
            <div style="width:32px; height:1px; background:var(--border-2); margin:14px auto 0;"></div>
        </div>

        <div class="card" style="padding:28px;">

            <div style="margin-bottom:20px;">
                <h2 style="font-size:15px; font-weight:600; color:var(--text); margin-bottom:4px;">Select a Project</h2>
                <p style="font-size:12px; color:var(--text-muted); line-height:1.5;">Choose the Jira project to track worklogs for.</p>
            </div>

            @if(empty($projects))
                <div style="padding:24px; text-align:center; border:1px dashed var(--border); border-radius:8px;">
                    <p style="font-size:13px; color:var(--text-muted);">No projects found.</p>
                    <p style="font-size:11.5px; color:var(--text-subtle); margin-top:4px;">Make sure your API token has project access.</p>
                </div>
                <a href="{{ route('setup') }}"
                   style="display:block; text-align:center; margin-top:16px; font-size:13px; color:var(--text-muted); text-decoration:none; transition:color 120ms;"
                   onmouseover="this.style.color='var(--text)'"
                   onmouseout="this.style.color='var(--text-muted)'">
                    ← Back to credentials
                </a>
            @else
                <form method="POST" action="{{ route('setup.project.store') }}" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf

                    <div style="display:flex; flex-direction:column; gap:6px; max-height:320px; overflow-y:auto; margin-bottom:20px; padding-right:2px;">
                        @foreach($projects as $project)
                            <label style="display:flex; align-items:center; gap:12px; padding:11px 14px; border-radius:7px; background:var(--surface-2); border:1px solid var(--border); cursor:pointer; transition:all 120ms; position:relative;"
                                   onmouseover="this.style.borderColor='var(--border-2)'"
                                   onmouseout="if(!this.querySelector('input').checked){ this.style.borderColor='var(--border)'; this.style.background='var(--surface-2)'; }">
                                <input type="radio"
                                       name="project_key"
                                       value="{{ $project['key'] }}"
                                       {{ $loop->first ? 'checked' : '' }}
                                       style="accent-color:var(--accent); width:14px; height:14px; flex-shrink:0;"
                                       onchange="document.querySelectorAll('[data-project-label]').forEach(el => { el.style.borderColor='var(--border)'; el.style.background='var(--surface-2)'; }); this.closest('label').style.borderColor='rgba(237,217,76,0.35)'; this.closest('label').style.background='var(--accent-dim)';"
                                       data-project-label>
                                <span class="mono" style="font-size:11px; font-weight:600; color:var(--accent); background:rgba(237,217,76,0.08); padding:2px 7px; border-radius:4px; border:1px solid rgba(237,217,76,0.15); flex-shrink:0;">{{ $project['key'] }}</span>
                                <span style="font-size:13px; color:var(--text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $project['name'] }}</span>
                            </label>
                        @endforeach
                    </div>

                    @error('project_key')
                        <p style="font-size:11.5px; color:var(--red); margin-bottom:14px;">{{ $message }}</p>
                    @enderror

                    <button type="submit"
                            class="btn btn-primary"
                            :disabled="submitting"
                            :class="{ 'opacity-60': submitting }"
                            style="width:100%; justify-content:center; padding:10px;">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2" x-show="!submitting">
                            <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span x-text="submitting ? 'Starting sync…' : 'Start Tracking'"></span>
                    </button>

                </form>

                <div style="margin-top:14px; text-align:center;">
                    <a href="{{ route('setup') }}"
                       style="font-size:11.5px; color:var(--text-subtle); text-decoration:none; transition:color 120ms;"
                       onmouseover="this.style.color='var(--text-muted)'"
                       onmouseout="this.style.color='var(--text-subtle)'">
                        ← Back to credentials
                    </a>
                </div>
            @endif

        </div>

    </div>

    <script>
        // Highlight the initially checked radio's label
        document.addEventListener('DOMContentLoaded', function () {
            const checked = document.querySelector('input[type=radio]:checked');
            if (checked) {
                checked.closest('label').style.borderColor = 'rgba(237,217,76,0.35)';
                checked.closest('label').style.background = 'var(--accent-dim)';
            }
        });
    </script>

</body>
</html>
