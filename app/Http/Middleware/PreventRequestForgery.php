<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as BasePreventRequestForgery;

/**
 * CSRF protection without the JavaScript-readable XSRF-TOKEN cookie.
 *
 * The frontend sends the token from the `csrf-token` meta tag (X-CSRF-TOKEN header)
 * or the `_token` form field, so the non-HttpOnly cookie is never needed.
 */
class PreventRequestForgery extends BasePreventRequestForgery
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = false;
}
