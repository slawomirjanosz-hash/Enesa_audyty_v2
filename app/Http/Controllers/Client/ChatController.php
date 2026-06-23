<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;

class ChatController extends Controller
{
    public function index()
    {
        return view('client.chat');
    }
}
