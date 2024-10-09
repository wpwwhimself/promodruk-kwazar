@extends("layouts.app")
@section("title", "Szczegóły oferty")

@section("content")

<form action="{{ route('offers.prepare') }}" method="post"
    class="flex-down"
    onsubmit="event.preventDefault(); submitWithLoader()"
>
    @csrf
    <input type="hidden" name="user_id" value="{{ Auth::id() }}">

    <x-app.loader text="Przeliczanie" />
    <x-app.dialog title="Wybierz kalkulację" />

    <x-app.section title="Konfiguracja" class="sticky">
        <x-slot:buttons>
            <button type="submit">Przelicz wycenę</button>
            <span class="button" onclick="prepareSaveOffer()">Zapisz i zakończ</button>
        </x-slot:buttons>

        <div class="flex-right center middle barred-right">
            <div>
                <x-multi-input-field
                    name="product"
                    label="Dodaj produkt do listy"
                    empty-option="Wybierz..."
                    :options="[]"
                />
            </div>

            <div class="flex-right center middle">
                <span class="button" onclick="toggleDiscounts(this)">Rabaty</span>
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
        </div>

        <div id="discounts-wrapper" class="hidden flex-right center">
            <x-user.discounts :user="Auth::user()" field-name="discounts" />
        </div>
    </x-app.section>

    <div id="positions" class="flex-down"></div>
</form>

<script defer>
const form = document.forms[0]
const submitWithLoader = () => {
    toggleLoader()
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
            suppliers: {!! json_encode($suppliers->pluck("name")) !!}
        }),
    },
    width: "20em",
}).on("select2:select", function(e) {
    submitWithLoader()
    $(this).val(null).trigger("change")
})

//?// discounts //?//

const toggleDiscounts = (btn) => {
    document.querySelector("#discounts-wrapper").classList.toggle("hidden")
    btn.classList.toggle("active")
}

//?// quantities //?//

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
    toggleDialog(
        "main-dialog",
        "Wybierz kalkulację",
        [...availableCalculations, "new"]
            .map((calc) => `<span class="button"
                onclick="addCalculation('${product_id}', '${calc}', '${marking}')"
            >
                ${calc == "new" ? "Nowa kalkulacja" : `Kalkulacja nr ${calc + 1}`}
            </span>`)
            .join("")
    )
}

const addCalculation = (product_id, calculation, marking) => {
    const container = document.querySelector(`.calculations[data-product-id="${product_id}"]`)
    calculation = (calculation == "new") ? container.dataset.count : calculation
    document.querySelector(`.calculations[data-product-id="${product_id}"]`)
        .append(fromHTML(`<input type="hidden" name="calculations[${product_id}][${calculation}][][code]" value="${marking}" />`))
    toggleDialog("main-dialog")
    submitWithLoader()
}

const deleteCalculation = (product_id, calc_id, code) => {
    document.querySelector(`input[name^="calculations[${product_id}][${calc_id}]"][value="${code}"]`).remove()
    submitWithLoader()
}

//?// save offer //?//
const prepareSaveOffer = () => {
    toggleDialog(
        "main-dialog",
        "Zapisz ofertę",
        `<x-input-field type="text"
            name="offer_name" label="Nazwa oferty"
        />`,
        function() {
            form.action = "{{ route('offers.save') }}"
            form.submit()
        }
    )
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
