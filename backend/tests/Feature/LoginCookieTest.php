<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\User;
use App\Services\LoginCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The sign-in cookie: a browser that has dropped its stored login (Safari on
 * iPhone) is not asked to sign in again while its 30-day login is still good.
 */
class LoginCookieTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['email' => 'staff@pepy.test', 'password' => Hash::make('secret-123'), 'role' => 'operations_hr_manager']);
    }

    /** Signs in and returns [plain-text token, the sign-in cookie]. */
    private function signIn(bool $remember = true): array
    {
        $response = $this->postJson('/api/login', ['email' => 'staff@pepy.test', 'password' => 'secret-123', 'remember' => $remember])->assertOk();

        return [$response->json('token'), $response->getCookie(LoginCookie::NAME, false)];
    }

    public function test_login_sets_a_cookie_that_lasts_as_long_as_the_login(): void
    {
        $this->user();

        [$token, $cookie] = $this->signIn(remember: true);
        $this->assertSame($token, $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $cookie->getExpiresTime(), 120);

        [, $short] = $this->signIn(remember: false);
        $this->assertEqualsWithDelta(now()->addHours(12)->getTimestamp(), $short->getExpiresTime(), 120);
    }

    public function test_the_cookie_restores_the_session_without_signing_in_again(): void
    {
        $user = $this->user();
        [$token] = $this->signIn();

        $this->withCredentials()->withUnencryptedCookie(LoginCookie::NAME, $token)->getJson('/api/session')
            ->assertOk()
            ->assertJsonPath('token', $token)
            ->assertJsonPath('user.id', $user->id);

        // Nothing to restore without the cookie, or with a made-up one.
        $this->unencryptedCookies = [];
        $this->withCredentials()->getJson('/api/session')->assertNoContent();
        $this->withCredentials()->withUnencryptedCookie(LoginCookie::NAME, '999|not-a-real-token')->getJson('/api/session')->assertNoContent();
    }

    public function test_the_tag_page_opens_signed_in_from_the_cookie(): void
    {
        $this->user();
        [$token] = $this->signIn();
        $category = AssetCategory::create(['name' => 'Fixture & Furniture', 'short_name' => 'FAF']);
        $asset = Asset::create(['asset_code' => 'PEY-SR-FAF-0001', 'name' => 'Office Chair', 'category_id' => $category->id,
            'location_id' => Location::where('code', 'SR')->firstOrFail()->id, 'status' => 'active', 'condition' => 'good']);

        // With the cookie the page carries the session and must not be cached…
        $this->withCredentials()->withUnencryptedCookie(LoginCookie::NAME, $token)->get("/asset/{$asset->asset_code}")
            ->assertOk()
            ->assertSee(json_encode($token), false)
            ->assertHeader('Cache-Control', 'no-store, private');

        // …and without it the page stays the public, signed-out one.
        $this->unencryptedCookies = [];
        $this->get("/asset/{$asset->asset_code}")->assertOk()->assertDontSee($token, false);
    }

    public function test_signing_out_or_locking_the_account_ends_the_cookie_session(): void
    {
        $user = $this->user();
        [$token] = $this->signIn();

        // Signing out deletes the token and tells the browser to drop the cookie.
        $out = $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertTrue($out->getCookie(LoginCookie::NAME, false)->isCleared());
        $this->flushHeaders();
        $this->unencryptedCookies = [];
        $this->app['auth']->forgetGuards();
        $this->withCredentials()->withUnencryptedCookie(LoginCookie::NAME, $token)->getJson('/api/session')->assertNoContent();

        // A locked account's cookie restores nothing, even with a live token.
        [$second] = $this->signIn();
        $user->update(['is_locked' => true]);
        $this->withCredentials()->withUnencryptedCookie(LoginCookie::NAME, $second)->getJson('/api/session')->assertNoContent();
    }
}
