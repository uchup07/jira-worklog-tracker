@extends('layouts.app')

@section('content')

{{-- Stats row --}}
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Today</p>
        <p class="text-2xl font-bold text-white">{{ gmdate('G\h i\m', $todaySeconds) }}</p>
    </div>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">This Week</p>
        <p class="text-2xl font-bold text-white">{{ gmdate('G\h i\m', $weekSeconds) }}</p>
    </div>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Open Issues</p>
        <p class="text-2xl font-bold text-white">{{ $openIssues->count() }}</p>
    </div>
</div>

<div class="grid grid-cols-2 gap-4 mb-6">
    {{-- Recent worklogs --}}
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h2 class="text-sm font-semibold text-gray-300 mb-4">My Recent Worklogs</h2>
        @if($recentWorklogs->isEmpty())
            <p class="text-sm text-gray-500">No worklogs yet.</p>
        @else
            <div class="space-y-3">
                @foreach($recentWorklogs as $wl)
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-mono text-blue-400">{{ $wl->issue_key }}</span>
                            @if($wl->comment)
                                <p class="text-xs text-gray-400 mt-0.5 truncate max-w-48">{{ $wl->comment }}</p>
                            @endif
                        </div>
                        <div class="text-right shrink-0 ml-3">
                            <span class="text-sm font-semibold text-white">{{ gmdate('G\h i\m', $wl->time_spent_seconds) }}</span>
                            <p class="text-xs text-gray-500">{{ $wl->started_at?->format('M j') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('worklogs.index') }}" class="mt-4 inline-block text-xs text-blue-400 hover:text-blue-300">View all →</a>
        @endif
    </div>

    {{-- Team this week --}}
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h2 class="text-sm font-semibold text-gray-300 mb-4">Team This Week</h2>
        @if($teamWorklogs->isEmpty())
            <p class="text-sm text-gray-500">No team worklogs this week.</p>
        @else
            @php $maxSeconds = $teamWorklogs->max('total_seconds') ?: 1; @endphp
            <div class="space-y-3">
                @foreach($teamWorklogs as $member)
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-xs text-gray-300 truncate max-w-32">{{ $member->author_display_name }}</span>
                            <span class="text-xs font-semibold text-white">{{ gmdate('G\h i\m', $member->total_seconds) }}</span>
                        </div>
                        <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full"
                                 style="width: {{ round(($member->total_seconds / $maxSeconds) * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- Open issues --}}
<div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-300">My Open Issues</h2>
        <a href="{{ route('issues.index') }}" class="text-xs text-blue-400 hover:text-blue-300">View all →</a>
    </div>
    @if($openIssues->isEmpty())
        <p class="text-sm text-gray-500">No open issues assigned to you.</p>
    @else
        <div class="divide-y divide-gray-800">
            @foreach($openIssues as $issue)
                <div class="flex items-center justify-between py-2.5">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="text-xs font-mono font-semibold text-blue-400 bg-blue-950 px-1.5 py-0.5 rounded shrink-0">{{ $issue->issue_key }}</span>
                        <span class="text-sm text-gray-200 truncate">{{ $issue->summary }}</span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 ml-3">
                        <span class="text-xs text-gray-500 bg-gray-800 px-2 py-0.5 rounded">{{ $issue->status }}</span>
                        <a href="{{ route('worklogs.create', ['issue' => $issue->issue_key]) }}"
                           class="text-xs text-blue-400 hover:text-white hover:bg-blue-600 px-2 py-0.5 rounded border border-blue-700 hover:border-blue-600 transition-colors">
                            Log Time
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@endsection
