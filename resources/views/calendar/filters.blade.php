@if($projectsEnabled || $canViewCalendarLeaves)
<form method="GET" action="{{ route('calendar.index') }}" class="calendar-actions" style="margin-bottom:18px">
    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
    <input type="hidden" name="scope" value="{{ $scope }}">
    @foreach($selectedUserIds as $id)<input type="hidden" name="user_ids[]" value="{{ $id }}">@endforeach
    @if($showUnassigned)<input type="hidden" name="unassigned" value="1">@endif
    <input type="hidden" name="show_projects" value="0">
    @if($projectsEnabled)
        <label class="calendar-btn"><input type="checkbox" name="show_projects" value="1" @checked($showProjects)> Projekty</label>
        <details class="calendar-people calendar-projects">
            <summary class="calendar-btn">{{ $projectScope === 'all' ? 'Wszystkie' : count($selectedProjectIds).' wybr.' }}</summary>
            <div class="calendar-people-options">
                <label><input type="radio" name="project_scope" value="all" @checked($projectScope === 'all')> Wszystkie projekty</label>
                <label><input type="radio" name="project_scope" value="selected" @checked($projectScope === 'selected')> Wybrane projekty</label>
                <div class="calendar-people-list">
                    @foreach($calendarProjects as $project)
                        <label><input type="checkbox" name="project_ids[]" value="{{ $project->id }}" @checked(in_array($project->id, $selectedProjectIds, true)) onchange="this.form.querySelector('[name=project_scope][value=selected]').checked=true"> {{ $project->number }} · {{ $project->name }}</label>
                    @endforeach
                </div>
                <button class="calendar-btn" type="submit">Pokaż</button>
            </div>
        </details>
    @endif
    @if($canViewCalendarLeaves)
        <input type="hidden" name="show_leaves" value="0">
        <label class="calendar-btn"><input type="checkbox" name="show_leaves" value="1" @checked($showLeaves)> Pokaż urlopy</label>
        <input type="hidden" name="show_sick_leave" value="0">
        <label class="calendar-btn"><input type="checkbox" name="show_sick_leave" value="1" @checked($showSickLeave)> Pokaż L4</label>
    @else
        <input type="hidden" name="show_leaves" value="{{ (int)$showLeaves }}">
        <input type="hidden" name="show_sick_leave" value="{{ (int)$showSickLeave }}">
    @endif
    <button type="submit" class="calendar-btn">Pokaż</button>
</form>
@endif
