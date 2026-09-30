<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentLocation;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashBookController extends Controller
{
    use ResolvesCurrentLocation;

    public function index(Request $request): View|RedirectResponse
    {
        $location = $this->currentLocation($request, false);
        $shift = $location->openShiftFor($request->user());

        if (! $shift) {
            return redirect()->route('shift.create')->with('status', 'Mở ca để xem sổ quỹ.');
        }

        $shift->load(['cashAdjustments' => fn ($q) => $q->latest(), 'orders']);

        return view('pos.cashbook', [
            'shift' => $shift,
            'expected' => $shift->expectedCash(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $location = $this->currentLocation($request, false);
        $shift = $location->openShiftFor($request->user());
        abort_if(! $shift, 404);

        $data = $request->validate([
            'type' => 'required|in:expense,deposit,withdrawal',
            'amount' => 'required|numeric|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        $shift->cashAdjustments()->create($data);

        return back()->with('status', 'Đã ghi sổ quỹ.');
    }

}
