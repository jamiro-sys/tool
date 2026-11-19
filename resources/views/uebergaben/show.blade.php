<x-app :titel="'Übergabe '.$uebergabe->ressort->name">
    @php
        $u = auth()->user();
        $beteiligt = $u->istAdmin() || in_array($u->id, [$uebergabe->von_id, $uebergabe->an_id], true);
        $offenStatus = ! in_array($uebergabe->status, ['abgeschlossen', 'abgebrochen'], true);
    @endphp
    <x-seitenkopf :titel="$uebergabe->ressort->name" ueber="Übergabe" :zurueck="route('uebergaben.index')">
        <x-slot:unter>{{ $uebergabe->von?->name ?? 'Unbesetzt' }} übergibt an {{ $uebergabe->an->name }} @if ($uebergabe->termin) bis {{ $uebergabe->termin->format('d.m.Y') }} @endif</x-slot:unter>
        <x-slot:aktionen>
            <a href="{{ route('ressorts.dossier', $uebergabe->ressort) }}" class="knopf-rahmen"><x-icon name="drucker" class="h-4 w-4" /> Dossier</a>
            <a href="{{ route('ressorts.show', $uebergabe->ressort) }}" class="knopf-nacht">Steckbrief</a>
        </x-slot:aktionen>
    </x-seitenkopf>

    {{-- Ablauf --}}
    @php
        $schritte = ['geplant' => 'Geplant', 'in_arbeit' => 'Einarbeitung', 'bestaetigt' => 'Bestätigung', 'abgeschlossen' => 'Übergeben'];
        $aktuell = $uebergabe->status === 'abgeschlossen' ? 3 : (($uebergabe->bestaetigt_von_am || $uebergabe->bestaetigt_an_am) ? 2 : ($uebergabe->status === 'in_arbeit' ? 1 : 0));
    @endphp
    <ol class="mb-6 grid grid-cols-4 gap-1">
        @foreach (array_values($schritte) as $i => $s)
            <li class="flex flex-col gap-2">
                <span class="h-1.5 {{ $i <= $aktuell ? 'bg-rot' : 'bg-nacht/10' }} transition-colors duration-500"></span>
                <span class="text-[10px] font-bold uppercase tracking-label {{ $i <= $aktuell ? 'text-nacht' : 'text-beton' }}">{{ $s }}</span>
            </li>
        @endforeach
    </ol>

    @if ($uebergabe->status === 'abgeschlossen')
        <div class="mb-6 flex items-center gap-3 bg-rot px-4 py-4 text-white">
            <x-icon name="pokal" class="h-6 w-6" />
            <p class="text-sm"><span class="font-bold">Übergabe abgeschlossen am {{ $uebergabe->abgeschlossen_am->format('d.m.Y') }}.</span> {{ $uebergabe->an->name }} leitet das Ressort. Danke {{ $uebergabe->von?->name }} für die saubere Übergabe!</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="karte" x-data="{ p: {{ $uebergabe->fortschritt() }} }" x-on:checkliste.window="p = $event.detail">
                <div class="karte-kopf"><h2 class="titel text-sm">Checkliste</h2><span class="zahl text-lg"><span x-text="p">{{ $uebergabe->fortschritt() }}</span>%</span></div>
                <div class="px-4 pt-3"><div class="balken"><span :style="`width: ${p}%`" style="width: {{ $uebergabe->fortschritt() }}%"></span></div></div>
                <ul class="mt-2">
                    @foreach ($uebergabe->checkliste ?? [] as $i => $punkt)
                        <li x-data="checkpunkt('{{ route('uebergaben.checkpunkt', $uebergabe) }}', {{ $i }}, {{ ! empty($punkt['erledigt']) ? 'true' : 'false' }})" class="border-b border-nacht/5 last:border-0">
                            <label class="flex cursor-pointer items-center gap-3 px-4 py-3">
                                <input type="checkbox" class="h-5 w-5" :checked="erledigt" x-on:change="umschalten()" @disabled(! $beteiligt || ! $offenStatus)>
                                <span class="flex-1 text-sm" :class="erledigt ? 'text-beton line-through' : ''">{{ $punkt['text'] }}</span>
                                @if (! empty($punkt['von']))<span class="text-xs text-beton">{{ $punkt['von'] }}</span>@endif
                            </label>
                        </li>
                    @endforeach
                </ul>
                @if ($beteiligt && $offenStatus)
                    <form method="POST" action="{{ route('uebergaben.checkpunkt', $uebergabe) }}" class="flex gap-2 border-t border-nacht/10 p-3">@csrf
                        <input name="neu" class="feld flex-1" placeholder="Eigener Punkt, z.B. Login Sponsoring-Plattform übergeben" required>
                        <button class="knopf-rahmen"><x-icon name="plus" class="h-4 w-4" /></button>
                    </form>
                @endif
            </section>

            <section class="karte">
                <div class="karte-kopf"><h2 class="titel text-sm">Offene Aufgaben im Ressort</h2><span class="label">{{ $offen->count() }}</span></div>
                @forelse ($offen as $a)
                    @include('aufgaben._zeile', ['aufgabe' => $a])
                @empty
                    <x-leer icon="check" titel="Keine offenen Aufgaben" />
                @endforelse
                <p class="border-t border-nacht/10 px-4 py-3 text-xs text-beton">Tipp: Die Nachfolge bei Aufgaben als "Begleitung" eintragen. So lernt sie das Ressort im Tun kennen.</p>
            </section>

            @if ($uebergabe->notiz)
                <section class="karte p-4"><p class="label mb-2">Notiz</p><div class="prosa">{{ $uebergabe->notiz }}</div></section>
            @endif
        </div>

        <aside class="space-y-6">
            <section class="karte p-4">
                @include('ressorts._ampel', ['stand' => $wissensstand])
                <ul class="mt-3 space-y-1.5 text-xs">@foreach ($wissensstand['hinweise'] as $h)<li class="flex gap-2"><x-icon name="warnung" class="h-3.5 w-3.5 shrink-0 text-beton" /> {{ $h }}</li>@endforeach</ul>
            </section>

            <section class="karte">
                <div class="karte-kopf"><h2 class="titel text-sm">Bestätigung</h2></div>
                <div class="divide-y divide-nacht/5">
                    @foreach ([['Abgebende Person', $uebergabe->von, $uebergabe->bestaetigt_von_am], ['Nachfolge', $uebergabe->an, $uebergabe->bestaetigt_an_am]] as [$rolle, $person, $am])
                        <div class="flex items-center gap-3 px-4 py-3">
                            <x-person :user="$person" gross />
                            <span class="min-w-0 flex-1 text-sm"><span class="label block">{{ $rolle }}</span>{{ $person?->name ?? 'unbesetzt' }}</span>
                            @if ($am)<span class="marke-erledigt"><x-icon name="check" class="h-3 w-3" /> {{ $am->format('d.m.') }}</span>@else<span class="marke-offen">offen</span>@endif
                        </div>
                    @endforeach
                </div>
                @if ($beteiligt && $offenStatus)
                    <div class="border-t border-nacht/10 p-4">
                        @if ($uebergabe->checklisteKomplett())
                            <form method="POST" action="{{ route('uebergaben.bestaetigen', $uebergabe) }}">@csrf<button class="knopf-rot w-full"><x-icon name="check" class="h-4 w-4" /> Übergabe bestätigen</button></form>
                        @else
                            <p class="text-xs text-beton">Sobald alle Punkte der Checkliste erledigt sind, bestätigen beide Seiten. Danach wechselt die Leitung automatisch.</p>
                        @endif
                    </div>
                @endif
            </section>

            @if ($offenStatus)
                @can('ressort-verwalten', $uebergabe->ressort)
                    <form method="POST" action="{{ route('uebergaben.abbrechen', $uebergabe) }}" onsubmit="return confirm('Übergabe abbrechen?')">@csrf<button class="knopf-leise w-full text-beton">Übergabe abbrechen</button></form>
                @endcan
            @endif
        </aside>
    </div>
</x-app>
