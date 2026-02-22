<section class="bg-white border border-gray-200 rounded-xl shadow-md p-6 space-y-6">
    <header>
        <h2 class="text-xl font-semibold text-blue-900">{{ __('Información de perfil') }}</h2>
        <p class="mt-1 text-sm text-gray-600">
            {{ __('Actualiza la información del perfil de tu cuenta y tu dirección de correo electrónico.') }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-5">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="puesto" :value="__('Puesto :')" />
            <x-input-label id="puesto" name="puesto" class="block w-full text-gray-700" :value="old('puesto', $user->puesto)" />
        </div>

        <div>
            <x-input-label for="name" :value="__('Nombre')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="contacto" :value="__('Teléfono de contacto')" />
            <x-text-input id="contacto" name="contacto" type="text" class="mt-1 block w-full"
            :value="old('contacto', $user->contacto)" maxlength="20" autocomplete="tel" />
            <x-input-error class="mt-2" :messages="$errors->get('contacto')" />
        </div>


        <div>
            <x-input-label for="email" :value="__('Correo electrónico')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-2 text-sm text-gray-800">
                    <p>
                        {{ __('Tu dirección de correo electrónico no está verificada.') }}
                        <button form="send-verification" class="underline text-blue-600 hover:text-blue-800">Haz clic aquí para reenviar el correo de verificación.</button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('Se ha enviado un nuevo enlace de verificación a tu correo electrónico.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Guardar') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm text-gray-600">
                    {{ __('Guardado.') }}
                </p>
            @endif
        </div>
    </form>
</section>
