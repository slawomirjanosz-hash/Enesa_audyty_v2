<input type="hidden" name="show_projects" value="{{ (int)$showProjects }}">
<input type="hidden" name="project_scope" value="{{ $projectScope }}">
@foreach($selectedProjectIds as $id)<input type="hidden" name="project_ids[]" value="{{ $id }}">@endforeach
<input type="hidden" name="show_leaves" value="{{ (int)$showLeaves }}">
<input type="hidden" name="show_sick_leave" value="{{ (int)$showSickLeave }}">
