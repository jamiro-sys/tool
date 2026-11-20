<x-app titel="Vorlagen">
    <x-seitenkopf titel="Vorlagen" ueber="Wiederkehrende Aufgaben">
        <x-slot:unter>Das Gedächtnis des OK. Aus diesen {{ $anzahl }} Vorlagen entsteht jede neue Ausgabe.</x-slot:unter>
        <x-slot:aktionen>
            @can('vorlagen-verwalten')
                <a href="{{ route('vorlagen.create') }}" class="knopf-rot" data-kuerzel="n"><x-icon name="plus" class="h-4 w-4" /> Vorlage</a>
            @endcan
        </x-slot:aktionen>
    </x-seitenkopf>

    <form method="GET" class="mb-5 flex flex-wrap items-end gap-2" x-data x-on:change="$el.submit()">
        <select name="ressort" class="feld w-auto" aria-label="Ressort"><option value="">Alle Ressorts</option>@foreach ($ressorts as $r)<option value="{{ $r->id }}" @selected(($filter['ressort'] ?? '') == $r->id)>{{ $r->name }}</option>@endforeach</select>
        <select name="turnier" class="feld w-auto" aria-label="Turnier"><option value="">Alle Turniere</option>@foreach ($turniere as $t)<option value="{{ $t->id }}" @selected(($filter['turnier'] ?? '') == $t->id)>{{ $t->name }}</option>@endforeach</select>
        <select name="status" class="feld w-auto" aria-label="Status"><option value="aktiv" @selected($filter['status'] === 'aktiv')>Aktiv</option><option value="inaktiv" @selected($filter['status'] === 'inaktiv')>Archiviert</option><option value="alle" @selected($filter['status'] === 'alle')>Alle</option></select>
        <label class="flex min-h-[44px] items-center gap-2 border border-nacht/20 bg-white px-3 text-sm"><input type="checkbox" name="nur" value="ohne_anleitung" @checked(($filter['nur'] ?? '') === 'ohne_anleitung')> ohne Anleitung</label>
        <input name="q" value="{{ $filter['q'] ?? '' }}" class="feld w-auto flex-1" placeholder="Suchen" aria-label="Suchen">
    </form>

    @forelse ($vorlagen as $ressortId => $liste)
        <section class="karte mb-5 animate-einblenden">
            <div class="karte-kopf"><a href="{{ route('ressorts.show', $ressortId) }}" class="titel text-sm hover:text-rot">{{ $ressorts[$ressortId]->name }}</a><span class="label">{{ $liste->count() }}</span></div>
            <div class="divide-y divide-nacht/5">
                @foreach ($liste as $v)
                    <a href="{{ route('vorlagen.show', $v) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-kreide/60 {{ $v->aktiv ? '' : 'opacity-60' }}">
                        <span class="w-24 shrink-0 text-xs font-bold text-beton">{{ $v->zeitpunktText() }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-bold">{{ $v->titel }}</span>
                            <span class="mt-0.5 flex flex-wrap gap-2">
                                @if ($v->turnier)<span class="marke-rahmen">nur {{ $v->turnier->kurz }}</span>@endif
                                @if (blank($v->anleitung))<span class="marke-gelb">ohne Anleitung</span>@endif
                                @if ($v->offene_erkenntnisse)<span class="marke bg-nacht text-white"><x-icon name="idee" class="h-3 w-3" /> {{ $v->offene_erkenntnisse }}</span>@endif
                                @if (! $v->aktiv)<span class="marke-entfaellt">archiviert</span>@endif
                            </span>
                        </span>
                        <span class="hidden text-xs text-beton sm:block">{{ $v->aufwand_std ? $v->aufwand_std.' Std.' : '' }}</span>
                        <x-icon name="pfeil" class="h-4 w-4 text-beton" />
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <div class="karte"><x-leer icon="vorlagen" titel="Keine Vorlagen" text="Erfasse wiederkehrende Aufgaben einmal, danach entstehen sie jedes Jahr automatisch." /></div>
    @endforelse
</x-app>
