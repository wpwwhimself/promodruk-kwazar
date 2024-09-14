<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OfferController extends Controller
{
    public function list()
    {
        $offers = Offer::orderByDesc("updated_at");

        if (!userIs("Administrator")) {
            $offers = $offers->where("creator_user_id", Auth::user()->id);
        }

        $offers = $offers->get();

        return view('pages.offers.list', compact(
            "offers",
        ));
    }

    public function edit(int $offer_id = null)
    {
        $offer = $offer_id
            ? Offer::find($offer_id)
            : null;

        if ($offer?->creator->id != Auth::user()->id && !userIs("Administrator")) {
            abort(403);
        }

        return view('pages.offers.edit', compact(
            "offer",
        ));
    }
}
