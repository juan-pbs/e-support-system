<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <link rel="icon" href="/images/ico.png" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="h-screen w-full relative overflow-hidden">

    <!-- Imagen de fondo -->
    <div class="absolute inset-0 bg-cover bg-center z-0" style="background-image: url('/images/home.jpg');"></div>

    <!-- Capa con blur y oscurecimiento -->
    <div class="absolute inset-0 bg-black bg-opacity-30 backdrop-blur-md z-5"></div>

    <!-- Contenido principal -->
    <div class="relative z-20 flex items-center justify-center w-full h-full px-4 lg:px-32">
        <!-- Área de Login -->
        <div class="bg-white bg-opacity-80 rounded-xl p-8 w-full max-w-md shadow-lg mr-10">
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Email -->
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700">Correo electrónico:</label>
                    <input id="email" type="email" name="email" required autofocus
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 h-12">
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700">Contraseña:</label>
                    <input id="password" type="password" name="password" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 h-12">
                </div>

                <!-- Recordarme -->
                <div class="mb-4 flex items-center">
                    <input type="checkbox" name="remember" id="remember" class="mr-2">
                    <label for="remember" class="text-sm text-gray-600">Recordarme</label>
                </div>

                <!-- Botón -->
                <div>
                    <button type="submit"
                        class="w-full bg-indigo-900 text-white py-2 px-4 rounded hover:bg-indigo-800 transition">
                        Iniciar sesión
                    </button>
                </div>
            </form>
        </div>

        <!-- Imagen del logo -->
        <div class="hidden lg:block">
            <img src="/images/logo2.png" alt="eSupport Logo" class="max-w-sm">
        </div>
    </div>

</body>
</html>
