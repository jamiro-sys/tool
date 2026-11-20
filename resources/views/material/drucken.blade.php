@php $f = fn ($z) => rtrim(rtrim(number_format($z, 2, '.', "'"), '0'), '.'); $titel = $turnier ? 'Packliste '.$turnier->titel : 'Materialliste'.($ressortName ? ' '.$ressortName : ''); @endphp
<!DOCTYPE html>
<html lang="de-CH">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titel }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 12mm; }
        @media print { a { color: inherit; text-decoration: none; } .gruppe { break-inside: avoid-page; } thead { display: table-header-group; } }
        .haken { width: 14px; height: 14px; border: 1.5px solid #14213D; display: inline-block; }
    </style>
</head>
<body class="bg-white text-nacht">
    <div class="kein-druck sticky top-0 z-10 flex items-center justify-between gap-3 bg-nacht px-4 py-3 text-white">
        <a href="{{ $turnier ? route('material.ausgabe', $turnier) : route('material.index', array_filter($filter)) }}" class="label text-white">Zurück</a>
        <button onclick="window.print()" class="knopf bg-white text-nacht"><x-icon name="drucker" class="h-4 w-4" /> Drucken / als PDF sichern</button>
    </div>

    <x-anstoss :ausgabe="$turnier" />

    <main class="mx-auto max-w-[186mm] px-4 py-8 sm:px-0">
        <p class="label text-rot">{{ $turnier ? 'Packliste' : 'Materialliste' }}</p>
        <h1 class="titel mt-1 text-4xl">{{ $turnier?->titel ?? ($ressortName ?? 'Alle Ressorts') }}</h1>
        <p class="mt-2 text-sm text-beton">
            Stand {{ now()->format('d.m.Y') }}
            @if ($turnier) | {{ $turnier->datumText() }}@endif
            @foreach (['gruppe' => 'Gruppe', 'standort' => 'Standort'] as $k => $l)@if ($filter[$k]) | {{ $l }}: {{ $filter[$k] }}@endif @endforeach
            @if ($filter['eigentum']) | {{ \App\Models\Material::EIGENTUM[$filter['eigentum']] }}@endif
            @if ($filter['q']) | Suche: «{{ $filter['q'] }}»@endif
        </p>
        @if ($turnier)<p class="mt-1 text-xs text-beton">Auf = aufgebaut, Ab = abgebaut, Weg = versorgt</p>@endif

        @forelse ($gruppiert as $ressort => $liste)
            <section class="gruppe mt-8">
                <h2 class="titel border-b-2 border-rot pb-1 text-base">{{ $ressort }} <span class="font-bold text-beton">({{ $liste->count() }})</span></h2>
                <div class="overflow-x-auto"><table class="mt-2 w-full min-w-[520px] text-[12px] print:min-w-0">
                    <thead>
                        <tr class="border-b border-nacht/20 text-left">
                            <th class="label py-1.5 pr-2">Material</th>
                            <th class="label w-16 py-1.5 pr-2 text-right">Menge</th>
                            <th class="label py-1.5 pr-2">Wofür</th>
                            <th class="label py-1.5 pr-2">{{ $turnier ? 'Woher' : 'Standort / Eigentum' }}</th>
                            @if ($turnier)
                                @foreach (['Auf', 'Ab', 'Weg'] as $l)<th class="label w-10 py-1.5 text-center">{{ $l }}</th>@endforeach
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($liste as $m)
                            @php $e = $einsatz[$m->id] ?? null; @endphp
                            <tr class="border-b border-nacht/10 align-top">
                                <td class="py-2 pr-2 font-bold">{{ $m->name }}@if ($m->zustand !== 'gut')<span class="ml-1 font-normal text-rot">({{ \App\Models\Material::ZUSTAND[$m->zustand] }})</span>@endif</td>
                                <td class="py-2 pr-2 text-right tabular-nums">{{ $f($e?->menge ?? $m->menge) }} {{ $m->einheit }}</td>
                                <td class="py-2 pr-2 text-beton">{{ $m->zweck }}</td>
                                <td class="py-2 pr-2 text-beton">
                                    @if ($e){{ $e->herkunftText() }}@if ($e->rueckgabe_bis), zurück bis {{ $e->rueckgabe_bis->format('d.m.') }}@endif
                                    @else{{ collect([$m->standort, \App\Models\Material::EIGENTUM[$m->eigentum].($m->eigentuemer ? ' ('.$m->eigentuemer.')' : '')])->filter()->implode(', ') }}@endif
                                </td>
                                @if ($turnier)
                                    @foreach (array_keys(\App\Models\MaterialEinsatz::HAKEN) as $feld)<td class="py-2 text-center"><span class="haken {{ $e?->$feld ? 'bg-rot border-rot' : '' }}"></span></td>@endforeach
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            </section>
        @empty
            <p class="mt-8 text-sm text-beton">Keine Einträge für diese Auswahl.</p>
        @endforelse

        <p class="mt-10 border-t border-nacht/10 pt-3 text-[10px] text-beton">{{ \App\Models\Einstellung::wertSicher('verein_name', 'FC Münsterlingen') }} | {{ \App\Models\Einstellung::wertSicher('tool_name', 'OK-Tool') }}</p>
    </main>
</body>
</html>
