@foreach(array_chunk($answers['ROLE']??[],6) as $roles)
<div class="plant-scroll"><table style="width:100%;border-collapse:collapse;font-size:10px"><thead><tr><th style="text-align:left">Zadanie</th>@foreach($roles as $role)<th title="{{$role['nazwa']??''}}">{{$role['skrot']??$role['id']??''}}</th>@endforeach</tr></thead><tbody>
@foreach($service->schema('5-3')['MAT_ZADANIA'] as $task)<tr><td style="padding:7px;border-bottom:1px solid #dce4df">{{$task['kod']}} · {{$task['nazwa']}}</td>@foreach($roles as $role)<td style="text-align:center;padding:7px;border-bottom:1px solid #dce4df">{{$role['MAT'][$task['kod']]??'—'}}</td>@endforeach</tr>@endforeach
</tbody></table></div>
@endforeach
