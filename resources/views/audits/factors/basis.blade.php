<details @if($errors->has($basisError??'')) open @endif><summary>Podstawa ustalenia — pomiar, dokument, deklaracja lub szacunek</summary>
<label>Rodzaj podstawy<select name="{{$basisPrefix}}[podstawa][rodzaj]"><option value="">Wybierz</option>@foreach(['pomiar'=>'Pomiar','dokument'=>'Dokument','deklaracja'=>'Deklaracja','szacunek'=>'Szacunek'] as $key=>$label)<option value="{{$key}}" @selected(($basisRow['podstawa']['rodzaj']??'')===$key)>{{$label}}</option>@endforeach</select></label>
<label>Źródło — dokument, pomiar albo osoba składająca deklarację<textarea name="{{$basisPrefix}}[podstawa][zrodlo]" maxlength="3000">{{$basisRow['podstawa']['zrodlo']??''}}</textarea></label>
<label>Okres / stan na dzień<input name="{{$basisPrefix}}[podstawa][okres]" value="{{$basisRow['podstawa']['okres']??''}}" maxlength="255"></label>
</details>
