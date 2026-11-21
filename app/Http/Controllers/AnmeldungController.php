<?php

namespace App\Http\Controllers;

use App\Models\Aktivitaet;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswortRegel;
use Illuminate\Validation\ValidationException;

class AnmeldungController extends Controller
{
    public function zeige()
    {
        return view('auth.anmelden');
    }

    public function anmelden(Request $request)
    {
        $daten = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $schluessel = 'login:'.Str::lower($daten['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($schluessel, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Zu viele Versuche. Bitte in '.RateLimiter::availableIn($schluessel).' Sekunden erneut versuchen.',
            ]);
        }

        if (! Auth::attempt([...$daten, 'aktiv' => true], $request->boolean('merken'))) {
            RateLimiter::hit($schluessel, 60);
            throw ValidationException::withMessages(['email' => 'E-Mail oder Passwort stimmen nicht.']);
        }

        RateLimiter::clear($schluessel);
        $request->session()->regenerate();
        Auth::user()->forceFill(['letzte_anmeldung' => now()])->save();
        Aktivitaet::protokolliere('anmeldung', Auth::user()->name.' hat sich angemeldet');

        return redirect()->intended(route('start'));
    }

    public function abmelden(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('anmelden');
    }

    public function einladung(string $token)
    {
        $user = User::where('einladung_token', $token)->where('aktiv', true)->firstOrFail();

        return view('auth.einladung', compact('user', 'token'));
    }

    public function einladungAnnehmen(Request $request, string $token)
    {
        $user = User::where('einladung_token', $token)->where('aktiv', true)->firstOrFail();
        $daten = $request->validate([
            'password' => ['required', 'confirmed', PasswortRegel::min(8)],
        ]);

        $user->forceFill([
            'password' => $daten['password'],
            'einladung_token' => null,
            'email_verified_at' => now(),
            'letzte_anmeldung' => now(),
        ])->save();

        Auth::login($user, true);
        $request->session()->regenerate();
        Aktivitaet::protokolliere('einladung.angenommen', $user->name.' ist dem OK-Tool beigetreten', $user);

        return redirect()->route('start')->with('erfolg', 'Willkommen im OK-Tool!');
    }

    public function vergessen()
    {
        return view('auth.vergessen');
    }

    public function vergessenSenden(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        // Immer die gleiche Antwort, damit keine Adressen erraten werden koennen.
        return back()->with('erfolg', 'Falls die Adresse bekannt ist, ist ein Link unterwegs.');
    }

    public function zuruecksetzen(Request $request, string $token)
    {
        return view('auth.zuruecksetzen', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function zuruecksetzenSpeichern(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswortRegel::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $passwort) {
                $user->forceFill(['password' => Hash::make($passwort), 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('anmelden')->with('erfolg', 'Passwort gespeichert. Du kannst dich jetzt anmelden.')
            : back()->withErrors(['email' => 'Der Link ist ungültig oder abgelaufen.']);
    }
}
