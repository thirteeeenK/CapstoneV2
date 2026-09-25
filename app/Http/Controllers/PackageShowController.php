<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\DestinationModel;
use App\Models\Package;
use App\Services\Preview\PackagePreviewService;
use Illuminate\Http\Request;

class PackageShowController extends Controller
{
    /**
     * Display full public catalog listing of all Tour Packages.
     */
    public function index(Request $request)
    {
        $destinations = DestinationModel::orderBy('name', 'asc')->get();

        $query = Package::where('is_active', true)->with(['destination', 'hotels', 'rooms', 'activities', 'addOns']);

        if ($request->has('destination_id') && ! empty($request->destination_id)) {
            $query->where('destination_id', $request->destination_id);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('type', 'ilike', "%{$search}%");
            });
        }

        $packages = $query->orderBy('price', 'asc')->get();

        $priceChanges = AdminAuditLog::recentPriceChanges('package', $packages->pluck('id')->all(), 'price');

        return view('package.index', compact('packages', 'destinations', 'priceChanges'));
    }

    /**
     * Return the JSON payload used by the shared package preview modal.
     */
    public function preview($id, PackagePreviewService $service)
    {
        $package = Package::with(['destination', 'hotels', 'rooms', 'activities', 'addOns'])->find($id);

        if (! $package || (! $package->is_active && ! auth('admin')->check())) {
            abort(404);
        }

        $change = AdminAuditLog::recentPriceChanges('package', [$package->id], 'price');

        return response()->json($service->build($package, $change[$package->id] ?? null));
    }
}
