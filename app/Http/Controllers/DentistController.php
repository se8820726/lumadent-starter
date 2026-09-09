<?php

namespace App\Http\Controllers;

use App\Content\DentistDirectory;
use Illuminate\View\View;

final class DentistController extends Controller
{
    public function __invoke(DentistDirectory $dentists): View
    {
        return view('dentists.index', ['dentists' => $dentists->all()]);
    }
}
