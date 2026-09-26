<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Services\AdminAuditService;
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
        $visibility = $request->query('visibility');

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

        if ($visibility === 'both') {
            $query->where('is_active', true)->where('show_on_landing', true);
        } elseif ($visibility === 'chatbot_only') {
            $query->where('is_active', true)->where('show_on_landing', false);
        } elseif ($visibility === 'faq_only') {
            $query->where('is_active', false)->where('show_on_landing', true);
        } elseif ($visibility === 'hidden') {
            $query->where('is_active', false)->where('show_on_landing', false);
        }

        $faqs = $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc')->paginate(15);

        $stats = [
            'total' => Faq::count(),
            'sunnybot' => Faq::where('is_active', true)->count(),
            'faq_page' => Faq::where('show_on_landing', true)->count(),
            'hidden' => Faq::where('is_active', false)->where('show_on_landing', false)->count(),
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
            'show_on_landing' => 'sometimes|boolean',
        ]);

        $faq = Faq::create([
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'keywords' => $validated['keywords'] ?? null,
            'category' => $validated['category'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => (bool) $validated['is_active'],
            'show_on_landing' => (bool) ($validated['show_on_landing'] ?? true),
        ]);

        AdminAuditService::log($faq);

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
        $oldValues = $faq->getOriginal();

        $validated = $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string|max:5000',
            'keywords' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'required|boolean',
            'show_on_landing' => 'sometimes|boolean',
        ]);

        $faq->update([
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'keywords' => $validated['keywords'] ?? null,
            'category' => $validated['category'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $faq->sort_order,
            'is_active' => (bool) $validated['is_active'],
            'show_on_landing' => (bool) ($validated['show_on_landing'] ?? $faq->show_on_landing),
        ]);

        AdminAuditService::log($faq, $oldValues);

        $this->refreshEmbedding($faq);

        return redirect()->route('admin.faqs.index')->with('success', "FAQ '{$faq->question}' updated successfully!");
    }

    /**
     * Toggle whether SunnyBot can use this FAQ.
     */
    public function toggleVisibility(Request $request, $id)
    {
        $faq = Faq::findOrFail($id);
        $oldValues = ['is_active' => $faq->is_active];
        $faq->is_active = ! $faq->is_active;
        $faq->save();

        AdminAuditService::log($faq, $oldValues);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => $faq->is_active,
                'message' => "FAQ '{$faq->question}' is now ".($faq->is_active ? 'available to SunnyBot' : 'hidden from SunnyBot').'.',
            ]);
        }

        $state = $faq->is_active ? 'available to SunnyBot' : 'hidden from SunnyBot';

        return redirect()->back()->with('success', "FAQ '{$faq->question}' is now {$state}.");
    }

    /**
     * Toggle whether this FAQ appears on the public FAQ page.
     */
    public function togglePageVisibility(Request $request, $id)
    {
        $faq = Faq::findOrFail($id);
        $oldValues = ['show_on_landing' => $faq->show_on_landing];
        $faq->show_on_landing = ! $faq->show_on_landing;
        $faq->save();

        AdminAuditService::log($faq, $oldValues);

        $state = $faq->show_on_landing ? 'visible on the FAQ page' : 'hidden from the FAQ page';
        $message = "FAQ '{$faq->question}' is now {$state}.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'show_on_landing' => $faq->show_on_landing,
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Delete an FAQ.
     */
    public function destroy($id)
    {
        $faq = Faq::findOrFail($id);
        $question = $faq->question;

        AdminAuditService::log($faq, $faq->getOriginal());

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
            Log::warning('FAQ embedding generation skipped: '.$e->getMessage());
        }
    }
}
