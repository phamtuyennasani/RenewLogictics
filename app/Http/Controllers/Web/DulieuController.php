<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DulieuController extends Controller
{
    public function index(Request $request, string $type = '')
    {
        return view('dulieu.index', [
            'type' => $type,
        ]);
    }
}
