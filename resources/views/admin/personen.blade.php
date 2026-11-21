<x-app titel="Personen">
    <x-seitenkopf titel="Personen" ueber="Admin">
        <x-slot:unter>{{ $personen->where('aktiv', true)->count() }} aktive Personen. Einladungen laufen per E-Mail-Link.</x-slot:unter>
        <x-slot:aktionen><button type="button" class="knopf-rot" x-on:click="$dispatch('modal-einladen')" data-kuerzel="n"><x-icon name="plus" class="h-4 w-4" /> Einladen</button></x-slot:aktionen>
    </x-seitenkopf>

    @if (session('einladungslink'))
        <div class="mb-5 border-l-4 border-gelb bg-white px-4 py-3 text-sm" x-data="{ kopiert: false }">
            <p class="label mb-1">Einladungslink (falls die Mail nicht ankommt)</p>
            <div class="flex gap-2"><input readonly value="{{ session('einladungslink') }}" class="feld flex-1 text-xs" x-ref="link">
                <button type="button" class="knopf-nacht" x-on:click="navigator.clipboard.writeText($refs.link.value); kopiert = true" x-text="kopiert ? 'Kopiert' : 'Kopieren'"></button></div>
        </div>
    @endif

    <div class="karte overflow-x-auto">
        <table class="tabelle">
            <thead><tr><th>Person</th><th>Rolle</th><th class="hidden md:table-cell">Ressorts</th><th class="hidden sm:table-cell">Zuletzt</th><th class="text-right">Aktionen</th></tr></thead>
            <tbody>
                @foreach ($personen as $p)
                    <tr class="{{ $p->aktiv ? '' : 'opacity-50' }}" x-data="{ auf: false }">
                        <td>
                            <div class="flex items-center gap-3"><x-person :user="$p" gross />
                                <div><p class="font-bold">{{ $p->name }}</p><p class="text-xs text-beton">{{ $p->email }}</p>
                                    @if ($p->einladung_token)<span class="marke-gelb mt-1">Einladung offen</span>@endif
                                    @if (! $p->aktiv)<span class="marke-entfaellt mt-1">gesperrt</span>@endif</div></div>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.personen.update', $p) }}" x-data="{ alt: @js($p->rolle) }" x-on:change="confirm(@js($p->name) + ': Rolle wirklich ändern?') ? $el.submit() : ($event.target.value = alt)">@csrf @method('PATCH')
                                <select name="rolle" class="feld min-h-[36px] w-auto py-1 text-xs" aria-label="Rolle von {{ $p->name }}">@foreach (\App\Models\User::ROLLEN as $k => $l)<option value="{{ $k }}" @selected($p->rolle === $k)>{{ $l }}</option>@endforeach</select>
                            </form>
                            <p class="mt-1 text-xs text-beton">Ansicht: {{ ucfirst($p->ansicht) }}</p>
                        </td>
                        <td class="hidden md:table-cell"><div class="flex flex-wrap gap-1">@foreach ($p->ressorts as $r)<span class="{{ $r->pivot->rolle === 'leitung' ? 'marke bg-nacht text-white' : 'marke-rahmen' }}">{{ $r->kuerzel }}</span>@endforeach</div></td>
                        <td class="hidden whitespace-nowrap text-xs text-beton sm:table-cell">{{ $p->letzte_anmeldung?->diffForHumans() ?? 'nie' }}</td>
                        <td class="text-right">
                            <div class="inline-block text-left" x-data="aktionsMenue">
                                <button type="button" class="knopf-leise knopf-klein" x-ref="knopf" x-on:click="umschalten()" aria-label="Aktionen für {{ $p->name }}" :aria-expanded="m"><x-icon name="mehr" class="h-4 w-4" /></button>
                                <template x-teleport="body">
                                <div x-show="m" x-cloak x-transition.opacity x-on:click.outside="if (! $refs.knopf.contains($event.target)) m = false" x-on:keydown.escape.window="m = false" :style="lage" class="fixed z-50 w-60 border border-nacht/15 bg-white py-1 text-sm">
                                    @if ($p->einladung_token)
                                        <form method="POST" action="{{ route('admin.personen.einladung', $p) }}">@csrf<button class="block w-full px-4 py-2 text-left hover:bg-kreide">Einladung erneut senden</button></form>
                                    @else
                                        <form method="POST" action="{{ route('admin.personen.passwort', $p) }}">@csrf<button class="block w-full px-4 py-2 text-left hover:bg-kreide">Passwort-Link senden</button></form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.personen.update', $p) }}">@csrf @method('PATCH')<input type="hidden" name="ansicht" value="{{ $p->istExperte() ? 'einfach' : 'experte' }}"><button class="block w-full px-4 py-2 text-left hover:bg-kreide">Ansicht: {{ $p->istExperte() ? 'Einfach' : 'Experte' }}</button></form>
                                    @if ($p->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.personen.imitieren', $p) }}">@csrf<button class="block w-full px-4 py-2 text-left hover:bg-kreide">Als {{ strtok($p->name, ' ') }} ansehen</button></form>
                                        <form method="POST" action="{{ route('admin.personen.update', $p) }}" onsubmit="return confirm('{{ $p->aktiv ? 'Zugang sperren?' : 'Zugang wieder freigeben?' }}')">@csrf @method('PATCH')<input type="hidden" name="aktiv" value="{{ $p->aktiv ? 0 : 1 }}"><button class="block w-full px-4 py-2 text-left hover:bg-kreide">{{ $p->aktiv ? 'Sperren' : 'Freigeben' }}</button></form>
                                    @endif
                                </div>
                                </template>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-modal name="einladen" titel="Person einladen">
        <form method="POST" action="{{ route('admin.personen') }}" class="space-y-4">@csrf
            <div><label class="feld-label" for="p-name">Name</label><input id="p-name" name="name" required class="feld"></div>
            <div><label class="feld-label" for="p-mail">E-Mail</label><input id="p-mail" type="email" name="email" required class="feld"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="feld-label" for="p-rolle">Rolle</label><select id="p-rolle" name="rolle" class="feld">@foreach (\App\Models\User::ROLLEN as $k => $l)<option value="{{ $k }}" @selected($k === 'mitglied')>{{ $l }}</option>@endforeach</select></div>
                <div><label class="feld-label" for="p-ansicht">Ansicht</label><select id="p-ansicht" name="ansicht" class="feld"><option value="einfach">Einfach</option><option value="experte">Experte</option></select></div>
                <div><label class="feld-label" for="p-ressort">Ressort</label><select id="p-ressort" name="ressort_id" class="feld"><option value="">Keins</option>@foreach ($ressorts as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select></div>
                <div><label class="feld-label" for="p-rr">Als</label><select id="p-rr" name="ressort_rolle" class="feld"><option value="mitglied">Mitglied</option><option value="leitung">Leitung</option><option value="nachfolge">Nachfolge</option></select></div>
            </div>
            <div class="flex justify-end"><button class="knopf-rot">Einladung senden</button></div>
        </form>
    </x-modal>
</x-app>
