<x-app-layout>
    <div style="display:flex; align-items:center; margin-bottom:20px;">
        <div>
            <h1 style="font-size:18px; font-weight:700; color:var(--text); letter-spacing:-0.025em; line-height:1;">Employee Utilization</h1>
            <p style="font-size:12px; color:var(--text-muted); margin-top:3px;">Logged hours vs. 8h/day target per team member</p>
        </div>
    </div>

    <livewire:utilization />
</x-app-layout>
