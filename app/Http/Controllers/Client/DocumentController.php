<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;

class DocumentController extends Controller
{
    public function index()
    {
        return view('client.documents');
    }
}
