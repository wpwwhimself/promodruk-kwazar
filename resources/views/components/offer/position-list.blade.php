@props([
    "products",
    "user",
    "edited" => [],
    "showPricesPerUnit" => false,
    "showGrossPrices" => false,
])

@php
$vat_coef = 1.23;
@endphp

@foreach ($products as $product)
<x-app.section
    title="{!! $product['name'] !!} ({{ $product['variant_name'] ?? $product['original_color_name'] }})"
    :subtitle="$product['id']"
    class="product flex-down {{ $product['missing'] ?? false ? 'product-missing ghost' : '' }}"
>
    <x-slot:buttons>
        <div class="flex-right middle barred-right">
            @if ($showStocks && ($product["stock"] ?? false))
            <div class="flex-down center" style="gap: 0;">
                <span>
                    <span title="Stan magazynowy">📦</span>
                    {{ $product["stock"]["current_stock"] ?? 0 }}
                </span>
                @if ($product["stock"]["future_delivery_amount"])
                <span>
                    <span title="Przewidywana dostawa">🚚</span>
                    {{ $product["stock"]["future_delivery_date"] }}: {{ $product["stock"]["future_delivery_amount"] }}
                </span>
                @endif
            </div>
            @endif

            @if ($product["quantities"] && !($product["missing"] ?? false))

            @if ($product["calculations"])
            <div>
                <strong class="accent">Kalkulacje: {{ count($product['calculations']) }}</strong>
            </div>
            @endif

            <div>
                <span>Ilości: <strong>{{ implode("/", $product["quantities"]) }}</strong></span>
            </div>

            <div class="flex-right">
                <span class="button" onclick="showQuantities(this.closest('section'))">Ilości</span>

                <x-input-field type="checkbox"
                    name="show_ofertownik_link[{{ $product['id'] }}]"
                    label="Dodaj link"
                    value="1"
                    :checked="$product['show_ofertownik_link'] ?? false"
                    onchange="submitWithLoader()"
                />
            </div>
            @endif

            <div class="flex-right">
                @unless ($product["missing"] ?? false)
                    @if ($product["quantities"])
                    <input type="checkbox" name="edited[]" class="hidden" value="{{ $product['id'] }}" {{ in_array($product["id"], $edited) ? "checked" : "" }}>
                    <span class="button" role="edit-button" onclick="makeEditable(this.closest('section'))">{{ in_array($product["id"], $edited) ? "Zamknij": "Edytuj" }}</span>
                    @else
                    <span class="button hidden" role="add-button" onclick="submitWithLoader()">Dodaj</span>
                    @endif
                @endunless

                <span class="button danger" onclick="deleteProductFromOffer(this.closest('section'))">Usuń</span>
            </div>
        </div>
    </x-slot:buttons>

    @if ($product["missing"] ?? false)
    <x-slot:midsection>
        <strong class="danger" style="font-size: 1.8em;">USUNIĘTY</strong>
        <input type="hidden" name="missing_products[]" value="{{ $product['id'] }}">
    </x-slot:midsection>
    @endif

    <input type="hidden" name="product_ids[]" value="{{ $product['id'] }}">

    <div class="{{ implode(" ", array_filter([
        "flex-right",
        "center",
        "middle",
        !$product["quantities"] ?: "hidden",
    ])) }}">
        <x-input-field type="number"
            name="quantities_maker[{{ $product['id'] }}]" label="Dodaj ilość"
            data-product="{{ $product['id'] }}"
            min="0" step="1"
        />
        <div class="quantities flex-right center middle"></div>
    </div>

    @if ($product["quantities"])
    <div role="prices" class="{{ implode(" ", array_filter([
        "flex-down",
        in_array($product["id"], $edited) ?: "hidden",
    ]))}}">
        <div class="flex-right stretch">
            <div class="flex-right">
                <div class="flex-right">
                    <span>Wartość produktu netto{{ $showGrossPrices ? " (brutto)" : "" }}:</span>
                    <ul>
                        @foreach ($product["quantities"] as $qty)
                        <li>
                            {{ $qty }} szt:
                            @php $prc = $product["price"] * $qty; @endphp
                            <strong>{{ as_pln($prc) }}</strong>
                            @if ($showGrossPrices)
                            <strong class="accent">({{ as_pln($prc * $vat_coef) }})</strong>
                            @endif

                            @if ($showPricesPerUnit)
                            @php $prc = $product["price"]; @endphp
                            <small class="ghost">
                                {{ as_pln($prc) }}/szt.
                                @if ($showGrossPrices) ({{ as_pln($prc * $vat_coef) }}/szt.) @endif
                            </small>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                </div>

                @if ($product["manipulation_cost"])
                <div class="flex-right">
                    <span>+ koszty manipulacyjne:</span>
                    <ul>
                        @foreach ($product["quantities"] as $qty)
                        <li>
                            {{ $qty }} szt:
                            @php $prc = ($product["price"] + $product["manipulation_cost"]) * $qty; @endphp
                            <strong>{{ as_pln($prc) }}</strong>
                            @if ($showGrossPrices)
                            <strong class="accent">({{ as_pln($prc * $vat_coef) }})</strong>
                            @endif

                            @if ($showPricesPerUnit)
                            @php $prc = $product["price"] + $product["manipulation_cost"]; @endphp
                            <small class="ghost">
                                {{ as_pln($prc) }}/szt.
                                @if ($showGrossPrices) ({{ as_pln($prc * $vat_coef) }}/szt.) @endif
                            </small>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <span class="button" style="align-self: start;"
                    @popper(Dodaj do kalkulacji)
                    onclick="openCalculationsPopup(
                        '{{ $product['id'] }}',
                        {!! json_encode(array_keys($product['calculations'] ?? [])) !!},
                        '!',
                        'pin_product'
                    )"
                >
                    +
                </span>

                <x-input-field type="number"
                    name="surcharge[{{ $product['id'] }}][product]" label="Nadwyżka (%)"
                    min="0" step="0.1"
                    :value="$product['surcharge']"
                />
            </div>

            <div class="calculations" data-product-id="{{ $product['id'] }}" data-count="{{ count($product["calculations"]) }}">
                @foreach ($product["calculations"] as $i => $calculation)
                <h3>Kalkulacja nr {{ $i + 1 }}</h3>
                <div class="grid" style="--col-count: 2;">
                    <div class="flex-down">
                        @if (
                            $calculation["pin_product"] ?? false
                            && !$calculation["items"]
                            && !$calculation["additional_services"]
                        )
                        <span>
                            <input type="hidden"
                                name="calculations[{{ $product['id'] }}][{{ $i }}][pin_product]"
                                value="!"
                            />
                            <span class="ghost">Sam produkt (przypięty)</span>

                            <span class="button" onclick="deleteCalculation('{{ $product['id'] }}', {{ $i }}, '!', 'pin_product')">×</span>
                        </span>
                        @endif

                        @if ($calculation["items"])
                        <h4>Znakowania</h4>
                        @foreach ($calculation["items"] as $item_i => ["code" => $code, "marking" => $marking])
                        <span>
                            <input type="hidden"
                                name="calculations[{{ $product['id'] }}][{{ $i }}][items][{{ $item_i }}][code]"
                                value="{{ $code }}"
                            />
                            @if ($marking)
                            {{ $marking["position"] }} - {{ $marking["technique"] }}
                                @if (Str::contains($code, "_")) ({{ Str::afterLast($code, "_") }}) @endif
                            @else
                            Bez nadruku:
                            @endif

                            <span class="button" onclick="deleteCalculation('{{ $product['id'] }}', {{ $i }}, '{{ $code }}')">×</span>
                        </span>
                        @endforeach
                        @endif

                        @if ($calculation["additional_services"] ?? false)
                        <h4>Usługi dodatkowe</h4>
                        @foreach ($calculation["additional_services"] as $service)
                        <span>
                            <input type="hidden"
                                name="calculations[{{ $product['id'] }}][{{ $i }}][additional_services][][code]"
                                value="{{ $service["id"] ?? null }}"
                            />
                            {{ $service["label"] }}
                            <span class="button" onclick="deleteCalculation('{{ $product['id'] }}', {{ $i }}, '{{ $service['id'] ?? null }}', 'additional_services')">×</span>
                        </span>
                        @endforeach
                        @endif
                    </div>
                    <ul>
                        @foreach ($calculation["summary"] as $qty => $sum)
                        <li>
                            {{ $qty }} szt.:
                            @php $prc = $sum; @endphp
                            <strong>{{ as_pln($prc) }}</strong>
                            @if ($showGrossPrices)
                            <strong class="accent">({{ as_pln($prc * $vat_coef) }})</strong>
                            @endif

                            @if ($showPricesPerUnit)
                            @php $prc = $sum / $qty; @endphp
                            <small class="ghost">
                                {{ as_pln($prc) }}/szt.
                                @if ($showGrossPrices) ({{ as_pln($prc * $vat_coef) }}/szt.) @endif
                            </small>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </div>
        </div>

        <div role="markings">
            @foreach ($product["markings"] as $position_name => $techniques)
            <h3 style="grid-column: span 2">{{ $position_name }}</h3>

            <div class="flex-down">
                @foreach ($techniques as $t)
                <div class="offer-position flex-right stretch top">
                    <div class="data flex-right">
                        @foreach (array_filter([0, $product["price"] + $product["manipulation_cost"]], fn ($price) => !is_null($price)) as $product_price)
                        <div class="grid" style="--col-count: 1;">
                            <h4>
                                @if ($product_price == 0)
                                {{ $t["technique"] }}
                                <small class="ghost">{{ $t["print_size"] }}</small>
                                @else
                                <small class="ghost">Cena: produkt + znakowanie</small>
                                @endif
                            </h4>

                            @foreach ($t["main_price_modifiers"] ?? ["" => null] as $label => $mod_data)
                            <div class="flex-right">
                                @if (!empty($mod_data)) <span>{{ $label }}</span> @endif
                                <ul>
                                    @foreach ($t["quantity_prices"] as $requested_quantity => $price_data)
                                    @php
                                    $price_per_unit = $price_data["price"];
                                    $modifier = $mod_data["mod"] ?? "*1";
                                    $mod_price_per_unit = eval("return $price_per_unit $modifier;");
                                    $mod_setup = ($mod_data["include_setup"] ?? false)
                                        ? eval("return $t[setup_price] ".(isset($mod_data["setup_mod"]) ? $mod_data["setup_mod"] : $modifier).";")
                                        : $t["setup_price"];
                                    @endphp
                                    <li>
                                        {{ $requested_quantity }} szt:
                                        @php
                                        $prc = ($price_data["flat"] ?? false)
                                            ? ($mod_setup + $mod_price_per_unit + $product_price * $requested_quantity)
                                            : ($mod_setup + ($mod_price_per_unit + $product_price) * $requested_quantity);
                                        @endphp
                                        <strong>{{ as_pln($prc) }}</strong>
                                        @if ($showGrossPrices)
                                        <strong class="accent">({{ as_pln($prc * $vat_coef) }})</strong>
                                        @endif

                                        @if ($showPricesPerUnit)
                                        @php $prc = $mod_price_per_unit + $product_price; @endphp
                                        <small class="ghost">
                                            {{ as_pln($prc) }}/szt.
                                            @if ($showGrossPrices) ({{ as_pln($prc * $vat_coef) }}/szt.) @endif
                                            @if ($t["setup_price"])
                                                + przygotowanie
                                                {{ as_pln($mod_setup) }}
                                                @if ($showGrossPrices) ({{ as_pln($mod_setup * $vat_coef) }}) @endif
                                            @endif
                                        </small>
                                        @endif
                                    </li>
                                    @endforeach
                                </ul>

                                @if ($product_price == 0)
                                <span class="button" style="align-self: start;"
                                    @popper(Dodaj do kalkulacji)
                                    onclick="openCalculationsPopup(
                                        '{{ $product['id'] }}',
                                        {!! json_encode(array_keys($product['calculations'] ?? [])) !!},
                                        '{{ !empty($mod_data) ? $t['id'].'_'.$label : $t['id'] }}'
                                    )"
                                >
                                    +
                                </span>
                                @endif
                            </div>
                            @endforeach
                        </div>
                        @endforeach

                        <x-input-field type="number"
                            name="surcharge[{{ $product['id'] }}][{{ $t['position'] }}][{{ $t['technique'] }}]" label="Nadwyżka (%)"
                            min="0" step="0.1"
                            :value="$t['surcharge']"
                        />
                    </div>

                    <div class="images flex-right">
                        @if ($t["images"])
                        <img class="thumbnail"
                            src="{{ $t["images"][0] }}"
                            {{ Popper::pop("<img src='" . $t["images"][0] . "' />") }}
                        />
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endforeach
        </div>

        @if ($product["additional_services"] ?? false)
        <div role="additional-services">
            <h3>Usługi dodatkowe</h3>

            <div class="flex-down">
                @foreach ($product["additional_services"] as $service)
                <div class="offer-position flex-right stretch top">
                    <div class="data flex-right">
                        <div class="grid" class="--col-count: 1;">
                            <h4>{{ $service["label"] }}</h4>

                            <div class="flex-right">
                                <ul>
                                    @foreach ($product["quantities"] as $qty)
                                    <li>
                                        {{ $qty }} szt:
                                        @php $prc = $service["price_per_unit"] * $qty; @endphp
                                        <strong>{{ as_pln($prc) }}</strong>
                                        @if ($showGrossPrices)
                                        <strong class="accent">({{ as_pln($prc * $vat_coef) }})</strong>
                                        @endif

                                        @if ($showPricesPerUnit)
                                        @php $prc = $service["price_per_unit"]; @endphp
                                        <small class="ghost">
                                            {{ as_pln($prc) }}/szt.
                                            @if ($showGrossPrices) ({{ as_pln($prc * $vat_coef) }}/szt.) @endif
                                        </small>
                                        @endif
                                    </li>
                                    @endforeach
                                </ul>

                                <span class="button" style="align-self: start;"
                                    @popper(Dodaj do kalkulacji)
                                    onclick="openCalculationsPopup(
                                        '{{ $product['id'] }}',
                                        {!! json_encode(array_keys($product['calculations'] ?? [])) !!},
                                        '{{ $service['id'] ?? null }}',
                                        'additional_services'
                                    )"
                                >
                                    +
                                </span>
                            </div>
                        </div>

                        <x-input-field type="number"
                            name="surcharge[{{ $product['id'] }}][additional_services][{{ $service['id'] ?? null }}]" label="Nadwyżka (%)"
                            min="0" step="0.1"
                            :value="$service['surcharge'] ?? null"
                        />
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    @endif
</x-app.section>
@endforeach

<script>
$("input[name^=quantities_maker]").on("change keypress", function(e) {
    if (e.type === "keypress" && e.which !== 13) return;
    e.preventDefault()
    _appendQuantity($(this), $(this).val())
    revealAddButton(this.closest('section'))
    $(this).val(null)
})
// init quantities
@if ($products)
@env (["local", "stage"])
console.debug({!! json_encode($products) !!})
@endenv
quantities = {!! json_encode($products->mapWithKeys(fn($p) => [$p["id"] => $p["quantities"]])) !!}
Object.keys(quantities).forEach(product_id => {
    quantities[product_id].forEach(qty => _appendQuantity($(`input[data-product="${product_id}"]`), qty))
})
@endif

$(".product input[name^=surcharge]").on("change", function(e) {
    $(`input[name=global_surcharge]`).val(null)
})
// init global surcharge (if no products available, show default for user)
@if (!collect($products)->pluck("quantities")->flatten()->count())
    $("input[name=global_surcharge]").val("{{ $user->global_surcharge }}")
@endif
</script>
