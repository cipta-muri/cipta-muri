<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class SocialiteController extends Controller
{
    /**
     * Redirect the user to the provider's authentication page.
     */
    public function redirect(string $provider): SymfonyRedirectResponse
    {
        $definition = $this->resolveProvider($provider);

        $driver = Socialite::driver($definition['driver']);

        if (!empty($definition['scopes'])) {
            $driver->scopes($definition['scopes']);
        }

        if (!empty($definition['with'])) {
            $driver->with($definition['with']);
        }

        return $driver->redirect();
    }

    /**
     * Handle the callback from the provider.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $definition = $this->resolveProvider($provider);

        try {
            $providerUser = Socialite::driver($definition['driver'])->user();
        } catch (Throwable $exception) {
            report($exception);

            return $this->failedResponse(
                __('Unable to login using :provider. Please try again.', ['provider' => $definition['label']])
            );
        }

        if (!$providerUser->getEmail()) {
            return $this->failedResponse(
                __('Your :provider account does not have an email address that we can use.', [
                    'provider' => $definition['label'],
                ])
            );
        }

        $user = $this->findOrCreateUser($providerUser, $provider);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Create or update a user from the provider payload.
     */
    protected function findOrCreateUser(ProviderUser $providerUser, string $provider): User
    {
        $user = User::where('provider_name', $provider)
            ->where('provider_id', $providerUser->getId())
            ->first();

        if (!$user && $providerUser->getEmail()) {
            $user = User::where('email', $providerUser->getEmail())->first();
        }

        $attributes = [
            'name' => $providerUser->getName() ?: $providerUser->getNickname() ?: $providerUser->getEmail(),
            'email' => $providerUser->getEmail(),
            'email_verified_at' => now(),
            'avatar_url' => $providerUser->getAvatar(),
            'provider_name' => $provider,
            'provider_id' => $providerUser->getId(),
            'provider_token' => $providerUser->token ?? null,
            'provider_refresh_token' => $providerUser->refreshToken ?? null,
        ];

        if ($user) {
            $user->fill(array_filter($attributes, fn ($value) => $value !== null));
            $user->save();

            return $user;
        }

        return User::create(array_merge($attributes, [
            'password' => Hash::make(Str::random(32)),
        ]));
    }

    /**
     * Resolve the provider definition or abort with 404.
     */
    protected function resolveProvider(string $provider): array
    {
        $providers = config('services.socialite.providers', []);

        if (!array_key_exists($provider, $providers)) {
            abort(404);
        }

        $credentials = config("services.{$provider}", []);

        if (blank(data_get($credentials, 'client_id')) || blank(data_get($credentials, 'client_secret'))) {
            abort(404);
        }

        return $providers[$provider];
    }

    /**
     * Redirect back to login with an error message.
     */
    protected function failedResponse(string $message): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->with('socialite_error', $message);
    }
}
