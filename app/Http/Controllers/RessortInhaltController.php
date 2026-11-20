<?php

namespace App\Http\Controllers;

use App\Models\Dokument;
use App\Models\Kontakt;
use App\Models\Ressort;
use App\Models\Wissen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** Kontakte, Wissensseiten und Dokumente eines Ressorts. */
class RessortInhaltController extends Controller
{
    public function kontaktSpeichern(Request $request, Ressort $ressort, ?Kontakt $kontakt = null)
    {
        Gate::authorize('ressort-mitarbeiten', $ressort);
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'firma' => ['nullable', 'string', 'max:150'],
            'funktion' => ['nullable', 'string', 'max:150'],
            'telefon' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'notizen' => ['nullable', 'string', 'max:5000'],
        ]);
        if ($kontakt && $kontakt->exists) {
            abort_unless($kontakt->ressort_id === $ressort->id, 404);
            $kontakt->update($daten);
        } else {
            $ressort->kontakte()->create($daten);
        }

        return redirect(route('ressorts.show', [$ressort, 'tab' => 'kontakte']))->with('erfolg', 'Kontakt gespeichert.');
    }

    public function kontaktLoeschen(Ressort $ressort, Kontakt $kontakt)
    {
        Gate::authorize('ressort-mitarbeiten', $ressort);
        abort_unless($kontakt->ressort_id === $ressort->id, 404);
        $kontakt->delete();

        return redirect(route('ressorts.show', [$ressort, 'tab' => 'kontakte']))->with('erfolg', 'Kontakt entfernt.');
    }

    public function wissenSpeichern(Request $request, Ressort $ressort, ?Wissen $wissen = null)
    {
        Gate::authorize('ressort-mitarbeiten', $ressort);
        $daten = $request->validate([
            'titel' => ['required', 'string', 'max:200'],
            'inhalt' => ['required', 'string', 'max:50000'],
        ]);
        if ($wissen && $wissen->exists) {
            abort_unless($wissen->ressort_id === $ressort->id, 404);
            $wissen->update($daten + ['user_id' => $request->user()->id]);
        } else {
            $ressort->wissen()->create($daten + ['user_id' => $request->user()->id, 'sortierung' => $ressort->wissen()->count()]);
        }

        return redirect(route('ressorts.show', [$ressort, 'tab' => 'wissen']))->with('erfolg', 'Wissensseite gespeichert.');
    }

    public function wissenLoeschen(Ressort $ressort, Wissen $wissen)
    {
        Gate::authorize('ressort-verwalten', $ressort);
        abort_unless($wissen->ressort_id === $ressort->id, 404);
        $wissen->delete();

        return redirect(route('ressorts.show', [$ressort, 'tab' => 'wissen']))->with('erfolg', 'Wissensseite entfernt.');
    }

    public function dokumentSpeichern(Request $request, Ressort $ressort)
    {
        Gate::authorize('ressort-mitarbeiten', $ressort);
        $daten = $request->validate([
            'titel' => ['required', 'string', 'max:200'],
            'url' => ['nullable', 'url', 'max:1024', 'required_without:datei'],
            'datei' => ['nullable', 'file', 'max:20480', 'required_without:url'],
            'aufgabe_id' => ['nullable', 'exists:aufgaben,id'],
        ]);

        $eintrag = ['titel' => $daten['titel'], 'user_id' => $request->user()->id, 'aufgabe_id' => $daten['aufgabe_id'] ?? null];
        if ($request->hasFile('datei')) {
            $eintrag['typ'] = 'datei';
            $datei = $request->file('datei');
            $eintrag['pfad'] = $datei->store('dokumente/'.$ressort->id, 'local');
            $eintrag += ['original_name' => $datei->getClientOriginalName(), 'groesse' => $datei->getSize(), 'mime' => $datei->getMimeType()];
        } else {
            $eintrag['typ'] = 'link';
            $eintrag['url'] = $daten['url'];
        }
        $ressort->dokumente()->create($eintrag);

        return back()->with('erfolg', 'Dokument abgelegt.');
    }

    public function dokumentHerunterladen(Dokument $dokument)
    {
        abort_unless($dokument->typ === 'datei' && $dokument->pfad && Storage::disk('local')->exists($dokument->pfad), 404);
        $endung = pathinfo($dokument->pfad, PATHINFO_EXTENSION);

        $name = \Illuminate\Support\Str::slug($dokument->titel).'.'.$endung;

        // Bilder und PDF direkt im Browser anzeigen, alles andere herunterladen
        return request()->boolean('ansehen') && preg_match('/^(image\/|application\/pdf)/', (string) $dokument->mime)
            ? Storage::disk('local')->response($dokument->pfad, $name)
            : Storage::disk('local')->download($dokument->pfad, $name);
    }

    public function dokumentLoeschen(Dokument $dokument)
    {
        abort_unless(
            ($dokument->ressort && Gate::allows('ressort-mitarbeiten', $dokument->ressort))
            || $dokument->user_id === auth()->id() || Gate::allows('admin'),
            403
        );
        if ($dokument->pfad) {
            Storage::disk('local')->delete($dokument->pfad);
        }
        $dokument->delete();

        return back()->with('erfolg', 'Dokument entfernt.');
    }
}
