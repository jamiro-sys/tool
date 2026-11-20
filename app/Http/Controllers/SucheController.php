<?php

namespace App\Http\Controllers;

use App\Models\Aufgabe;
use App\Models\Kontakt;
use App\Models\Ressort;
use App\Models\Vorlage;
use App\Models\Wissen;
use Illuminate\Http\Request;

class SucheController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json(['treffer' => []]);
        }
        $like = '%'.$q.'%';
        $treffer = collect();

        Aufgabe::with('ausgabe')->where('titel', 'like', $like)->latest('faellig_am')->limit(6)->get()
            ->each(fn ($a) => $treffer->push(['typ' => 'Aufgabe', 'titel' => $a->titel, 'info' => $a->ausgabe->titel, 'url' => route('aufgaben.show', $a)]));
        Vorlage::with('ressort')->where('titel', 'like', $like)->limit(5)->get()
            ->each(fn ($v) => $treffer->push(['typ' => 'Jahresplan', 'titel' => $v->titel, 'info' => $v->ressort->name, 'url' => route('vorlagen.show', $v)]));
        Ressort::where('name', 'like', $like)->limit(3)->get()
            ->each(fn ($r) => $treffer->push(['typ' => 'Ressort', 'titel' => $r->name, 'info' => 'Steckbrief', 'url' => route('ressorts.show', $r)]));
        Kontakt::with('ressort')->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('firma', 'like', $like))->limit(5)->get()
            ->each(fn ($k) => $treffer->push(['typ' => 'Kontakt', 'titel' => $k->name, 'info' => $k->firma ?? $k->ressort?->name, 'url' => $k->ressort ? route('ressorts.show', [$k->ressort, 'tab' => 'kontakte']) : '#']));
        Wissen::with('ressort')->where(fn ($w) => $w->where('titel', 'like', $like)->orWhere('inhalt', 'like', $like))->limit(5)->get()
            ->each(fn ($w) => $treffer->push(['typ' => 'Wissen', 'titel' => $w->titel, 'info' => $w->ressort->name, 'url' => route('ressorts.show', [$w->ressort, 'tab' => 'wissen']).'#wissen-'.$w->id]));

        return response()->json(['treffer' => $treffer->values()]);
    }
}
