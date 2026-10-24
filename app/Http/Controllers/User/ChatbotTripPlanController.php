<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\TripPlan;
use App\Services\CartService;
use App\Services\Chat\ChatbotTripPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotTripPlanController extends Controller
{
    public function __construct(private CartService $cart, private ChatbotTripPlanService $service) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['suggestion_ids' => ['required', 'array', 'max:50'], 'suggestion_ids.*' => ['required', 'uuid']]);
        $user = $request->user();
        $sessionToken = $this->cart->getSessionToken($request);
        if ($user) {
            $this->service->claimGuestPlans($user, $sessionToken);
        }
        $plans = $this->service->ownedPlans($user, $sessionToken)
            ->whereIn('source_suggestion_uuid', $data['suggestion_ids'])->get(['id', 'user_id', 'source_suggestion_uuid']);

        return response()->json(['data' => (object) $plans->mapWithKeys(fn (TripPlan $plan): array => [
            // A guest cannot open the planner yet, so the saved state is
            // reported without a link until the plan is claimed at sign-in.
            $plan->source_suggestion_uuid => $plan->user_id === null ? true : route('trip-planner.index', ['plan' => $plan->id]),
        ])->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'suggestion_id' => ['required', 'uuid'], 'title' => ['nullable', 'string', 'max:150'],
            'session_token' => ['nullable', 'string', 'max:255'],
            // Replace this saved chatbot draft instead of creating another plan.
            'plan_id' => ['nullable', 'integer'],
        ]);
        $user = $request->user();
        $result = isset($data['plan_id'])
            ? $this->service->update($user, $this->cart->getSessionToken($request), (int) $data['plan_id'], $data['suggestion_id'], $data['session_token'] ?? null)
            : $this->service->save(
                $user,
                $this->cart->getSessionToken($request),
                $data['suggestion_id'],
                $data['title'] ?? null,
                $data['session_token'] ?? null,
            );
        $plan = $result['plan'];

        return response()->json([
            'data' => $plan,
            'claimed' => $user !== null,
            'url' => $user === null ? null : route('trip-planner.index', ['plan' => $plan->id]),
        ], $result['created'] ? 201 : 200);
    }
}
