<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;

class AuditController extends Controller
{
    public function index()
    {
        return view('client.audits');
    }
}
