<?php

namespace App\Http\Controllers;

use App\Models\Aktivitaet;
use App\Models\Aufgabe;
use App\Models\Ressort;
use App\Models\Uebergabe;
use App\Models\User;
use App\Services\Benachrichtiger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UebergabeController extends Controller
{
    public function __construct(protected Benachrichtiger $benachrichtiger) {}

    /** Stand aller Uebergaben: nur fuer Admins. */
    public function index()
    {
        \Illuminate\Support\Facades\Gate::authorize('admin');

        return view('uebergaben.index', [
            'uebergaben' => Uebergabe::with(['ressort', 'von', 'an'])->latest()->get(),
            'ressorts' => Ressort::with('mitglieder', 'vorlagen', 'kontakte', 'wissen')->orderBy('sortierung')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $daten = $request->validate([
            'ressort_id' => ['required', 'exists:ressorts,id'],
            'an_id' => ['required', 'exists:users,id'],
            'termin' => ['nullable', 'date'],
            'notiz' => ['nullable', 'string', 'max:5000'],
        ]);
        $ressort = Ressort::with('mitglieder')->findOrFail($daten['ressort_id']);
        Gate::authorize('ressort-verwalten', $ressort);

        $uebergabe = Uebergabe::create($daten + [
            'von_id' => $ressort->leitung()?->id ?? $request->user()->id,
            'status' => 'geplant',
            'checkliste' => collect(Uebergabe::STANDARD_CHECKLISTE)->map(fn ($t) => ['text' => $t, 'erledigt' => false])->all(),
        ]);

        // Die Nachfolge erhaelt sofort Einblick ins Ressort.
        $an = User::find($daten['an_id']);
        if (! $an->gehoertZuRessort($ressort)) {
            $ressort->mitglieder()->attach($an->id, ['rolle' => 'nachfolge', 'seit' => now()]);
        }

        Aktivitaet::protokolliere('uebergabe.geplant', 'Übergabe '.$ressort->name.' an '.$an->name.' geplant', $uebergabe);
        $this->benachrichtiger->sende(
            [$an], 'uebergabe', 'Übergabe Ressort '.$ressort->name,
            'Du übernimmst das Ressort '.$ressort->name.'. Hier findest du die Checkliste und das Dossier.',
            route('uebergaben.show', $uebergabe), $request->user()
        );

        return redirect()->route('uebergaben.show', $uebergabe)->with('erfolg', 'Übergabe geplant.');
    }

    public function show(Uebergabe $uebergabe)
    {
        $uebergabe->load(['ressort.mitglieder', 'ressort.vorlagen', 'ressort.kontakte', 'ressort.wissen', 'von', 'an']);
        $offen = Aufgabe::where('ressort_id', $uebergabe->ressort_id)->offen()
            ->whereHas('ausgabe', fn ($q) => $q->where('status', '!=', 'abgeschlossen'))
            ->with('verantwortlich', 'ausgabe')->orderBy('faellig_am')->get();

        return view('uebergaben.show', [
            'uebergabe' => $uebergabe,
            'wissensstand' => $uebergabe->ressort->wissensstand(),
            'offen' => $offen,
        ]);
    }

    public function checkpunkt(Request $request, Uebergabe $uebergabe)
    {
        $this->darfMitwirken($request->user(), $uebergabe);
        $daten = $request->validate(['index' => ['nullable', 'integer', 'min:0'], 'neu' => ['nullable', 'string', 'max:300']]);
        $liste = $uebergabe->checkliste ?? [];

        if (filled($daten['neu'] ?? null)) {
            $liste[] = ['text' => $daten['neu'], 'erledigt' => false];
        } elseif (isset($daten['index'], $liste[$daten['index']])) {
            $liste[$daten['index']]['erledigt'] = ! ($liste[$daten['index']]['erledigt'] ?? false);
            $liste[$daten['index']]['von'] = $request->user()->name;
        }

        $uebergabe->checkliste = $liste;
        if ($uebergabe->status === 'geplant') {
            $uebergabe->status = 'in_arbeit';
        }
        $uebergabe->save();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'fortschritt' => $uebergabe->fortschritt()]);
        }

        return back();
    }

    public function bestaetigen(Request $request, Uebergabe $uebergabe)
    {
        $user = $request->user();
        $this->darfMitwirken($user, $uebergabe);
        abort_unless($uebergabe->checklisteKomplett(), 422, 'Zuerst alle Punkte der Checkliste erledigen.');

        if ($user->id === $uebergabe->von_id) {
            $uebergabe->bestaetigt_von_am = now();
        }
        if ($user->id === $uebergabe->an_id) {
            $uebergabe->bestaetigt_an_am = now();
        }
        if ($user->istAdmin() && ! in_array($user->id, [$uebergabe->von_id, $uebergabe->an_id], true)) {
            $uebergabe->bestaetigt_von_am ??= now();
        }
        $uebergabe->save();

        if ($uebergabe->bestaetigt_von_am && $uebergabe->bestaetigt_an_am) {
            $this->abschliessen($uebergabe);

            return back()->with('erfolg', 'Übergabe abgeschlossen. '.$uebergabe->an->name.' leitet jetzt das Ressort.');
        }

        $gegenueber = $user->id === $uebergabe->an_id ? $uebergabe->von : $uebergabe->an;
        $this->benachrichtiger->sende($gegenueber ? [$gegenueber] : [], 'uebergabe', 'Übergabe bestätigen',
            $user->name.' hat die Übergabe '.$uebergabe->ressort->name.' bestätigt. Jetzt fehlt nur noch deine Bestätigung.',
            route('uebergaben.show', $uebergabe), $user);

        return back()->with('erfolg', 'Bestätigt. Sobald beide bestätigt haben, wechselt die Leitung.');
    }

    public function abbrechen(Request $request, Uebergabe $uebergabe)
    {
        Gate::authorize('ressort-verwalten', $uebergabe->ressort);
        $uebergabe->update(['status' => 'abgebrochen']);

        return back()->with('erfolg', 'Übergabe abgebrochen.');
    }

    protected function abschliessen(Uebergabe $uebergabe): void
    {
        DB::transaction(function () use ($uebergabe) {
            $ressort = $uebergabe->ressort;
            if ($uebergabe->von_id && $uebergabe->von_id !== $uebergabe->an_id) {
                $ressort->mitglieder()->updateExistingPivot($uebergabe->von_id, ['rolle' => 'mitglied']);
            }
            $ressort->mitglieder()->syncWithoutDetaching([$uebergabe->an_id => ['rolle' => 'leitung', 'seit' => now()]]);
            $ressort->mitglieder()->updateExistingPivot($uebergabe->an_id, ['rolle' => 'leitung', 'seit' => now()]);

            $uebergabe->update(['status' => 'abgeschlossen', 'abgeschlossen_am' => now()]);
            Aktivitaet::protokolliere('uebergabe.abgeschlossen', 'Ressort '.$ressort->name.' an '.$uebergabe->an->name.' übergeben', $uebergabe);
        });

        $this->benachrichtiger->sende(User::where('aktiv', true)->get(), 'uebergabe', 'Neue Ressortleitung',
            $uebergabe->an->name.' leitet ab sofort das Ressort '.$uebergabe->ressort->name.'. Danke '.($uebergabe->von?->name ?? '').'!',
            route('ressorts.show', $uebergabe->ressort));
    }

    protected function darfMitwirken(User $user, Uebergabe $uebergabe): void
    {
        abort_unless(
            $user->istAdmin() || in_array($user->id, [$uebergabe->von_id, $uebergabe->an_id], true),
            403
        );
        abort_if(in_array($uebergabe->status, ['abgeschlossen', 'abgebrochen'], true), 422, 'Diese Übergabe ist beendet.');
    }
}
