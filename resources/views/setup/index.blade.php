<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect to Jira — Worklog Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body style="background:var(--bg); min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:24px; -webkit-app-region:drag;">

    <div class="titlebar-no-drag" style="width:100%; max-width:400px;">

        <div style="text-align:center; margin-bottom:28px;">
            <div style="width:36px; height:36px; border-radius:9px; background:var(--accent); display:flex; align-items:center; justify-content:center; margin:0 auto 14px;">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="oklch(0.109 0 0)" stroke-width="2.2">
                    <circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/>
                </svg>
            </div>
            <h1 style="font-size:18px; font-weight:700; color:var(--text); letter-spacing:-0.025em; line-height:1;">
                Worklog Tracker
            </h1>
            <p style="font-size:12.5px; color:var(--text-muted); margin-top:5px;">
                Connect your Jira Cloud account to get started
            </p>
        </div>

        <div class="card" style="padding:24px;">

            @if(session('error'))
                <div style="margin-bottom:16px; padding:9px 12px; background:oklch(0.65 0.22 25 / 0.08); border:1px solid oklch(0.65 0.22 25 / 0.2); border-radius:var(--radius); font-size:12.5px; color:var(--red); display:flex; gap:8px; align-items:flex-start;">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2" style="flex-shrink:0; margin-top:1px;"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4M12 16h.01"/></svg>
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('setup.store') }}"
                  x-data="{ submitting: false }" @submit="submitting = true">
                @csrf

                <div style="display:flex; flex-direction:column; gap:14px;">

                    <div>
                        <label class="label" for="domain">Jira Domain</label>
                        <input type="text" id="domain" name="domain"
                               value="{{ old('domain', $prefill['domain'] ?? '') }}"
                               placeholder="mycompany.atlassian.net"
                               class="input {{ $errors->has('domain') ? 'error' : '' }}">
                        @error('domain')
                            <p style="font-size:11.5px; color:var(--red); margin-top:4px;">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="label" for="email">Email</label>
                        <input type="email" id="email" name="email"
                               value="{{ old('email', $prefill['email'] ?? '') }}"
                               placeholder="you@example.com"
                               class="input {{ $errors->has('email') ? 'error' : '' }}">
                        @error('email')
                            <p style="font-size:11.5px; color:var(--red); margin-top:4px;">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:5px;">
                            <label class="label" for="api_token" style="margin-bottom:0;">API Token</label>
                            <a href="https://id.atlassian.com/manage-profile/security/api-tokens"
                               target="_blank"
                               style="font-size:11.5px; color:var(--accent); text-decoration:none; opacity:0.85; transition:opacity 100ms;"
                               onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.85'">
                                Get token →
                            </a>
                        </div>
                        <input type="password" id="api_token" name="api_token"
                               placeholder="••••••••••••••••••••"
                               class="input mono {{ $errors->has('api_token') ? 'error' : '' }}">
                        @error('api_token')
                            <p style="font-size:11.5px; color:var(--red); margin-top:4px;">{{ $message }}</p>
                        @enderror
                    </div>

                </div>

                <button type="submit" class="btn btn-primary"
                        :disabled="submitting" :class="{ 'opacity-50': submitting }"
                        style="width:100%; justify-content:center; margin-top:20px; padding:8px 12px;">
                    <svg width="13" height="13" fill="none" stroke="currentColor"
                         viewBox="0 0 24 24" stroke-width="2.2" x-show="!submitting">
                        <path stroke-linecap="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span x-text="submitting ? 'Connecting…' : 'Connect to Jira'"></span>
                </button>
            </form>
        </div>

        <p style="text-align:center; font-size:11px; color:var(--text-subtle); margin-top:14px; line-height:1.6;">
            Credentials are stored locally on this device only.
        </p>
    </div>

</body>
</html>
