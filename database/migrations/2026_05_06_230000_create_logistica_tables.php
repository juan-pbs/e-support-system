<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cliente_direcciones_logisticas')) {
            Schema::create('cliente_direcciones_logisticas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('clave_cliente');
                $table->string('alias', 120);
                $table->string('direccion_formateada');
                $table->string('place_id')->nullable();
                $table->decimal('latitud', 10, 7)->nullable();
                $table->decimal('longitud', 10, 7)->nullable();
                $table->text('referencia')->nullable();
                $table->boolean('activa')->default(true);
                $table->boolean('predeterminada')->default(false);
                $table->boolean('verificada_en_mapa')->default(false);
                $table->string('metodo_verificacion', 30)->nullable();
                $table->timestamps();

                $table->foreign('clave_cliente')
                    ->references('clave_cliente')
                    ->on('cliente')
                    ->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('jornadas_logisticas')) {
            Schema::create('jornadas_logisticas', function (Blueprint $table) {
                $table->id();
                $table->string('folio', 40)->unique();
                $table->string('nombre')->nullable();
                $table->date('fecha')->nullable();
                $table->string('estado', 30)->default('abierta')->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->text('observaciones')->nullable();
                $table->timestamps();

                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('movimientos_logisticos')) {
            Schema::create('movimientos_logisticos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('jornada_logistica_id')->nullable();
                $table->unsignedBigInteger('orden_servicio_id')->nullable();
                $table->unsignedBigInteger('clave_cliente')->nullable();
                $table->unsignedBigInteger('cliente_direccion_id')->nullable();
                $table->unsignedBigInteger('clave_proveedor')->nullable();
                $table->unsignedBigInteger('tecnico_id')->nullable();
                $table->string('tipo', 30)->index();
                $table->string('origen_tipo', 50)->nullable()->index();
                $table->unsignedBigInteger('origen_id')->nullable()->index();
                $table->string('contacto')->nullable();
                $table->string('telefono', 40)->nullable();
                $table->string('alias_direccion', 150)->nullable();
                $table->string('direccion_formateada')->nullable();
                $table->string('place_id')->nullable();
                $table->decimal('latitud', 10, 7)->nullable();
                $table->decimal('longitud', 10, 7)->nullable();
                $table->text('referencia')->nullable();
                $table->string('estado', 30)->default('pendiente')->index();
                $table->date('fecha_programada')->nullable();
                $table->time('hora_programada')->nullable();
                $table->timestamp('fecha_inicio')->nullable();
                $table->timestamp('fecha_llegada')->nullable();
                $table->timestamp('fecha_cierre')->nullable();
                $table->decimal('llegada_latitud', 10, 7)->nullable();
                $table->decimal('llegada_longitud', 10, 7)->nullable();
                $table->decimal('cierre_latitud', 10, 7)->nullable();
                $table->decimal('cierre_longitud', 10, 7)->nullable();
                $table->unsignedInteger('radio_validacion_metros')->default(150);
                $table->decimal('distancia_metros', 10, 2)->nullable();
                $table->text('observaciones')->nullable();
                $table->text('incidencia_descripcion')->nullable();
                $table->timestamp('recepcion_confirmada_at')->nullable();
                $table->unsignedBigInteger('recepcion_confirmada_por')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->foreign('jornada_logistica_id')
                    ->references('id')
                    ->on('jornadas_logisticas')
                    ->nullOnDelete();
                $table->foreign('orden_servicio_id')
                    ->references('id_orden_servicio')
                    ->on('orden_servicio')
                    ->nullOnDelete();
                $table->foreign('clave_cliente')
                    ->references('clave_cliente')
                    ->on('cliente')
                    ->nullOnDelete();
                $table->foreign('cliente_direccion_id')
                    ->references('id')
                    ->on('cliente_direcciones_logisticas')
                    ->nullOnDelete();
                $table->foreign('clave_proveedor')
                    ->references('clave_proveedor')
                    ->on('proveedores')
                    ->nullOnDelete();
                $table->foreign('tecnico_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
                $table->foreign('recepcion_confirmada_por')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasTable('movimiento_logistico_detalles')) {
            Schema::create('movimiento_logistico_detalles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('movimiento_logistico_id');
                $table->unsignedBigInteger('codigo_producto')->nullable();
                $table->string('nombre_producto');
                $table->decimal('cantidad', 12, 2)->default(0);
                $table->string('unidad', 60)->nullable();
                $table->string('tipo_control', 30)->nullable();
                $table->json('seriales')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->foreign('movimiento_logistico_id')
                    ->references('id')
                    ->on('movimientos_logisticos')
                    ->cascadeOnDelete();
                $table->foreign('codigo_producto')
                    ->references('codigo_producto')
                    ->on('productos')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasTable('movimiento_logistico_evidencias')) {
            Schema::create('movimiento_logistico_evidencias', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('movimiento_logistico_id');
                $table->string('tipo_foto', 60);
                $table->string('ruta_archivo');
                $table->decimal('latitud', 10, 7)->nullable();
                $table->decimal('longitud', 10, 7)->nullable();
                $table->timestamp('tomado_en')->nullable();
                $table->text('comentario')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('movimiento_logistico_id')
                    ->references('id')
                    ->on('movimientos_logisticos')
                    ->cascadeOnDelete();
                $table->foreign('created_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        Schema::table('proveedores', function (Blueprint $table) {
            if (!Schema::hasColumn('proveedores', 'direccion_logistica')) {
                $table->string('direccion_logistica')->nullable()->after('direccion');
            }
            if (!Schema::hasColumn('proveedores', 'direccion_logistica_place_id')) {
                $table->string('direccion_logistica_place_id')->nullable()->after('direccion_logistica');
            }
            if (!Schema::hasColumn('proveedores', 'direccion_logistica_latitud')) {
                $table->decimal('direccion_logistica_latitud', 10, 7)->nullable()->after('direccion_logistica_place_id');
            }
            if (!Schema::hasColumn('proveedores', 'direccion_logistica_longitud')) {
                $table->decimal('direccion_logistica_longitud', 10, 7)->nullable()->after('direccion_logistica_latitud');
            }
            if (!Schema::hasColumn('proveedores', 'direccion_logistica_referencia')) {
                $table->text('direccion_logistica_referencia')->nullable()->after('direccion_logistica_longitud');
            }
            if (!Schema::hasColumn('proveedores', 'direccion_logistica_verificada_en_mapa')) {
                $table->boolean('direccion_logistica_verificada_en_mapa')->default(false)->after('direccion_logistica_referencia');
            }
            if (!Schema::hasColumn('proveedores', 'direccion_logistica_metodo')) {
                $table->string('direccion_logistica_metodo', 30)->nullable()->after('direccion_logistica_verificada_en_mapa');
            }
        });

        Schema::table('inventario', function (Blueprint $table) {
            if (!Schema::hasColumn('inventario', 'forma_ingreso')) {
                $table->string('forma_ingreso', 40)->default('recibido_almacen')->after('clave_proveedor');
            }
            if (!Schema::hasColumn('inventario', 'estado_recepcion')) {
                $table->string('estado_recepcion', 40)->default('recibido')->after('forma_ingreso');
            }
            if (!Schema::hasColumn('inventario', 'movimiento_logistico_id')) {
                $table->unsignedBigInteger('movimiento_logistico_id')->nullable()->after('estado_recepcion');
                $table->foreign('movimiento_logistico_id')
                    ->references('id')
                    ->on('movimientos_logisticos')
                    ->nullOnDelete();
            }
        });

        Schema::table('orden_servicio', function (Blueprint $table) {
            if (!Schema::hasColumn('orden_servicio', 'cliente_direccion_id')) {
                $table->unsignedBigInteger('cliente_direccion_id')->nullable()->after('id_cliente');
                $table->foreign('cliente_direccion_id')
                    ->references('id')
                    ->on('cliente_direcciones_logisticas')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('orden_servicio', 'requiere_logistica')) {
                $table->boolean('requiere_logistica')->default(false)->after('cliente_direccion_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orden_servicio', function (Blueprint $table) {
            if (Schema::hasColumn('orden_servicio', 'cliente_direccion_id')) {
                $table->dropForeign(['cliente_direccion_id']);
                $table->dropColumn('cliente_direccion_id');
            }
            if (Schema::hasColumn('orden_servicio', 'requiere_logistica')) {
                $table->dropColumn('requiere_logistica');
            }
        });

        Schema::table('inventario', function (Blueprint $table) {
            if (Schema::hasColumn('inventario', 'movimiento_logistico_id')) {
                $table->dropForeign(['movimiento_logistico_id']);
                $table->dropColumn('movimiento_logistico_id');
            }
            if (Schema::hasColumn('inventario', 'estado_recepcion')) {
                $table->dropColumn('estado_recepcion');
            }
            if (Schema::hasColumn('inventario', 'forma_ingreso')) {
                $table->dropColumn('forma_ingreso');
            }
        });

        Schema::table('proveedores', function (Blueprint $table) {
            foreach ([
                'direccion_logistica_metodo',
                'direccion_logistica_verificada_en_mapa',
                'direccion_logistica_referencia',
                'direccion_logistica_longitud',
                'direccion_logistica_latitud',
                'direccion_logistica_place_id',
                'direccion_logistica',
            ] as $column) {
                if (Schema::hasColumn('proveedores', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('movimiento_logistico_evidencias');
        Schema::dropIfExists('movimiento_logistico_detalles');
        Schema::dropIfExists('movimientos_logisticos');
        Schema::dropIfExists('jornadas_logisticas');
        Schema::dropIfExists('cliente_direcciones_logisticas');
    }
};
