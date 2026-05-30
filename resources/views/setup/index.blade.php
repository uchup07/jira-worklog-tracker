<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect to Jira — Worklog Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body style="background:var(--bg); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px;">

    <div style="width:100%; max-width:420px;">

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

            <div style="margin-bottom:22px;">
                <h2 style="font-size:15px; font-weight:600; color:var(--text); margin-bottom:4px;">Connect to Jira Cloud</h2>
                <p style="font-size:12px; color:var(--text-muted); line-height:1.5;">Enter your Atlassian credentials to start tracking worklogs.</p>
            </div>

            @if(session('error'))
                <div style="margin-bottom:18px; padding:10px 12px; background:rgba(255,94,94,0.07); border:1px solid rgba(255,94,94,0.2); border-radius:7px; font-size:12.5px; color:#FF5E5E; display:flex; align-items:flex-start; gap:8px;">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2" style="flex-shrink:0; margin-top:1px;">
                        <circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4M12 16h.01"/>
                    </svg>
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('setup.store') }}" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf

                <div style="display:flex; flex-direction:column; gap:16px;">

                    <div>
                        <label class="label" for="domain">Jira Domain</label>
                        <input type="text"
                               id="domain"
                               name="domain"
                               value="{{ old('domain', $prefill['domain'] ?? '') }}"
                               placeholder="mycompany.atlassian.net"
                               class="input {{ $errors->has('domain') ? 'error' : '' }}"
                               onfocus="this.style.borderColor='var(--border-2)'; this.style.boxShadow='0 0 0 3px var(--accent-ring)'"
                               onblur="this.style.borderColor=''; this.style.boxShadow=''">
                        @error('domain')
                            <p style="font-size:11.5px; color:var(--red); margin-top:5px;">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="label" for="email">Email</label>
                        <input type="email"
                               id="email"
                               name="email"
                               value="{{ old('email', $prefill['email'] ?? '') }}"
                               placeholder="you@example.com"
                               class="input {{ $errors->has('email') ? 'error' : '' }}"
                               onfocus="this.style.borderColor='var(--border-2)'; this.style.boxShadow='0 0 0 3px var(--accent-ring)'"
                               onblur="this.style.borderColor=''; this.style.boxShadow=''">
                        @error('email')
                            <p style="font-size:11.5px; color:var(--red); margin-top:5px;">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:5px;">
                            <label class="label" for="api_token" style="margin-bottom:0;">API Token</label>
                            <a href="https://id.atlassian.com/manage-profile/security/api-tokens"
                               target="_blank"
                               style="font-size:11px; color:var(--accent); text-decoration:none; opacity:0.8; transition:opacity 120ms;"
                               onmouseover="this.style.opacity='1'"
                               onmouseout="this.style.opacity='0.8'">
                                Get token →
                            </a>
                        </div>
                        <input type="password"
                               id="api_token"
                               name="api_token"
                               placeholder="••••••••••••••••••••"
                               class="input mono {{ $errors->has('api_token') ? 'error' : '' }}"
                               onfocus="this.style.borderColor='var(--border-2)'; this.style.boxShadow='0 0 0 3px var(--accent-ring)'"
                               onblur="this.style.borderColor=''; this.style.boxShadow=''">
                        @error('api_token')
                            <p style="font-size:11.5px; color:var(--red); margin-top:5px;">{{ $message }}</p>
                        @enderror
                    </div>

                </div>

                <button type="submit"
                        class="btn btn-primary"
                        :disabled="submitting"
                        :class="{ 'opacity-60': submitting }"
                        style="width:100%; justify-content:center; margin-top:22px; padding:10px;">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2" x-show="!submitting">
                        <path stroke-linecap="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span x-text="submitting ? 'Connecting…' : 'Connect to Jira'"></span>
                </button>

            </form>
        </div>

        <p style="text-align:center; font-size:11px; color:var(--text-subtle); margin-top:16px; line-height:1.6;">
            Credentials are stored locally on this device.<br>Never sent to any third party.
        </p>

    </div>

</body>
</html>
