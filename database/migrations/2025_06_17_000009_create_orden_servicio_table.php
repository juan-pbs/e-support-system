<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Si vas a correr migrate:fresh, normalmente la tabla no existe.
        // Aun así, por seguridad:
        if (Schema::hasTable('orden_servicio')) {
            Schema::drop('orden_servicio');
        }

        // Opcional si quieres evitar problemas de orden de creación con FKs:
        Schema::disableForeignKeyConstraints();

        Schema::create('orden_servicio', function (Blueprint $table) {
            $table->bigIncrements('id_orden_servicio');

            // ===== Relaciones principales =====
            $table->unsignedBigInteger('id_cotizacion')->nullable(); // FK -> cotizaciones.id_cotizacion
            $table->unsignedBigInteger('id_cliente');                 // FK -> cliente.clave_cliente
            $table->unsignedBigInteger('id_tecnico')->nullable();     // FK -> users.id (legacy)
            $table->unsignedBigInteger('autorizado_por')->nullable(); // FK -> users.id (quien autoriza)

            // ===== Datos de la orden =====
            $table->date('fecha_orden')->nullable();
            $table->string('estado', 50)->default('en proceso')->index(); // texto + índice
            $table->enum('prioridad', ['Baja','Media','Alta','Urgente'])->default('Baja')->index();
            $table->date('fecha_finalizacion')->nullable();

            // ===== Servicio / descripciones =====
            $table->string('servicio')->nullable();
            $table->text('descripcion_servicio')->nullable();
            $table->text('descripcion')->nullable();

            // ===== Costos / totales =====
            $table->decimal('precio', 12, 2)->default(0);
            $table->decimal('costo_operativo', 12, 2)->nullable();
            $table->decimal('impuestos', 12, 2)->default(0);

            $table->string('precio_escrito')->nullable();
            $table->text('materiales')->nullable();
            $table->text('condiciones_generales')->nullable(); // ya en TEXT desde el inicio

            // ===== Firma/acta heredadas =====
            $table->longText('firma_conformidad')->nullable(); // si antes guardabas base64 o texto

            // ===== Pago / tipo de orden / archivos =====
            $table->enum('tipo_pago', ['efectivo','transferencia','tarjeta','credito_cliente'])
                  ->default('efectivo');
            $table->enum('tipo_orden', ['compra','servicio_simple','servicio_proyecto','salida_manual'])
                  ->default('servicio_simple')
                  ->index();
            $table->string('archivo_pdf')->nullable();

            // ===== Moneda / TC =====
            $table->string('moneda', 3)->default('MXN')->index();
            $table->decimal('tasa_cambio', 10, 4)->default(1);

            // ===== Acta de conformidad =====
            $table->string('acta_pdf_path')->nullable();
            $table->string('acta_pdf_hash', 64)->nullable();
            $table->timestamp('acta_firmada_at')->nullable();
            $table->enum('acta_estado', ['borrador','firmada'])->nullable();

            // Datos del acta en borrador/firmada (JSON)
            $table->json('acta_data')->nullable();

            // ===== Firmas dedicadas =====
            $table->string('firma_resp_path')->nullable();
            $table->string('firma_emp_path')->nullable();
            $table->longText('firma_resp_data')->nullable();
            $table->longText('firma_emp_data')->nullable();

            $table->timestamps();

            // ===== Claves foráneas =====
            // Ten en cuenta los nombres y PKs reales de tus tablas relacionadas.
            $table->foreign('id_cotizacion')
                  ->references('id_cotizacion')->on('cotizaciones')
                  ->nullOnDelete();

            // Aquí se asume que cliente.clave_cliente es BIGINT (como has venido usando).
            $table->foreign('id_cliente')
                  ->references('clave_cliente')->on('cliente')
                  ->cascadeOnDelete();

            $table->foreign('id_tecnico')
                  ->references('id')->on('users')
                  ->nullOnDelete();

            $table->foreign('autorizado_por')
                  ->references('id')->on('users')
                  ->nullOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Si agregaste CHECKs/constraints manuales, podrías intentar retirarlos aquí.
        Schema::dropIfExists('orden_servicio');
    }
};
