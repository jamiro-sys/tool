@props(['titel' => null])
<!DOCTYPE html>
<html lang="de-CH">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#B8161C">
    <meta name="vapid-key" content="{{ config('services.webpush.public_key') }}">
    <title>{{ isset($titel) ? $titel.' | ' : '' }}{{ \App\Models\Einstellung::wertSicher('tool_name', 'OK-Tool') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/img/icon-192.png">
    <link rel="apple-touch-icon" href="/img/icon-192.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $u = auth()->user();
    $navAusgabe ??= null;
    $ungelesen ??= 0;
    $nav = [
        ['start', 'Start', route('start'), 'start', request()->routeIs('start')],
        ['aufgaben', 'Aufgaben', $navAusgabe ? route('aufgaben.index', $navAusgabe) : route('ausgaben.index'), 'aufgaben', request()->routeIs('aufgaben.*')],
        ['ausgaben', 'Turniere', route('ausgaben.index'), 'pokal', request()->routeIs('ausgaben.*', 'festwirtschaft.*', 'material.ausgabe')],
        ['ressorts', 'Ressorts', route('ressorts.index'), 'ressorts', request()->routeIs('ressorts.*')],
    ];
    $navRessorts ??= collect();
    $aktivesRessort = request()->route('ressort');
    $admin = [
        ['Personen', route('admin.personen'), 'ressorts', request()->routeIs('admin.personen')],
        ['Vorlagen', route('vorlagen.index'), 'vorlagen', request()->routeIs('vorlagen.*')],
        ['Auswertung', route('auswertung'), 'auswertung', request()->routeIs('auswertung')],
        ['Übergaben', route('uebergaben.index'), 'uebergabe', request()->routeIs('uebergaben.*')],
        ['Material', route('material.index'), 'kiste', request()->routeIs('material.index')],
        ['System', route('admin.system'), 'server', request()->routeIs('admin.system', 'admin.logs')],
        ['Sicherungen', route('admin.sicherungen'), 'datenbank', request()->routeIs('admin.sicherungen')],
        ['Daten', route('admin.daten'), 'archiv', request()->routeIs('admin.daten')],
        ['Rundmeldungen', route('admin.rundmeldungen'), 'senden', request()->routeIs('admin.rundmeldungen')],
        ['Protokoll', route('admin.protokoll'), 'protokoll', request()->routeIs('admin.protokoll')],
        ['Einstellungen', route('admin.einstellungen'), 'einstellungen', request()->routeIs('admin.einstellungen')],
    ];
@endphp
<body class="min-h-screen" x-data>
    <a href="#inhalt" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:bg-white focus:p-2">Zum Inhalt</a>

    @if (session('imitiert_von'))
        <form method="POST" action="{{ route('imitieren.beenden') }}" class="flex items-center justify-center gap-3 bg-gelb px-4 py-2 text-xs font-bold uppercase tracking-label text-nacht kein-druck">
            @csrf
            <span>Du siehst das Tool als {{ $u->name }}</span>
            <button class="underline">Zurück zu meinem Konto</button>
        </form>
    @endif

    <x-anstoss :ausgabe="$navAusgabe" class="kein-druck sticky top-0 z-30">
        <div x-data="suche('{{ route('suche') }}')" x-on:suche-oeffnen.window="oeffnen()">
            <button type="button" x-on:click="oeffnen()" class="flex h-11 w-11 items-center justify-center hover:bg-white/10" aria-label="Suche (Taste /)"><x-icon name="suche" /></button>
            <template x-teleport="body">
                <div x-show="offen" x-cloak class="fixed inset-0 z-50 flex items-start justify-center bg-nacht/60 px-4 pt-[12vh]" x-on:click.self="schliessen()" x-on:keydown.escape.window="schliessen()" x-transition.opacity>
                    <div class="w-full max-w-xl bg-white">
                        <div class="flex items-center gap-3 border-b border-nacht/10 px-4">
                            <x-icon name="suche" class="w-5 h-5 text-beton" />
                            <input x-ref="eingabe" x-model="q" x-on:input="tippen()" x-on:keydown.down.prevent="runter()" x-on:keydown.up.prevent="hoch()" x-on:keydown.enter.prevent="waehlen()"
                                   class="h-14 w-full border-0 text-base focus:outline-none focus:ring-0" placeholder="Aufgaben, Vorlagen, Kontakte, Wissen ...">
                        </div>
                        <ul class="max-h-[50vh] overflow-y-auto">
                            <template x-for="(t, i) in treffer" :key="t.url + i">
                                <li><a :href="t.url" class="flex items-center justify-between gap-3 px-4 py-3" :class="i === index ? 'bg-kreide' : ''" x-on:mouseenter="index = i">
                                    <span class="min-w-0"><span class="block truncate text-sm font-bold" x-text="t.titel"></span><span class="block truncate text-xs text-beton" x-text="t.info"></span></span>
                                    <span class="marke-rahmen" x-text="t.typ"></span>
                                </a></li>
                            </template>
                            <li x-show="q.length >= 2 && treffer.length === 0" class="px-4 py-6 text-center text-sm text-beton">Nichts gefunden.</li>
                        </ul>
                    </div>
                </div>
            </template>
        </div>
        <a href="{{ route('benachrichtigungen.index') }}" x-data="glocke('{{ route('benachrichtigungen.anzahl') }}', {{ $ungelesen }})" class="relative flex h-11 w-11 items-center justify-center hover:bg-white/10" aria-label="Benachrichtigungen">
            <x-icon name="glocke" />
            <span x-show="anzahl > 0" x-text="anzahl > 9 ? '9+' : anzahl" class="absolute right-1 top-1 flex h-[18px] min-w-[18px] items-center justify-center bg-gelb px-1 text-[10px] font-extrabold text-nacht" x-transition.scale></span>
        </a>
        <div x-data="{ offen: false }" class="relative hidden sm:block">
            <button type="button" x-on:click="offen = !offen" x-on:click.outside="offen = false" class="flex h-11 items-center gap-2 px-2 hover:bg-white/10">
                <span class="flex h-8 w-8 items-center justify-center bg-white text-[11px] font-extrabold text-rot">{{ $u->initialen() }}</span>
            </button>
            <div x-show="offen" x-cloak x-transition.origin.top.right class="absolute right-0 mt-1 w-60 bg-white text-nacht border border-nacht/10">
                <div class="border-b border-nacht/10 px-4 py-3">
                    <p class="text-sm font-bold">{{ $u->name }}</p>
                    <p class="text-xs text-beton">{{ \App\Models\User::ROLLEN[$u->rolle] }}</p>
                </div>
                <a href="{{ route('profil') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-kreide"><x-icon name="person" class="w-4 h-4" /> Profil</a>
                <a href="{{ route('benachrichtigungen.einstellungen') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-kreide"><x-icon name="glocke" class="w-4 h-4" /> Benachrichtigungen</a>
                <a href="{{ route('neuigkeiten') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-kreide"><x-icon name="idee" class="w-4 h-4" /> Neu im Tool</a>
                <form method="POST" action="{{ route('abmelden') }}" class="border-t border-nacht/10">@csrf
                    <button class="flex w-full items-center gap-2 px-4 py-2.5 text-sm hover:bg-kreide"><x-icon name="abmelden" class="w-4 h-4" /> Abmelden</button>
                </form>
            </div>
        </div>
    </x-anstoss>

    <div class="flex">
        {{-- Seitenleiste (PC) --}}
        <aside class="kein-druck sticky top-[76px] hidden h-[calc(100vh-76px)] w-60 shrink-0 flex-col overflow-y-auto bg-nacht py-4 lg:flex">
            <nav class="flex flex-col">
                @foreach ($nav as [$k, $label, $href, $icon, $aktiv])
                    @if ($k === 'ressorts')
                        @include('partials.nav-ressorts')
                    @else
                        <a href="{{ $href }}" class="nav-link {{ $aktiv ? 'aktiv' : '' }}"><x-icon :name="$icon" class="w-[18px] h-[18px]" /> {{ $label }}</a>
                    @endif
                @endforeach
            </nav>
            @can('admin')
                <p class="label mt-6 mb-1 px-4 text-white/40">Admin</p>
                <nav class="flex flex-col">
                    @foreach ($admin as [$label, $href, $icon, $aktiv])
                        <a href="{{ $href }}" class="nav-link {{ $aktiv ? 'aktiv' : '' }}"><x-icon :name="$icon" class="w-[18px] h-[18px]" /> {{ $label }}</a>
                    @endforeach
                </nav>
            @endcan
            <div class="mt-auto px-4 pt-6">
                <form method="POST" action="{{ route('profil.ansicht') }}" class="border border-white/15 p-3">
                    @csrf
                    <p class="label text-white/50 mb-2">Ansicht</p>
                    <div class="grid grid-cols-2 gap-1">
                        <button name="ansicht" value="einfach" class="py-2 text-[11px] font-bold uppercase tracking-label {{ $u->istExperte() ? 'text-white/60 hover:text-white' : 'bg-white text-nacht' }}">Einfach</button>
                        <button name="ansicht" value="experte" class="py-2 text-[11px] font-bold uppercase tracking-label {{ $u->istExperte() ? 'bg-white text-nacht' : 'text-white/60 hover:text-white' }}">Experte</button>
                    </div>
                </form>
                <p class="mt-3 text-[10px] text-white/40">Version {{ config('app.version') }}</p>
            </div>
        </aside>

        <main id="inhalt" class="min-w-0 flex-1 px-4 pb-28 pt-6 sm:px-6 lg:px-10 lg:pb-12">
            @if (session('erfolg') || session('warnung') || $errors->any())
                <div class="mb-5 space-y-2 kein-druck">
                    @if (session('erfolg'))
                        <div class="flex items-center gap-3 border-l-4 border-rot bg-white px-4 py-3 text-sm animate-einblenden" x-data="{ s: true }" x-show="s" x-init="setTimeout(() => s = false, 6000)" x-transition>
                            <x-icon name="check" class="w-4 h-4 text-rot shrink-0" /> <span>{{ session('erfolg') }}</span>
                            @if (session('vorlage_link'))<a href="{{ session('vorlage_link') }}" class="ml-auto label text-rot">Vorlage ansehen</a>@endif
                        </div>
                    @endif
                    @if (session('warnung'))
                        <div class="flex items-center gap-3 border-l-4 border-gelb bg-white px-4 py-3 text-sm"><x-icon name="warnung" class="w-4 h-4 shrink-0" /> {{ session('warnung') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="border-l-4 border-nacht bg-white px-4 py-3 text-sm">
                            <p class="label text-nacht mb-1">Bitte prüfen</p>
                            <ul class="list-inside list-disc text-beton">@foreach ($errors->all() as $f)<li>{{ $f }}</li>@endforeach</ul>
                        </div>
                    @endif
                </div>
            @endif

            @if ($u->gesehene_version !== config('app.version') && config('app.version') !== 'dev' && \App\Models\Deployment::exists())
                <div x-data="neuigkeit('{{ route('profil.version') }}')" x-show="sichtbar" x-transition class="mb-5 flex items-center gap-3 bg-nacht px-4 py-3 text-sm text-white kein-druck">
                    <x-icon name="idee" class="w-4 h-4 text-gelb shrink-0" />
                    <span>Das OK-Tool wurde aktualisiert (Version {{ config('app.version') }}).</span>
                    <a href="{{ route('neuigkeiten') }}" class="label text-white underline">Was ist neu?</a>
                    <button x-on:click="schliessen()" class="ml-auto" aria-label="Schliessen"><x-icon name="x" class="w-4 h-4" /></button>
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

    {{-- Untere Navigation (Handy) --}}
    @php $mobil = array_slice(array_values(array_filter($nav, fn ($n) => in_array($n[0], ['start', 'aufgaben', 'ressorts'], true))), 0, 3); @endphp
    <nav class="kein-druck fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t border-nacht/10 bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" x-data="{ mehr: false }">
        @foreach ($mobil as [$k, $label, $href, $icon, $aktiv])
            <a href="{{ $href }}" class="relative flex flex-col items-center justify-center gap-1 py-2.5 text-[10px] font-bold uppercase tracking-label {{ $aktiv ? 'text-rot' : 'text-beton' }}">
                @if ($aktiv)<span class="absolute inset-x-6 top-0 h-[3px] bg-rot"></span>@endif
                <x-icon :name="$icon" class="w-[22px] h-[22px]" /> {{ $label }}
            </a>
        @endforeach
        <button type="button" x-on:click="mehr = true" class="flex flex-col items-center justify-center gap-1 py-2.5 text-[10px] font-bold uppercase tracking-label text-beton">
            <x-icon name="menue" class="w-[22px] h-[22px]" /> Mehr
        </button>

        <template x-teleport="body">
            <div x-show="mehr" x-cloak class="fixed inset-0 z-50 lg:hidden">
                <div class="absolute inset-0 bg-nacht/60" x-on:click="mehr = false" x-show="mehr" x-transition.opacity></div>
                <div class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto bg-nacht pb-[env(safe-area-inset-bottom)] text-white" x-show="mehr"
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
                    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                        <span class="flex items-center gap-2"><span class="flex h-8 w-8 items-center justify-center bg-white text-[11px] font-extrabold text-rot">{{ $u->initialen() }}</span><span class="text-sm font-bold">{{ $u->name }}</span></span>
                        <button x-on:click="mehr = false" class="flex h-11 w-11 items-center justify-center" aria-label="Schliessen"><x-icon name="x" /></button>
                    </div>
                    <div class="grid grid-cols-1 py-2">
                        @foreach ($nav as [$k, $label, $href, $icon, $aktiv])
                            @if ($k === 'ressorts')
                                @include('partials.nav-ressorts')
                            @else
                                <a href="{{ $href }}" class="nav-link {{ $aktiv ? 'aktiv' : '' }}"><x-icon :name="$icon" class="w-[18px] h-[18px]" /> {{ $label }}</a>
                            @endif
                        @endforeach
                        <a href="{{ route('profil') }}" class="nav-link"><x-icon name="person" class="w-[18px] h-[18px]" /> Profil</a>
                        <a href="{{ route('benachrichtigungen.einstellungen') }}" class="nav-link"><x-icon name="glocke" class="w-[18px] h-[18px]" /> Benachrichtigungen</a>
                    </div>
                    @can('admin')
                        <p class="label px-4 pt-2 text-white/40">Admin</p>
                        <div class="grid grid-cols-2 py-2">
                            @foreach ($admin as [$label, $href, $icon, $aktiv])
                                <a href="{{ $href }}" class="nav-link {{ $aktiv ? 'aktiv' : '' }}"><x-icon :name="$icon" class="w-[18px] h-[18px]" /> {{ $label }}</a>
                            @endforeach
                        </div>
                    @endcan
                    <div class="grid grid-cols-2 gap-2 border-t border-white/10 p-4">
                        <form method="POST" action="{{ route('profil.ansicht') }}">@csrf
                            <button name="ansicht" value="{{ $u->istExperte() ? 'einfach' : 'experte' }}" class="knopf w-full border border-white/20 text-white">{{ $u->istExperte() ? 'Einfache Ansicht' : 'Expertenansicht' }}</button>
                        </form>
                        <form method="POST" action="{{ route('abmelden') }}">@csrf<button class="knopf w-full border border-white/20 text-white">Abmelden</button></form>
                    </div>
                </div>
            </div>
        </template>
    </nav>

    {{-- Rueckmeldungen --}}
    <div class="pointer-events-none fixed inset-x-0 bottom-20 z-50 flex flex-col items-center gap-2 px-4 lg:bottom-6">
        <template x-for="t in $store.toast.liste" :key="t.id">
            <div class="pointer-events-auto flex items-center gap-2 px-4 py-3 text-sm font-bold text-white" :class="t.art === 'fehler' ? 'bg-nacht' : 'bg-rot'"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0">
                <span x-text="t.text"></span>
            </div>
        </template>
    </div>

    @stack('skripte')
</body>
</html>
