<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PassengerCategoryRule;
use Illuminate\Http\Request;

class PassengerRuleController extends Controller
{
    /**
     * Display list of passenger category rules for admin management.
     */
    public function index()
    {
        $rules = PassengerCategoryRule::orderBy('id')->get();
        return view('admin.passenger-rules.index', compact('rules'));
    }

    /**
     * Update pricing rule settings (amount, type, status).
     */
    public function update(Request $request, $id)
    {
        $rule = PassengerCategoryRule::findOrFail($id);

        $validated = $request->validate([
            'display_label' => 'required|string|max:100',
            'adjustment_type' => 'required|string|in:discount,surcharge,none',
            'amount' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        $rule->update($validated);

        return redirect()->back()->with('success', "Updated passenger pricing rule for '{$rule->category_name}' successfully!");
    }
}
