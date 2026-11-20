<!DOCTYPE html>
<html lang="de-CH">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Übergabedossier {{ $ressort->name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 15mm; }
        @media print { .seite-umbruch { break-before: page; } a { color: inherit; text-decoration: none; } }
    </style>
</head>
<body class="bg-white">
    <div class="kein-druck sticky top-0 z-10 flex items-center justify-between gap-3 bg-nacht px-4 py-3 text-white">
        <a href="{{ route('ressorts.show', $ressort) }}" class="label text-white">Zurück zum Steckbrief</a>
        <button onclick="window.print()" class="knopf bg-white text-nacht"><x-icon name="drucker" class="h-4 w-4" /> Drucken / als PDF sichern</button>
    </div>

    <x-anstoss />

    <main class="mx-auto max-w-[180mm] px-[6%] py-8 sm:px-0">
        <p class="label text-rot">Übergabedossier</p>
        <h1 class="titel mt-1 text-5xl">{{ $ressort->name }}</h1>
        <p class="mt-2 text-sm text-beton">Stand {{ now()->format('d.m.Y') }} | {{ \App\Models\Einstellung::wertSicher('verein_name', 'FC Münsterlingen') }}</p>

        <div class="tempo mt-6 text-white"><span class="px-2 text-xs font-bold uppercase tracking-label">Alles, was die Nachfolge wissen muss</span></div>

        <section class="mt-8">
            <h2 class="titel border-b-2 border-rot pb-1 text-lg">1. Auftrag</h2>
            <div class="prosa mt-3">{{ $ressort->auftrag ?: '[Auftrag noch nicht beschrieben]' }}</div>
        </section>

        <section class="mt-8">
            <h2 class="titel border-b-2 border-rot pb-1 text-lg">2. Personen</h2>
            <table class="tabelle mt-3">
                <thead><tr><th>Name</th><th>Rolle</th><th>Telefon</th><th>E-Mail</th></tr></thead>
                <tbody>@foreach ($ressort->mitglieder as $m)<tr><td class="font-bold">{{ $m->name }}</td><td>{{ ['leitung' => 'Leitung', 'mitglied' => 'Mitglied', 'nachfolge' => 'Nachfolge'][$m->pivot->rolle] ?? '' }}</td><td>{{ $m->telefon }}</td><td>{{ $m->email }}</td></tr>@endforeach</tbody>
            </table>
        </section>

        <section class="mt-8">
            <h2 class="titel border-b-2 border-rot pb-1 text-lg">3. Jahresplan</h2>
            @foreach ($ressort->vorlagen as $v)
                <article class="mt-4 border-l-4 border-nacht/15 pl-4" style="break-inside: avoid">
                    <p class="label">{{ $v->zeitpunktText() }} | {{ $v->turniere->isNotEmpty() ? 'nur '.$v->turniere->pluck('name')->implode(' und ') : 'alle Turnierarten' }} @if ($v->aufwand_std) | ca. {{ \App\Support\Aufwand::text($v->aufwand_std) }} @endif</p>
                    <h3 class="mt-0.5 text-base font-bold normal-case">{{ $v->titel }}</h3>
                    @if ($v->beschreibung)<p class="prosa mt-1">{{ $v->beschreibung }}</p>@endif
                    @if ($v->anleitung)<div class="prosa mt-2 bg-kreide p-3">{{ $v->anleitung }}</div>@else<p class="mt-1 text-sm text-beton">[Anleitung fehlt]</p>@endif
                    @if ($v->checkliste)<ul class="mt-2 space-y-0.5 text-sm">@foreach ($v->checkliste as $p)<li>☐ {{ is_array($p) ? $p['text'] : $p }}</li>@endforeach</ul>@endif
                </article>
            @endforeach
        </section>

        <section class="mt-8 seite-umbruch">
            <h2 class="titel border-b-2 border-rot pb-1 text-lg">4. Kontakte</h2>
            <table class="tabelle mt-3">
                <thead><tr><th>Name</th><th>Funktion</th><th>Telefon / E-Mail</th><th>Hinweise</th></tr></thead>
                <tbody>
                    @forelse ($ressort->kontakte as $k)
                        <tr><td class="font-bold">{{ $k->name }}<div class="font-normal text-beton">{{ $k->firma }}</div></td><td>{{ $k->funktion }}</td><td>{{ $k->telefon }}<div>{{ $k->email }}</div></td><td>{{ $k->notizen }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-beton">[Keine Kontakte erfasst]</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="mt-8">
            <h2 class="titel border-b-2 border-rot pb-1 text-lg">5. Wissen</h2>
            @forelse ($ressort->wissen as $w)
                <article class="mt-4" style="break-inside: avoid"><h3 class="text-base font-bold normal-case">{{ $w->titel }}</h3><div class="prosa mt-1">{{ $w->inhalt }}</div></article>
            @empty
                <p class="mt-3 text-sm text-beton">[Noch keine Wissensseiten]</p>
            @endforelse
        </section>

        <section class="mt-8">
            <h2 class="titel border-b-2 border-rot pb-1 text-lg">6. Dokumente</h2>
            <ul class="mt-3 space-y-1 text-sm">
                @forelse ($ressort->dokumente as $d)<li>{{ $d->titel }}: <span class="text-beton">{{ $d->typ === 'datei' ? 'Datei im OK-Tool' : $d->url }}</span></li>@empty<li class="text-beton">[Keine Dokumente]</li>@endforelse
            </ul>
        </section>

        <section class="mt-8">
            <h2 class="titel border-b-2 border-rot pb-1 text-lg">7. Erkenntnisse der letzten Turniere</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($erkenntnisse as $e)
                    <li><span class="font-bold">{{ $e->ausgabe?->titel }}</span> ({{ \App\Models\Erkenntnis::VORSCHLAEGE[$e->vorschlag] }}{{ $e->umgesetzt_am ? ', umgesetzt' : '' }}): {{ $e->text }}</li>
                @empty
                    <li class="text-beton">[Keine Erkenntnisse]</li>
                @endforelse
            </ul>
        </section>

        <section class="mt-8">
            <h2 class="titel border-b-2 border-rot pb-1 text-lg">8. Offene Punkte</h2>
            <ul class="mt-3 space-y-1 text-sm">
                @forelse ($offen as $a)<li>{{ $a->faellig_am?->format('d.m.Y') }}: <span class="font-bold">{{ $a->titel }}</span> ({{ $a->verantwortlich?->name ?? 'ohne Person' }}, {{ $a->ausgabe->titel }})</li>@empty<li class="text-beton">Keine offenen Aufgaben.</li>@endforelse
            </ul>
        </section>

        <footer class="mt-12 flex items-center justify-between border-t border-nacht/10 pt-3">
            <span class="flex items-center gap-2"><span class="h-4 w-1 bg-rot"></span><span class="label">Hauptsponsor</span></span>
            <span class="text-xs text-beton">[Logo Raiffeisen]</span>
        </footer>
    </main>
</body>
</html>
