{{-- Einrichtung Schritt fuer Schritt, nur fuer Admins, solange noch nicht alles angelegt ist --}}
@php
    $schritte = [
        ['Hallen erfassen', 'Wo ihr spielt, mit Hallenwart und Adresse.', \App\Models\Halle::exists(), route('admin.einstellungen')],
        ['Turniere anlegen', 'Z. B. Hallencup Dezember und Februar, je mit Standardhalle.', \App\Models\Turnier::exists(), route('admin.einstellungen')],
        ['Ressorts anlegen', 'Wie ihr die Arbeit im OK aufteilt.', \App\Models\Ressort::exists(), route('ressorts.index')],
        ['Personen einladen', 'Das OK bekommt einen Einladungslink per Mail.', \App\Models\User::count() > 1, route('admin.personen')],
        ['Vorlagen erfassen', 'Wiederkehrende Aufgaben mit Zeitpunkt vor dem Turnier.', \App\Models\Vorlage::exists(), route('vorlagen.create')],
        ['Erste Ausgabe planen', 'Der Assistent erstellt daraus alle Aufgaben.', \App\Models\Ausgabe::exists(), route('ausgaben.create')],
    ];
    $erledigt = collect($schritte)->where(2, true)->count();
@endphp
@if ($erledigt < count($schritte))
    <section class="karte mb-6">
        <div class="karte-kopf"><h2 class="titel text-sm">Erste Schritte</h2><span class="label">{{ $erledigt }} von {{ count($schritte) }}</span></div>
        <div class="px-4 pt-3"><x-fortschritt :prozent="round($erledigt / count($schritte) * 100)" /></div>
        <ol class="divide-y divide-nacht/5 p-2">
            @foreach ($schritte as $i => [$titel, $text, $fertig, $link])
                @php $naechster = ! $fertig && collect($schritte)->take($i)->every(fn ($s) => $s[2]); @endphp
                <li>
                    <a href="{{ $link }}" class="flex items-center gap-3 px-2 py-3 hover:bg-kreide">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center border-2 text-xs font-extrabold {{ $fertig ? 'border-rot bg-rot text-white' : ($naechster ? 'border-nacht text-nacht' : 'border-nacht/20 text-beton') }}">@if ($fertig)<x-icon name="check" class="h-4 w-4" />@else{{ $i + 1 }}@endif</span>
                        <span class="min-w-0 flex-1"><span class="block text-sm font-bold {{ $fertig ? 'text-beton line-through' : '' }}">{{ $titel }}</span><span class="block text-xs text-beton">{{ $text }}</span></span>
                        @if ($naechster)<span class="marke-gelb">als Nächstes</span>@endif
                        <x-icon name="pfeil" class="h-4 w-4 shrink-0 text-beton" />
                    </a>
                </li>
            @endforeach
        </ol>
    </section>
@endif
