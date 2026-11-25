<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    private const BCRYPT_INPUT = 'regex:/\A[^\x00]{1,72}\z/';

    private const NEW_PASSWORD_RULES = ['bail', 'required', 'string', 'min:8', self::BCRYPT_INPUT, 'confirmed'];

    public function register(Request $request): RedirectResponse
    {
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => Str::lower($email)]);
        }
        $data = $this->validateCashflow($request, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'email', 'max:255', 'unique:users'],
            'password' => self::NEW_PASSWORD_RULES,
        ], ['email.unique' => __('Email ini sudah terdaftar.')]);

        try {
            $user = User::create($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => __('Email ini sudah terdaftar.')]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('households.onboarding');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $this->validateCashflow($request, [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['bail', 'required', 'string', self::BCRYPT_INPUT],
        ]);
        $credentials['email'] = Str::lower($credentials['email']);

        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['email' => __('Email atau kata sandi tidak cocok.')])->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $this->validateCashflow($request, ['email' => ['required', 'email', 'max:255']]);
        Password::sendResetLink(['email' => Str::lower($data['email'])]);

        return back()->with('status', __('Jika email terdaftar, tautan reset akan dikirim.'));
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $credentials = $this->validateCashflow($request, [
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => self::NEW_PASSWORD_RULES,
        ]);
        $credentials['email'] = Str::lower($credentials['email']);

        $status = Password::reset($credentials, function (User $user, string $password): void {
            $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
            $user->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PasswordReset) {
            return back()->withErrors(['email' => __('Tautan reset tidak valid atau sudah kedaluwarsa.')])
                ->withInput($request->only('email'));
        }

        return redirect()->route('login')->with('status', __('Kata sandi berhasil diatur ulang.'));
    }
}
