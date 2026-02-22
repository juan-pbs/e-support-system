<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('firmas', function (Blueprint $table) {
            $table->id();
            // Firma ligada al usuario que genera la cotización (si usas auth). Si no hay auth, quedará NULL.
            $table->unsignedBigInteger('user_id')->nullable()->unique();

            // Campos cifrados en la app (se guardan como texto cifrado)
            $table->longText('nombre');                 // Crypt::encryptString(...)
            $table->longText('puesto')->nullable();     // Crypt::encryptString(...)
            $table->longText('empresa')->nullable();    // Crypt::encryptString(...)

            // Opcional: trazo o imagen de la firma
            $table->longText('firma_svg')->nullable();            // SVG cifrado (opcional)
            $table->longText('firma_image_base64')->nullable();   // PNG/JPG Base64 cifrado (opcional)

            $table->timestamps();

            // Si quieres forzar FK a users (actívalo sólo si existe tabla users siempre):
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firmas');
    }
};
