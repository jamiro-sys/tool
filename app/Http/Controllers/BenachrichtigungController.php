<?php

namespace App\Http\Controllers;

use App\Channels\WebPushKanal;
use App\Models\User;
use Illuminate\Http\Request;

class BenachrichtigungController extends Controller
{
    public function index(Request $request)
    {
        return view('benachrichtigungen.index', [
            'meldungen' => $request->user()->notifications()->paginate(30),
        ]);
    }

    public function oeffnen(Request $request, string $id)
    {
        $meldung = $request->user()->notifications()->findOrFail($id);
        $meldung->markAsRead();

        return redirect($meldung->data['url'] ?? route('benachrichtigungen.index'));
    }

    public function alleGelesen(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function anzahl(Request $request)
    {
        return response()->json(['ungelesen' => $request->user()->unreadNotifications()->count()]);
    }

    public function einstellungen(Request $request)
    {
        return view('benachrichtigungen.einstellungen', [
            'user' => $request->user(),
            'pushBereit' => WebPushKanal::konfiguriert(),
            'geraete' => $request->user()->pushAbos,
        ]);
    }

    public function einstellungenSpeichern(Request $request)
    {
        $daten = $request->validate([
            'kanal' => ['array'],
            'kanal.*' => ['array'],
            'ruhe_von' => ['nullable', 'date_format:H:i'],
            'ruhe_bis' => ['nullable', 'date_format:H:i'],
            'zusammenfassung' => ['sometimes', 'boolean'],
        ]);

        $einstellungen = [];
        foreach (array_keys(User::EREIGNISSE) as $ereignis) {
            $einstellungen[$ereignis] = array_values(array_intersect(array_keys(User::KANAELE), $daten['kanal'][$ereignis] ?? []));
        }

        $request->user()->update([
            'benachrichtigungen' => $einstellungen,
            'ruhe_von' => $daten['ruhe_von'] ?? null,
            'ruhe_bis' => $daten['ruhe_bis'] ?? null,
            'zusammenfassung' => $request->boolean('zusammenfassung'),
        ]);

        return back()->with('erfolg', 'Benachrichtigungen gespeichert.');
    }

    public function pushAbonnieren(Request $request)
    {
        $daten = $request->validate([
            // Nur echte Push-Dienste der Browser (Google, Apple, Mozilla, Microsoft)
            'endpoint' => ['required', 'url:https', 'max:500', 'regex:/^https:\/\/([a-z0-9-]+\.)*(googleapis\.com|push\.apple\.com|push\.services\.mozilla\.com|notify\.windows\.com)\//i'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $request->user()->pushAbos()->updateOrCreate(
            ['endpoint' => $daten['endpoint']],
            ['public_key' => $daten['keys']['p256dh'], 'auth_token' => $daten['keys']['auth'], 'geraet' => mb_substr((string) $request->userAgent(), 0, 250)]
        );

        return response()->json(['ok' => true]);
    }

    public function pushAbmelden(Request $request)
    {
        $request->validate(['endpoint' => ['required', 'string']]);
        $request->user()->pushAbos()->where('endpoint', $request->input('endpoint'))->delete();

        return response()->json(['ok' => true]);
    }

    public function pushTest(Request $request)
    {
        app(\App\Services\Benachrichtiger::class)->sende($request->user(), 'rundmeldung', 'Test erfolgreich', 'So sehen Benachrichtigungen aus dem OK-Tool aus.', route('start'));

        return back()->with('erfolg', 'Testmeldung ist unterwegs.');
    }
}
