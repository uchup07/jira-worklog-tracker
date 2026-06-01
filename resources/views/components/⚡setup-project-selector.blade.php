<?php

use Livewire\Component;
use Native\Desktop\Facades\Alert;
use Native\Desktop\Facades\Settings;

new class extends Component
{
    public array $projects = [];

    public string $currentProjectKey = '';

    public string $currentProjectName = '';

    public function mount(array $projects): void
    {
        $this->projects = collect($projects)
            ->filter(fn (mixed $project) => is_array($project) && filled($project['key'] ?? null))
            ->map(fn (array $project) => [
                'key' => (string) $project['key'],
                'name' => (string) ($project['name'] ?? $project['key']),
            ])
            ->values()
            ->all();

        $this->currentProjectKey = Settings::get('selected_project_key', '');
        $this->currentProjectName = Settings::get('selected_project_name', '');
    }

    public function confirmProjectSelection(string $projectKey, string $projectName): void
    {
        $projectKey = trim($projectKey);
        $projectName = trim($projectName) !== '' ? trim($projectName) : $projectKey;

        if ($projectKey === '') {
            return;
        }

        $selection = Alert::title('Change tracked project?')
            ->type('question')
            ->detail("Project: {$projectName} ({$projectKey})\nA background sync will start after switching.")
            ->buttons(['Continue', 'Cancel'])
            ->defaultId(0)
            ->cancelId(1)
            ->show('Use this Jira project for worklog tracking?');

        if ($selection !== 0) {
            return;
        }

        $this->dispatch('project-confirmed', projectKey: $projectKey, projectName: $projectName);
    }
};
?>

<div
    x-data="{
        submitting: false,
        requestSelection(event) {
            if (this.submitting) {
                return;
            }

            const row = event.target.closest('[data-list-row]');

            if (! row || ! this.$root.contains(row)) {
                return;
            }

            const projectKey = row.dataset.listName || '';
            const projectName = row.dataset.listCaption || projectKey;

            if (! projectKey) {
                return;
            }

            $wire.confirmProjectSelection(projectKey, projectName);
        },
        submitSelection(detail) {
            if (! detail?.projectKey) {
                return;
            }

            this.submitting = true;
            this.$refs.projectKey.value = detail.projectKey;
            this.$refs.projectName.value = detail.projectName || detail.projectKey;
            this.$refs.form.submit();
        }
    }"
    x-on:project-confirmed.window="submitSelection($event.detail)"
>
    <style>
        [data-project-selector] [data-list-row] {
            cursor: pointer;
        }
    </style>

    <form x-ref="form" method="POST" action="{{ route('setup.project.store') }}" class="hidden">
        @csrf
        <input x-ref="projectKey" type="hidden" name="project_key">
        <input x-ref="projectName" type="hidden" name="project_name">
    </form>

    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px;">
        <div>
            <p style="font-size:11px; letter-spacing:0.08em; text-transform:uppercase; color:var(--text-subtle);">Projects</p>
            <p style="font-size:12.5px; color:var(--text-muted); margin-top:3px;">Click a project row to switch and start sync.</p>
        </div>

        @if($currentProjectKey !== '')
            <div style="text-align:right;">
                <p style="font-size:11px; color:var(--text-subtle);">Current</p>
                <p class="mono" style="font-size:12px; color:var(--accent); margin-top:3px;">{{ $currentProjectKey }}</p>
            </div>
        @endif
    </div>

    <div data-project-selector x-on:click="requestSelection($event)">
        <x-list searchable height="80" search-placeholder="Search project key or name">
            @foreach($projects as $project)
                <x-list.items :name="$project['key']" :caption="$project['name']">
                    @if($project['key'] === $currentProjectKey)
                        <x-badge text="Current" color="green" xs style="margin-left:8px;" />
                    @endif
                </x-list.items>
            @endforeach

            <x-slot:empty>
                <div style="padding:18px 10px; text-align:center;">
                    <p style="font-size:12.5px; color:var(--text-muted);">No matching project found.</p>
                </div>
            </x-slot:empty>
        </x-list>
    </div>

    @error('project_key')
        <p style="font-size:11.5px; color:var(--red); margin-top:12px;">{{ $message }}</p>
    @enderror

    @error('project_name')
        <p style="font-size:11.5px; color:var(--red); margin-top:8px;">{{ $message }}</p>
    @enderror
</div>
