<!DOCTYPE html>
<html lang="en"
      data-theme="{{ $appTheme }}"
      x-data="tallstackui_darkTheme({ default: @js($appTheme), name: 'app-theme' })"
      x-bind:data-theme="darkTheme ? 'dark' : 'light'"
      x-effect="window.appTheme && window.appTheme.sync(darkTheme ? 'dark' : 'light')">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Connect to Jira — Worklog Tracker</title>
    <tallstackui:script />
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-bind:class="{ dark: darkTheme }"
      style="background:var(--bg); min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:24px; -webkit-app-region:drag;">

    <div class="titlebar-no-drag" style="position:fixed; top:16px; right:16px; z-index:20;">
        <x-theme-switch simple only-icons sm class="app-theme-switch" />
    </div>

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

        <x-card style="padding:22px;">

            @if(session('error'))
                <x-alert text="{{ session('error') }}" color="red" class="mb-4" />
            @endif

            <form method="POST" action="{{ route('setup.store') }}"
                  x-data="{ submitting: false }" @submit="submitting = true">
                @csrf

                <div style="display:flex; flex-direction:column; gap:14px;">

                    <x-input label="Jira Domain"
                             name="domain"
                             placeholder="mycompany.atlassian.net"
                             :value="old('domain', $prefill['domain'] ?? '')"
                             :error="$errors->first('domain')" />

                    <x-input label="Email"
                             name="email"
                             type="email"
                             placeholder="you@example.com"
                             :value="old('email', $prefill['email'] ?? '')"
                             :error="$errors->first('email')" />

                    <div>
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                            <span style="font-size:12px; font-weight:500; color:var(--text-muted);">API Token</span>
                            <a href="https://id.atlassian.com/manage-profile/security/api-tokens"
                               target="_blank"
                               style="font-size:11.5px; color:var(--accent); text-decoration:none; opacity:0.85;"
                               onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.85'">
                                Get token →
                            </a>
                        </div>
                        <x-password name="api_token"
                                    placeholder="Your Atlassian API token"
                                    :error="$errors->first('api_token')" />
                    </div>

                </div>

                <div style="margin-top:20px;">
                    <x-button type="submit"
                              color="primary"
                              full
                              x-bind:loading="submitting">
                        Connect to Jira
                    </x-button>
                </div>
            </form>
        </x-card>

        <p style="text-align:center; font-size:11px; color:var(--text-subtle); margin-top:14px; line-height:1.6;">
            Credentials are stored locally on this device only.
        </p>

        {{-- SMTP / Email Settings --}}
        <div style="margin-top:24px;">
            <h2 style="font-size:14px; font-weight:700; color:var(--text); margin-bottom:12px; letter-spacing:-0.02em;">Email / SMTP</h2>
            <x-card style="padding:22px;">
                <form method="POST" action="{{ route('setup.smtp') }}">
                    @csrf
                    <div style="display:flex; flex-direction:column; gap:14px;">
                        <x-input label="SMTP Host"
                                 name="smtp_host"
                                 placeholder="smtp.gmail.com"
                                 :value="old('smtp_host', \Native\Desktop\Facades\Settings::get('smtp_host', ''))"
                                 :error="$errors->first('smtp_host')" />
                        <x-input label="SMTP Port"
                                 name="smtp_port"
                                 placeholder="587"
                                 :value="old('smtp_port', \Native\Desktop\Facades\Settings::get('smtp_port', '587'))"
                                 :error="$errors->first('smtp_port')" />
                        <x-input label="SMTP Username"
                                 name="smtp_username"
                                 placeholder="you@example.com"
                                 :value="old('smtp_username', \Native\Desktop\Facades\Settings::get('smtp_username', ''))"
                                 :error="$errors->first('smtp_username')" />
                        <x-input label="SMTP Password"
                                 name="smtp_password"
                                 type="password"
                                 placeholder="App password or SMTP password"
                                 :value="old('smtp_password', \Native\Desktop\Facades\Settings::get('smtp_password', ''))"
                                 :error="$errors->first('smtp_password')" />
                        <x-input label="From Address"
                                 name="smtp_from_address"
                                 type="email"
                                 placeholder="you@example.com"
                                 :value="old('smtp_from_address', \Native\Desktop\Facades\Settings::get('smtp_from_address', ''))"
                                 :error="$errors->first('smtp_from_address')" />
                        <x-input label="From Name"
                                 name="smtp_from_name"
                                 placeholder="Worklog Tracker"
                                 :value="old('smtp_from_name', \Native\Desktop\Facades\Settings::get('smtp_from_name', 'Worklog Tracker'))"
                                 :error="$errors->first('smtp_from_name')" />
                        <div>
                            <label style="font-size:12px; font-weight:500; color:var(--text-muted); display:block; margin-bottom:6px;">Encryption</label>
                            <select name="smtp_encryption"
                                    style="background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius); padding:7px 10px; font-size:13px; color:var(--text); width:100%; outline:none;">
                                <option value="">None</option>
                                <option value="tls" @selected(\Native\Desktop\Facades\Settings::get('smtp_encryption') === 'tls')>TLS</option>
                                <option value="ssl" @selected(\Native\Desktop\Facades\Settings::get('smtp_encryption') === 'ssl')>SSL</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top:20px;">
                        <x-button type="submit" color="primary" full>Save SMTP Settings</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>

    @livewireScripts
</body>
</html>
