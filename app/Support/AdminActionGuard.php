<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AdminActionGuard
{
    private const SESSION_KEY = 'admin.action_pin_confirmed_at';
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 300;

    public function ttlMinutes(): int
    {
        return max(1, (int) config('services.security.admin_password_confirm_minutes', 15));
    }

    public function recentlyConfirmed(?int $ttlMinutes = null): bool
    {
        $ttlMinutes ??= $this->ttlMinutes();
        $confirmedAt = (int) session(self::SESSION_KEY, 0);

        return $confirmedAt > 0 && (time() - $confirmedAt) < ($ttlMinutes * 60);
    }

    public function confirm(?string $pin): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $key = $this->rateLimitKey($user);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return false;
        }

        $pin = trim((string) ($pin ?? ''));
        $storedPin = (string) ($user->admin_action_pin ?? '');

        if (! preg_match('/^\d{6}$/', $pin) || $storedPin === '' || ! Hash::check($pin, $storedPin)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            return false;
        }

        RateLimiter::clear($key);
        session([self::SESSION_KEY => time()]);

        return true;
    }

    public function availableIn(): int
    {
        $user = auth()->user();
        if (! $user) return 0;

        return RateLimiter::availableIn($this->rateLimitKey($user));
    }

    public function clearConfirmation(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function invalidateUserSessions(User $user): void
    {
        $user->forceFill([
            'session_token' => null,
            'remember_token' => Str::random(60),
        ])->save();
    }

    private function rateLimitKey(User $user): string
    {
        return 'admin-action-pin:' . $user->getKey() . ':' . sha1((string) request()->ip());
    }
}
