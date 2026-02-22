<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id('codigo_producto'); // PK
            $table->string('nombre');
            $table->string('unidad', 50);
            $table->text('descripcion')->nullable();
            $table->string('numero_parte')->nullable();
            $table->integer('stock_seguridad')->default(0); // en piezas
            $table->integer('stock_total')->default(0);     // en piezas
            $table->integer('stock_paquetes')->default(0);  // paquetes completos
            $table->integer('stock_piezas_sueltas')->default(0); // piezas sueltas
            $table->string('imagen')->nullable();
            $table->string('categoria');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('inventario', function (Blueprint $table) {
            $table->id(); // PK
            $table->decimal('costo', 10, 2);
            $table->decimal('precio', 10, 2);
            $table->string('tipo_control', 50);
            $table->integer('cantidad_ingresada');
            $table->integer('piezas_por_paquete')->nullable();
            $table->integer('paquetes_restantes')->default(0);
            $table->integer('piezas_sueltas')->default(0);
            $table->string('numero_serie')->nullable();
            $table->date('fecha_entrada');
            $table->time('hora_entrada');
            $table->date('fecha_caducidad')->nullable();
            $table->time('hora_salida')->nullable();
            $table->date('fecha_salida')->nullable();
            $table->unsignedBigInteger('codigo_producto');
            $table->timestamps();

            $table->foreign('codigo_producto')->references('codigo_producto')->on('productos');
        });

        Schema::create('proveedores', function (Blueprint $table) {
            $table->id('clave_proveedor'); // PK
            $table->string('nombre');
            $table->string('direccion')->nullable();
            $table->string('contacto')->nullable();
            $table->string('telefono', 20);
            $table->string('correo')->unique();
            $table->timestamps();
        });

        Schema::create('producto_proveedor', function (Blueprint $table) {
            $table->unsignedBigInteger('clave_proveedor');
            $table->unsignedBigInteger('codigo_producto');

            $table->primary(['clave_proveedor', 'codigo_producto']);

            $table->foreign('clave_proveedor')->references('clave_proveedor')->on('proveedores');
            $table->foreign('codigo_producto')->references('codigo_producto')->on('productos');
        });

        Schema::create('cliente', function (Blueprint $table) {
            $table->id('clave_cliente'); // PK

            // ✅ NUEVO: código manual (texto/combinado)
            $table->string('codigo_cliente', 60)->unique();

            $table->string('nombre');
            $table->string('nombre_empresa')->nullable();
            $table->string('direccion_fiscal');
            $table->string('contacto')->nullable();
            $table->string('telefono', 20);
            $table->string('contacto_adicional', 20)->nullable();
            $table->string('correo_electronico')->unique();
            $table->string('datos_fiscales')->nullable();
            $table->string('ubicacion')->nullable();
            $table->timestamps();
        });

        Schema::create('credito_cliente', function (Blueprint $table) {
            $table->id('id_credito'); // PK
            $table->decimal('monto', 10, 2);
            $table->integer('dias');
            $table->unsignedBigInteger('clave_cliente');
            $table->timestamps();

            $table->foreign('clave_cliente')->references('clave_cliente')->on('cliente');
        });

        Schema::create('detalle_producto', function (Blueprint $table) {
            $table->id('id_detalle_producto'); // PK
            $table->unsignedBigInteger('codigo_producto');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();

            $table->foreign('codigo_producto')->references('codigo_producto')->on('productos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_producto');
        Schema::dropIfExists('credito_cliente');
        Schema::dropIfExists('cliente');
        Schema::dropIfExists('producto_proveedor');
        Schema::dropIfExists('proveedores');
        Schema::dropIfExists('inventario');
        Schema::dropIfExists('productos');
    }
};
