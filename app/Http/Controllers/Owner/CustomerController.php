<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentLocation;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use ResolvesCurrentLocation;

    public function index(Request $request): View
    {
        $location = $this->currentLocation($request);

        $customers = $location->customers()
            ->when($request->query('q'), function ($q, $search) {
                $q->where(fn ($q2) => $q2->where('phone', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            })
            ->orderByDesc('points')
            ->paginate(20)
            ->withQueryString();

        return view('owner.customers.index', compact('location', 'customers'));
    }

}
