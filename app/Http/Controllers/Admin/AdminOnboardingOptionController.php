<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OnboardingOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminOnboardingOptionController extends Controller
{
    public const TYPES = [
        'vibe' => 'Atmosphere Vibe',
        'traveler_type' => 'Traveler Type',
        'activity' => 'Activity / Experience',
        'amenity' => 'Amenity',
    ];

    /**
     * Display a listing of onboarding options with search/type/visibility filters.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $type = $request->query('type');
        $visibility = $request->query('visibility');

        $query = OnboardingOption::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($visibility === 'visible') {
            $query->where('is_active', true);
        } elseif ($visibility === 'hidden') {
            $query->where('is_active', false);
        }

        $options = $query->orderBy('type', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'desc')
            ->paginate(15);

        $stats = [
            'total' => OnboardingOption::count(),
            'visible' => OnboardingOption::where('is_active', true)->count(),
            'hidden' => OnboardingOption::where('is_active', false)->count(),
        ];

        $types = self::TYPES;

        if ($request->ajax()) {
            return view('admin.onboarding-options._table', compact('options', 'search', 'type', 'visibility', 'types'));
        }

        return view('admin.onboarding-options.index', compact('options', 'search', 'type', 'visibility', 'stats', 'types'));
    }

    /**
     * Show the form for creating a new option.
     */
    public function create()
    {
        return view('admin.onboarding-options.create', ['types' => self::TYPES]);
    }

    /**
     * Store a newly created option.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(self::TYPES))],
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'required|boolean',
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'image_url' => ['nullable', 'url', 'max:2048'],
        ]);

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('onboarding/'.$validated['type'], 'public');
            $validated['image_path'] = $path;
        }

        if ($request->filled('image_url')) {
            $validated['image_url'] = trim($request->input('image_url'));
        }

        $option = OnboardingOption::create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
            'description' => $validated['description'] ?? null,
            'image_path' => $validated['image_path'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()->route('admin.onboarding-options.index')
            ->with('success', "Option '{$option->name}' created successfully!");
    }

    /**
     * Show the form for editing an option.
     */
    public function edit($id)
    {
        $option = OnboardingOption::findOrFail($id);

        return view('admin.onboarding-options.edit', ['option' => $option, 'types' => self::TYPES]);
    }

    /**
     * Update the specified option.
     */
    public function update(Request $request, $id)
    {
        $option = OnboardingOption::findOrFail($id);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(self::TYPES))],
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'required|boolean',
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('image_file')) {
            if ($option->image_path && Storage::disk('public')->exists($option->image_path)) {
                Storage::disk('public')->delete($option->image_path);
            }
            $path = $request->file('image_file')->store('onboarding/'.$validated['type'], 'public');
            $validated['image_path'] = $path;
        }

        if ($request->boolean('remove_image')) {
            if ($option->image_path && Storage::disk('public')->exists($option->image_path)) {
                Storage::disk('public')->delete($option->image_path);
            }
            $validated['image_path'] = null;
            $validated['image_url'] = null;
        } elseif ($request->filled('image_url')) {
            $validated['image_url'] = trim($request->input('image_url'));
        } elseif ($request->has('image_url') && $request->input('image_url') === '') {
            $validated['image_url'] = null;
        }

        $option->update([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
            'description' => $validated['description'] ?? null,
            'image_path' => $validated['image_path'] ?? $option->image_path,
            'image_url' => $validated['image_url'] ?? $option->image_url,
            'sort_order' => $validated['sort_order'] ?? $option->sort_order,
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()->route('admin.onboarding-options.index')
            ->with('success', "Option '{$option->name}' updated successfully!");
    }

    /**
     * Toggle the active status of an option.
     */
    public function toggleActive(Request $request, $id)
    {
        $option = OnboardingOption::findOrFail($id);
        $option->is_active = ! $option->is_active;
        $option->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => $option->is_active,
                'message' => "Option '{$option->name}' is now ".($option->is_active ? 'Visible' : 'Hidden').'.',
            ]);
        }

        return redirect()->back()->with('success', "Option '{$option->name}' visibility toggled.");
    }

    /**
     * Remove the option from the database.
     */
    public function destroy($id)
    {
        $option = OnboardingOption::findOrFail($id);
        $name = $option->name;

        if ($option->image_path && Storage::disk('public')->exists($option->image_path)) {
            Storage::disk('public')->delete($option->image_path);
        }

        $option->delete();

        return redirect()->route('admin.onboarding-options.index')
            ->with('success', "Option '{$name}' deleted successfully.");
    }
}
