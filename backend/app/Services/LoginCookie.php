<?php

namespace App\Services;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * A copy of the sign-in token in a cookie the server sets.
 *
 * The SPA keeps its Sanctum token in localStorage. Safari on iPhone throws
 * that away far more readily than other browsers (after seven days without a
 * visit, among other cases), so a phone that ticked "remember me for 30 days"
 * was still asked to sign in again when it scanned the next tag. A cookie the
 * server sets is not subject to that, so the tag page and the app fall back to
 * it and restore the session instead of sending the person to the login page.
 *
 * It is the same token, with the same expiry: signing out deletes both the
 * token and the cookie, and locking an account deletes its tokens, which makes
 * the cookie worthless. HttpOnly, so page scripts cannot read it; requests
 * are still authorised by the bearer token, never by this cookie alone.
 */
class LoginCookie
{
    public const NAME = 'ams_token';

    public static function make(string $plainTextToken, \DateTimeInterface $expiresAt, Request $request): Cookie
    {
        $minutes = max(1, (int) ceil(($expiresAt->getTimestamp() - time()) / 60));

        return cookie(self::NAME, $plainTextToken, $minutes, '/', null, $request->secure(), true, false, 'lax');
    }

    public static function forget(): Cookie
    {
        return cookie()->forget(self::NAME);
    }

    /**
     * The token in the request's cookie, if it is still good: it exists, has
     * not expired, and belongs to an account that may sign in. Null otherwise.
     *
     * @return array{0: string, 1: \App\Models\User}|null [plain-text token, user]
     */
    public static function session(Request $request): ?array
    {
        $plain = $request->cookie(self::NAME);
        if (! is_string($plain) || $plain === '') {
            return null;
        }

        $token = PersonalAccessToken::findToken($plain);
        if (! $token || ($token->expires_at && $token->expires_at->isPast())) {
            return null;
        }

        $user = $token->tokenable;
        if (! $user || ! $user->is_active || $user->is_locked) {
            return null;
        }

        return [$plain, $user];
    }
}
