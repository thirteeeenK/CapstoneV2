<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveKnowledgeArticleRequest;
use App\Models\DestinationModel;
use App\Models\KnowledgeArticle;
use App\Services\AdminAuditService;
use App\Services\GeminiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdminKnowledgeArticleController extends Controller
{
    /**
     * Reviewed travel guidance with live search, destination/category/status
     * filters and visible review, expiry and embedding state.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $destinationId = $request->query('destinationId');
        $category = $request->query('category');
        $visibility = $request->query('visibility');

        $query = KnowledgeArticle::query()->with('destination:id,name');
        if ($search) {
            $query->where(fn ($q) => $q->where('title', 'ilike', "%{$search}%")->orWhere('body', 'ilike', "%{$search}%")->orWhere('source_label', 'ilike', "%{$search}%"));
        }
        if ($destinationId === 'general') {
            $query->whereNull('destination_id');
        } elseif ($destinationId) {
            $query->where('destination_id', (int) $destinationId);
        }
        if ($category) {
            $query->where('category', $category);
        }
        match ($visibility) {
            'citable' => $query->citable(),
            'draft', 'archived' => $query->where('status', $visibility),
            'expired' => $query->where('status', 'published')->whereDate('expires_at', '<', today()),
            'unreviewed' => $query->whereNull('reviewed_at'),
            default => null,
        };

        $articles = $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(15)->withQueryString();

        if ($request->ajax()) {
            return view('admin.knowledge._table', compact('articles'));
        }

        $destinations = DestinationModel::orderBy('name')->get(['id', 'name']);
        $stats = [
            'total' => KnowledgeArticle::count(),
            'citable' => KnowledgeArticle::citable()->count(),
            'draft' => KnowledgeArticle::where('status', 'draft')->count(),
            'expired' => KnowledgeArticle::where('status', 'published')->whereDate('expires_at', '<', today())->count(),
        ];

        return view('admin.knowledge.index', compact('articles', 'destinations', 'search', 'destinationId', 'category', 'visibility', 'stats'));
    }

    public function create(): View
    {
        return view('admin.knowledge.create', [
            'article' => new KnowledgeArticle(['status' => 'draft']),
            'destinations' => DestinationModel::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveKnowledgeArticleRequest $request): RedirectResponse
    {
        $article = KnowledgeArticle::create($request->validated());
        AdminAuditService::log($article);
        $this->refreshEmbedding($article);

        return redirect()->route('admin.knowledge.index')->with('success', "Article '{$article->title}' saved.");
    }

    public function edit(KnowledgeArticle $knowledgeArticle): View
    {
        return view('admin.knowledge.edit', [
            'article' => $knowledgeArticle,
            'destinations' => DestinationModel::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(SaveKnowledgeArticleRequest $request, KnowledgeArticle $knowledgeArticle): RedirectResponse
    {
        $oldValues = $knowledgeArticle->getOriginal();
        $knowledgeArticle->update($request->validated());
        AdminAuditService::log($knowledgeArticle, $oldValues);
        $this->refreshEmbedding($knowledgeArticle);

        return redirect()->route('admin.knowledge.index')->with('success', "Article '{$knowledgeArticle->title}' updated.");
    }

    public function destroy(KnowledgeArticle $knowledgeArticle): RedirectResponse
    {
        $title = $knowledgeArticle->title;
        AdminAuditService::log($knowledgeArticle, $knowledgeArticle->getOriginal());
        $knowledgeArticle->delete();

        return redirect()->route('admin.knowledge.index')->with('success', "Article '{$title}' deleted.");
    }

    /**
     * Re-embed when the text changed. A failure leaves the article stale,
     * which retrieval detects and answers lexically instead.
     */
    protected function refreshEmbedding(KnowledgeArticle $article): void
    {
        if (! $article->embeddingIsStale()) {
            return;
        }
        try {
            $gemini = app(GeminiService::class);
            $article->load('destination');
            $text = $article->embeddingText();
            $vector = $gemini->generateEmbedding($text, 'RETRIEVAL_DOCUMENT', $article->title);
            if ($vector) {
                $model = (string) config('services.gemini.embedding_model');
                $article->forceFill([
                    'embedding' => $gemini->formatVectorForDb($vector),
                    'embedding_source_hash' => sha1($text.$model),
                    'embedding_model' => $model,
                    'embedded_at' => now(),
                ])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('Knowledge article embedding skipped.', ['article_id' => $article->id, 'exception' => $e::class]);
        }
    }
}
