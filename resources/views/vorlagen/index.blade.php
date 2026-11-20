<x-app titel="Jahresplan">
    <x-seitenkopf titel="Jahresplan" ueber="Was jedes Jahr kommt">
        <x-slot:unter>{{ $anzahl === 1 ? 'Eine Aufgabe kommt' : $anzahl.' Aufgaben kommen' }} bei jedem neuen Turnier automatisch.@if ($verwaltbar->isNotEmpty()) Mit Nein kommt eine Aufgabe nicht mehr, ihr Verlauf bleibt erhalten.@endif</x-slot:unter>
    </x-seitenkopf>

    @if ($ressorts->isEmpty())
        <div class="karte p-4"><x-ohne-ressort /></div>
    @else
        @if ($verwaltbar->isNotEmpty())
            @php $schnell = session('schnell', []); @endphp
            <form method="POST" action="{{ route('vorlagen.store') }}" class="karte mb-6 p-4" id="schnell">@csrf
                <input type="hidden" name="schnell" value="1"><input type="hidden" name="prioritaet" value="normal">
                @foreach (['ressort', 'status'] as $f)@if (filled($filter[$f] ?? null) && ($f !== 'status' || $filter[$f] !== 'alle'))<input type="hidden" name="{{ $f }}" value="{{ $filter[$f] }}">@endif @endforeach
                <p class="label mb-3 text-rot">Neu in den Jahresplan</p>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[240px] flex-1 basis-full sm:basis-[320px]"><label class="feld-label" for="s-titel">Aufgabe</label>
                        <input id="s-titel" name="titel" required maxlength="200" class="feld" placeholder="z. B. Halle reservieren" value="{{ old('titel') }}" @if ($schnell || $errors->any()) autofocus @endif></div>
                    <div class="w-full sm:w-56"><label class="feld-label" for="s-ressort">Ressort</label>
                        <select id="s-ressort" name="ressort_id" required class="feld">@foreach ($verwaltbar as $r)<option value="{{ $r->id }}" @selected(old('ressort_id', $schnell['ressort_id'] ?? $filter['ressort'] ?? null) == $r->id)>{{ $r->name }}</option>@endforeach</select></div>
                    <div><label class="feld-label" for="s-zeit">Wann</label><x-zeitpunkt id="s-zeit" :wert="old('tage_vorher', $schnell['tage_vorher'] ?? 30)" /></div>
                    <div><label class="feld-label" for="s-aufwand">Aufwand</label><x-aufwand name="aufwand_std" id="s-aufwand" :wert="old('aufwand_std')" /></div>
                    @if ($turniere->count() > 1)
                        <div><p class="feld-label">Gilt für</p><x-turnierarten :turniere="$turniere" :gewaehlt="old('turniere', $schnell['turniere'] ?? [])" klein /></div>
                    @endif
                    <button class="knopf-rot w-full sm:w-auto"><x-icon name="plus" class="h-4 w-4" /> Hinzufügen</button>
                </div>
                <p class="mt-2 text-xs text-beton">Nach dem Hinzufügen ist das Feld gleich wieder bereit für die nächste Aufgabe. Anleitung und Checkliste ergänzt du später mit einem Klick auf die Aufgabe, oder du <a href="{{ route('vorlagen.create') }}" class="font-bold text-nacht underline hover:text-rot">erfasst sie gleich ausführlich</a>.</p>
            </form>
        @endif

        @if ($verwaltbar->isEmpty())
            <p class="mb-5 border-l-4 border-nacht bg-white px-4 py-3 text-sm">Hier siehst du, was jedes Jahr ansteht und wann. Änderungen macht die Ressortleitung.</p>
        @endif
        <form method="GET" class="mb-5 flex flex-wrap items-end gap-2" x-data x-on:change="$el.submit()">
            <select name="ressort" class="feld w-auto" aria-label="Ressort"><option value="">Alle Ressorts</option>@if ($hatRessorts)<option value="meine" @selected(($filter['ressort'] ?? '') === 'meine')>Meine Ressorts</option>@endif @foreach ($ressorts as $r)<option value="{{ $r->id }}" @selected(($filter['ressort'] ?? '') == $r->id)>{{ $r->name }}</option>@endforeach</select>
            @if ($turniere->count() > 1)<select name="turnier" class="feld w-auto" aria-label="Turnierart"><option value="">Alle Turnierarten</option>@foreach ($turniere as $t)<option value="{{ $t->id }}" @selected(($filter['turnier'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach</select>@endif
            <select name="status" class="feld w-auto" aria-label="Jedes Jahr"><option value="alle" @selected($filter['status'] === 'alle')>Ja und Nein</option><option value="ja" @selected($filter['status'] === 'ja')>Nur Ja</option><option value="nein" @selected($filter['status'] === 'nein')>Nur Nein</option></select>
            @if ($verwaltbar->isNotEmpty())
            <label class="flex min-h-[44px] items-center gap-2 border border-nacht/20 bg-white px-3 text-sm"><input type="checkbox" name="nur" value="ohne_anleitung" @checked(($filter['nur'] ?? '') === 'ohne_anleitung')> ohne Anleitung</label>
            @endif
            <input name="q" value="{{ $filter['q'] ?? '' }}" class="feld w-auto min-w-[160px] flex-1" placeholder="Suchen" aria-label="Suchen">
            @if ($gefiltert)<a href="{{ route('vorlagen.index') }}" class="knopf-leise">Zurücksetzen</a>@endif
        </form>

        @forelse ($sichtbar as $ressort)
            @php $liste = $vorlagen[$ressort->id] ?? collect(); $darf = auth()->user()->can('ressort-verwalten', $ressort); @endphp
            <section class="karte mb-5">
                <div class="karte-kopf"><a href="{{ route('ressorts.show', $ressort) }}" class="titel text-sm hover:text-rot">{{ $ressort->name }}</a><span class="label">{{ $liste->where('aktiv', true)->count() }} jedes Jahr</span></div>
                @if ($liste->isEmpty())
                    <p class="px-4 py-4 text-sm text-beton">Noch nichts im Jahresplan.</p>
                @else
                    <ul class="divide-y divide-nacht/5">
                        @foreach ($liste as $v)
                            <li class="flex items-center gap-3 px-4 py-2.5 {{ ($schnell['neu'] ?? null) === $v->id ? 'bg-gelb/15' : '' }}" x-data="jahresplanSchalter(@js(route('vorlagen.aktiv', $v)), {{ $v->aktiv ? 'true' : 'false' }})" :class="wert ? '' : 'bg-kreide/60'">
                                <a href="{{ route('vorlagen.show', $v) }}" class="flex min-w-0 flex-1 flex-col gap-0.5 hover:text-rot sm:flex-row sm:items-center sm:gap-3" :class="wert ? '' : 'opacity-60'">
                                    <span class="shrink-0 text-xs font-bold text-beton sm:w-32">{{ $v->zeitpunktText() }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-bold">{{ $v->titel }}</span>
                                        @if ($v->turniere->isNotEmpty() || blank($v->anleitung) || $v->offene_erkenntnisse)
                                            <span class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                                @foreach ($v->turniere as $t)<span class="marke-rahmen">{{ $t->kurz ?: $t->name }}</span>@endforeach
                                                @if (blank($v->anleitung))<span class="text-xs text-beton">ohne Anleitung</span>@endif
                                                @if ($v->offene_erkenntnisse)<span class="marke bg-nacht text-white"><x-icon name="idee" class="h-3 w-3" /> {{ $v->offene_erkenntnisse }}</span>@endif
                                            </span>
                                        @endif
                                    </span>
                                    <span class="hidden shrink-0 text-xs text-beton md:block">{{ \App\Support\Aufwand::text($v->aufwand_std) }}</span>
                                </a>
                                @if ($darf)
                                    <div class="grid shrink-0 grid-cols-2 border border-nacht/20 text-[11px] font-bold uppercase tracking-label" role="group" aria-label="Jedes Jahr: {{ $v->titel }}">
                                        <button type="button" class="px-3 py-2 uppercase" :class="wert ? 'bg-rot text-white' : 'text-beton hover:bg-nacht/5'" x-on:click="setze(true)" :aria-pressed="wert">Ja</button>
                                        <button type="button" class="px-3 py-2 uppercase" :class="wert ? 'text-beton hover:bg-nacht/5' : 'bg-nacht text-white'" x-on:click="setze(false)" :aria-pressed="!wert">Nein</button>
                                    </div>
                                @else
                                    <span class="label shrink-0" x-text="wert ? 'Jedes Jahr' : 'Kommt nicht mehr'"></span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @empty
            <div class="karte">@if ($gefiltert)<x-leer icon="vorlagen" titel="Nichts gefunden" text="Passe die Filter an." />@else<x-leer icon="vorlagen" titel="Noch nichts im Jahresplan" text="Sobald die Ressortleitungen ihre Aufgaben erfassen, siehst du hier, was jedes Jahr ansteht." />@endif</div>
        @endforelse
    @endif
</x-app>
