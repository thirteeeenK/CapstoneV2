<?php

namespace App\Http\Requests\Admin;

/**
 * Campaign edits use the same rules as creation; they apply prospectively
 * because each issued offer keeps a snapshot of the terms it was issued under.
 */
class UpdatePromotionRequest extends StorePromotionRequest {}
