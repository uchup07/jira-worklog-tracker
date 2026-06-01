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
        $months = collect(range(0, 11))->map(function (int $i) {
            $date = now()->subMonths($i)->startOfMonth();

            return [
                'value' => $date->format('Y-m'),
                'label' => $date->format('F Y'),
            ];
        })->toArray();

        return [
            'rows' => [],
            'months' => $months,
            'targetHours' => 0.0,
            'workingDays' => 0,
        ];
    }
};
?>

<div>
    {{-- placeholder --}}
</div>
