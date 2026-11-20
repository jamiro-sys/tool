<x-app titel="Auswertung">
    <x-seitenkopf titel="Auswertung" ueber="Zahlen und Trends">
        <x-slot:unter>Wo steckt die Arbeit? Wie entwickeln sich die Turniere? Wie gut sind wir für die Zukunft aufgestellt?</x-slot:unter>
    </x-seitenkopf>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach (['neu' => 'Neu im Jahresplan', 'angepasst' => 'Angepasst', 'gestrichen' => 'Kommen nicht mehr', 'offen' => 'Erkenntnisse offen'] as $k => $l)
            <div class="karte p-4"><p class="label">{{ $l }}</p><p class="zahl mt-2 text-3xl {{ $k === 'offen' && $wandel[$k] ? 'text-rot' : '' }}" x-data="zaehler({{ $wandel[$k] }})" x-text="wert">{{ $wandel[$k] }}</p></div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="karte xl:col-span-2">
            <div class="karte-kopf"><h2 class="titel text-sm">Aufwand pro Ressort und Turnier</h2><span class="label">Stunden, effektiv sonst geplant</span></div>
            <div class="h-80 p-4">
                @if (count($aufwand['reihen']))
                    <canvas x-data x-init="diagramm($el, 'balken-gestapelt', {{ json_encode($aufwand) }})" aria-label="Aufwand pro Ressort"></canvas>
                @else
                    <x-leer icon="auswertung" titel="Noch keine Aufwandsdaten" text="Im Rückblick die effektiven Stunden erfassen." />
                @endif
            </div>
        </section>

        <section class="karte">
            <div class="karte-kopf"><h2 class="titel text-sm">Turnierarten im Vergleich</h2><span class="label">Aufgaben pro Ressort</span></div>
            <div class="h-80 p-4"><canvas x-data x-init="diagramm($el, 'balken', { labels: {{ json_encode($ressortNamen) }}, reihen: {{ json_encode(collect($vergleich)->map(fn ($v, $i) => ['name' => $v['name'], 'werte' => $v['werte'], 'farbe' => $i ? '#14213D' : '#B8161C'])->values()) }} })"></canvas></div>
        </section>

        <section class="karte">
            <div class="karte-kopf"><h2 class="titel text-sm">Pünktlichkeit</h2><span class="label">Erledigt bis Fälligkeit</span></div>
            <div class="h-80 p-4"><canvas x-data x-init="diagramm($el, 'linie', { labels: {{ json_encode($puenktlich['labels']) }}, werte: {{ json_encode($puenktlich['werte']) }}, name: 'Pünktlich' })"></canvas></div>
        </section>

        @if ($naechste && count($last))
            <section class="karte">
                <div class="karte-kopf"><h2 class="titel text-sm">Arbeitsverteilung</h2><span class="label">{{ $naechste->titel }}</span></div>
                <div class="p-4" style="height: {{ max(200, count($last) * 34 + 60) }}px"><canvas x-data x-init="diagramm($el, 'balken', { labels: {{ json_encode(array_column($last, 'name')) }}, horizontal: true, reihen: [{ name: 'Offen', werte: {{ json_encode(array_column($last, 'offen')) }}, farbe: '#14213D' }, { name: 'Erledigt', werte: {{ json_encode(array_column($last, 'erledigt')) }}, farbe: '#B8161C' }] })"></canvas></div>
            </section>
        @endif

        <section class="karte">
            <div class="karte-kopf"><h2 class="titel text-sm">Übergabe-Bereitschaft</h2></div>
            <div class="space-y-4 p-4">
                @foreach ($wissen as $w)
                    <a href="{{ route('ressorts.show', $w['ressort']) }}" class="block hover:text-rot">
                        <p class="mb-1 text-sm font-bold">{{ $w['ressort']->name }}</p>
                        @include('ressorts._ampel', ['stand' => $w['stand']])
                    </a>
                @endforeach
            </div>
        </section>
    </div>

    @if ($kennzahlen['reihen'])
        <section class="karte mt-6">
            <div class="karte-kopf"><h2 class="titel text-sm">Kennzahlen im Vergleich</h2><span class="label">eigene Kennzahlen je Turnier</span></div>
            <div class="h-80 p-4"><canvas x-data x-init="diagramm($el, 'balken', {{ json_encode($kennzahlen) }})" aria-label="Kennzahlen je Turnier"></canvas></div>
        </section>
    @endif
</x-app>
