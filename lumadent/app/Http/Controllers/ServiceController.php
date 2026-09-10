<?php

namespace App\Http\Controllers;

use App\Content\ServiceCatalog;
use Illuminate\View\View;

final class ServiceController extends Controller
{
    public function index(ServiceCatalog $services): View
    {
        return view('services.index', ['services' => $services->all()]);
    }

    public function show(string $service, ServiceCatalog $services): View
    {
        return view('services.show', ['service' => $services->findOrFail($service)]);
    }
}
