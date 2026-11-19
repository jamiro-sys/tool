@props(['ausgabe' => null, 'kompakt' => false])
{{-- Kopfzeile "Anstoss" gemaess Styleguide: rote Flaeche, Negativlogo, Titel, Mittellinie und Mittelkreis in Weiss 35 % --}}
<header {{ $attributes->merge(['class' => 'relative bg-rot text-white']) }}>
    <div class="flex h-[64px] items-stretch sm:h-[76px]">
        <a href="{{ route('start') }}" class="flex shrink-0 items-center gap-3 pl-4 pr-2 sm:pl-6" aria-label="Zur Startseite">
            <img src="{{ asset('img/fcm-logo-negativ.svg') }}" alt="FC Münsterlingen" class="h-[50px] w-auto sm:h-[58px]">
            <span class="flex flex-col leading-none">
                <span class="text-[9px] font-bold tracking-weit sm:text-[10px]">RAIFFEISEN</span>
                <span class="text-lg font-extrabold tracking-tight sm:text-[22px]">HALLENCUP</span>
                @if ($ausgabe)
                    <span class="mt-1 hidden text-[10px] font-bold uppercase tracking-[0.1em] sm:block">{{ $ausgabe->datumText() }}</span>
                @else
                    <span class="mt-1 hidden text-[10px] font-bold uppercase tracking-[0.1em] sm:block">OK-TOOL</span>
                @endif
            </span>
        </a>

        {{-- Freie Flaeche mit Hallenlinien --}}
        <div class="relative mx-4 min-w-[48px] flex-1" aria-hidden="true">
            <svg class="absolute inset-0 h-full w-full" preserveAspectRatio="none">
                <line x1="50%" y1="0" x2="50%" y2="100%" stroke="#fff" stroke-opacity=".35" stroke-width="2" />
            </svg>
            <svg class="absolute left-1/2 top-1/2 h-[46px] w-[46px] -translate-x-1/2 -translate-y-1/2 sm:h-[56px] sm:w-[56px]" viewBox="0 0 60 60">
                <circle cx="30" cy="30" r="28" fill="none" stroke="#fff" stroke-opacity=".35" stroke-width="2" />
                <circle cx="30" cy="30" r="2.5" fill="#fff" fill-opacity=".35" />
            </svg>
        </div>

        <div class="flex shrink-0 items-center gap-1 pr-2 sm:gap-2 sm:pr-4">
            {{ $slot }}
        </div>
    </div>
</header>
