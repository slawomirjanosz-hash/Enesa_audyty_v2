<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;

class OfferRequestController extends Controller
{
    public function index()
    {
        return view('client.request-offer');
    }
}
