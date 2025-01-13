@extends("layouts.base")

@section("content")

<section style="text-align: center;">
    <h1 style="font-size: 3em;">
        {{ $exception->getStatusCode() }} | @yield("title")
    </h1>

    <p>
        @yield("description")
    </p>

    @if (userIs("technical"))
    <p class="ghost">
        {{ $exception->getMessage() }}
    </p>
    @endif
</section>

@endsection
