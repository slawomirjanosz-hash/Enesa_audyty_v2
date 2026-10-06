@auth
@once
<link rel="stylesheet" href="{{asset('css/form-drafts.css')}}">
<script src="{{asset('js/form-drafts.js')}}?v={{filemtime(public_path('js/form-drafts.js'))}}" data-draft-endpoint="{{url('/form-drafts')}}" data-csrf="{{csrf_token()}}" defer></script>
@endonce
@endauth
