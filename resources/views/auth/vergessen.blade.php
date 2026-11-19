<x-gast titel="Passwort vergessen">
    <p class="label text-rot">Zugang</p>
    <h1 class="titel mt-1 text-4xl">Passwort vergessen</h1>
    <p class="mt-2 text-sm text-beton">Gib deine E-Mail ein. Du erhältst einen Link, mit dem du ein neues Passwort setzen kannst.</p>
    @if (session('erfolg'))<div class="mt-5 border-l-4 border-rot bg-white px-4 py-3 text-sm">{{ session('erfolg') }}</div>@endif
    <form method="POST" action="{{ route('password.email') }}" class="karte mt-6 space-y-4 p-5">
        @csrf
        <div><label class="feld-label" for="email">E-Mail</label><input id="email" type="email" name="email" required class="feld" value="{{ old('email') }}"></div>
        <button class="knopf-rot w-full">Link senden</button>
    </form>
    <a href="{{ route('anmelden') }}" class="label mt-4 inline-block hover:text-rot">Zurück zur Anmeldung</a>
</x-gast>
