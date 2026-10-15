<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class AccountSecurityService
{
    private const SESSION_KEY = 'account_security.operation';

    /** @param array{name?: string, email?: string} $data */
    public function begin(Request $request, string $purpose, array $data): void
    {
        $this->cancel($request);
        $user = $request->user();
        if ($user->hasPassword() || blank($user->google_id)) {
            $this->reject();
        }
        $id = Str::random(64);
        $request->session()->put(self::SESSION_KEY, $id);
        Cache::put($this->key($id), [
            'id' => $id, 'user_id' => $user->id, 'purpose' => $purpose,
            'session' => hash('sha256', $request->session()->getId()),
            'google_id' => $user->google_id, 'email' => $user->email,
            'password' => hash('sha256', (string) $user->password),
            'data' => $data, 'expires' => now()->timestamp + 600,
            'confirmed' => null, 'state' => null,
        ], 600);
    }

    /** @return array<string, mixed> */
    public function pending(Request $request, bool $confirmed = false): array
    {
        $id = $request->session()->get(self::SESSION_KEY);
        $operation = is_string($id) ? Cache::get($this->key($id)) : null;
        $user = $request->user()?->fresh();
        if (! is_array($operation) || ! $user
            || $operation['user_id'] !== $user->id
            || $operation['session'] !== hash('sha256', $request->session()->getId())
            || $operation['expires'] <= now()->timestamp
            || $operation['google_id'] !== $user->google_id
            || $operation['email'] !== $user->email
            || $operation['password'] !== hash('sha256', (string) $user->password)
            || ($confirmed && (! $operation['confirmed'] || now()->timestamp >= $operation['confirmed'] + 300))) {
            Log::notice('auth.account_security.invalid', [
                'present' => is_array($operation),
                'session_matches' => is_array($operation) && $operation['session'] === hash('sha256', $request->session()->getId()),
                'user_matches' => is_array($operation) && $user && $operation['user_id'] === $user->id,
            ]);
            $this->cancel($request);
            $this->reject();
        }

        return $operation;
    }

    public function rememberState(Request $request, string $state): void
    {
        $operation = $this->pending($request);
        $operation['state'] = $state;
        $this->store($operation);
    }

    public function confirm(Request $request, string $googleId): void
    {
        $operation = $this->pending($request);
        Cache::lock($this->key($operation['id']).':lock', 15)->block(3, function () use ($request, $googleId): void {
            $operation = $this->pending($request);
            if ($googleId !== $operation['google_id'] || $operation['confirmed'] !== null) {
                $this->cancel($request);
                $this->reject();
            }
            $operation['confirmed'] = now()->timestamp;
            $operation['state'] = null;
            $this->store($operation);
        });
    }

    /** @param Closure(User, array<string, mixed>): void $commit */
    public function consume(Request $request, string $purpose, mixed $id, Closure $commit): void
    {
        $operation = $this->pending($request, confirmed: true);
        if (! is_string($id) || ! hash_equals($operation['id'], $id) || $operation['purpose'] !== $purpose) {
            $this->reject();
        }

        try {
            Cache::lock($this->key($id).':lock', 15)->block(3, function () use ($request, $purpose, $commit): void {
                DB::transaction(function () use ($request, $purpose, $commit): void {
                    $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                    $operation = $this->pending($request, confirmed: true);
                    if ($operation['purpose'] !== $purpose) {
                        $this->reject();
                    }
                    $commit($user, $operation);
                    $this->cancel($request);
                });
            });
        } catch (LockTimeoutException) {
            throw new TooManyRequestsHttpException(3, 'This account change is being processed. Please try again in a few seconds.');
        }
    }

    public function cancel(Request $request): void
    {
        $id = $request->session()->pull(self::SESSION_KEY);
        if (is_string($id)) {
            Cache::forget($this->key($id));
        }
    }

    private function key(string $id): string
    {
        return 'account-security:operation:'.$id;
    }

    /** @param array<string, mixed> $operation */
    private function store(array $operation): void
    {
        Cache::put($this->key($operation['id']), $operation, max(1, $operation['expires'] - now()->timestamp));
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['confirmation' => 'Please confirm this change with your linked Google account again.']);
    }
}
