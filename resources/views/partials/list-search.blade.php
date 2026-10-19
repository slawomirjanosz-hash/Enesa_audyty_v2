@php
    $searchId = $searchId ?? 'list-search';
    $serverSearch = $serverSearch ?? false;
@endphp
@if($serverSearch)
<form method="GET" action="{{ url()->current() }}" class="app-header-search" data-list-search-form role="search">
    @foreach(\Illuminate\Support\Arr::dot(request()->except(['q', 'page', 'completed_page'])) as $key => $value)
        @if(is_scalar($value))<input type="hidden" name="{{ preg_replace('/\.([^\.]+)/', '[$1]', $key) }}" value="{{ $value }}">@endif
    @endforeach
@else
<div class="app-header-search">
@endif
    <i class="ti ti-search" aria-hidden="true"></i>
    <input type="search" id="{{ $searchId }}" @if($serverSearch) name="q" value="{{ is_string(request('q')) ? request('q') : '' }}" @else data-list-search="{{ $searchTargets }}" @endif autocomplete="off" maxlength="{{ $searchMaxLength ?? 200 }}" aria-label="{{ $searchPlaceholder ?? 'Szukaj na liście' }}" placeholder="{{ $searchPlaceholder ?? 'Szukaj na liście…' }}">
    <div class="app-header-search-result" data-list-search-result aria-live="polite">@if($serverSearch && request()->filled('q'))Wyniki dla wpisanej frazy.@endif</div>
@if($serverSearch)</form>@else</div>@endif
