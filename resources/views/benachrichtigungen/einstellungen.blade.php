<x-app titel="Benachrichtigungen einstellen">
    <x-seitenkopf titel="Benachrichtigungen" ueber="Einstellungen" :zurueck="route('benachrichtigungen.index')">
        <x-slot:unter>Du entscheidest, was dich wie erreicht. Weniger ist oft mehr.</x-slot:unter>
    </x-seitenkopf>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" class="space-y-6 lg:col-span-2">
            @csrf
            <section class="karte overflow-x-auto">
                <table class="tabelle">
                    <thead><tr><th>Ereignis</th>@foreach (\App\Models\User::KANAELE as $k => $l)<th class="w-24 text-center">{{ $l }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach (\App\Models\User::EREIGNISSE as $e => $label)
                            @php $aktiv = $user->kanaeleFuer($e); @endphp
                            <tr>
                                <td class="text-sm">{{ $label }}</td>
                                @foreach (\App\Models\User::KANAELE as $k => $l)
                                    <td class="text-center"><input type="checkbox" class="h-5 w-5" name="kanal[{{ $e }}][]" value="{{ $k }}" @checked(in_array($k, $aktiv, true)) aria-label="{{ $label }}: {{ $l }}"></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            <section class="karte space-y-4 p-4">
                <p class="titel text-sm">Ruhezeit</p>
                <p class="text-xs text-beton">In dieser Zeit werden E-Mails und Push zurückgehalten und danach zugestellt. In der App siehst du alles sofort.</p>
                <div class="flex items-center gap-3">
                    <input type="time" name="ruhe_von" value="{{ $user->ruhe_von }}" class="feld w-32" aria-label="Ruhezeit von">
                    <span class="text-sm">bis</span>
                    <input type="time" name="ruhe_bis" value="{{ $user->ruhe_bis }}" class="feld w-32" aria-label="Ruhezeit bis">
                </div>
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="zusammenfassung" value="1" class="mt-0.5 h-5 w-5" @checked($user->zusammenfassung)>
                    <span><span class="font-bold">Tageszusammenfassung statt einzelner E-Mails</span><br><span class="text-beton">Einmal am Abend um 18.00 Uhr eine Mail mit allem Neuen.</span></span></label>
            </section>
            <button class="knopf-rot">Speichern</button>
        </form>

        <aside class="space-y-6">
            <section class="karte p-4" x-data="pushVerwaltung(@js(config('services.webpush.public_key')), '{{ route('push.abo') }}')">
                <p class="titel text-sm">Push auf diesem Gerät</p>
                @if (! $pushBereit)
                    <p class="mt-2 text-sm text-beton">Push ist auf dem Server noch nicht eingerichtet. Der Admin erstellt dafür die VAPID-Schlüssel.</p>
                @else
                    <template x-if="!unterstuetzt"><p class="mt-2 text-sm text-beton">Dieser Browser unterstützt keine Push-Meldungen.</p></template>
                    <template x-if="unterstuetzt">
                        <div class="mt-3 space-y-3">
                            <p x-show="ios" class="border-l-4 border-gelb bg-kreide px-3 py-2 text-xs">Auf dem iPhone: zuerst über Teilen und "Zum Home-Bildschirm" installieren, dann hier aktivieren.</p>
                            <p class="flex items-center gap-2 text-sm"><span class="h-2.5 w-2.5" :class="aktiv ? 'bg-rot' : 'bg-beton/40'"></span><span x-text="aktiv ? 'Aktiv' : 'Nicht aktiv'"></span></p>
                            <button type="button" class="knopf-rot w-full" x-show="!aktiv" x-on:click="einschalten()" :disabled="laedt">Push aktivieren</button>
                            <button type="button" class="knopf-rahmen w-full" x-show="aktiv" x-on:click="ausschalten()" :disabled="laedt">Push deaktivieren</button>
                        </div>
                    </template>
                @endif
                @if ($geraete->count())
                    <p class="label mt-4">Registrierte Geräte ({{ $geraete->count() }})</p>
                    <ul class="mt-1 space-y-1 text-xs text-beton">@foreach ($geraete as $g)<li class="truncate">{{ \Illuminate\Support\Str::limit($g->geraet, 60) }}</li>@endforeach</ul>
                @endif
            </section>
            <form method="POST" action="{{ route('benachrichtigungen.test') }}" class="karte p-4">@csrf
                <p class="titel text-sm">Testen</p>
                <p class="mt-1 text-xs text-beton">Schickt dir eine Testmeldung über die Kanäle, die du für Rundmeldungen gewählt hast.</p>
                <button class="knopf-nacht mt-3 w-full">Testmeldung senden</button>
            </form>
        </aside>
    </div>
</x-app>
