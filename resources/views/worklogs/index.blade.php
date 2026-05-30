@extends('layouts.app')

@section('content')

<div class="flex items-center justify-between mb-5">
    <h1 class="text-lg font-semibold text-white">Worklogs</h1>
    <a href="{{ route('worklogs.create') }}"
       class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition-colors">
        + New Worklog
    </a>
</div>

<form method="GET" action="{{ route('worklogs.index') }}" class="flex items-center gap-3 mb-5">
    <select name="author" class="bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">All authors</option>
        @foreach($authors as $author)
            <option value="{{ $author->author_account_id }}"
                    {{ request('author') === $author->author_account_id ? 'selected' : '' }}>
                {{ $author->author_display_name }}
            </option>
        @endforeach
    </select>

    <input type="date" name="from" value="{{ request('from') }}"
           class="bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
    <span class="text-gray-500 text-sm">to</span>
    <input type="date" name="to" value="{{ request('to') }}"
           class="bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">

    <button type="submit" class="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm rounded-lg border border-gray-700 transition-colors">
        Filter
    </button>

    @if(request()->hasAny(['author', 'from', 'to', 'mine']))
        <a href="{{ route('worklogs.index') }}" class="text-xs text-gray-500 hover:text-gray-300">Clear</a>
    @endif

    <label class="flex items-center gap-2 ml-auto text-sm text-gray-400 cursor-pointer">
        <input type="checkbox" name="mine" value="1" {{ request()->boolean('mine') ? 'checked' : '' }}
               class="rounded bg-gray-700 border-gray-600 text-blue-500">
        Mine only
    </label>
</form>

@if($worklogs->isEmpty())
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-8 text-center">
        <p class="text-gray-500">No worklogs found. Try adjusting your filters or sync first.</p>
    </div>
@else
    <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-800">
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Issue</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Author</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Date</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Time</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Comment</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @foreach($worklogs as $wl)
                    <tr class="hover:bg-gray-800/50">
                        <td class="px-4 py-3">
                            <span class="font-mono text-xs font-semibold text-blue-400 bg-blue-950 px-1.5 py-0.5 rounded">{{ $wl->issue_key }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-300">{{ $wl->author_display_name }}</td>
                        <td class="px-4 py-3 text-gray-400">{{ $wl->started_at?->format('M j, Y') }}</td>
                        <td class="px-4 py-3 font-semibold text-white">{{ gmdate('G\h i\m', $wl->time_spent_seconds) }}</td>
                        <td class="px-4 py-3 text-gray-400 max-w-xs truncate">{{ $wl->comment ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $worklogs->appends(request()->query())->links() }}
    </div>
@endif

@endsection
