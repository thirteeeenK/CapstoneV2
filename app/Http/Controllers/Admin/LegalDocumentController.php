<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use Illuminate\Http\Request;

class LegalDocumentController extends Controller
{
    /**
     * List all legal documents.
     */
    public function index()
    {
        $documents = LegalDocument::orderBy('key')->get();

        return view('admin.adminLegal.index', compact('documents'));
    }

    /**
     * Show the edit form for a document.
     */
    public function edit($key)
    {
        $document = LegalDocument::where('key', $key)->firstOrFail();

        return view('admin.adminLegal.edit', compact('document'));
    }

    /**
     * Update a document (title, version, and sectioned content).
     */
    public function update(Request $request, $key)
    {
        $document = LegalDocument::where('key', $key)->firstOrFail();

        $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'version' => ['required', 'string', 'max:10'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.heading' => ['required', 'string', 'max:150'],
            'sections.*.body' => ['required', 'string'],
        ]);

        $sections = collect($request->input('sections'))
            ->map(fn ($section) => [
                'heading' => trim($section['heading']),
                'body' => trim($section['body']),
            ])
            ->values()
            ->all();

        $document->update([
            'title' => $request->title,
            'version' => $request->version,
            'content' => $sections,
            'updated_by' => optional(auth('admin')->user())->name ?? 'admin',
        ]);

        return redirect()
            ->route('admin.legal.edit', $key)
            ->with('success', "$document->title updated successfully.");
    }
}