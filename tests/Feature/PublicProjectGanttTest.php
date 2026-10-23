<?php

use App\Models\CompanySettings;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\URL;

test('public project schedule works anonymously with assets on the link origin', function () {
    CompanySettings::create([
        'name' => 'Test',
        'logo_data' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=',
        'logo_mime' => 'image/png',
    ]);
    $project = Project::create([
        'number' => 'PUBLIC/1', 'name' => 'Public schedule', 'status' => 'active',
        'public_gantt_token' => str_repeat('a', 48),
        'start_date' => '2026-09-24', 'end_date' => '2027-01-18',
    ]);
    Task::create([
        'project_id' => $project->id, 'title' => 'Visible task',
        'start_date' => '2026-10-06', 'due_date' => '2026-10-07',
        'status' => 'in_progress', 'progress' => 20,
    ]);
    Project::create([
        'number' => 'PRIVATE/1', 'name' => 'Other private project', 'status' => 'active',
    ]);
    URL::forceRootUrl('https://canonical.example');
    URL::forceScheme('https');
    try {
        $this->get('/public/project-gantt/'.$project->public_gantt_token)
            ->assertOk()->assertSee('Visible task')->assertDontSee('Other private project')
            ->assertSee('src="data:image/png;base64,', false)
            ->assertSee('href="/vendor/frappe-gantt/frappe-gantt.css"', false)
            ->assertSee('src="/vendor/frappe-gantt/frappe-gantt.min.js"', false)
            ->assertSee('src="/js/project-gantt-colors.js', false)
            ->assertSee('src="/js/table-sort.js', false)
            ->assertDontSee('https://canonical.example', false)
            ->assertDontSee('cdn.jsdelivr.net', false);
        expect($project->fresh()->public_gantt_token)->toBe(str_repeat('a', 48));
        $this->get('/public/project-gantt/invalid-token')->assertNotFound();
    } finally {
        URL::forceRootUrl(null);
        URL::forceScheme(null);
    }
});
