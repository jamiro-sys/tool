<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AnmeldungController;
use App\Http\Controllers\AufgabeController;
use App\Http\Controllers\AusgabeController;
use App\Http\Controllers\AuswertungController;
use App\Http\Controllers\BenachrichtigungController;
use App\Http\Controllers\DateienController;
use App\Http\Controllers\ErkenntnisController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RessortController;
use App\Http\Controllers\RessortInhaltController;
use App\Http\Controllers\StartController;
use App\Http\Controllers\SucheController;
use App\Http\Controllers\UebergabeController;
use App\Http\Controllers\VerknuepfungController;
use App\Http\Controllers\VorlageController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/anmelden', [AnmeldungController::class, 'zeige'])->name('anmelden');
    Route::post('/anmelden', [AnmeldungController::class, 'anmelden'])->middleware('throttle:20,1');
    Route::get('/einladung/{token}', [AnmeldungController::class, 'einladung'])->name('einladung');
    Route::post('/einladung/{token}', [AnmeldungController::class, 'einladungAnnehmen']);
    Route::get('/passwort-vergessen', [AnmeldungController::class, 'vergessen'])->name('password.request');
    Route::post('/passwort-vergessen', [AnmeldungController::class, 'vergessenSenden'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/passwort-zuruecksetzen/{token}', [AnmeldungController::class, 'zuruecksetzen'])->name('password.reset');
    Route::post('/passwort-zuruecksetzen', [AnmeldungController::class, 'zuruecksetzenSpeichern'])->name('password.update');
});

Route::get('/offline', fn () => view('offline'))->name('offline');

Route::middleware(['auth', 'aktiv'])->group(function () {
    Route::post('/abmelden', [AnmeldungController::class, 'abmelden'])->name('abmelden');
    Route::get('/', [StartController::class, 'index'])->name('start');
    Route::get('/suche', SucheController::class)->name('suche');

    // Ausgaben
    Route::get('/ausgaben', [AusgabeController::class, 'index'])->name('ausgaben.index');
    Route::get('/ausgaben/neu', [AusgabeController::class, 'create'])->name('ausgaben.create');
    Route::post('/ausgaben', [AusgabeController::class, 'store'])->name('ausgaben.store');
    Route::get('/ausgaben/{ausgabe}', [AusgabeController::class, 'show'])->name('ausgaben.show');
    Route::patch('/ausgaben/{ausgabe}', [AusgabeController::class, 'update'])->name('ausgaben.update');
    Route::delete('/ausgaben/{ausgabe}', [AusgabeController::class, 'destroy'])->name('ausgaben.destroy');
    Route::get('/ausgaben/{ausgabe}/rueckblick', [AusgabeController::class, 'rueckblick'])->name('ausgaben.rueckblick');
    Route::post('/ausgaben/{ausgabe}/aufwand', [AusgabeController::class, 'aufwand'])->name('ausgaben.aufwand');

    // Links zu externen Werkzeugen und Kennzahlen
    Route::post('/verknuepfungen', [VerknuepfungController::class, 'speichern'])->name('verknuepfungen.speichern');
    Route::delete('/verknuepfungen/{verknuepfung}', [VerknuepfungController::class, 'loeschen'])->name('verknuepfungen.loeschen');
    Route::post('/ausgaben/{ausgabe}/kennzahlen', [VerknuepfungController::class, 'kennzahlen'])->name('ausgaben.kennzahlen');

    // Gemeinsame Dateiablage
    Route::get('/dateien', [DateienController::class, 'index'])->name('dateien.index');
    Route::post('/dateien', [DateienController::class, 'speichern'])->name('dateien.speichern');
    Route::patch('/dateien/{dokument}', [DateienController::class, 'aendern'])->name('dateien.aendern');

    // Material und Inventar
    Route::get('/material', [MaterialController::class, 'index'])->name('material.index');
    Route::get('/material/drucken', [MaterialController::class, 'drucken'])->name('material.drucken');
    Route::post('/material/{material?}', [MaterialController::class, 'speichern'])->name('material.speichern');
    Route::delete('/material/{material}', [MaterialController::class, 'loeschen'])->name('material.loeschen');
    Route::get('/material/{material}/foto', [MaterialController::class, 'foto'])->name('material.foto');
    Route::get('/ausgaben/{ausgabe}/material', [MaterialController::class, 'ausgabe'])->name('material.ausgabe');
    Route::post('/ausgaben/{ausgabe}/material/uebernehmen', [MaterialController::class, 'uebernehmen'])->name('material.uebernehmen');
    Route::post('/ausgaben/{ausgabe}/material/{einsatz?}', [MaterialController::class, 'einsatz'])->name('material.einsatz');
    Route::post('/ausgaben/{ausgabe}/material/{einsatz}/haken', [MaterialController::class, 'haken'])->name('material.haken');
    Route::delete('/ausgaben/{ausgabe}/material/{einsatz}', [MaterialController::class, 'einsatzLoeschen'])->name('material.einsatz.loeschen');

    // Aufgaben
    Route::get('/ausgaben/{ausgabe}/aufgaben', [AufgabeController::class, 'index'])->name('aufgaben.index');
    Route::post('/ausgaben/{ausgabe}/aufgaben', [AufgabeController::class, 'store'])->name('aufgaben.store');
    Route::get('/aufgaben/{aufgabe}', [AufgabeController::class, 'show'])->name('aufgaben.show');
    Route::patch('/aufgaben/{aufgabe}', [AufgabeController::class, 'update'])->name('aufgaben.update');
    Route::delete('/aufgaben/{aufgabe}', [AufgabeController::class, 'destroy'])->name('aufgaben.destroy');
    Route::post('/aufgaben/{aufgabe}/status', [AufgabeController::class, 'status'])->name('aufgaben.status');
    Route::post('/aufgaben/{aufgabe}/checkpunkt', [AufgabeController::class, 'checkpunkt'])->name('aufgaben.checkpunkt');
    Route::post('/aufgaben/{aufgabe}/kommentare', [AufgabeController::class, 'kommentar'])->name('aufgaben.kommentar');
    Route::delete('/kommentare/{kommentar}', [AufgabeController::class, 'kommentarLoeschen'])->name('kommentare.destroy');

    // Vorlagen
    Route::get('/vorlagen', [VorlageController::class, 'index'])->name('vorlagen.index');
    Route::get('/vorlagen/neu', [VorlageController::class, 'create'])->name('vorlagen.create');
    Route::post('/vorlagen', [VorlageController::class, 'store'])->name('vorlagen.store');
    Route::get('/vorlagen/{vorlage}', [VorlageController::class, 'show'])->name('vorlagen.show');
    Route::get('/vorlagen/{vorlage}/bearbeiten', [VorlageController::class, 'edit'])->name('vorlagen.edit');
    Route::put('/vorlagen/{vorlage}', [VorlageController::class, 'update'])->name('vorlagen.update');
    Route::post('/vorlagen/{vorlage}/aktiv', [VorlageController::class, 'aktiv'])->name('vorlagen.aktiv');

    // Erkenntnisse
    Route::post('/erkenntnisse', [ErkenntnisController::class, 'store'])->name('erkenntnisse.store');
    Route::post('/erkenntnisse/{erkenntnis}/uebernehmen', [ErkenntnisController::class, 'uebernehmen'])->name('erkenntnisse.uebernehmen');
    Route::delete('/erkenntnisse/{erkenntnis}', [ErkenntnisController::class, 'destroy'])->name('erkenntnisse.destroy');

    // Ressorts
    Route::get('/ressorts', [RessortController::class, 'index'])->name('ressorts.index');
    Route::post('/ressorts', [RessortController::class, 'store'])->name('ressorts.store');
    Route::get('/ressorts/{ressort}', [RessortController::class, 'show'])->name('ressorts.show');
    Route::get('/ressorts/{ressort}/dossier', [RessortController::class, 'dossier'])->name('ressorts.dossier');
    Route::patch('/ressorts/{ressort}', [RessortController::class, 'update'])->name('ressorts.update');
    Route::delete('/ressorts/{ressort}', [RessortController::class, 'destroy'])->name('ressorts.destroy');
    Route::post('/ressorts/{ressort}/mitglieder', [RessortController::class, 'mitglied'])->name('ressorts.mitglied');
    Route::post('/ressorts/{ressort}/kontakte/{kontakt?}', [RessortInhaltController::class, 'kontaktSpeichern'])->name('kontakte.speichern');
    Route::delete('/ressorts/{ressort}/kontakte/{kontakt}', [RessortInhaltController::class, 'kontaktLoeschen'])->name('kontakte.loeschen');
    Route::post('/ressorts/{ressort}/wissen/{wissen?}', [RessortInhaltController::class, 'wissenSpeichern'])->name('wissen.speichern');
    Route::delete('/ressorts/{ressort}/wissen/{wissen}', [RessortInhaltController::class, 'wissenLoeschen'])->name('wissen.loeschen');
    Route::post('/ressorts/{ressort}/dokumente', [RessortInhaltController::class, 'dokumentSpeichern'])->name('dokumente.speichern');
    Route::get('/dokumente/{dokument}', [RessortInhaltController::class, 'dokumentHerunterladen'])->name('dokumente.download');
    Route::delete('/dokumente/{dokument}', [RessortInhaltController::class, 'dokumentLoeschen'])->name('dokumente.loeschen');

    // Uebergaben
    Route::get('/uebergaben', [UebergabeController::class, 'index'])->name('uebergaben.index');
    Route::post('/uebergaben', [UebergabeController::class, 'store'])->name('uebergaben.store');
    Route::get('/uebergaben/{uebergabe}', [UebergabeController::class, 'show'])->name('uebergaben.show');
    Route::post('/uebergaben/{uebergabe}/checkpunkt', [UebergabeController::class, 'checkpunkt'])->name('uebergaben.checkpunkt');
    Route::post('/uebergaben/{uebergabe}/bestaetigen', [UebergabeController::class, 'bestaetigen'])->name('uebergaben.bestaetigen');
    Route::post('/uebergaben/{uebergabe}/abbrechen', [UebergabeController::class, 'abbrechen'])->name('uebergaben.abbrechen');

    Route::get('/auswertung', [AuswertungController::class, 'index'])->name('auswertung');

    // Benachrichtigungen
    Route::get('/benachrichtigungen', [BenachrichtigungController::class, 'index'])->name('benachrichtigungen.index');
    Route::get('/benachrichtigungen/anzahl', [BenachrichtigungController::class, 'anzahl'])->name('benachrichtigungen.anzahl');
    Route::get('/benachrichtigungen/einstellungen', [BenachrichtigungController::class, 'einstellungen'])->name('benachrichtigungen.einstellungen');
    Route::post('/benachrichtigungen/einstellungen', [BenachrichtigungController::class, 'einstellungenSpeichern']);
    Route::post('/benachrichtigungen/gelesen', [BenachrichtigungController::class, 'alleGelesen'])->name('benachrichtigungen.gelesen');
    Route::post('/benachrichtigungen/test', [BenachrichtigungController::class, 'pushTest'])->name('benachrichtigungen.test');
    Route::post('/push/abo', [BenachrichtigungController::class, 'pushAbonnieren'])->name('push.abo');
    Route::delete('/push/abo', [BenachrichtigungController::class, 'pushAbmelden']);
    Route::get('/benachrichtigungen/{id}', [BenachrichtigungController::class, 'oeffnen'])->name('benachrichtigungen.oeffnen');

    // Profil
    Route::get('/profil', [ProfilController::class, 'zeige'])->name('profil');
    Route::patch('/profil', [ProfilController::class, 'speichern']);
    Route::post('/profil/passwort', [ProfilController::class, 'passwort'])->name('profil.passwort');
    Route::post('/profil/ansicht', [ProfilController::class, 'ansicht'])->name('profil.ansicht');
    Route::post('/profil/version', [ProfilController::class, 'versionGesehen'])->name('profil.version');
    Route::get('/neuigkeiten', [ProfilController::class, 'neuigkeiten'])->name('neuigkeiten');
    Route::post('/imitieren/beenden', [Admin\PersonenController::class, 'imitierenBeenden'])->name('imitieren.beenden');

    // Admin
    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/personen', [Admin\PersonenController::class, 'index'])->name('personen');
        Route::post('/personen', [Admin\PersonenController::class, 'store']);
        Route::patch('/personen/{person}', [Admin\PersonenController::class, 'update'])->name('personen.update');
        Route::post('/personen/{person}/einladung', [Admin\PersonenController::class, 'einladungErneut'])->name('personen.einladung');
        Route::post('/personen/{person}/passwort', [Admin\PersonenController::class, 'passwortLink'])->name('personen.passwort');
        Route::post('/personen/{person}/imitieren', [Admin\PersonenController::class, 'imitieren'])->name('personen.imitieren');

        Route::get('/system', [Admin\SystemController::class, 'index'])->name('system');
        Route::post('/system/aktion', [Admin\SystemController::class, 'aktion'])->name('system.aktion');
        Route::post('/system/jobs/wiederholen', [Admin\SystemController::class, 'jobWiederholen'])->name('system.jobs.wiederholen');
        Route::post('/system/jobs/loeschen', [Admin\SystemController::class, 'jobsLoeschen'])->name('system.jobs.loeschen');
        Route::post('/system/mailtest', [Admin\SystemController::class, 'mailTest'])->name('system.mailtest');
        Route::post('/system/vapid', [Admin\SystemController::class, 'vapid'])->name('system.vapid');
        Route::get('/logs', [Admin\SystemController::class, 'logs'])->name('logs');
        Route::post('/logs/leeren', [Admin\SystemController::class, 'logsLeeren'])->name('logs.leeren');

        Route::get('/sicherungen', [Admin\SicherungController::class, 'index'])->name('sicherungen');
        Route::post('/sicherungen', [Admin\SicherungController::class, 'erstellen']);
        Route::post('/sicherungen/hochladen', [Admin\SicherungController::class, 'hochladen'])->name('sicherungen.hochladen');
        Route::get('/sicherungen/{name}', [Admin\SicherungController::class, 'herunterladen'])->name('sicherungen.download');
        Route::post('/sicherungen/{name}/wiederherstellen', [Admin\SicherungController::class, 'wiederherstellen'])->name('sicherungen.wiederherstellen');
        Route::delete('/sicherungen/{name}', [Admin\SicherungController::class, 'loeschen'])->name('sicherungen.loeschen');

        Route::get('/daten', [Admin\DatenController::class, 'index'])->name('daten');
        Route::get('/daten/vorlagen', [Admin\DatenController::class, 'vorlagenExport'])->name('daten.vorlagen');
        Route::post('/daten/vorlagen', [Admin\DatenController::class, 'vorlagenImport']);
        Route::get('/daten/gesamt', [Admin\DatenController::class, 'gesamtExport'])->name('daten.gesamt');
        Route::post('/daten/leeren', [Admin\DatenController::class, 'leeren'])->name('daten.leeren');
        Route::get('/daten/ausgaben/{ausgabe}/csv', [Admin\DatenController::class, 'aufgabenCsv'])->name('daten.csv');

        Route::get('/einstellungen', [Admin\EinstellungenController::class, 'index'])->name('einstellungen');
        Route::post('/einstellungen', [Admin\EinstellungenController::class, 'speichern']);
        Route::post('/auswahllisten', [Admin\AuswahllistenController::class, 'speichern'])->name('auswahllisten.speichern');
        Route::delete('/auswahllisten/{eintrag}', [Admin\AuswahllistenController::class, 'loeschen'])->name('auswahllisten.loeschen');
        Route::post('/hallen/{halle?}', [Admin\EinstellungenController::class, 'halle'])->name('hallen.speichern');
        Route::post('/turniere/{turnier?}', [Admin\EinstellungenController::class, 'turnier'])->name('turniere.speichern');

        Route::get('/protokoll', [Admin\ProtokollController::class, 'index'])->name('protokoll');
        Route::get('/rundmeldungen', [Admin\ProtokollController::class, 'rundmeldungen'])->name('rundmeldungen');
        Route::post('/rundmeldungen', [Admin\ProtokollController::class, 'rundmeldungSenden']);
    });
});
