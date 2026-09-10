<?php

namespace App\Http\Controllers;

use App\Content\ClinicProfile;
use App\Content\DentistDirectory;
use App\Content\ServiceCatalog;
use Illuminate\View\View;

final class HomeController extends Controller
{
    public function __invoke(
        ClinicProfile $clinic,
        ServiceCatalog $services,
        DentistDirectory $dentists,
    ): View {
        return view('home', [
            'clinic' => $clinic->get(),
            'services' => $services->featured(),
            'dentists' => $dentists->featured(),
        ]);
    }
}
