<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aktivitaet;
use App\Models\Rundmeldung;
use App\Models\User;
use App\Services\Benachrichtiger;
use Illuminate\Http\Request;

class ProtokollController extends Controller
{
    public function index(Request $request)
    {
        $eintraege = Aktivitaet::with('user')
            ->when($request->filled('person'), fn ($q) => $q->where('user_id', $request->integer('person')))
            ->when($request->filled('aktion'), fn ($q) => $q->where('aktion', 'like', $request->query('aktion').'%'))
            ->latest()->paginate(50)->withQueryString();

        return view('admin.protokoll', [
            'eintraege' => $eintraege,
            'personen' => User::orderBy('name')->get(),
            'aktionen' => Aktivitaet::query()->distinct()->pluck('aktion')->map(fn ($a) => strtok($a, '.'))->unique()->sort()->values(),
        ]);
    }

    public function rundmeldungen()
    {
        return view('admin.rundmeldungen', [
            'meldungen' => Rundmeldung::with('user', 'ressort')->latest()->limit(30)->get(),
            'ressorts' => \App\Models\Ressort::orderBy('sortierung')->get(),
        ]);
    }

    public function rundmeldungSenden(Request $request, Benachrichtiger $benachrichtiger)
    {
        $daten = $request->validate([
            'titel' => ['required', 'string', 'max:150'],
            'text' => ['required', 'string', 'max:2000'],
            'ressort_id' => ['nullable', 'exists:ressorts,id'],
        ]);

        $empfaenger = $daten['ressort_id'] ? Benachrichtiger::empfaengerRessort((int) $daten['ressort_id']) : User::where('aktiv', true)->get();
        $anzahl = $benachrichtiger->sende($empfaenger, 'rundmeldung', $daten['titel'], $daten['text'], route('start'));
        Rundmeldung::create($daten + ['user_id' => $request->user()->id, 'empfaenger' => $anzahl]);

        return back()->with('erfolg', 'Rundmeldung an '.$anzahl.' Personen gesendet.');
    }
}
