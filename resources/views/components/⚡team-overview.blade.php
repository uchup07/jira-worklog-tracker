<?php

use Livewire\Component;
use Native\Desktop\Facades\Settings;

new class extends Component
{
    public string $period = 'month';

    public string $selectedProject = '';

    public function mount(): void
    {
        $this->selectedProject = Settings::get('selected_project_key', '');
    }

    public function with(): array
    {
        return [
            'availableProjects' => [],
            'totalWorkSeconds' => 0,
            'totalWorklogsToday' => 0,
            'totalWorklogsMonth' => 0,
            'activeUsers' => 0,
            'usersNotLogging' => collect(),
            'topContributors' => collect(),
            'worklogsPerStatus' => collect(),
            'worklogsPerProject' => collect(),
        ];
    }
};
?>

<div wire:poll.300s="pollRefresh" style="padding:16px 18px;">
    <p style="color:var(--text);">Team Overview — scaffold</p>
</div>
