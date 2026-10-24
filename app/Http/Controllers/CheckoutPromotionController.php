<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplyPromotionRequest;
use App\Models\CartItem;
use App\Services\CartService;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;

class CheckoutPromotionController extends Controller
{
    public function __construct(private readonly CartService $cart, private readonly PromotionService $promotions) {}

    /**
     * Preview a Lucky code against the selected basket. Nothing is reserved:
     * the code is validated again, under lock, when the booking is submitted.
     */
    public function apply(ApplyPromotionRequest $request): JsonResponse
    {
        $lines = $this->cart->getCartQuery($request)->where('is_selected', true)->with('itemable')->get();
        $subtotal = (float) $lines->sum(fn (CartItem $line) => $line->subtotal);
        $evaluation = $this->promotions->evaluate($request->user(), $request->validated('promotion_code'), $lines, (int) round($subtotal * 100));
        $discount = $evaluation['discount_cents'] / 100;

        return response()->json([
            'success' => true,
            'code' => $evaluation['offer']->code,
            'discount' => $discount,
            'formatted_discount' => '₱'.number_format($discount, 2),
            'eligible_subtotal' => $evaluation['eligible_cents'] / 100,
            'formatted_eligible_subtotal' => '₱'.number_format($evaluation['eligible_cents'] / 100, 2),
            'eligible_line_ids' => $evaluation['lines']->modelKeys(),
            'eligible_items' => $evaluation['lines']->map(fn (CartItem $line) => $line->item_title)->values(),
            'message' => 'Lucky discount applied to '.$evaluation['lines']->count().' item(s) of your Lucky itinerary. Other items keep their regular price.',
        ]);
    }
}
