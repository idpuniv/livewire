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
    

    <div class="px-0 px-md-3">
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






    <!-- Livewire Scripts -->
    @livewireScripts


    {{-- Conteneur d'alertes --}}
    <div id="alertContainer" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999;"></div>

    {{-- ... le reste --}}

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('showAlert', (data) => {
                const type = data[0].type || 'info';
                const message = data[0].message;
                
                const alertDiv = document.createElement('div');
                alertDiv.className = `custom-alert custom-alert-${type}`;
                alertDiv.innerHTML = `
                    <div class="custom-alert-icon">
                        ${type === 'success' ? '✓' : type === 'error' ? '✗' : '⚠'}
                    </div>
                    <div class="custom-alert-message">${message}</div>
                    <button class="custom-alert-close" onclick="this.parentElement.remove()">×</button>
                `;
                
                document.getElementById('alertContainer').appendChild(alertDiv);
                
                setTimeout(() => {
                    if (alertDiv.parentElement) {
                        alertDiv.remove();
                    }
                }, 5000);
            });
        });
    </script>

    <style>
        .custom-alert {
            margin-bottom: 10px;
            min-width: 300px;
            padding: 12px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideDown 0.3s ease;
        }
        .custom-alert-success { background: #d4edda; color: #155724; }
        .custom-alert-error { background: #f8d7da; color: #721c24; }
        .custom-alert-warning { background: #fff3cd; color: #856404; }
        .custom-alert-info { background: #d1ecf1; color: #0c5460; }
        .custom-alert-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            opacity: 0.5;
        }
        .custom-alert-close:hover { opacity: 1; }
        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>

</body>

</html>