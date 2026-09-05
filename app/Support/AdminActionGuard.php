<?php



namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminActionGuard
{
    private const SESSION_KEY = 'admin.action_pin_confirmed_at';

    public function ttlMinutes(): int
    {
        return max(1, (int) config('services.security.admin_password_confirm_minutes', 15));
    }

    public function recentlyConfirmed(?int $ttlMinutes = null): bool
    {
        $ttlMinutes ??= $this->ttlMinutes();
        $confirmedAt = (int) session(self::SESSION_KEY, 0);

        if ($confirmedAt <= 0) {
            return false;
        }

        return (time() - $confirmedAt) < ($ttlMinutes * 60);
    }

    public function confirm(?string $pin): bool
    {

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $pin = trim((string) ($pin ?? ''));

        if (! preg_match('/^\d{6}$/', $pin)) {
            return false;
        }

        $storedPin = (string) ($user->admin_action_pin ?? '');

        if ($storedPin === '') {
            return false;
        }

        if (! Hash::check($pin, $storedPin)) {
            return false;
        }

        session([self::SESSION_KEY => time()]);

        return true;
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
}
