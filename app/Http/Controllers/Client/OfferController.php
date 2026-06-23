<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;

class OfferController extends Controller
{
    public function index()
    {
        return view('client.offers');
    }
}
