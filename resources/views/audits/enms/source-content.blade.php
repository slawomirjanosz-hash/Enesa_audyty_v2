@if($content['type']==='text')
<p class="enms-source-text">{{ $content['text'] }}</p>
@elseif($content['type']==='list')
<ul class="enms-source-list">
@foreach($content['children'] as $child)<li>@include('audits.enms.source-content',['content'=>$child['content']])</li>@endforeach
</ul>
@else
<dl class="enms-source-fields">
@foreach($content['children'] as $child)
<dt>{{ $child['label'] }}</dt><dd>@include('audits.enms.source-content',['content'=>$child['content']])</dd>
@endforeach
</dl>
@endif
