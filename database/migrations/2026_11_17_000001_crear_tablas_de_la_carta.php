<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estructura de la carta del apartado 5.3 del diseño: categorías con su
 * orden, los 14 alérgenos legales, productos con baja lógica (retirado_en)
 * y la tabla N:M productos_alergenos. El CHECK del precio va en la migración
 * de restricciones.
 */
return new class extends Migration
{
    /**
     * Crea categorias y alergenos, después productos, que apunta a su
     * categoría, y por último productos_alergenos, que borra en cascada los
     * alérgenos de un producto al borrarlo pero no deja borrar un alérgeno en uso.
     *
     * @return void
     *
     * @throws QueryException si falla una tabla o una de las tres claves externas
     */
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $tabla) {
            $tabla->comment('Secciones de la carta');
            $tabla->tinyIncrements('id')->comment('Id de la sección');
            $tabla->string('nombre', 40)->comment('Nombre en la carta');
            $tabla->unsignedTinyInteger('orden')->default(0)->comment('Orden en la carta');
            $tabla->boolean('activa')->default(true)->comment('Si se ve en la carta');
            $tabla->unique('nombre', 'uq_categorias_nombre');
        });

        Schema::create('alergenos', function (Blueprint $tabla) {
            $tabla->comment('Los 14 alérgenos obligatorios (Reglamento UE 1169/2011)');
            $tabla->tinyIncrements('id')->comment('Id del alérgeno');
            $tabla->string('nombre', 40)->comment('Nombre del alérgeno');
            $tabla->string('descripcion', 255)->comment('Texto para el cliente');
            $tabla->unique('nombre', 'uq_alergenos_nombre');
        });

        Schema::create('productos', function (Blueprint $tabla) {
            $tabla->comment('Productos de la carta');
            $tabla->smallIncrements('id')->comment('Id del producto');
            $tabla->unsignedTinyInteger('categoria_id')->comment('Sección de la carta');
            $tabla->string('nombre', 60)->comment('Nombre');
            $tabla->string('descripcion', 255)->nullable()->comment('Ingredientes; opcional en bebidas');
            $tabla->decimal('precio', 5, 2)->comment('Precio actual con IVA');
            $tabla->boolean('disponible')->default(true)->comment('FALSE = agotado');
            $tabla->dateTime('retirado_en')->nullable()->comment('Fecha de retirada (NULL = en la carta)');
            $tabla->dateTime('creado_en')->useCurrent()->comment('Fecha de alta');
            $tabla->dateTime('actualizado_en')->nullable()->useCurrentOnUpdate()->comment('Fecha del último cambio');
            $tabla->unique('nombre', 'uq_productos_nombre');
            $tabla->foreign('categoria_id', 'fk_productos_categoria')->references('id')->on('categorias');
        });

        Schema::create('productos_alergenos', function (Blueprint $tabla) {
            $tabla->comment('Alérgenos de cada producto (N:M)');
            $tabla->unsignedSmallInteger('producto_id')->comment('Producto');
            $tabla->unsignedTinyInteger('alergeno_id')->comment('Alérgeno');
            $tabla->primary(['producto_id', 'alergeno_id']);
            $tabla->foreign('producto_id', 'fk_productos_alergenos_producto')
                ->references('id')->on('productos')->cascadeOnDelete();
            $tabla->foreign('alergeno_id', 'fk_productos_alergenos_alergeno')->references('id')->on('alergenos');
        });
    }

    /**
     * Deshace la carta en orden inverso: productos_alergenos, productos,
     * alergenos y categorias.
     *
     * @return void
     *
     * @throws QueryException si otra tabla aún apunta a alguna de ellas
     */
    public function down(): void
    {
        Schema::dropIfExists('productos_alergenos');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('alergenos');
        Schema::dropIfExists('categorias');
    }
};
