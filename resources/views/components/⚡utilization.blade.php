<?php

use Livewire\Component;
use Native\Desktop\Facades\Settings;

new class extends Component
{
    public string $month = '';

    public string $selectedProject = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->selectedProject = Settings::get('selected_project_key', '');
    }

    public function with(): array
    {
        return [
            'rows' => [],
            'months' => [],
            'targetHours' => 0.0,
            'workingDays' => 0,
        ];
    }
};
?>

<div>
    {{-- placeholder --}}
</div>
