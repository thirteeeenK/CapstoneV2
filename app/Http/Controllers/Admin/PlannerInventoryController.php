<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityModel;
use App\Models\HotelModel;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlannerInventoryController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['search' => ['nullable', 'string', 'max:200']]);
        $type = in_array($request->query('type'), ['activity', 'hotel'], true) ? $request->query('type') : 'activity';
        $model = match ($type) {
            'hotel' => HotelModel::class, default => ActivityModel::class
        };
        $nameColumn = match ($type) {
            'hotel' => 'hotel_name', default => 'activity_name'
        };
        $listings = $model::query()->with('destination')->when($request->filled('search'), fn ($q) => $q->where($nameColumn, 'ilike', '%'.$request->string('search').'%'))
            ->orderBy($nameColumn)->paginate(10)->withQueryString();
        $data = ['listings' => $listings, 'type' => $type, 'nameColumn' => $nameColumn,
            'search' => $request->query('search', '')];

        return view($request->ajax() ? 'admin.planner-inventory._results' : 'admin.planner-inventory.index', $data);
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $model = match ($type) {
            'hotel' => HotelModel::class, 'activity' => ActivityModel::class, default => abort(404)
        };
        $listing = $model::query()->findOrFail($id);
        $data = $request->validate([
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'check_in_time' => ['nullable', 'date_format:H:i'], 'check_out_time' => ['nullable', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'between:1,1440'], 'environment' => ['nullable', 'in:indoor,outdoor'],
            'opens_at' => ['nullable', 'date_format:H:i'], 'closes_at' => ['nullable', 'date_format:H:i'],
            'fixed_start_times' => ['nullable', 'string', 'max:255'], 'includes_lunch' => ['sometimes', 'boolean'], 'is_shown' => ['sometimes', 'boolean'],
        ]);
        if (! empty($data['opens_at']) && ! empty($data['closes_at']) && $data['opens_at'] >= $data['closes_at']) {
            throw ValidationException::withMessages(['closes_at' => 'Closing time must be later than opening time. Overnight activities need separate operator scheduling.']);
        }
        $fixed = array_values(array_filter(array_map('trim', explode(',', $data['fixed_start_times'] ?? ''))));
        foreach ($fixed as $time) {
            if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
                throw ValidationException::withMessages(['fixed_start_times' => 'Use comma-separated 24-hour times, such as 09:00, 14:30.']);
            }
        }
        $old = $listing->getOriginal();
        $payload = ['latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'is_shown' => $request->boolean('is_shown')];
        if ($type === 'hotel') {
            $payload += ['check_in_time' => $data['check_in_time'] ?? null, 'check_out_time' => $data['check_out_time'] ?? null];
        } else {
            $payload['scheduling'] = array_filter([
                'duration_minutes' => isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
                'environment' => $data['environment'] ?? null, 'opens_at' => $data['opens_at'] ?? null, 'closes_at' => $data['closes_at'] ?? null,
                'fixed_start_times' => $fixed, 'includes_lunch' => $request->boolean('includes_lunch'),
            ], fn ($value) => $value !== null && $value !== '');
        }
        $listing->update($payload);
        AdminAuditService::log($listing, $old);

        return back()->with('success', 'Planner scheduling details saved. Existing itinerary snapshots remain unchanged.');
    }
}
