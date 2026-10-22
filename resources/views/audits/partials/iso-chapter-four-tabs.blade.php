<link rel="stylesheet" href="{{asset('css/iso-chapter-four.css')}}">
<nav class="iso-clause-tabs" aria-label="Zakładki ankiety" data-clause-tabs>
@foreach(app(\App\Services\IsoChapterFourPresentation::class)->tabs($clauseSection, $client) as [$tabTitle,$tabSelectors])
<a href="{{explode(',',$tabSelectors)[0]}}" data-clause-targets="{{$tabSelectors}}">{{$tabTitle}}</a>
@endforeach
</nav>
<script src="{{asset('js/iso-chapter-four-tabs.js')}}" defer></script>
