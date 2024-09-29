@extends("layouts.app")
@section("title", "Szczegóły oferty")

@section("content")

<x-app.loader text="Przeliczanie" />
<x-app.dialog title="Wybierz kalkulację" />

<form action="{{ route('offers.prepare') }}" method="post"
    class="flex-down"
    onsubmit="event.preventDefault(); submitWithLoader()"
>
    @csrf
    <input type="hidden" name="user_id" value="{{ Auth::id() }}">

    <section class="flex-right center middle sticky barred-right">
        <div>
            <x-multi-input-field
                name="product"
                label="Dodaj produkt do listy"
                empty-option="Wybierz..."
                :options="[]"
            />
        </div>

        <div class="flex-right center middle">
            @foreach ([
                "Rabat: prod. (%)" => "global_products_discount",
                "Rabat: znak. (%)" => "global_markings_discount",
            ] as $label => $name)
            <x-input-field type="number"
                :name="$name" :label="$label"
                min="0" step="0.1"
                :value="$discounts[$name] ?? Auth::user()->{$name}"
            />
            @endforeach

            <x-input-field type="number"
                name="global_surcharge" label="Nadwyżka (%)"
                min="0" step="0.1"
            />
        </div>

        <div>
            <x-input-field type="checkbox"
                name="show_prices_per_unit" label="Ceny/szt."
                value="1"
                :checked="false"
                onchange="submitWithLoader()"
            />
        </div>

        <div>
            <button type="submit">Przelicz wycenę</button>
        </div>
    </section>

    <div id="positions" class="flex-down"></div>
</form>

<script defer>
const form = document.forms[0]
const submitWithLoader = () => {
    $("#loader").removeClass("hidden")
    $.ajax({
        url: form.action,
        method: form.method,
        data: $(form).serialize(),
        success: (res) => {
            $("#loader").addClass("hidden")
            $("#positions").html(res)
        },
    })
}

$("select#product").select2({
    ajax: {
        url: "{{ env('MAGAZYN_API_URL') }}products/for-markings",
        data: (params) => ({
            q: params.term,
        }),
    },
    width: "20em",
}).on("select2:select", function(e) {
    submitWithLoader()
    $(this).val(null).trigger("change")
})

let _appendQuantity = (input, quantity) => {
    input.closest("section").find(".quantities").append(`<div {{ Popper::pop("Usuń ilość") }} onclick="this.remove()">
        <input type="hidden" name="quantities[${input.attr("data-product")}][]" value="${quantity}">
        <span class="button">${quantity}</span>
    </div>`)
}

let quantities = {}

const showQuantities = (section) => {
    section.querySelector(".quantities").parentElement.classList.toggle("hidden")
}

const deleteProductFromOffer = (section) => {
    section.remove()
    submitWithLoader()
}

//?// calculations //?//
const openCalculationsPopup = (product_id, availableCalculations, marking) => {
    document.querySelector("#dialog .contents").innerHTML = [...availableCalculations, "new"]
        .map((calc) => `<span class="button"
            onclick="addCalculation('${product_id}', '${calc}', '${marking}')"
        >
            ${calc == "new" ? "Nowa kalkulacja" : `Kalkulacja nr ${calc + 1}`}
        </span>`)
        .join("")
    toggleDialog()
}

const addCalculation = (product_id, calculation, marking) => {
    const container = document.querySelector(`.calculations[data-product-id="${product_id}"]`)
    calculation = (calculation == "new") ? container.dataset.count : calculation
    document.querySelector(`.calculations[data-product-id="${product_id}"]`)
        .append(fromHTML(`<input type="hidden" name="calculations[${product_id}][${calculation}][][code]" value="${marking}" />`))
    toggleDialog()
    submitWithLoader()
}

const deleteCalculation = (product_id, calc_id, code) => {
    document.querySelector(`input[name^="calculations[${product_id}][${calc_id}]"][value="${code}"]`).remove()
    submitWithLoader()
}
</script>

<style>
input[type=number] {
    width: 4.5em;
}
.grid {
    gap: 0;
}
</style>

@endsection
