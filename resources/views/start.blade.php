<x-app titel="Start">
    @php $u = auth()->user(); $vorname = strtok($u->name, ' '); @endphp

    <div class="mb-6 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between animate-einblenden">
        <div>
            <p class="label text-rot mb-1">{{ now()->locale('de_CH')->isoFormat('dddd, D. MMMM') }}</p>
            <h1 class="titel text-3xl sm:text-4xl">Hallo {{ $vorname }}</h1>
            <p class="mt-2 text-sm text-beton">
                @if ($meine->isEmpty())
                    Für dich ist gerade nichts offen. Geniess die Pause.
                @else
                    Du hast {{ $meine->count() }} offene {{ $meine->count() === 1 ? 'Aufgabe' : 'Aufgaben' }}{{ $gruppen['ueberfaellig']->count() ? ', davon '.$gruppen['ueberfaellig']->count().' überfällig' : '' }}.
                @endif
            </p>
        </div>
        @if ($ausgabe)
            @php $tage = $ausgabe->tageBisTurnier(); @endphp
            <a href="{{ route('ausgaben.show', $ausgabe) }}" class="tempo self-start text-white lg:self-auto">
                <span class="flex items-baseline gap-2 px-2">
                    @if ($tage > 0)
                        <span class="zahl text-3xl" x-data="zaehler({{ $tage }})" x-text="wert">{{ $tage }}</span>
                        <span class="text-[11px] font-bold uppercase tracking-label">{{ $tage === 1 ? 'Tag' : 'Tage' }} bis {{ $ausgabe->turnier->kurz }}</span>
                    @elseif ($tage === 0)
                        <span class="zahl text-2xl">ANPFIFF</span><span class="text-[11px] font-bold uppercase tracking-label">heute</span>
                    @else
                        <span class="text-[11px] font-bold uppercase tracking-label">{{ $ausgabe->titel }}: {{ \App\Models\Ausgabe::STATUS[$ausgabe->status] }}</span>
                    @endif
                </span>
            </a>
        @endif
    </div>

    @if (! $ausgabe)
        <div class="karte">
            <x-leer icon="pokal" titel="Keine laufende Ausgabe" text="Sobald die nächste Ausgabe geplant ist, erscheinen hier deine Aufgaben.">
                @can('ausgaben-verwalten')
                    <a href="{{ route('ausgaben.create') }}" class="knopf-rot mt-4"><x-icon name="plus" class="h-4 w-4" /> Neue Ausgabe planen</a>
                @endcan
            </x-leer>
        </div>
    @else
        {{-- Kennzahlen --}}
        <div class="mb-6 grid grid-cols-3 gap-3">
            <div class="karte p-4">
                <p class="label">Offen</p>
                <p class="zahl mt-2 text-3xl sm:text-4xl" x-data="zaehler({{ $meine->count() }})" x-text="wert">{{ $meine->count() }}</p>
            </div>
            <div class="karte p-4 {{ $gruppen['ueberfaellig']->count() ? 'border-l-4 border-l-gelb' : '' }}">
                <p class="label">Überfällig</p>
                <p class="zahl mt-2 text-3xl sm:text-4xl" x-data="zaehler({{ $gruppen['ueberfaellig']->count() }})" x-text="wert">{{ $gruppen['ueberfaellig']->count() }}</p>
            </div>
            <div class="karte p-4">
                <p class="label">Erledigt (7 Tage)</p>
                <p class="zahl mt-2 text-3xl text-rot sm:text-4xl" x-data="zaehler({{ $erledigtWoche }})" x-text="wert">{{ $erledigtWoche }}</p>
            </div>
        </div>

        <div class="grid gap-6 {{ $u->istExperte() ? 'xl:grid-cols-3' : '' }}">
            <div class="{{ $u->istExperte() ? 'xl:col-span-2' : '' }} space-y-6">
                @foreach (['ueberfaellig' => 'Überfällig', 'woche' => 'Diese Woche', 'spaeter' => 'Später'] as $schluessel => $titel)
                    @if ($gruppen[$schluessel]->count())
                        <section class="karte animate-einblenden">
                            <div class="karte-kopf">
                                <h2 class="titel text-sm {{ $schluessel === 'ueberfaellig' ? 'text-rot' : '' }}">{{ $titel }}</h2>
                                <span class="label">{{ $gruppen[$schluessel]->count() }}</span>
                            </div>
                            <div>
                                @foreach ($gruppen[$schluessel] as $aufgabe)
                                    @include('aufgaben._zeile', ['aufgabe' => $aufgabe, 'ohnePerson' => true, 'mitAusgabe' => $naechste->count() > 1])
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach

                @if ($meine->isEmpty())
                    <div class="karte">
                        <x-leer icon="check" titel="Alles erledigt" text="Schau im Ressort vorbei, vielleicht kannst du jemandem helfen.">
                            <a href="{{ route('aufgaben.index', [$ausgabe, 'person' => 'keine']) }}" class="knopf-rahmen mt-4">Aufgaben ohne Zuteilung</a>
                        </x-leer>
                    </div>
                @endif

                <p class="text-center text-xs text-beton lg:hidden">Tipp: Aufgabe nach rechts wischen, um sie abzuhaken.</p>
            </div>

            @if ($u->istExperte())
                <div class="space-y-6">
                    <section class="karte">
                        <div class="karte-kopf">
                            <h2 class="titel text-sm">Ressorts</h2>
                            <a href="{{ route('ausgaben.show', $ausgabe) }}" class="label hover:text-rot">Dashboard</a>
                        </div>
                        <div class="space-y-4 p-4">
                            @foreach ($ressorts as $r)
                                <a href="{{ route('aufgaben.index', [$ausgabe, 'ressort' => $r['ressort']->id]) }}" class="block group">
                                    <div class="mb-1.5 flex items-center justify-between text-sm">
                                        <span class="font-bold group-hover:text-rot">{{ $r['ressort']->name }}</span>
                                        <span class="flex items-center gap-2 text-xs text-beton">
                                            @if ($r['ueberfaellig'])<span class="marke-gelb">{{ $r['ueberfaellig'] }}</span>@endif
                                            {{ $r['erledigt'] }}/{{ $r['total'] }}
                                        </span>
                                    </div>
                                    <x-fortschritt :prozent="$r['prozent']" />
                                </a>
                            @endforeach
                        </div>
                    </section>

                    <section class="grid grid-cols-2 gap-3">
                        <a href="{{ route('aufgaben.index', [$ausgabe, 'person' => 'keine', 'status' => 'offen_alle']) }}" class="karte p-4 hover:border-nacht">
                            <p class="label">Ohne Zuteilung</p>
                            <p class="zahl mt-2 text-2xl">{{ $ohneZuteilung }}</p>
                        </a>
                        <a href="{{ route('aufgaben.index', [$ausgabe, 'nur' => 'ueberfaellig']) }}" class="karte p-4 hover:border-nacht">
                            <p class="label">Überfällig total</p>
                            <p class="zahl mt-2 text-2xl">{{ $ueberfaelligAlle }}</p>
                        </a>
                    </section>

                    <section class="karte">
                        <div class="karte-kopf"><h2 class="titel text-sm">Zuletzt passiert</h2></div>
                        <ul class="divide-y divide-nacht/5">
                            @forelse ($aktivitaeten as $a)
                                <li class="flex gap-3 px-4 py-2.5 text-sm">
                                    <x-person :user="$a->user" />
                                    <span class="min-w-0 flex-1"><span class="block truncate">{{ $a->beschreibung }}</span><span class="text-xs text-beton">{{ $a->created_at->diffForHumans() }}</span></span>
                                </li>
                            @empty
                                <li class="px-4 py-6 text-center text-sm text-beton">Noch keine Aktivität.</li>
                            @endforelse
                        </ul>
                    </section>
                </div>
            @endif
        </div>
    @endif
</x-app>
