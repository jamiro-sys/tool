<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\Einladung;
use App\Models\Aktivitaet;
use App\Models\Ressort;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PersonenController extends Controller
{
    public function index()
    {
        return view('admin.personen', [
            'personen' => User::with('ressorts')->orderBy('aktiv', 'desc')->orderBy('name')->get(),
            'ressorts' => Ressort::orderBy('sortierung')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'rolle' => ['required', Rule::in(array_keys(User::ROLLEN))],
            'ansicht' => ['required', Rule::in(['einfach', 'experte'])],
            'ressort_id' => ['nullable', 'exists:ressorts,id'],
            'ressort_rolle' => ['nullable', Rule::in(['leitung', 'mitglied', 'nachfolge'])],
        ]);

        $user = User::create([
            'name' => $daten['name'],
            'email' => $daten['email'],
            'rolle' => $daten['rolle'],
            'ansicht' => $daten['ansicht'],
            'einladung_token' => Str::random(48),
            'eingeladen_am' => now(),
        ]);
        if (! empty($daten['ressort_id'])) {
            $user->ressorts()->attach($daten['ressort_id'], ['rolle' => $daten['ressort_rolle'] ?? 'mitglied', 'seit' => now()]);
        }

        $this->sendeEinladung($user);
        Aktivitaet::protokolliere('person.eingeladen', $user->name.' eingeladen', $user);

        return back()->with('erfolg', $user->name.' ist eingeladen.')->with('einladungslink', route('einladung', $user->einladung_token));
    }

    public function update(Request $request, User $person)
    {
        $daten = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($person->id)],
            'rolle' => ['sometimes', Rule::in(array_keys(User::ROLLEN))],
            'ansicht' => ['sometimes', Rule::in(['einfach', 'experte'])],
            'aktiv' => ['sometimes', 'boolean'],
        ]);

        if ($person->id === $request->user()->id && (($daten['rolle'] ?? 'admin') !== 'admin' || ($daten['aktiv'] ?? true) == false)) {
            return back()->withErrors(['rolle' => 'Du kannst dir die Admin-Rechte nicht selbst entziehen.']);
        }
        if (($daten['rolle'] ?? null) && $daten['rolle'] !== 'admin' && $person->istAdmin() && User::where('rolle', 'admin')->where('aktiv', true)->count() <= 1) {
            return back()->withErrors(['rolle' => 'Es muss mindestens ein Admin bleiben.']);
        }

        $person->update($daten);
        Aktivitaet::protokolliere('person.geaendert', $person->name.' geändert', $person, $daten);

        return back()->with('erfolg', 'Gespeichert.');
    }

    public function einladungErneut(User $person)
    {
        $person->update(['einladung_token' => Str::random(48), 'eingeladen_am' => now()]);
        $this->sendeEinladung($person);

        return back()->with('erfolg', 'Einladung erneut gesendet.')->with('einladungslink', route('einladung', $person->einladung_token));
    }

    public function passwortLink(User $person)
    {
        Password::sendResetLink(['email' => $person->email]);

        return back()->with('erfolg', 'Link zum Zurücksetzen an '.$person->email.' gesendet.');
    }

    public function imitieren(Request $request, User $person)
    {
        abort_if($person->id === $request->user()->id, 422);
        Aktivitaet::protokolliere('person.imitiert', $request->user()->name.' sieht das Tool als '.$person->name, $person);
        $request->session()->put('imitiert_von', $request->user()->id);
        Auth::login($person);

        return redirect()->route('start')->with('erfolg', 'Du siehst das Tool jetzt als '.$person->name.'.');
    }

    public function imitierenBeenden(Request $request)
    {
        $id = $request->session()->pull('imitiert_von');
        abort_unless($id, 403);
        Auth::login(User::findOrFail($id));

        return redirect()->route('admin.personen')->with('erfolg', 'Zurück in deinem Konto.');
    }

    protected function sendeEinladung(User $user): void
    {
        try {
            Mail::to($user)->send(new Einladung($user));
        } catch (\Throwable $e) {
            report($e);
            session()->flash('warnung', 'Die E-Mail konnte nicht gesendet werden. Teile den Link manuell.');
        }
    }
}
