<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'admin.email' => 'ionvop@gmail.com',
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-client-secret',
        'services.google.redirect' => '/admin/auth/google/callback',
    ]);
});

/**
 * Build a fake Google account for the Socialite callback.
 */
function fakeGoogleUser(string $email, string $id = 'google-123'): SocialiteUser
{
    return SocialiteUser::fake([
        'id' => $id,
        'name' => 'Ion Vop',
        'email' => $email,
        'avatar' => 'https://example.com/avatar.png',
    ]);
}

test('guests are redirected to the admin sign-in page', function () {
    $this->get(route('admin'))
        ->assertRedirect(route('admin.login'));
});

test('the sign-in page renders the Google button', function () {
    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('Continue with Google')
        ->assertDontSee('Admin sign-in is not configured yet');
});

test('the sign-in page warns when the credentials are missing', function () {
    config(['admin.email' => null]);

    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('Admin sign-in is not configured yet');
});

test('the redirect route sends the visitor to Google', function () {
    Socialite::fake('google');

    $this->get(route('admin.auth.redirect'))->assertRedirect();
});

test('the configured Google account is signed in', function () {
    Socialite::fake('google', fakeGoogleUser('ionvop@gmail.com'));

    $this->get(route('admin.auth.callback'))
        ->assertRedirect(route('admin'));

    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', [
        'email' => 'ionvop@gmail.com',
        'google_id' => 'google-123',
    ]);
});

test('the configured account is matched case-insensitively', function () {
    Socialite::fake('google', fakeGoogleUser('IonVop@Gmail.com'));

    $this->get(route('admin.auth.callback'))->assertRedirect(route('admin'));

    $this->assertAuthenticated();
});

test('a different Google account is denied and not stored', function () {
    Socialite::fake('google', fakeGoogleUser('someone@example.com'));

    $this->get(route('admin.auth.callback'))
        ->assertRedirect(route('admin.login'))
        ->assertSessionHasErrors('google');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'someone@example.com']);
});

test('an empty admin email denies every account', function () {
    config(['admin.email' => null]);

    Socialite::fake('google', fakeGoogleUser('ionvop@gmail.com'));

    $this->get(route('admin.auth.callback'))->assertStatus(503);

    $this->assertGuest();
});

test('a signed-in non-admin is forbidden from the dashboard', function () {
    $user = User::factory()->create(['email' => 'someone@example.com']);

    $this->actingAs($user)
        ->get(route('admin'))
        ->assertForbidden();
});

test('the administrator can sign out', function () {
    $user = User::factory()->create(['email' => 'ionvop@gmail.com']);

    $this->actingAs($user)
        ->post(route('admin.logout'))
        ->assertRedirect(route('admin.login'));

    $this->assertGuest();
});
