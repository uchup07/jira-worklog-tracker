@extends('layouts.app')
@section('title', 'Worklogs')

@section('content')

<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
    <div>
        <h1 style="font-size:18px; font-weight:700; color:var(--text); letter-spacing:-0.025em; line-height:1;">Worklogs</h1>
        <p style="font-size:12px; color:var(--text-muted); margin-top:3px;">All logged time for the project</p>
    </div>
    <x-button href="{{ route('worklogs.create') }}" color="primary">
        Log Time
    </x-button>
</div>

<livewire:worklog-filter />

@endsection
