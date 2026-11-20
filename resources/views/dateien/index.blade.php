<x-app titel="Dateien">
    <x-seitenkopf titel="Dateien" ueber="Gemeinsame Ablage">
        <x-slot:unter>Alles an einem Ort: je Ressort ein Ordner, darin eigene Unterordner. PDF und Bilder öffnen direkt in der Vorschau.</x-slot:unter>
    </x-seitenkopf>

    <a href="{{ route('styleguide') }}" class="karte mb-6 flex items-center gap-4 border-l-4 border-l-rot p-4 hover:border-nacht">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center bg-rot text-white"><img src="{{ asset('img/fcm-logo-negativ.svg') }}" alt="" class="h-8"></span>
        <span class="min-w-0 flex-1"><span class="titel block text-sm">FCM-Styleguide</span><span class="block text-xs text-beton">Farben, Schrift, Logos, Aufbau und Schreibweise. Immer aktuell, als PDF herunterladbar. Stand {{ config('app.version') }}</span></span>
        <x-icon name="pfeil" class="h-4 w-4 shrink-0" />
    </a>

    @can('schreiben')
        @include('dateien._hochladen')
    @endcan

    <form method="GET" class="mb-5 grid gap-2 sm:grid-cols-[220px_160px_1fr] sm:items-end" x-data x-on:change="$el.submit()">
        <div><label class="feld-label" for="d-ressort">Ressort</label>
            <select id="d-ressort" name="ressort" class="feld"><option value="">Alle</option><option value="allgemein" @selected($filter['ressort'] === 'allgemein')>Allgemein</option>@foreach ($ressorts as $r)<option value="{{ $r->id }}" @selected($filter['ressort'] == $r->id)>{{ $r->name }}</option>@endforeach</select></div>
        <div><label class="feld-label" for="d-art">Art</label>
            <select id="d-art" name="art" class="feld"><option value="">Alle</option><option value="datei" @selected($filter['art'] === 'datei')>Dateien</option><option value="link" @selected($filter['art'] === 'link')>Links</option></select></div>
        <div><label class="feld-label" for="d-q">Suche</label><input id="d-q" name="q" value="{{ $filter['q'] }}" class="feld" placeholder="Titel oder Dateiname"></div>
    </form>

    @php
        $orte = \App\Models\Ordner::orte($ressorts, $ordner);
        $nurRessort = $filter['ressort'] === 'allgemein' ? collect() : ($filter['ressort'] ? $ressorts->where('id', (int) $filter['ressort']) : $ressorts);
        $gruppen = \App\Models\Ordner::gruppen($nurRessort, $ordner, $dokumente, ! $filter['ressort'] || $filter['ressort'] === 'allgemein');
    @endphp
    @if ($flach)
        <section class="karte">
            <div class="karte-kopf"><h2 class="titel text-sm">Treffer</h2><span class="label">{{ $dokumente->count() }} von {{ $gesamt }}</span></div>
            @if ($dokumente->isEmpty())
                <x-leer icon="datei" titel="Nichts gefunden" text="Passe die Suche oder die Filter an." />
            @else
                <ul class="divide-y divide-nacht/5">@foreach ($dokumente as $d)@include('dateien._eintrag', ['d' => $d, 'pfad' => true])@endforeach</ul>
            @endif
        </section>
    @elseif ($gesamt === 0 && $ordner->isEmpty())
        <div class="karte"><x-leer icon="datei" titel="Noch keine Dateien" text="Lege Reglemente, Pläne, Verträge oder Logos ab. Jedes Ressort hat seinen eigenen Ordner, darin kannst du Unterordner anlegen." /></div>
        @if ($ressorts->isNotEmpty())<div class="mt-4">@include('dateien._ablage', ['gruppen' => $gruppen, 'offen' => false])</div>@endif
    @else
        @include('dateien._ablage', ['gruppen' => $gruppen, 'offen' => count($gruppen) === 1])
    @endif
</x-app>
