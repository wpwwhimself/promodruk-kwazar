@extends("layouts.app")
@section("title", implode(" | ", [$product->name ?? "Nowa oferta", "Edycja oferty"]))

@section("content")

<x-app-section title="Odbiorca">
    <div class="table" style="--col-count: {{ 3 + userIs("Administrator") }};">
        <span class="head">Kontrahent</span>
        @if (userIs("Administrator")) <span class="head">Pracownik</span> @endif
        <span class="head">Ostatnia zmiana</span>
        <span class="head"></span>

        <hr>

        @forelse ($offers as $offer)

        <a href="{{ route("offers.show", $offer->id) }}">{{ $offer->name }}"</a>
        @if (userIs("Administrator")) <span>{{ $offfer->creator->name }}</span> @endif
        <span>{{ $offer->updated_at->diffForHumans() }}</span>
        <span>
            <a class="button" href="{{ route("offers.edit", $offer->id) }}">Edytuj</a>
        </span>

        @empty

        <span class="ghost">Brak ofert</span>

        @endforelse
    </div>
</x-app-section>

@endsection
