@props(['titel' => null])
<!DOCTYPE html>
<html lang="de-CH">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#B8161C">
    <title>{{ $titel ? $titel.' | ' : '' }}OK-Tool</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/img/icon-192.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    <x-anstoss />
    <main class="flex flex-1 items-start justify-center px-4 py-10 sm:py-16">
        <div class="w-full max-w-md animate-einblenden">
            {{ $slot }}
        </div>
    </main>
    <footer class="flex items-center justify-between border-t border-nacht/10 bg-white px-[6%] py-4">
        <span class="flex items-center gap-2"><span class="h-4 w-1 bg-rot"></span><span class="label">Hauptsponsor</span></span>
        <img src="{{ asset('img/raiffeisen-logo.svg') }}" alt="Raiffeisenbank Regio Altnau" class="h-10 w-auto">
    </footer>
</body>
</html>
