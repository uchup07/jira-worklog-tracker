<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Project — Worklog Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-950 text-gray-100 flex items-center justify-center min-h-screen">

    <div class="w-full max-w-lg">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-white">Select a Project</h1>
            <p class="text-gray-400 mt-2 text-sm">Choose the Jira project to track worklogs for</p>
        </div>

        <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
            @if(empty($projects))
                <p class="text-gray-500 text-sm text-center py-4">No projects found. Make sure your API token has project access.</p>
            @else
                <form method="POST" action="{{ route('setup.project.store') }}">
                    @csrf
                    <div class="space-y-2 max-h-80 overflow-y-auto">
                        @foreach($projects as $project)
                            <label class="flex items-center gap-3 px-4 py-3 rounded-lg bg-gray-800 hover:bg-gray-750 cursor-pointer border border-transparent has-[:checked]:border-blue-500 has-[:checked]:bg-blue-950/40 transition-colors">
                                <input type="radio"
                                       name="project_key"
                                       value="{{ $project['key'] }}"
                                       {{ $loop->first ? 'checked' : '' }}
                                       class="text-blue-500 bg-gray-700 border-gray-600">
                                <span class="text-xs font-mono font-semibold text-blue-400 bg-blue-950 px-1.5 py-0.5 rounded">{{ $project['key'] }}</span>
                                <span class="text-sm text-gray-200">{{ $project['name'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('project_key')
                        <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                    <button type="submit"
                            class="mt-5 w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg text-sm transition-colors">
                        Start Tracking
                    </button>
                </form>
            @endif
        </div>
    </div>

</body>
</html>
