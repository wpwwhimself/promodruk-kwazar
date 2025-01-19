<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OfferController extends Controller
{
    public function list()
    {
        $offers = (userIs("offer-master"))
            ? Offer::orderByDesc("created_at")
                ->paginate(25)
            : Offer::where("created_by", Auth::user()->id)
                ->orderByDesc("created_at")
                ->paginate(25);
        $document_formats = ["docx"]; //array_keys(DocumentOutputController::FORMATS);

        return view("pages.offers.list", compact(
            "offers",
            "document_formats",
        ));
    }

    public function offer($id = null)
    {
        $suppliers = Supplier::orderBy("name")->get();
        $offer = $id
            ? Offer::find($id)
            : null;

        return view("pages.offers.offer", compact(
            "offer",
            "suppliers",
        ));
    }

    public function prepare(Request $rq)
    {
        $products = $this->prepareProducts($rq);
        $user = Auth::user() ?? User::find($rq->user_id);
        $showPricesPerUnit = $rq->has("show_prices_per_unit");

        return view("components.offer.position-list", compact("products", "user", "showPricesPerUnit"));
    }

    public function save(Request $rq)
    {
        $products = $this->prepareProducts($rq);
        Offer::updateOrCreate(
            ["id" => $rq->offer_id],
            [
                "name" => $rq->offer_name ?? now()->format("Y-m-d H:i"),
                "positions" => $products,
            ]
        );

        return redirect()->route("offers.list")->with("success", "Oferta utworzona");
    }

    //////////////////////////////////////

    private function prepareProducts(Request $rq): Collection
    {
        $user = Auth::user() ?? User::find($rq->user_id);
        $suppliers = Supplier::all();

        $discounts = $rq->discounts;

        $products = Http::post(env("MAGAZYN_API_URL") . "products/by/ids", [
            "ids" => array_merge($rq->product_ids ?? [], [$rq->product]),
            "include" => ["markings"],
        ])
            ->collect();

        $products = $products
            // get only what's necessary
            ->map(fn ($p) => array_filter($p, fn($k) => !in_array($k, [
                "original_sku",
                "tabs",
                "images",
                "thumbnails",
                "color",
            ]), ARRAY_FILTER_USE_KEY))
            // filtering marking prices to given quantities
            ->map(fn ($p) => [
                ...$p,
                "markings" => collect($p["markings"])
                    ->map(fn ($m) => collect([
                        ...$m,
                        "surcharge" => $rq->global_surcharge ?? $rq->surcharge[$p["id"]][$m["position"]][$m["technique"]] ?? $user->global_surcharge,
                    ]))
                    ->map(fn ($m) => [
                        ...$m,
                        "quantity_prices" => collect($rq->quantities[$p["id"]] ?? [])
                            ->sort()
                            ->mapWithKeys(fn ($q) => [
                                $q => collect($m["quantity_prices"])
                                    ->last(fn ($data, $pricelist_quantity) => $pricelist_quantity <= $q)
                            ])
                            ->map(fn ($data, $quantity) => [
                                ...$data,
                                "price" => round(
                                    $data["price"]
                                    * (in_array("markings_discount", $suppliers->firstWhere("name", $p["product_family"]["source"])->allowed_discounts ?? [])
                                        && ($m["enable_discount"] ?? true)
                                        ? (1 - $discounts[$p["product_family"]["source"]]["markings_discount"] / 100)
                                        : 1
                                    )
                                    / (1 - $m["surcharge"] / 100)
                                , 2),
                            ])
                            ->toArray(),
                    ])
                    ->groupBy("position"),
                "quantities" => collect($rq->quantities[$p["id"]] ?? [])
                    ->sort()
                    ->values()
                    ->toArray(),
                "surcharge" => $rq->global_surcharge ?? $rq->surcharge[$p["id"]]["product"] ?? $user->global_surcharge,
                "show_ofertownik_link" => $rq->has("show_ofertownik_link.$p[id]"),
            ])
            ->map(fn ($p) => [
                ...$p,
                "price" => round(
                    $p["price"]
                    * (in_array("products_discount", $suppliers->firstWhere("name", $p["product_family"]["source"])->allowed_discounts ?? [])
                        && ($p["enable_discount"] ?? true)
                        ? (1 - $discounts[$p["product_family"]["source"]]["products_discount"] / 100)
                        : 1
                    )
                    / (1 - $p["surcharge"] / 100)
                , 2),

            ])
            ->map(fn ($p) => [
                ...$p,
                "calculations" => collect($rq->calculations[$p["id"]] ?? [])
                    ->map(fn ($calc) => [
                        "items" => collect($calc)
                            ->map(fn ($calc_item) => [
                                ...$calc_item,
                                "marking" => collect($p["markings"])
                                    ->flatten(1)
                                    ->firstWhere("id", Str::beforeLast($calc_item["code"], "_")),
                            ])
                            ->toArray(),
                    ])
                    ->map(fn ($calc) => [
                        ...$calc,
                        "summary" => collect($p["quantities"])
                            ->mapWithKeys(function ($qty) use ($p, $calc) {
                                $product_price = $p["price"] + $p["manipulation_cost"];
                                $markings_price = 0;

                                foreach ($calc["items"] as ["code" => $code, "marking" => $marking]) {
                                    $price_data = $marking["quantity_prices"][$qty];
                                    $mod_data = $marking["main_price_modifiers"][Str::afterLast($code, "_")] ?? [];

                                    $price_per_unit = $price_data["price"];
                                    $modifier = $mod_data["mod"] ?? "*1";
                                    $mod_price_per_unit = eval("return $price_per_unit $modifier;");
                                    $mod_setup = ($mod_data["include_setup"] ?? false)
                                        ? eval("return $marking[setup_price] $modifier;")
                                        : $marking["setup_price"];

                                    $added_marking_price = $mod_price_per_unit ?? 0;

                                    if (!($price_data["flat"] ?? false))
                                        $added_marking_price *= $qty;

                                    $markings_price += $mod_setup + $added_marking_price;
                                }
                                return [$qty => $markings_price + $product_price * $qty];
                            })
                            ->toArray(),
                    ])
                    ->toArray(),
            ]);

        return $products;
    }
}
