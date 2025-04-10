<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield("title") | {{ config("app.name") }} | Promodruk</title>

        <link rel="stylesheet" href="{{ asset("css/app.css") }}">
        {{-- <link rel="stylesheet" href="{{ asset("css/ckeditor.css") }}?{{ time() }}"> --}}
        {{-- <link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/43.0.0/ckeditor5.css"> --}}
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

        @env (["local", "stage"])
        <style>
        :root {
            @env ("local")
            --test-color: #0f0;
            @endenv
            @env ("stage")
            --test-color: #ff0;
            @endenv
        }
        header {
            background: repeating-linear-gradient(45deg, var(--test-color), var(--test-color) 25px, #000 25px, #000 50px) !important;
        }
        </style>
        @endenv

        <script src="{{ asset("js/earlies.js") }}"></script>

        {{-- ckeditor stuff --}}
        <script type="importmap">
        {
            "imports": {
                "ckeditor5": "https://cdn.ckeditor.com/ckeditor5/43.0.0/ckeditor5.js",
                "ckeditor5/": "https://cdn.ckeditor.com/ckeditor5/43.0.0/"
            }
        }
        </script>
        {{-- <script type="module" src="{{ asset("js/ckeditor.js") }}?{{ time() }}"></script> --}}
        <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        @include("popper::assets")
    </head>
    <body>
        <div id="main-wrapper" class="flex-down">
            <header class="flex-right middle stretch">
                <h1>
                    @yield("title")
                    <small class="ghost">{{ config("app.name") }}</small>
                </h1>

                @auth
                <x-top-nav />
                @endauth
            </header>

            <main class="flex-down">
            @yield("content")
            </main>

            <footer class="flex-right stretch">
                <span class="flex-right barred-right">
                    @foreach (["success", "error"] as $status)
                    @if (session($status))
                    <x-popup-alert :status="$status" />
                    @endif
                    @endforeach
                </span>

                <span class="flex-right barred-right">
                    <a href="/">{{ config("app.name") }}</a>
                    {{-- <span>Projekt i wykonanie: <a href="https://wpww.pl/">Wojciech Przybyła</a></span> --}}
                    @if (Auth::check() && userIs("technical"))
                    <a href="https://github.com/wpwwhimself/promodruk-kwazar/tree/main/docs">Dokumentacja</a>
                    @endif
                </span>
            </footer>
        </div>

        <script src="{{ asset("js/app.js") }}"></script>
    </body>
</html>
