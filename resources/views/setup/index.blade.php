<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect to Jira — Worklog Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-950 text-gray-100 flex items-center justify-center min-h-screen">

    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-white">Jira Worklog Tracker</h1>
            <p class="text-gray-400 mt-2 text-sm">Connect your Jira Cloud account to get started</p>
        </div>

        <div class="bg-gray-900 rounded-xl border border-gray-800 p-8">
            <form method="POST" action="{{ route('setup.store') }}">
                @csrf

                <div class="space-y-5">
                    <div>
                        <label for="domain" class="block text-sm font-medium text-gray-300 mb-1.5">Jira Domain</label>
                        <input type="text"
                               id="domain"
                               name="domain"
                               value="{{ old('domain', $prefill['domain'] ?? '') }}"
                               placeholder="mycompany.atlassian.net"
                               class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3.5 py-2.5 text-sm text-white placeholder-gray-500
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                      @error('domain') border-red-500 @enderror">
                        @error('domain')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-300 mb-1.5">Email</label>
                        <input type="email"
                               id="email"
                               name="email"
                               value="{{ old('email', $prefill['email'] ?? '') }}"
                               placeholder="you@example.com"
                               class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3.5 py-2.5 text-sm text-white placeholder-gray-500
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                      @error('email') border-red-500 @enderror">
                        @error('email')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="api_token" class="block text-sm font-medium text-gray-300">API Token</label>
                            <a href="https://id.atlassian.com/manage-profile/security/api-tokens"
                               target="_blank"
                               class="text-xs text-blue-400 hover:text-blue-300">How to get a token →</a>
                        </div>
                        <input type="password"
                               id="api_token"
                               name="api_token"
                               placeholder="••••••••••••••••••••"
                               class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3.5 py-2.5 text-sm text-white placeholder-gray-500
                                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                      @error('api_token') border-red-500 @enderror">
                        @error('api_token')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <button type="submit"
                        class="mt-6 w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg text-sm transition-colors">
                    Connect to Jira
                </button>
            </form>
        </div>
    </div>

</body>
</html>
