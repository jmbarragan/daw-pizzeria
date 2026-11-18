<?php

/**
 * Datos de prueba de la carta: categorías, alérgenos, productos y sus alérgenos.
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga la carta del diseño: las cuatro secciones de la propuesta más
 * Focaccias, los 14 alérgenos legales y diez productos. La Tartufo está
 * agotada, la Limonada casera se retiró después de venderse y la Margherita
 * subió de 9,00 a 9,50 (un pedido antiguo conserva el precio de entonces).
 */
class CartaSeeder extends Seeder
{
    /**
     * Inserta la carta con los mismos identificadores que el script del diseño.
     *
     * @param  CalendarioDePrueba  $calendario  fechas desplazadas al día de la instalación
     * @return void
     */
    public function run(CalendarioDePrueba $calendario): void
    {
        DB::table('categorias')->insert([
            ['id' => 1, 'nombre' => 'Pizzas', 'orden' => 1],
            ['id' => 2, 'nombre' => 'Focaccias', 'orden' => 2],
            ['id' => 3, 'nombre' => 'Entrantes', 'orden' => 3],
            ['id' => 4, 'nombre' => 'Bebidas', 'orden' => 4],
            ['id' => 5, 'nombre' => 'Postres', 'orden' => 5],
        ]);

        // Reglamento (UE) 1169/2011, anexo II
        $alergenos = [
            'Gluten' => 'Cereales con gluten: trigo, centeno, cebada, avena, espelta y kamut, y sus derivados',
            'Crustáceos' => 'Crustáceos y productos a base de crustáceos',
            'Huevos' => 'Huevos y productos a base de huevo',
            'Pescado' => 'Pescado y productos a base de pescado',
            'Cacahuetes' => 'Cacahuetes y productos a base de cacahuetes',
            'Soja' => 'Soja y productos a base de soja',
            'Lácteos' => 'Leche y sus derivados, incluida la lactosa',
            'Frutos de cáscara' => 'Almendras, avellanas, nueces, anacardos, pacanas, nueces de Brasil, pistachos y macadamias',
            'Apio' => 'Apio y productos derivados',
            'Mostaza' => 'Mostaza y productos derivados',
            'Sésamo' => 'Granos de sésamo y productos a base de sésamo',
            'Sulfitos' => 'Dióxido de azufre y sulfitos en concentraciones superiores a 10 mg/kg o 10 mg/l',
            'Altramuces' => 'Altramuces y productos a base de altramuces',
            'Moluscos' => 'Moluscos y productos a base de moluscos',
        ];
        $id = 0;
        foreach ($alergenos as $nombre => $descripcion) {
            DB::table('alergenos')->insert(['id' => ++$id, 'nombre' => $nombre, 'descripcion' => $descripcion]);
        }

        $alta = $calendario->momento('2026-10-01 10:00:00');
        $productos = [
            [1, 1, 'Margherita', 'Tomate, mozzarella fior di latte y albahaca fresca', 9.50, true, null],
            [2, 1, 'Diavola', 'Tomate, mozzarella y salami picante', 11.00, true, null],
            [3, 1, 'Quattro formaggi', 'Mozzarella, gorgonzola, parmesano y fontina', 12.00, true, null],
            [4, 1, 'Tartufo', 'Crema de trufa negra, mozzarella y champiñones', 14.50, false, null],
            [5, 2, 'Focaccia di Trevi', 'Focaccia con mortadela, burrata y pistacho', 13.50, true, null],
            [6, 2, 'Focaccia al rosmarino', 'Focaccia con romero, sal en escamas y aceite de oliva', 6.50, true, null],
            [7, 3, 'Bruschetta', 'Pan tostado con tomate, ajo y albahaca', 5.50, true, null],
            [8, 4, 'Agua mineral 50 cl', null, 1.50, true, null],
            [9, 4, 'Limonada casera', 'Limón exprimido y hierbabuena', 2.50, true, $calendario->momento('2026-11-05 12:00:00')],
            [10, 5, 'Tiramisú', 'Bizcocho, café, mascarpone y cacao', 4.50, true, null],
        ];
        foreach ($productos as [$producto, $categoria, $nombre, $descripcion, $precio, $disponible, $retirado]) {
            DB::table('productos')->insert([
                'id' => $producto, 'categoria_id' => $categoria, 'nombre' => $nombre, 'descripcion' => $descripcion,
                'precio' => $precio, 'disponible' => $disponible, 'retirado_en' => $retirado, 'creado_en' => $alta,
            ]);
        }

        // producto => alérgenos (1 gluten, 3 huevos, 7 lácteos, 8 frutos de cáscara)
        $declarados = [1 => [1, 7], 2 => [1, 7], 3 => [1, 7], 4 => [1, 7], 5 => [1, 7, 8], 6 => [1], 7 => [1], 10 => [1, 3, 7]];
        foreach ($declarados as $producto => $lista) {
            foreach ($lista as $alergeno) {
                DB::table('productos_alergenos')->insert(['producto_id' => $producto, 'alergeno_id' => $alergeno]);
            }
        }
    }
}
