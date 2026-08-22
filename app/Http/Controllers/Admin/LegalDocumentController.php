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

    /**
     * Show the form for creating a new document.
     */
    public function create()
    {
        return view('admin.adminLegal.create');
    }

    /**
     * Store a newly created document in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'key' => ['required', 'string', 'max:50', 'unique:legal_documents,key', 'regex:/^[a-zA-Z0-9_]+$/'],
            'title' => ['required', 'string', 'max:150'],
            'version' => ['required', 'string', 'max:10'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.heading' => ['required', 'string', 'max:150'],
            'sections.*.body' => ['required', 'string'],
        ], [
            'key.regex' => 'The key must only contain letters, numbers, and underscores.',
        ]);

        $sections = collect($request->input('sections'))
            ->map(fn ($section) => [
                'heading' => trim($section['heading']),
                'body' => trim($section['body']),
            ])
            ->values()
            ->all();

        LegalDocument::create([
            'key' => $request->key,
            'title' => $request->title,
            'version' => $request->version,
            'content' => $sections,
            'updated_by' => optional(auth('admin')->user())->name ?? 'admin',
        ]);

        return redirect()
            ->route('admin.legal.index')
            ->with('success', "Legal document '{$request->title}' created successfully.");
    }

    /**
     * Remove the specified document from storage.
     */
    public function destroy($key)
    {
        $document = LegalDocument::where('key', $key)->firstOrFail();

        $title = $document->title;
        $document->delete();

        return redirect()
            ->route('admin.legal.index')
            ->with('success', "Legal document '{$title}' deleted successfully.");
    }
}
