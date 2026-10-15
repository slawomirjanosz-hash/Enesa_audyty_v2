<?php

namespace App\Http\Controllers;

use App\Models\CylinderInspectionSeries;
use App\Services\CylinderProtocolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CylinderProtocolController extends Controller
{
    public function preview(CylinderInspectionSeries $series, CylinderProtocolService $service)
    {
        if ($series->protocol()->exists()) {
            return redirect()->route('cylinders.series.protocol.download', $series);
        }
        $snapshot = $service->snapshot($series);

        return view('cylinders.protocols.preview', ['series' => $series, 'snapshot' => $snapshot, 'fingerprint' => $service->fingerprint($snapshot)]);
    }

    public function issue(Request $request, CylinderInspectionSeries $series, CylinderProtocolService $service)
    {
        $data = $request->validate(['fingerprint' => ['required', 'string', 'size:64']]);
        $service->issue($series, $request->user(), $data['fingerprint']);

        return redirect()->route('cylinders.series.show', $series)->with('success', 'Wydano protokół. Zapisany PDF zachowuje dane z chwili wydania.');
    }

    public function draft(Request $request, CylinderInspectionSeries $series, CylinderProtocolService $service)
    {
        $data = $request->validate(['fingerprint' => ['required', 'string', 'size:64']]);
        $snapshot = $service->snapshot($series);
        abort_unless(hash_equals($service->fingerprint($snapshot), $data['fingerprint']), 409, 'Dane zmieniły się. Otwórz podgląd ponownie.');

        return response($service->render($snapshot))->header('Content-Type', 'application/pdf')
            ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }

    public function download(CylinderInspectionSeries $series)
    {
        $protocol = $series->protocol()->firstOrFail();
        $disk = Storage::disk('local');
        abort_unless($disk->exists($protocol->stored_path), 404, 'Zapisany plik protokołu jest niedostępny.');
        abort_unless(hash_equals($protocol->sha256, hash_file('sha256', $disk->path($protocol->stored_path))), 409, 'Plik protokołu wymaga sprawdzenia integralności.');

        return response()->file($disk->path($protocol->stored_path), [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'inline; filename="'.str_replace('/', '-', $protocol->number).'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
