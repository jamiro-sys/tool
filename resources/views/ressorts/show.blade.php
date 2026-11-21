<x-app :titel="$ressort->name">
    @php
        $u = auth()->user();
        $verwalten = $u->can('ressort-verwalten', $ressort);
        $mitarbeiten = $u->can('ressort-mitarbeiten', $ressort) || $verwalten;
        $rollen = ['leitung' => 'Leitung', 'mitglied' => 'Mitglied', 'nachfolge' => 'Nachfolge in Einarbeitung'];
    @endphp
    <x-seitenkopf :titel="$ressort->name" ueber="Ressort-Steckbrief" :zurueck="route('ressorts.index')">
        <x-slot:aktionen>
            <a href="{{ route('ressorts.dossier', $ressort) }}" class="knopf-rahmen"><x-icon name="drucker" class="h-4 w-4" /> Übergabedossier</a>
            @if ($verwalten)
                <button type="button" class="knopf-nacht" x-on:click="$dispatch('modal-uebergabe')"><x-icon name="uebergabe" class="h-4 w-4" /> Übergabe planen</button>
            @endif
        </x-slot:aktionen>
    </x-seitenkopf>

    @php
        $reiter = [
            'uebersicht' => ['Übersicht', null],
            'auftrag' => ['Auftrag', null],
            'personen' => ['Personen', $ressort->mitglieder->count()],
            'aufgaben' => ['Aufgaben', $offen->count()],
            'jahreslauf' => ['Jahreslauf', $kalender->flatten()->count()],
            'kontakte' => ['Kontakte', $ressort->kontakte->count()],
            'wissen' => ['Wissen', $ressort->wissen->count()],
            'material' => ['Material', $ressort->material->count()],
            'dateien' => ['Dateien', $ressort->dokumente->count()],
            'erkenntnisse' => ['Erkenntnisse', $erkenntnisse->count()],
        ];
        $ueberfaellig = $offen->filter(fn ($a) => $a->faellig_am && $a->faellig_am->lt(today()))->count();
    @endphp

    {{-- Reiter: Uebersicht zuerst, dann jeder Bereich einzeln --}}
    <nav class="-mx-4 mb-6 flex gap-1 overflow-x-auto border-b border-nacht/10 px-4 sm:mx-0 sm:px-0 kein-druck" aria-label="Bereiche des Ressorts">
        @foreach ($reiter as $k => [$l, $n])
            <a href="{{ route('ressorts.show', [$ressort, 'tab' => $k]) }}" @if ($tab === $k) aria-current="page" x-init="$el.scrollIntoView({ inline: 'center', block: 'nearest' })" @endif
               class="flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-3 text-[11px] font-bold uppercase tracking-label {{ $tab === $k ? 'border-rot text-nacht' : 'border-transparent text-beton hover:border-nacht/30 hover:text-nacht' }}">
                {{ $l }}@if ($n)<span class="{{ $tab === $k ? 'bg-rot text-white' : 'bg-nacht/10 text-nacht' }} px-1.5 py-0.5 text-[10px] leading-none">{{ $n }}</span>@endif
            </a>
        @endforeach
    </nav>

    @if ($tab === 'uebersicht')
        @php
            $kacheln = [
                ['aufgaben', 'Offen', $offen->count(), $ueberfaellig ? $ueberfaellig.' überfällig' : ($ausgabe ? 'im nächsten Turnier' : 'kein Turnier geplant')],
                ['jahreslauf', 'Jahresplan', $kalender->flatten()->count(), 'Aufgaben jedes Jahr'],
                ['personen', 'Personen', $ressort->mitglieder->count(), $ressort->leitung()?->name ?? 'ohne Leitung'],
                ['material', 'Material', $ressort->material->count(), 'Einträge'],
                ['kontakte', 'Kontakte', $ressort->kontakte->count(), 'Ansprechpersonen'],
                ['dateien', 'Dateien', $ressort->dokumente->count(), 'Dateien und Links'],
            ];
        @endphp
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
            @foreach ($kacheln as [$k, $l, $n, $txt])
                <a href="{{ route('ressorts.show', [$ressort, 'tab' => $k]) }}" class="karte group flex flex-col p-3 hover:border-nacht/40 {{ $k === 'aufgaben' && $ueberfaellig ? 'border-l-4 border-l-gelb' : '' }}">
                    <span class="label">{{ $l }}</span>
                    <span class="zahl mt-1 text-3xl">{{ $n }}</span>
                    <span class="mt-0.5 truncate text-xs text-beton">{{ $txt }}</span>
                </a>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section class="karte p-4">
                    <div class="mb-2 flex items-center justify-between"><p class="label">Auftrag des Ressorts</p><a href="{{ route('ressorts.show', [$ressort, 'tab' => 'auftrag']) }}" class="label hover:text-rot">{{ $verwalten ? 'Bearbeiten' : 'Ganz lesen' }}</a></div>
                    <p class="text-sm {{ $ressort->auftrag ? '' : 'text-beton' }}">{{ $ressort->auftrag ? \Illuminate\Support\Str::limit($ressort->auftrag, 320) : 'Noch nicht beschrieben. Was ist die Aufgabe dieses Ressorts? Wofür ist es verantwortlich?' }}</p>
                </section>

                <section class="karte">
                    <div class="karte-kopf"><h2 class="titel text-sm">Als Nächstes {{ $ausgabe ? 'in '.$ausgabe->titel : '' }}</h2><span class="flex items-center gap-3"><a href="{{ route('ressorts.show', [$ressort, 'tab' => 'aufgaben']) }}" class="label hover:text-rot">Alle {{ $offen->count() }}</a>@if ($ausgabe && $mitarbeiten)<button type="button" class="knopf-rahmen knopf-klein" x-on:click="$dispatch('modal-assistent')" aria-label="Mit Claude erfassen"><x-icon name="funken" class="h-3.5 w-3.5" /></button><button type="button" class="knopf-rot knopf-klein" x-on:click="$dispatch('modal-neue-aufgabe')" aria-label="Neue Aufgabe"><x-icon name="plus" class="h-3.5 w-3.5" /> Aufgabe</button>@endif</span></div>
                    @forelse ($offen->take(5) as $a)
                        @include('aufgaben._zeile', ['aufgabe' => $a->setRelation('ressort', $ressort), 'ohneRessort' => true])
                    @empty
                        <x-leer icon="check" titel="Nichts offen" />
                    @endforelse
                </section>

                <section class="karte">
                    <div class="karte-kopf"><h2 class="titel text-sm">Jahreslauf</h2><a href="{{ route('ressorts.show', [$ressort, 'tab' => 'jahreslauf']) }}" class="label hover:text-rot">Ganzer Jahreslauf</a></div>
                    @php $jahr = $ressort->vorlagen->where('aktiv', true)->sortByDesc('tage_vorher'); @endphp
                    @forelse ($jahr->take(6) as $v)
                        <a href="{{ route('vorlagen.show', $v) }}" class="flex items-center gap-3 border-b border-nacht/5 px-4 py-2.5 text-sm last:border-0 hover:text-rot">
                            <span class="w-32 shrink-0 text-xs font-bold text-beton">{{ $v->zeitpunktText() }}</span><span class="min-w-0 flex-1 truncate font-bold">{{ $v->titel }}</span>
                        </a>
                    @empty
                        <p class="px-4 py-4 text-sm text-beton">Noch nichts im Jahresplan.</p>
                    @endforelse
                    @if ($jahr->count() > 6)<p class="px-4 py-2 text-xs text-beton">und {{ $jahr->count() - 6 }} weitere</p>@endif
                </section>

                @if ($aufwandJahre->count())
                                <section class="karte">
                                    <div class="karte-kopf"><h2 class="titel text-sm">Aufwand pro Turnier</h2></div>
                                    <div class="h-56 p-4"><canvas x-data x-init="diagramm($el, 'balken', { labels: {{ json_encode($aufwandJahre->keys()) }}, reihen: [{ name: 'Geplant', werte: {{ json_encode($aufwandJahre->pluck('geplant')->values()) }}, farbe: '#A9AEB8' }, { name: 'Effektiv', werte: {{ json_encode($aufwandJahre->pluck('effektiv')->values()) }}, farbe: '#B8161C' }] })"></canvas></div>
                                </section>
                            @endif

                            @if ($ressort->uebergaben->count())
                                <section class="karte">
                                    <div class="karte-kopf"><h2 class="titel text-sm">Übergaben</h2></div>
                                    <ul class="divide-y divide-nacht/5">
                                        @foreach ($ressort->uebergaben->sortByDesc('created_at') as $ue)
                                            <li><a href="{{ route('uebergaben.show', $ue) }}" class="flex items-center justify-between px-4 py-2.5 text-sm hover:bg-kreide">
                                                <span>{{ $ue->von?->name ?? '?' }} an {{ $ue->an->name }}</span><span class="marke-rahmen">{{ \App\Models\Uebergabe::STATUS[$ue->status] }}</span></a></li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endif
            </div>

            <aside class="space-y-6">
            <section class="karte p-4">
                @include('ressorts._ampel', ['stand' => $wissensstand])
                @if ($wissensstand['hinweise'])
                    <ul class="mt-3 space-y-1.5 text-xs">@foreach ($wissensstand['hinweise'] as $h)<li class="flex gap-2"><x-icon name="warnung" class="h-3.5 w-3.5 shrink-0 text-beton" /> {{ $h }}</li>@endforeach</ul>
                @else
                    <p class="mt-3 text-xs text-beton">Bereit für eine Übergabe. Stark!</p>
                @endif
            </section>


                <section class="karte">
                    <div class="karte-kopf"><h2 class="titel text-sm">Personen</h2><a href="{{ route('ressorts.show', [$ressort, 'tab' => 'personen']) }}" class="label hover:text-rot">Alle</a></div>
                    <ul class="divide-y divide-nacht/5">
                        @forelse ($ressort->mitglieder->sortBy(fn ($m) => array_search($m->pivot->rolle, array_keys($rollen))) as $m)
                            <li class="flex items-center gap-3 px-4 py-2.5"><x-person :user="$m" /><span class="min-w-0 flex-1 truncate text-sm font-bold">{{ $m->name }}</span><span class="text-xs text-beton">{{ $rollen[$m->pivot->rolle] ?? $m->pivot->rolle }}</span></li>
                        @empty
                            <li class="px-4 py-4 text-sm text-beton">Noch niemand.</li>
                        @endforelse
                    </ul>
                </section>

                @include('partials.verknuepfungen', ['bezug' => $ressort, 'typ' => 'ressort'])

                <section class="karte">
                    <div class="karte-kopf"><h2 class="titel text-sm">Neueste Dateien</h2><a href="{{ route('ressorts.show', [$ressort, 'tab' => 'dateien']) }}" class="label hover:text-rot">Alle</a></div>
                    <ul class="divide-y divide-nacht/5">
                        @forelse ($ressort->dokumente->sortByDesc('created_at')->take(4) as $d)
                            <li class="flex items-center gap-2 px-4 py-2.5 text-sm"><x-icon :name="$d->typ === 'datei' ? 'datei' : 'link'" class="h-4 w-4 shrink-0 text-beton" /><x-datei-link :d="$d" class="min-w-0 flex-1 truncate hover:text-rot">{{ $d->titel }}</x-datei-link></li>
                        @empty
                            <li class="px-4 py-4 text-sm text-beton">Noch keine Dateien.</li>
                        @endforelse
                    </ul>
                </section>
            </aside>
        </div>
    @else
        <div class="max-w-4xl">
            @switch($tab)
                @case('auftrag')
<section id="auftrag" class="karte p-4" x-data="{ bearbeiten: false }">
                <div class="mb-2 flex items-center justify-between"><p class="label">Auftrag des Ressorts</p>
                    @if ($verwalten)<button type="button" class="label hover:text-rot" x-on:click="bearbeiten = !bearbeiten">Bearbeiten</button>@endif</div>
                <div x-show="!bearbeiten" class="prosa">{{ $ressort->auftrag ?: 'Noch nicht beschrieben. Was ist die Aufgabe dieses Ressorts? Wofür ist es verantwortlich?' }}</div>
                @if ($verwalten)
                    <form x-show="bearbeiten" x-cloak method="POST" action="{{ route('ressorts.update', $ressort) }}" class="space-y-3">@csrf @method('PATCH')
                        <textarea name="auftrag" class="feld" rows="5">{{ $ressort->auftrag }}</textarea>
                        <button class="knopf-rot knopf-klein">Speichern</button>
                    </form>
                @endif
            </section>
                    @break
                @case('personen')
<section id="personen" class="karte">
                <div class="karte-kopf"><h2 class="titel text-sm">Personen</h2></div>
                <ul class="divide-y divide-nacht/5">
                    @forelse ($ressort->mitglieder->sortBy(fn ($m) => array_search($m->pivot->rolle, array_keys($rollen))) as $m)
                        <li class="flex items-center gap-3 px-4 py-3">
                            <x-person :user="$m" gross />
                            <span class="min-w-0 flex-1"><span class="block text-sm font-bold">{{ $m->name }}</span><span class="text-xs text-beton">{{ $rollen[$m->pivot->rolle] ?? $m->pivot->rolle }} @if ($m->pivot->seit) seit {{ \Carbon\Carbon::parse($m->pivot->seit)->format('m/Y') }} @endif</span></span>
                            @if ($m->telefon)<a href="tel:{{ $m->telefon }}" class="text-beton hover:text-rot" aria-label="Anrufen"><x-icon name="telefon" class="h-4 w-4" /></a>@endif
                            <a href="mailto:{{ $m->email }}" class="text-beton hover:text-rot" aria-label="E-Mail"><x-icon name="mail" class="h-4 w-4" /></a>
                        </li>
                    @empty
                        <li class="px-4 py-5 text-sm text-beton">Noch niemand.</li>
                    @endforelse
                </ul>
                @if ($verwalten)
                    <form method="POST" action="{{ route('ressorts.mitglied', $ressort) }}" class="space-y-2 border-t border-nacht/10 p-4">@csrf
                        <p class="label">Person zuordnen</p>
                        <select name="user_id" class="feld" required>@foreach ($personen as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
                        <div class="flex gap-2">
                            <select name="rolle" class="feld flex-1">
                                <option value="mitglied">Mitglied</option><option value="nachfolge">Nachfolge</option>
                                @can('admin')<option value="leitung">Leitung</option>@endcan
                                <option value="entfernen">Entfernen</option>
                            </select>
                            <button class="knopf-nacht">OK</button>
                        </div>
                    </form>
                @endif
            </section>
                    @break
                @case('aufgaben')
<section id="aufgaben" class="karte">
                <div class="karte-kopf"><h2 class="titel text-sm">Offen {{ $ausgabe ? 'in' : '' }} {{ $ausgabe?->titel ?? 'im nächsten Turnier' }}</h2>
                    <span class="flex items-center gap-3">
                        @if ($ausgabe)<a href="{{ route('aufgaben.index', [$ausgabe, 'ressort' => $ressort->id]) }}" class="label hover:text-rot">Alle</a>@endif
                        @if ($ausgabe && $mitarbeiten)<button type="button" class="knopf-rahmen knopf-klein" x-on:click="$dispatch('modal-assistent')"><x-icon name="funken" class="h-3.5 w-3.5" /> Mit Claude</button><button type="button" class="knopf-rot knopf-klein" x-on:click="$dispatch('modal-neue-aufgabe')"><x-icon name="plus" class="h-3.5 w-3.5" /> Aufgabe</button>@endif
                    </span></div>
                @if (! $ausgabe)<p class="border-b border-nacht/5 px-4 py-3 text-sm text-beton">Es ist noch kein Turnier geplant. Aufgaben gehören immer zu einem Turnier: zuerst unter Turniere eines anlegen.</p>@endif
                @forelse ($offen as $a)
                    @include('aufgaben._zeile', ['aufgabe' => $a->setRelation('ressort', $ressort), 'ohneRessort' => true])
                @empty
                    <x-leer icon="check" titel="Nichts offen" />
                @endforelse
            </section>
                    @break
                @case('jahreslauf')
<section id="jahreslauf" class="karte">
                <div class="karte-kopf"><h2 class="titel text-sm">Jahreslauf</h2><a href="{{ route('vorlagen.index', ['ressort' => $ressort->id]) }}" class="label hover:text-rot">Jahresplan</a></div>
                <div class="p-4">
                    @forelse ($kalender as $phase => $vorlagen)
                        <div class="relative border-l-2 border-nacht/15 pb-5 pl-5 last:pb-0">
                            <span class="absolute -left-[7px] top-0.5 h-3 w-3 {{ $phase === 'Turniertag' ? 'bg-rot' : 'border-2 border-nacht bg-white' }}"></span>
                            <p class="label text-nacht">{{ $phase }}</p>
                            <ul class="mt-1.5 space-y-1">
                                @foreach ($vorlagen as $v)
                                    <li><a href="{{ route('vorlagen.show', $v) }}" class="flex items-center gap-2 text-sm hover:text-rot">{{ $v->titel }}
                                        @foreach ($v->turniere as $t)<span class="marke-rahmen">{{ $t->kurz ?: $t->name }}</span>@endforeach
                                        @if (blank($v->anleitung))<span class="marke-gelb">ohne Anleitung</span>@endif</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <x-leer icon="vorlagen" titel="Noch nichts im Jahresplan" text="Erfasse die Aufgaben, die in diesem Ressort jedes Jahr kommen." />
                    @endforelse
                </div>
            </section>
                    @break
                @case('kontakte')
<section id="kontakte" class="karte" x-data="{ neu: false }">
                <div class="karte-kopf"><h2 class="titel text-sm">Kontakte</h2>
                    @if ($mitarbeiten)<button type="button" class="knopf-rahmen knopf-klein" x-on:click="neu = !neu"><x-icon name="plus" class="h-3.5 w-3.5" /></button>@endif</div>
                @if ($mitarbeiten)
                    <form x-show="neu" x-collapse method="POST" action="{{ route('kontakte.speichern', $ressort) }}" class="space-y-2 border-b border-nacht/10 p-4">@csrf
                        <input name="name" required class="feld" placeholder="Name">
                        <input name="firma" class="feld" placeholder="Firma / Organisation">
                        <input name="funktion" class="feld" placeholder="Funktion, z.B. Hallenwart">
                        <div class="grid grid-cols-2 gap-2"><input name="telefon" class="feld" placeholder="Telefon"><input name="email" type="email" class="feld" placeholder="E-Mail"></div>
                        <textarea name="notizen" class="feld" rows="2" placeholder="Was man wissen muss"></textarea>
                        <button class="knopf-rot knopf-klein">Speichern</button>
                    </form>
                @endif
                <ul class="divide-y divide-nacht/5">
                    @forelse ($ressort->kontakte as $k)
                        <li class="px-4 py-3 text-sm" x-data="{ bearbeiten: false }">
                            <div x-show="!bearbeiten">
                                <div class="flex items-start justify-between gap-2">
                                    <div><span class="font-bold">{{ $k->name }}</span>@if ($k->firma)<span class="text-beton">, {{ $k->firma }}</span>@endif
                                        @if ($k->funktion)<p class="text-xs text-beton">{{ $k->funktion }}</p>@endif</div>
                                    <div class="flex gap-2">
                                        @if ($k->telefon)<a href="tel:{{ $k->telefon }}" class="text-beton hover:text-rot" aria-label="Anrufen"><x-icon name="telefon" class="h-4 w-4" /></a>@endif
                                        @if ($k->email)<a href="mailto:{{ $k->email }}" class="text-beton hover:text-rot" aria-label="E-Mail"><x-icon name="mail" class="h-4 w-4" /></a>@endif
                                        @if ($mitarbeiten)<button type="button" class="text-beton hover:text-rot" x-on:click="bearbeiten = true" aria-label="Bearbeiten"><x-icon name="stift" class="h-4 w-4" /></button>@endif
                                    </div>
                                </div>
                                @if ($k->notizen)<p class="mt-1 text-xs">{{ $k->notizen }}</p>@endif
                            </div>
                            @if ($mitarbeiten)
                                <form x-show="bearbeiten" x-cloak method="POST" action="{{ route('kontakte.speichern', [$ressort, $k]) }}" class="space-y-2">@csrf
                                    <input name="name" required class="feld" value="{{ $k->name }}">
                                    <input name="firma" class="feld" value="{{ $k->firma }}" placeholder="Firma">
                                    <input name="funktion" class="feld" value="{{ $k->funktion }}" placeholder="Funktion">
                                    <div class="grid grid-cols-2 gap-2"><input name="telefon" class="feld" value="{{ $k->telefon }}" placeholder="Telefon"><input name="email" type="email" class="feld" value="{{ $k->email }}" placeholder="E-Mail"></div>
                                    <textarea name="notizen" class="feld" rows="2">{{ $k->notizen }}</textarea>
                                    <div class="flex gap-2"><button class="knopf-rot knopf-klein">Speichern</button><button type="button" class="knopf-leise knopf-klein" x-on:click="bearbeiten = false">Abbrechen</button></div>
                                </form>
                                <form x-show="bearbeiten" x-cloak method="POST" action="{{ route('kontakte.loeschen', [$ressort, $k]) }}" class="mt-2" onsubmit="return confirm('Kontakt löschen?')">@csrf @method('DELETE')<button class="label hover:text-rot">Kontakt löschen</button></form>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-5 text-sm text-beton">Noch keine Kontakte.</li>
                    @endforelse
                </ul>
            </section>
                    @break
                @case('wissen')
<section id="wissen" class="karte" x-data="{ neu: false }">
                <div class="karte-kopf"><h2 class="titel text-sm flex items-center gap-2"><x-icon name="buch" class="h-4 w-4 text-rot" /> Wissen</h2>
                    @if ($mitarbeiten)<button type="button" class="knopf-rahmen knopf-klein" x-on:click="neu = !neu"><x-icon name="plus" class="h-3.5 w-3.5" /> Seite</button>@endif</div>
                @if ($mitarbeiten)
                    <form x-show="neu" x-collapse method="POST" action="{{ route('wissen.speichern', $ressort) }}" class="space-y-3 border-b border-nacht/10 p-4">@csrf
                        <input name="titel" required class="feld" placeholder="Titel, z.B. Ablauf Festwirtschaft am Turniertag">
                        <textarea name="inhalt" required class="feld" rows="6" placeholder="Was muss eine neue Person wissen?"></textarea>
                        <button class="knopf-rot knopf-klein">Speichern</button>
                    </form>
                @endif
                <div class="divide-y divide-nacht/5">
                    @forelse ($ressort->wissen as $w)
                        <article id="wissen-{{ $w->id }}" x-data="{ auf: false, bearbeiten: false }" class="px-4 py-3">
                            <button type="button" class="flex w-full items-center justify-between text-left" x-on:click="auf = !auf">
                                <span class="text-sm font-bold">{{ $w->titel }}</span>
                                <span class="flex items-center gap-2 text-xs text-beton">{{ $w->updated_at->format('d.m.Y') }}<x-icon name="pfeil" class="h-4 w-4 transition-transform" ::class="auf ? 'rotate-90' : ''" /></span>
                            </button>
                            <div x-show="auf" x-collapse>
                                <div x-show="!bearbeiten" class="prosa mt-2">{{ $w->inhalt }}</div>
                                <p class="mt-2 text-xs text-beton">Zuletzt von {{ $w->user?->name ?? 'unbekannt' }}</p>
                                @if ($mitarbeiten)
                                    <div class="mt-2 flex gap-2" x-show="!bearbeiten">
                                        <button type="button" class="knopf-leise knopf-klein" x-on:click="bearbeiten = true">Bearbeiten</button>
                                        @if ($verwalten)
                                            <form method="POST" action="{{ route('wissen.loeschen', [$ressort, $w]) }}" onsubmit="return confirm('Seite löschen?')">@csrf @method('DELETE')<button class="knopf-leise knopf-klein text-beton">Löschen</button></form>
                                        @endif
                                    </div>
                                    <form x-show="bearbeiten" method="POST" action="{{ route('wissen.speichern', [$ressort, $w]) }}" class="mt-2 space-y-2">@csrf
                                        <input name="titel" required class="feld" value="{{ $w->titel }}">
                                        <textarea name="inhalt" required class="feld" rows="8">{{ $w->inhalt }}</textarea>
                                        <div class="flex gap-2"><button class="knopf-rot knopf-klein">Speichern</button><button type="button" class="knopf-leise knopf-klein" x-on:click="bearbeiten = false">Abbrechen</button></div>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <x-leer icon="buch" titel="Noch kein Wissen" text="Abläufe, Tipps, Stolpersteine. Alles, was nicht in eine einzelne Aufgabe passt." />
                    @endforelse
                </div>
            </section>
                    @break
                @case('material')
<section id="material" class="karte" x-data="{ neu: false }">
                <div class="karte-kopf"><h2 class="titel text-sm">Material</h2>
                    <span class="flex items-center gap-1">
                        @if ($ressort->material->isNotEmpty())<a href="{{ route('material.drucken', ['ressort' => $ressort->id]) }}" target="_blank" class="knopf-leise knopf-klein" aria-label="Materialliste drucken"><x-icon name="drucker" class="h-4 w-4" /></a>@endif
                        @can('schreiben')<button type="button" class="knopf-leise knopf-klein" x-on:click="neu = !neu" aria-label="Material erfassen"><x-icon name="plus" class="h-4 w-4" /></button>@endcan
                    </span>
                </div>
                @can('schreiben')
                    <div x-show="neu" x-collapse x-cloak class="border-b border-nacht/10 px-4 pb-4">@include('material._formular', ['m' => null, 'vorRessort' => $ressort->id])</div>
                @endcan
                @forelse ($ressort->material as $m)
                    @php $b = $m->mietBilanz(); @endphp
                    <a href="{{ route('material.index', ['ressort' => $ressort->id, 'q' => $m->name]) }}" class="flex items-center justify-between gap-2 border-b border-nacht/5 px-4 py-2.5 text-sm hover:text-rot">
                        <span class="min-w-0"><span class="block truncate font-bold">{{ $m->name }} <span class="font-normal text-beton">{{ rtrim(rtrim(number_format($m->menge, 2, '.', "'"), '0'), '.') }} {{ $m->einheit }}</span></span>@if ($m->zweck)<span class="block truncate text-xs text-beton">{{ $m->zweck }}</span>@endif</span>
                        @if ($b['pruefen'])<span class="marke-gelb shrink-0">Kauf prüfen</span>@elseif ($m->zustand !== 'gut')<span class="marke-offen shrink-0">{{ \App\Models\Material::ZUSTAND[$m->zustand] }}</span>@endif
                    </a>
                @empty
                    <p class="px-4 py-3 text-xs text-beton">Noch kein Material für dieses Ressort. Mit + erfassen.</p>
                @endforelse
                @if ($ressort->material->isNotEmpty())<a href="{{ route('material.index', ['ressort' => $ressort->id]) }}" class="label flex items-center gap-2 px-4 py-3 hover:text-rot">In der Materialliste filtern <x-icon name="pfeil" class="h-3.5 w-3.5" /></a>@endif
            </section>
                    @break
                @case('dateien')
@if ($mitarbeiten)
                        @include('dateien._hochladen', ['ressorts' => $ressorts, 'ordner' => $alleOrdner->where('ressort_id', $ressort->id), 'vorRessort' => $ressort->id])
                    @endif
                    @php $orte = \App\Models\Ordner::orte($ressorts, $alleOrdner); @endphp
                    @include('dateien._ablage', ['gruppen' => \App\Models\Ordner::gruppen([$ressort], $alleOrdner, $ressort->dokumente, false), 'offen' => true])
                    <a href="{{ route('dateien.index', ['ressort' => $ressort->id]) }}" class="label mt-3 inline-flex items-center gap-2 hover:text-rot">In der Dateiablage öffnen <x-icon name="pfeil" class="h-3.5 w-3.5" /></a>
                    @break
                @case('erkenntnisse')
<section id="erkenntnisse" class="karte">
                <div class="karte-kopf"><h2 class="titel text-sm">Erkenntnisse aus früheren Turnieren</h2></div>
                <ul class="divide-y divide-nacht/5">
                    @forelse ($erkenntnisse as $e)
                        <li class="px-4 py-3 text-sm">
                            <div class="flex flex-wrap items-center gap-2"><span class="marke-rahmen">{{ \App\Models\Erkenntnis::VORSCHLAEGE[$e->vorschlag] }}</span>@if ($e->umgesetzt_am)<span class="marke-erledigt">umgesetzt</span>@endif<span class="text-xs text-beton">{{ $e->ausgabe?->titel }}</span></div>
                            <p class="mt-1">{{ $e->text }}</p>
                        </li>
                    @empty
                        <li class="px-4 py-5 text-sm text-beton">Noch keine Erkenntnisse.</li>
                    @endforelse
                </ul>
            </section>
                    @break
            @endswitch
        </div>
    @endif

    @if ($ausgabe && $mitarbeiten && in_array($tab, ['uebersicht', 'aufgaben'], true))
        @include('assistent._fenster', ['art' => 'ressort', 'ressort' => $ressort])
    @endif
    @if ($ausgabe && $mitarbeiten && in_array($tab, ['uebersicht', 'aufgaben'], true))
        <x-modal name="neue-aufgabe" titel="Neue Aufgabe" :auf="$errors->any() && old('titel') !== null">
            <form method="POST" action="{{ route('aufgaben.store', $ausgabe) }}">@csrf
                <input type="hidden" name="zurueck" value="{{ route('ressorts.show', [$ressort, 'tab' => 'aufgaben'], false) }}">
                <p class="mb-4 text-xs text-beton">Für {{ $ausgabe->titel }}</p>
                @include('aufgaben._formular', ['aufgabe' => null, 'ressorts' => $ressorts->filter(fn ($r) => auth()->user()->can('ressort-mitarbeiten', $r) || auth()->user()->can('ressort-verwalten', $r))->values(), 'vorRessort' => $ressort->id])
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="knopf-leise" x-on:click="offen = false">Abbrechen</button>
                    <button class="knopf-rot">Erstellen</button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($verwalten)
        <x-modal name="uebergabe" titel="Übergabe planen">
            <form method="POST" action="{{ route('uebergaben.store') }}" class="space-y-4">@csrf
                <input type="hidden" name="ressort_id" value="{{ $ressort->id }}">
                <p class="text-sm text-beton">Die Nachfolge erhält sofort Einblick ins Ressort und eine Checkliste. Die Leitung wechselt erst, wenn beide bestätigt haben.</p>
                <div><label class="feld-label" for="an_id">Nachfolge</label>
                    <select id="an_id" name="an_id" class="feld" required>@foreach ($personen as $p)<option value="{{ $p->id }}" @selected($ressort->nachfolge()?->id === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
                <div><label class="feld-label" for="termin">Übergabe bis</label><input id="termin" type="date" name="termin" class="feld"></div>
                <div><label class="feld-label" for="notiz">Notiz</label><textarea id="notiz" name="notiz" class="feld" rows="3"></textarea></div>
                <div class="flex justify-end"><button class="knopf-rot">Übergabe starten</button></div>
            </form>
        </x-modal>
    @endif
</x-app>
