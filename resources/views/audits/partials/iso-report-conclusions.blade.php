@foreach($conclusions as $conclusion)
<h3>Wniosek {{$loop->iteration}}@if(filled($conclusion['czynnik']??null)) · {{$conclusion['czynnik']}}@endif</h3>
@if(!empty($conclusion['czynniki']))<p>Powiązane ustalenia: {{implode(', ',$conclusion['czynniki'])}}</p>@endif
@foreach(['znaczenie'=>'Co z tego wynika?', 'decyzja'=>'Co wybieramy?', 'dzialanie'=>'Co konkretnie robimy?', 'sprawdzenie'=>'Po czym poznamy, że zadziałało?'] as $key=>$label)<p><strong>{{$label}}</strong><br>{{$conclusion[$key]??'Do uzupełnienia'}}</p>@endforeach
@endforeach
