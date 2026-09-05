<?php



namespace App\Support;

class MailNotificationGuard
{
    public static function enabled(): bool
    {
        return (bool) config('mail.notifications_enabled', true);
    }

    public static function hasValidFrom(): bool
    {
        $address = trim((string) config('mail.from.address', ''));

        return $address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function canSend(): bool
    {
        return self::enabled() && self::hasValidFrom();
    }
}