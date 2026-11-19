<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfilController extends Controller
{
    public function zeige(Request $request)
    {
        return view('profil', ['user' => $request->user()->load('ressorts')]);
    }

    public function speichern(Request $request)
    {
        $user = $request->user();
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
            'telefon' => ['nullable', 'string', 'max:50'],
        ]);
        $user->update($daten);

        return back()->with('erfolg', 'Profil gespeichert.');
    }

    public function passwort(Request $request)
    {
        $request->validate([
            'aktuell' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $request->user()->update(['password' => Hash::make($request->input('password'))]);

        return back()->with('erfolg', 'Passwort geändert.');
    }

    public function ansicht(Request $request)
    {
        $request->validate(['ansicht' => ['required', Rule::in(['einfach', 'experte'])]]);
        $request->user()->update(['ansicht' => $request->input('ansicht')]);

        return back()->with('erfolg', $request->input('ansicht') === 'experte' ? 'Expertenansicht aktiv.' : 'Einfache Ansicht aktiv.');
    }

    public function versionGesehen(Request $request)
    {
        $request->user()->update(['gesehene_version' => config('app.version')]);

        return response()->json(['ok' => true]);
    }

    public function neuigkeiten()
    {
        return view('neuigkeiten', ['deployments' => Deployment::latest()->limit(20)->get()]);
    }
}
