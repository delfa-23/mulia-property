<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;

class SitePlanController extends Controller
{
    public function edit(Property $property): RedirectResponse
    {
        return to_route('site-plans.index', ['property_id' => $property->id]);
    }
}
