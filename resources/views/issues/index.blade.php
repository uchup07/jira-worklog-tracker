@extends('layouts.app')

@section('content')

<div class="flex items-center justify-between mb-5">
    <h1 class="text-lg font-semibold text-white">My Issues</h1>

    <div class="flex items-center gap-2">
        <a href="{{ route('issues.index') }}"
           class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors
                  {{ !request('status') ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white' }}">
            All
        </a>
        <a href="{{ route('issues.index', ['status' => 'open']) }}"
           class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors
                  {{ request('status') === 'open' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white' }}">
            Open
        </a>
    </div>
</div>

@if($issues->isEmpty())
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-8 text-center">
        <p class="text-gray-500">No issues assigned to you. Try syncing first.</p>
    </div>
@else
    <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-800">
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Key</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Summary</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Type</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Priority</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @foreach($issues as $issue)
                    <tr class="hover:bg-gray-800/50">
                        <td class="px-4 py-3">
                            <span class="font-mono text-xs font-semibold text-blue-400 bg-blue-950 px-1.5 py-0.5 rounded">
                                {{ $issue->issue_key }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-200 max-w-sm">
                            <span class="truncate block">{{ $issue->summary }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $issue->issue_type }}</td>
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $issue->priority ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs text-gray-500 bg-gray-800 px-2 py-0.5 rounded">{{ $issue->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('worklogs.create', ['issue' => $issue->issue_key]) }}"
                               class="text-xs text-blue-400 hover:text-white hover:bg-blue-600 px-2 py-1 rounded border border-blue-700 hover:border-blue-600 transition-colors">
                                Log Time
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@endsection
