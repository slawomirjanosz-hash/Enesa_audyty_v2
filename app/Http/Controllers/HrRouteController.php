<?php

namespace App\Http\Controllers;

use App\Services\GoogleMapsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrRouteController extends Controller
{
    public function calculateRoute(Request $request, GoogleMapsService $maps): JsonResponse
    {
        $data = $request->validate([
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($maps->calculateRoute($data['origin'], $data['destination']));
    }

    public function autocompletePlaces(Request $request, GoogleMapsService $maps): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:150']]);

        return response()->json(['suggestions' => $maps->suggestions($data['q'])]);
    }
}
