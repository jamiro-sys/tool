<?php

namespace App\Providers;

use App\Listeners\VersandProtokollieren;
use App\Models\Aufgabe;
use App\Models\Ressort;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('de_CH');

        \Illuminate\Database\Eloquent\Relations\Relation::morphMap(\App\Models\Verknuepfung::BEZUEGE);

        \Illuminate\Support\Facades\View::composer('components.app', function ($view) {
            $user = auth()->user();
            // Gewaehltes Turnier: das der aktuellen Seite, sonst die Wahl in der Kopfzeile, sonst das naechste
            $navAusgabe = null;
            if ($user) {
                $route = request()->route();
                $hier = $route?->parameter('ausgabe');
                $aufgabe = $route?->parameter('aufgabe');
                if (! $hier instanceof \App\Models\Ausgabe && $aufgabe instanceof \App\Models\Aufgabe) {
                    $hier = $aufgabe->ausgabe;
                }
                if ($hier instanceof \App\Models\Ausgabe) {
                    session([\App\Models\Ausgabe::WAHL => $hier->id]);
                    $navAusgabe = $hier;
                } else {
                    $navAusgabe = \App\Models\Ausgabe::gewaehlt();
                }
            }
            $view->with('navAusgabe', $navAusgabe);
            $view->with('navTurniere', $user ? \App\Models\Ausgabe::with('turnier')->orderByDesc('datum_von')->get() : collect());
            $view->with('ungelesen', $user ? $user->unreadNotifications()->count() : 0);
            $view->with('navRessorts', $user ? \App\Models\Ressort::orderBy('sortierung')->orderBy('name')->get(['id', 'name', 'kuerzel']) : collect());
        });

        Event::listen(NotificationSent::class, VersandProtokollieren::class);
        Event::listen(NotificationFailed::class, VersandProtokollieren::class);

        // Admin darf alles.
        Gate::before(fn (User $user) => $user->istAdmin() ? true : null);

        Gate::define('admin', fn (User $user) => false);

        Gate::define('schreiben', fn (User $user) => ! $user->istLesend());

        Gate::define('ressort-verwalten', fn (User $user, Ressort $ressort) => $user->leitetRessort($ressort));

        Gate::define('ressort-mitarbeiten', fn (User $user, Ressort $ressort) => ! $user->istLesend() && $user->gehoertZuRessort($ressort));

        Gate::define('vorlagen-verwalten', fn (User $user) => $user->ressorts->contains(fn ($r) => $r->pivot->rolle === 'leitung'));

        Gate::define('ausgaben-verwalten', fn (User $user) => false);

        Gate::define('aufgabe-bearbeiten', function (User $user, Aufgabe $aufgabe) {
            if ($user->istLesend()) {
                return false;
            }

            return $aufgabe->verantwortlich_id === $user->id
                || $aufgabe->begleitung_id === $user->id
                || $user->gehoertZuRessort($aufgabe->ressort_id);
        });

        Gate::define('aufgabe-verwalten', fn (User $user, Aufgabe $aufgabe) => $user->leitetRessort($aufgabe->ressort_id));

        // Loeschen: Ressortleitung und Admin, dazu die verantwortliche Person ihre eigenen Aufgaben
        Gate::define('aufgabe-loeschen', fn (User $user, Aufgabe $aufgabe) => ! $user->istLesend()
            && ($user->leitetRessort($aufgabe->ressort_id) || $aufgabe->verantwortlich_id === $user->id));
    }
}
