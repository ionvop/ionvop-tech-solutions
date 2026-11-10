<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class AuthController extends Controller
{
    /**
     * Show the sign-in page.
     */
    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin');
        }

        return view('admin.login', [
            'configured' => $this->isConfigured(),
        ]);
    }

    /**
     * Send the visitor to Google's consent screen.
     */
    public function redirectToGoogle(): SymfonyRedirectResponse
    {
        abort_unless($this->isConfigured(), 503, 'Admin sign-in is not configured.');

        // The Google driver already requests the openid, profile, and email scopes.
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google and sign the administrator in.
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        abort_unless($this->isConfigured(), 503, 'Admin sign-in is not configured.');

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->deny($request, 'Your sign-in attempt expired. Please try again.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->deny($request, 'Google sign-in failed. Please try again.');
        }

        $email = $googleUser->getEmail();

        if (! is_string($email) || strcasecmp($email, (string) config('admin.email')) !== 0) {
            return $this->deny($request, 'That Google account is not allowed to access the admin dashboard.');
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $googleUser->getName() ?? $email,
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ],
        );

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(route('admin'));
    }

    /**
     * Sign the administrator out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /**
     * Determine whether the Google credentials and admin email are configured.
     */
    private function isConfigured(): bool
    {
        return filled(config('admin.email'))
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    /**
     * Sign out and return to the sign-in page with an error.
     */
    private function deny(Request $request, string $message): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('admin.login')
            ->withErrors(['google' => $message]);
    }
}
