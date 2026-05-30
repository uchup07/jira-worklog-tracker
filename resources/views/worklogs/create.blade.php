@extends('layouts.app')

@section('content')

<div class="max-w-xl">
    <h1 class="text-lg font-semibold text-white mb-5">Log Time</h1>

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <form method="POST" action="{{ route('worklogs.store') }}">
            @csrf

            <div class="space-y-5">
                <div>
                    <label for="issue_key" class="block text-sm font-medium text-gray-300 mb-1.5">Issue</label>
                    <select id="issue_key" name="issue_key"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3.5 py-2.5 text-sm text-white
                                   focus:outline-none focus:ring-2 focus:ring-blue-500
                                   @error('issue_key') border-red-500 @enderror">
                        <option value="">Select an issue…</option>
                        @foreach($issues as $issue)
                            <option value="{{ $issue->issue_key }}"
                                    {{ (old('issue_key', $selectedIssue) === $issue->issue_key) ? 'selected' : '' }}>
                                {{ $issue->issue_key }} — {{ $issue->summary }}
                            </option>
                        @endforeach
                    </select>
                    @error('issue_key')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="time_spent" class="block text-sm font-medium text-gray-300 mb-1.5">Time Spent</label>
                    <input type="text" id="time_spent" name="time_spent" value="{{ old('time_spent') }}"
                           placeholder="e.g. 1h 30m, 2h, 45m"
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3.5 py-2.5 text-sm text-white placeholder-gray-500
                                  focus:outline-none focus:ring-2 focus:ring-blue-500
                                  @error('time_spent') border-red-500 @enderror">
                    @error('time_spent')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="started_at" class="block text-sm font-medium text-gray-300 mb-1.5">Date</label>
                    <input type="date" id="started_at" name="started_at"
                           value="{{ old('started_at', now()->toDateString()) }}"
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3.5 py-2.5 text-sm text-white
                                  focus:outline-none focus:ring-2 focus:ring-blue-500
                                  @error('started_at') border-red-500 @enderror">
                    @error('started_at')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="comment" class="block text-sm font-medium text-gray-300 mb-1.5">
                        Comment <span class="text-gray-500 font-normal">(optional)</span>
                    </label>
                    <textarea id="comment" name="comment" rows="3"
                              placeholder="What did you work on?"
                              class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3.5 py-2.5 text-sm text-white placeholder-gray-500
                                     focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none
                                     @error('comment') border-red-500 @enderror">{{ old('comment') }}</textarea>
                    @error('comment')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center gap-3 mt-6">
                <button type="submit"
                        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg text-sm transition-colors">
                    Log Time
                </button>
                <a href="{{ route('worklogs.index') }}"
                   class="px-5 py-2.5 text-gray-400 hover:text-white text-sm transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

@endsection
