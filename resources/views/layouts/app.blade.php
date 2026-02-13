<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? config('app.name') }}</title>

    <!-- Bootstrap CSS via Cloudflare CDN (cdnjs) -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Bootstrap Icons via Cloudflare CDN -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Font Awesome via Cloudflare CDN -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Tes CSS et JS via Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Livewire Styles -->
    @livewireStyles

    <link rel="stylesheet" href="{{ asset('css/layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/produit.css') }}">
</head>

<body class="bg-light">

    <div class="container">
        <!-- Messages de succès -->
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        <!-- Slot Livewire / Contenu de la page -->
        {{ $slot }}
    </div>

    <!-- Bootstrap JS Bundle via Cloudflare CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <!-- <script>
document.addEventListener('DOMContentLoaded', function () {

    if (!window.Echo) {
        console.error('Echo not loaded yet');
        return;
    }

    window.Echo.channel('products')
        .listen('.product.updated', (e) => {
            console.log('Produit mis à jour:', e.product);
        });

});
</script> -->


    <!-- Livewire Scripts -->
    @livewireScripts

</body>

</html>