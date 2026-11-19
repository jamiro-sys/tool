<x-app titel="Profil">
    <x-seitenkopf :titel="$user->name" ueber="Profil" />
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <form method="POST" class="karte space-y-4 p-4">@csrf @method('PATCH')
                <p class="titel text-sm">Angaben</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="feld-label" for="name">Name</label><input id="name" name="name" required class="feld" value="{{ old('name', $user->name) }}"></div>
                    <div><label class="feld-label" for="telefon">Telefon</label><input id="telefon" name="telefon" class="feld" value="{{ old('telefon', $user->telefon) }}"></div>
                    <div class="sm:col-span-2"><label class="feld-label" for="email">E-Mail</label><input id="email" type="email" name="email" required class="feld" value="{{ old('email', $user->email) }}"></div>
                </div>
                <button class="knopf-rot">Speichern</button>
            </form>
            <form method="POST" action="{{ route('profil.passwort') }}" class="karte space-y-4 p-4">@csrf
                <p class="titel text-sm">Passwort ändern</p>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div><label class="feld-label" for="aktuell">Aktuell</label><input id="aktuell" type="password" name="aktuell" required class="feld" autocomplete="current-password"></div>
                    <div><label class="feld-label" for="password">Neu</label><input id="password" type="password" name="password" required class="feld" autocomplete="new-password"></div>
                    <div><label class="feld-label" for="password_confirmation">Wiederholen</label><input id="password_confirmation" type="password" name="password_confirmation" required class="feld" autocomplete="new-password"></div>
                </div>
                <button class="knopf-nacht">Passwort ändern</button>
            </form>
        </div>
        <aside class="space-y-6">
            <section class="karte p-4">
                <p class="titel text-sm">Ansicht</p>
                <p class="mt-1 text-xs text-beton">Einfach: Fokus auf deine Aufgaben. Experte: Dashboards, Vorlagen, Auswertungen und Tastenkürzel.</p>
                <form method="POST" action="{{ route('profil.ansicht') }}" class="mt-3 grid grid-cols-2 gap-2">@csrf
                    <button name="ansicht" value="einfach" class="knopf {{ $user->istExperte() ? 'border border-nacht/20' : 'bg-nacht text-white' }}">Einfach</button>
                    <button name="ansicht" value="experte" class="knopf {{ $user->istExperte() ? 'bg-nacht text-white' : 'border border-nacht/20' }}">Experte</button>
                </form>
                @if ($user->istExperte())
                    <div class="mt-4 border-t border-nacht/10 pt-3 text-xs">
                        <p class="label mb-2">Tastenkürzel</p>
                        <dl class="grid grid-cols-[40px_1fr] gap-y-1"><dt class="font-bold">/</dt><dd>Suche</dd><dt class="font-bold">n</dt><dd>Neu (Aufgabe, Vorlage, Ausgabe)</dd><dt class="font-bold">e</dt><dd>Bearbeiten</dd></dl>
                    </div>
                @endif
            </section>
            <section class="karte p-4">
                <p class="titel text-sm">Meine Ressorts</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @forelse ($user->ressorts as $r)<li><a href="{{ route('ressorts.show', $r) }}" class="hover:text-rot">{{ $r->name }}</a> <span class="text-xs text-beton">({{ $r->pivot->rolle }})</span></li>@empty<li class="text-beton">Noch keinem Ressort zugeordnet.</li>@endforelse
                </ul>
            </section>
        </aside>
    </div>
</x-app>
