<div style="margin-top:18px"><label>Nazwa działu<input name="name" maxlength="150" required value="{{ $department?->name ?? old('name') }}"></label></div>
<fieldset><legend>Osoby w dziale</legend><div class="fp-muted">Z tych osób wybierzesz koszty wynagrodzeń i samochodów. Sam skład działu nie daje dostępu do planu.</div><div class="fp-check-list">
    @foreach($staff as $person)<label class="fp-check"><input type="checkbox" name="member_ids[]" value="{{ $person->id }}" @checked($department ? $department->members->contains('id', $person->id) : in_array($person->id, old('member_ids', [])))>{{ $person->name }}</label>@endforeach
</div></fieldset>
<fieldset><legend>Osoby uprawnione do planowania</legend><div class="fp-muted">Właściciel działu ma dostęp automatycznie. Wskazane osoby potrzebują także uprawnienia „Plan finansowy działu” w ustawieniach użytkownika. Podgląd obejmuje wynagrodzenia.</div><div class="fp-check-list">
    @foreach($staff as $person)<label class="fp-check"><input type="checkbox" name="planner_ids[]" value="{{ $person->id }}" @checked($department ? $department->planners->contains('id', $person->id) : in_array($person->id, old('planner_ids', [])))>{{ $person->name }}</label>@endforeach
</div></fieldset>
