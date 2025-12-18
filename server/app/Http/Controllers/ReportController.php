<?php

namespace App\Http\Controllers;

class ReportController extends Controller
{
    public function index()
    {
        return redirect()->route('home');
    }
}
