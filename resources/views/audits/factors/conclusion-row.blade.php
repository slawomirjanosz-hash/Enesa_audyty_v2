@php($conclusion=is_array($conclusion)?$conclusion:[])
<div class="plant-question" data-swot-conclusion>
<label>Czynnik źródłowy<select name="swot[wnioski][{{$index}}][czynnik]"><option value="">Bez odniesienia</option>@foreach($factors as $factor)@if($factor['visible'] && ($factor['rodzaj']==='AUTO' || ($answers['factors'][$factor['kod']]['decyzja']??'')==='potwierdzony'))<option value="{{$factor['kod']}}" @selected(($conclusion['czynnik']??'')===$factor['kod'])>{{$factor['kod']}}</option>@endif @endforeach</select></label>
@foreach(['znaczenie'=>'Znaczenie dla systemu','decyzja'=>'Co postanawiamy?','dzialanie'=>'Co konkretnie robimy?','sprawdzenie'=>'Po czym poznamy efekt?'] as $key=>$label)<label>{{$label}}<textarea name="swot[wnioski][{{$index}}][{{$key}}]" maxlength="5000">{{is_scalar($conclusion[$key]??null)?$conclusion[$key]:''}}</textarea></label>@endforeach
<button type="button" data-remove-conclusion>Usuń wniosek</button></div>
