@extends('layouts.sidebar-navigation')

@section('content')
    <div class="py-8 flex justify-center">
        <div class="w-full max-w-4xl space-y-8 px-4">

            {{-- Información de perfil --}}
            @include('profile.partials.update-profile-information-form')

            {{-- Cambiar contraseña --}}
            @include('profile.partials.update-password-form')

            {{-- Eliminar cuenta --}}
            @include('profile.partials.delete-user-form')

        </div>
    </div>
@endsection
