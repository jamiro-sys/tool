<x-app titel="System">
    @php
        $cronOk = $cron && $cron->gt(now()->subMinutes(5));
        $queueOk = $jobs === 0 || ($aeltesterJob && $aeltesterJob > now()->subMinutes(10)->getTimestamp());
    @endphp
    <x-seitenkopf titel="System" ueber="Admin">
        <x-slot:aktionen>
            <a href="{{ route('admin.logs') }}" class="knopf-rahmen"><x-icon name="datei" class="h-4 w-4" /> Logs</a>
        </x-slot:aktionen>
    </x-seitenkopf>

    @if (session('wartung_link'))
        <div class="mb-5 border-l-4 border-gelb bg-white px-4 py-3 text-sm"><p class="label mb-1">Zugang während der Wartung</p><a href="{{ session('wartung_link') }}" class="break-all font-bold text-rot">{{ session('wartung_link') }}</a></div>
    @endif
    @if (session('vapid'))
        <div class="mb-5 border-l-4 border-gelb bg-white px-4 py-3 text-sm">
            <p class="label mb-2">Neue Push-Schlüssel: nur die Werte nach dem Gleichzeichen in die .env eintragen</p>
            <pre class="overflow-x-auto bg-kreide p-3 text-xs">VAPID_PUBLIC_KEY={{ session('vapid')['publicKey'] }}
VAPID_PRIVATE_KEY={{ session('vapid')['privateKey'] }}</pre>
        </div>
    @endif
    @if (session('ausgabe'))
        <pre class="mb-5 max-h-60 overflow-auto bg-nacht p-3 text-xs text-white">{{ session('ausgabe') }}</pre>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="karte p-4 {{ $cronOk ? '' : 'border-l-4 border-l-gelb' }}">
            <p class="label">Cron</p>
            <p class="mt-2 flex items-center gap-2 text-sm font-bold"><span class="h-2.5 w-2.5 {{ $cronOk ? 'bg-rot' : 'bg-gelb' }}"></span>{{ $cronOk ? 'läuft' : 'prüfen' }}</p>
            <p class="mt-1 text-xs text-beton">{{ $cron ? 'zuletzt '.$cron->diffForHumans() : 'noch nie gelaufen' }}</p>
        </div>
        <div class="karte p-4 {{ $queueOk ? '' : 'border-l-4 border-l-gelb' }}">
            <p class="label">Warteschlange</p>
            <p class="zahl mt-2 text-2xl">{{ $jobs }}</p>
            <p class="mt-1 text-xs text-beton">wartende Jobs</p>
        </div>
        <div class="karte p-4 {{ $fehlgeschlagen->count() ? 'border-l-4 border-l-gelb' : '' }}">
            <p class="label">Fehlgeschlagen</p>
            <p class="zahl mt-2 text-2xl">{{ $fehlgeschlagen->count() }}</p>
        </div>
        <div class="karte p-4 {{ $versandFehler ? 'border-l-4 border-l-gelb' : '' }}">
            <p class="label">Versandfehler 7 Tage</p>
            <p class="zahl mt-2 text-2xl">{{ $versandFehler }}</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="karte">
            <div class="karte-kopf"><h2 class="titel text-sm">Umgebung</h2></div>
            <dl class="divide-y divide-nacht/5 text-sm">
                @foreach ($info as $k => $v)<div class="flex justify-between gap-3 px-4 py-2"><dt class="label">{{ $k }}</dt><dd class="text-right">{{ $v }}</dd></div>@endforeach
                <div class="flex justify-between gap-3 px-4 py-2"><dt class="label">Log-Datei</dt><dd>{{ number_format($logGroesse / 1024, 0, '.', "'") }} KB</dd></div>
            </dl>
        </section>

        <section class="karte">
            <div class="karte-kopf"><h2 class="titel text-sm">Werkzeuge</h2></div>
            <div class="grid gap-2 p-4">
                @foreach (['cache' => ['Cache leeren', 'neu-laden'], 'optimieren' => ['Zwischenspeicher aufbauen', 'server'], 'queue' => ['Warteschlange jetzt abarbeiten', 'senden'], 'migrieren' => ['Datenbank migrieren', 'datenbank'], 'speicher_link' => ['Speicher-Link erstellen', 'link']] as $aktion => [$label, $icon])
                    <form method="POST" action="{{ route('admin.system.aktion') }}">@csrf<input type="hidden" name="aktion" value="{{ $aktion }}">
                        <button class="knopf-rahmen w-full justify-start"><x-icon :name="$icon" class="h-4 w-4" /> {{ $label }}</button></form>
                @endforeach
                <form method="POST" action="{{ route('admin.system.aktion') }}" onsubmit="return confirm('{{ $wartung ? 'Wartung beenden?' : 'Wartungsmodus aktivieren? Alle anderen sehen eine Wartungsseite.' }}')">@csrf
                    <input type="hidden" name="aktion" value="{{ $wartung ? 'wartung_aus' : 'wartung_an' }}">
                    <button class="{{ $wartung ? 'knopf-rot' : 'knopf-nacht' }} w-full justify-start"><x-icon name="schild" class="h-4 w-4" /> {{ $wartung ? 'Wartungsmodus beenden' : 'Wartungsmodus aktivieren' }}</button></form>
                <form method="POST" action="{{ route('admin.system.mailtest') }}">@csrf<button class="knopf-rahmen w-full justify-start"><x-icon name="mail" class="h-4 w-4" /> Testmail an mich</button></form>
                <form method="POST" action="{{ route('admin.system.vapid') }}" onsubmit="return confirm('Neue Push-Schlüssel erzeugen? Bestehende Geräte müssen Push danach neu aktivieren.')">@csrf<button class="knopf-rahmen w-full justify-start"><x-icon name="glocke" class="h-4 w-4" /> Push-Schlüssel erzeugen</button></form>
            </div>
        </section>

        <section class="karte">
            <div class="karte-kopf"><h2 class="titel text-sm">Updates</h2><a href="{{ route('neuigkeiten') }}" class="label hover:text-rot">Alle</a></div>
            <ul class="divide-y divide-nacht/5 text-sm">
                @forelse ($deployments as $d)
                    <li class="px-4 py-2.5"><div class="flex items-center justify-between"><span class="font-bold">{{ $d->version }}</span><span class="text-xs text-beton">{{ $d->created_at->format('d.m.Y H:i') }}</span></div>
                        @if ($d->commit)<p class="font-mono text-[11px] text-beton">{{ \Illuminate\Support\Str::limit($d->commit, 12, '') }}</p>@endif</li>
                @empty
                    <li class="px-4 py-5 text-beton">Noch kein automatisches Update registriert.</li>
                @endforelse
            </ul>
        </section>

        <section class="karte xl:col-span-2">
            <div class="karte-kopf"><h2 class="titel text-sm">Fehlgeschlagene Jobs</h2>
                @if ($fehlgeschlagen->count())
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.system.jobs.wiederholen') }}">@csrf<button class="knopf-rahmen knopf-klein">Alle wiederholen</button></form>
                        <form method="POST" action="{{ route('admin.system.jobs.loeschen') }}" onsubmit="return confirm('Alle fehlgeschlagenen Jobs löschen?')">@csrf<button class="knopf-leise knopf-klein">Löschen</button></form>
                    </div>
                @endif
            </div>
            <ul class="divide-y divide-nacht/5 text-xs">
                @forelse ($fehlgeschlagen as $f)
                    <li class="px-4 py-2.5" x-data="{ auf: false }">
                        <button type="button" class="flex w-full items-center justify-between gap-3 text-left" x-on:click="auf = !auf">
                            <span class="truncate font-bold">{{ json_decode($f->payload)->displayName ?? 'Job' }}</span><span class="shrink-0 text-beton">{{ $f->failed_at }}</span></button>
                        <pre x-show="auf" x-collapse class="mt-2 max-h-60 overflow-auto bg-kreide p-2 text-[11px]">{{ \Illuminate\Support\Str::limit($f->exception, 3000) }}</pre>
                    </li>
                @empty
                    <li class="px-4 py-5 text-beton">Keine. Alles läuft rund.</li>
                @endforelse
            </ul>
        </section>

        <section class="karte">
            <div class="karte-kopf"><h2 class="titel text-sm">Versandprotokoll</h2></div>
            <ul class="max-h-96 divide-y divide-nacht/5 overflow-y-auto text-xs">
                @forelse ($versand as $v)
                    <li class="flex items-center gap-2 px-4 py-2"><span class="{{ $v->status === 'ok' ? 'marke-rahmen' : 'marke-gelb' }}">{{ $v->kanal }}</span><span class="min-w-0 flex-1 truncate">{{ $v->user?->name }}: {{ $v->typ }}@if ($v->fehler) <span class="text-beton">({{ $v->fehler }})</span>@endif</span><span class="shrink-0 text-beton">{{ $v->created_at->format('d.m. H:i') }}</span></li>
                @empty
                    <li class="px-4 py-5 text-beton">Noch nichts versendet.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-app>
