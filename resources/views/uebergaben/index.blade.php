<x-app titel="Übergaben">
    <x-seitenkopf titel="Übergaben" ueber="Nachfolge">
        <x-slot:unter>Weitsicht statt Feuerwehr: Ressorts werden geplant, begleitet und mit Checkliste übergeben.</x-slot:unter>
    </x-seitenkopf>

    <section class="mb-8">
        <h2 class="titel mb-3 text-sm">Laufende und vergangene Übergaben</h2>
        @if ($uebergaben->isEmpty())
            <div class="karte"><x-leer icon="uebergabe" titel="Noch keine Übergabe" text="Eine Übergabe startest du im Steckbrief des Ressorts." /></div>
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($uebergaben as $ue)
                    <a href="{{ route('uebergaben.show', $ue) }}" class="karte block p-4 hover:border-nacht {{ $ue->status === 'abgebrochen' ? 'opacity-60' : '' }}">
                        <div class="flex items-center justify-between"><span class="label text-rot">{{ $ue->ressort->name }}</span>
                            <span class="{{ $ue->status === 'abgeschlossen' ? 'marke-erledigt' : ($ue->status === 'abgebrochen' ? 'marke-entfaellt' : 'marke-in_arbeit') }}">{{ \App\Models\Uebergabe::STATUS[$ue->status] }}</span></div>
                        <div class="mt-3 flex items-center gap-3">
                            <x-person :user="$ue->von" gross />
                            <x-icon name="uebergabe" class="h-4 w-4 text-beton" />
                            <x-person :user="$ue->an" gross />
                            <span class="min-w-0 text-sm"><span class="block truncate font-bold">{{ $ue->an->name }}</span><span class="text-xs text-beton">von {{ $ue->von?->name ?? 'unbesetzt' }}</span></span>
                        </div>
                        <div class="mt-4 flex items-center gap-2"><x-fortschritt :prozent="$ue->fortschritt()" /><span class="label w-10 text-right">{{ $ue->fortschritt() }}%</span></div>
                        @if ($ue->termin && ! in_array($ue->status, ['abgeschlossen', 'abgebrochen']))<p class="mt-2 text-xs text-beton">bis {{ $ue->termin->format('d.m.Y') }}</p>@endif
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section>
        <h2 class="titel mb-3 text-sm">Übergabe-Bereitschaft der Ressorts</h2>
        <div class="karte divide-y divide-nacht/5">
            @foreach ($ressorts as $r)
                @php $stand = $r->wissensstand(); @endphp
                <a href="{{ route('ressorts.show', $r) }}" class="grid items-center gap-3 px-4 py-3 hover:bg-kreide/60 sm:grid-cols-[200px_1fr_auto]">
                    <span class="text-sm font-bold">{{ $r->name }}</span>
                    @include('ressorts._ampel', ['stand' => $stand])
                    <span class="text-xs text-beton sm:w-56 sm:text-right">{{ $stand['hinweise'][0] ?? 'bereit' }}</span>
                </a>
            @endforeach
        </div>
    </section>
</x-app>
