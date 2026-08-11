<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminFaqController extends Controller
{
    /**
     * Display listing of all FAQs with search and visibility filters.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $category = $request->query('category');
        $visibility = $request->query('visibility'); // 'visible', 'hidden'

        $categories = Faq::whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category');

        $query = Faq::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('question', 'ilike', "%{$search}%")
                    ->orWhere('answer', 'ilike', "%{$search}%")
                    ->orWhere('keywords', 'ilike', "%{$search}%");
            });
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($visibility === 'visible') {
            $query->where('is_active', true);
        } elseif ($visibility === 'hidden') {
            $query->where('is_active', false);
        }

        $faqs = $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc')->paginate(15);

        $stats = [
            'total' => Faq::count(),
            'visible' => Faq::where('is_active', true)->count(),
            'hidden' => Faq::where('is_active', false)->count(),
        ];

        if ($request->ajax()) {
            return view('admin.faqs._table', compact('faqs', 'categories', 'search', 'category', 'visibility'));
        }

        return view('admin.faqs.index', compact('faqs', 'categories', 'search', 'category', 'visibility', 'stats'));
    }

    /**
     * Show form to create a new FAQ.
     */
    public function create()
    {
        return view('admin.faqs.create');
    }

    /**
     * Store a new FAQ in the database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string|max:5000',
            'keywords' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'required|boolean',
        ]);

        $faq = Faq::create([
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'keywords' => $validated['keywords'] ?? null,
            'category' => $validated['category'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => (bool) $validated['is_active'],
        ]);

        $this->refreshEmbedding($faq);

        return redirect()->route('admin.faqs.index')->with('success', "FAQ '{$faq->question}' created successfully!");
    }

    /**
     * Show form to edit an existing FAQ.
     */
    public function edit($id)
    {
        $faq = Faq::findOrFail($id);
        return view('admin.faqs.edit', compact('faq'));
    }

    /**
     * Update an existing FAQ.
     */
    public function update(Request $request, $id)
    {
        $faq = Faq::findOrFail($id);

        $validated = $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string|max:5000',
            'keywords' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'required|boolean',
        ]);

        $faq->update([
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'keywords' => $validated['keywords'] ?? null,
            'category' => $validated['category'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $faq->sort_order,
            'is_active' => (bool) $validated['is_active'],
        ]);

        $this->refreshEmbedding($faq);

        return redirect()->route('admin.faqs.index')->with('success', "FAQ '{$faq->question}' updated successfully!");
    }

    /**
     * Instant toggle visibility (is_active).
     */
    public function toggleVisibility(Request $request, $id)
    {
        $faq = Faq::findOrFail($id);
        $faq->is_active = !$faq->is_active;
        $faq->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => $faq->is_active,
                'message' => "FAQ '{$faq->question}' is now " . ($faq->is_active ? 'Visible' : 'Hidden') . '.',
            ]);
        }

        return redirect()->back()->with('success', "FAQ '{$faq->question}' visibility toggled.");
    }

    /**
     * Delete an FAQ.
     */
    public function destroy($id)
    {
        $faq = Faq::findOrFail($id);
        $question = $faq->question;
        $faq->delete();

        return redirect()->route('admin.faqs.index')->with('success', "FAQ '{$question}' deleted successfully.");
    }

    /**
     * Auto-regenerate the FAQ embedding for semantic chatbot matching.
     */
    protected function refreshEmbedding(Faq $faq): void
    {
        try {
            $geminiService = app(GeminiService::class);
            $text = $geminiService->buildFaqEmbeddingText($faq);
            $vector = $geminiService->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $faq->question);
            if ($vector) {
                $faq->embedding = $geminiService->formatVectorForDb($vector);
                $faq->save();
            }
        } catch (\Exception $e) {
            Log::warning('FAQ embedding generation skipped: ' . $e->getMessage());
        }
    }
}
