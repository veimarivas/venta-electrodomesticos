<?php

namespace App\Support;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Support\Excel\EscritorXlsx;
use App\Support\Excel\LectorXlsx;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Carga masiva del catálogo desde un Excel.
 *
 * Un solo archivo con dos hojas —«Categorias» y «Productos»— evita registrar
 * uno por uno lo que la tienda ya tiene en una lista. La jerarquía se resuelve
 * dentro del archivo: una categoría puede declarar su padre y los productos
 * apuntan a la categoría (o al padre y la subcategoría) por nombre.
 *
 * Estrategia de datos:
 * - **Categoría** se identifica por `(padre_id, nombre)` sin distinguir
 *   mayúsculas: reimportar el mismo archivo actualiza en vez de duplicar.
 * - **Producto** se identifica por `(categoria_id, nombre)`.
 * - **Marca** se crea sola si el producto la menciona y no existe.
 *
 * Las filas con error no detienen la carga: se importan las válidas y el
 * resumen devuelve qué fila falló y por qué. Todo lo escrito va en una
 * transacción, así que un fallo inesperado de base de datos no deja medio
 * catálogo a medias.
 */
class ImportadorCatalogo
{
    /** Lo máximo que admite una celda de Excel. */
    private const MAX_CELDA = 32000;

    public function __construct(
        private readonly LectorXlsx $lector = new LectorXlsx,
        private readonly EscritorXlsx $escritor = new EscritorXlsx,
    ) {}

    // =========================================================================
    // Plantilla
    // =========================================================================

    /**
     * Devuelve el `.xlsx` de la plantilla, listo para descargar.
     */
    public function plantilla(): string
    {
        return $this->escritor->generar([
            [
                'nombre' => 'Instrucciones',
                'filas' => $this->filasInstrucciones(),
            ],
            [
                'nombre' => 'Categorias',
                'filas' => [
                    ['Nombre', 'Categoria padre', 'Descripcion', 'Activo (SI/NO)'],
                    ['# Televisores', '', 'Televisores y accesorios', 'SI'],
                    ['# Smart TV', 'Televisores', 'Categoria de ejemplo', 'SI'],
                ],
            ],
            [
                'nombre' => 'Productos',
                'filas' => [
                    [
                        'Categoria',
                        'Subcategoria',
                        'Nombre',
                        'Marca',
                        'Modelo',
                        'Descripcion',
                        'Precio venta',
                        'Descuento maximo',
                        'Stock minimo',
                        'Meses garantia',
                        'Tiene serial (SI/NO)',
                        'Activo (SI/NO)',
                        'Especificaciones',
                    ],
                    [
                        '# Televisores',
                        '# Smart TV',
                        '# Televisor 55 4K',
                        '# Samsung',
                        'UN55',
                        'Producto de ejemplo: no se importa',
                        '4999.00',
                        '200',
                        '2',
                        '12',
                        'SI',
                        'SI',
                        'Pantalla=55 pulgadas; Panel=QLED; Bluetooth=',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function filasInstrucciones(): array
    {
        return [
            ['Carga masiva del catalogo - Electronica del Hogar'],
            [''],
            ['Como usar esta plantilla'],
            ['1. Rellena la hoja "Categorias" con las categorias y subcategorias.'],
            ['2. Rellena la hoja "Productos" con los productos.'],
            ['3. Las filas de ejemplo empiezan con # y se ignoran al importar. Borralas o reemplazalas por tus datos.'],
            ['4. Guarda y sube el archivo desde el panel (Catalogo -> Importar) o desde la app.'],
            [''],
            ['Hoja "Categorias"'],
            ['Nombre: obligatorio.'],
            ['Categoria padre: vacio para una categoria principal; el nombre de otra categoria para hacerla subcategoria.'],
            ['Descripcion: opcional.'],
            ['Activo: SI o NO (vacio = SI).'],
            [''],
            ['Hoja "Productos"'],
            ['Categoria: obligatorio. Nombre de una categoria ya registrada (en esta plantilla o en el sistema).'],
            ['Subcategoria: opcional. Para colgar el producto de una subcategoria de "Categoria".'],
            ['Nombre: obligatorio.'],
            ['Marca: opcional. Si no existe, se crea sola.'],
            ['Modelo / Descripcion: opcionales.'],
            ['Precio venta: obligatorio, numero. Se acepta coma o punto decimal.'],
            ['Descuento maximo: rebaja autorizada en Bs. No puede superar el precio. Vacio = 0.'],
            ['Stock minimo: entero. Vacio = 0.'],
            ['Meses garantia: entero. Vacio = 12.'],
            ['Tiene serial: SI o NO (vacio = SI).'],
            ['Activo: SI o NO (vacio = SI).'],
            ['Especificaciones: opcional. pares clave=valor separados por punto y coma.'],
            ['   Ejemplo: Pantalla=55 pulgadas; Panel=QLED; Bluetooth='],
            [''],
            ['Si un producto o categoria ya existe, se actualiza en vez de duplicarse.'],
        ];
    }

    // =========================================================================
    // Importacion
    // =========================================================================

    /**
     * Importa el archivo y devuelve el resumen de lo hecho.
     *
     * @return array{
     *     categorias_creadas: int,
     *     categorias_actualizadas: int,
     *     marcas_creadas: int,
     *     productos_creados: int,
     *     productos_actualizados: int,
     *     errores: array<int, string>
     * }
     */
    public function importar(string $ruta): array
    {
        try {
            $hojas = $this->lector->leer($ruta);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el Excel: '.$e->getMessage(),
            ]);
        }

        $hojaCategorias = $this->buscarHoja($hojas, ['categorias', 'categoria']);
        $hojaProductos = $this->buscarHoja($hojas, ['productos', 'producto']);

        if ($hojaCategorias === null && $hojaProductos === null) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo debe tener una hoja «Categorias» y/o «Productos». '
                    .'Descarga la plantilla para ver el formato.',
            ]);
        }

        $resumen = [
            'categorias_creadas' => 0,
            'categorias_actualizadas' => 0,
            'marcas_creadas' => 0,
            'productos_creados' => 0,
            'productos_actualizados' => 0,
            'errores' => [],
        ];

        try {
            DB::transaction(function () use ($hojaCategorias, $hojaProductos, &$resumen): void {
                // Las categorías primero: un producto no puede colgar de una
                // categoría que todavía no existe.
                if ($hojaCategorias !== null) {
                    $this->importarCategorias($hojaCategorias, $resumen);
                }

                if ($hojaProductos !== null) {
                    $this->importarProductos($hojaProductos, $resumen);
                }
            });
        } catch (QueryException $e) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo completar la importación y no se guardó nada. '
                    .'Revisa que no haya datos repetidos. Detalle: '.$e->getMessage(),
            ]);
        }

        return $resumen;
    }

    // ---- Hojas y encabezados -------------------------------------------------

    /**
     * @param  array<string, array<int, array<int, string>>>  $hojas
     * @param  array<int, string>  $alias
     * @return array<int, array<int, string>>|null
     */
    private function buscarHoja(array $hojas, array $alias): ?array
    {
        foreach ($hojas as $nombre => $filas) {
            if (in_array($this->normalizar($nombre), $alias, true)) {
                return $filas;
            }
        }

        return null;
    }

    /**
     * Índice de cada columna por nombre de encabezado. La primera fila con
     * datos es la de encabezados; las demás, datos.
     *
     * @param  array<int, array<int, string>>  $filas
     * @param  array<string, array<int, string>>  $alias  Campo => encabezados posibles.
     * @return array{columnas: array<string, int|null>, datos: array<int, array<int, string>>}
     */
    private function interpretar(array $filas, array $alias): array
    {
        $encabezado = array_shift($filas) ?? [];

        $normalizado = array_map(fn ($valor) => $this->normalizar($valor), $encabezado);

        $columnas = [];

        foreach ($alias as $campo => $posibles) {
            $indice = array_search(true, array_map(
                fn ($titulo) => in_array($titulo, $posibles, true),
                $normalizado,
            ), true);

            $columnas[$campo] = $indice === false ? null : $indice;
        }

        return ['columnas' => $columnas, 'datos' => array_values($filas)];
    }

    /**
     * @param  array<int, string>  $fila
     */
    private function celda(array $fila, ?int $indice): string
    {
        if ($indice === null) {
            return '';
        }

        return trim((string) ($fila[$indice] ?? ''));
    }

    // ---- Categorías ----------------------------------------------------------

    /**
     * @param  array<int, array<int, string>>  $filas
     * @param  array<string, mixed>  $resumen
     */
    private function importarCategorias(array $filas, array &$resumen): void
    {
        $vista = $this->interpretar($filas, [
            'nombre' => ['nombre', 'categoria', 'categorias'],
            'padre' => ['categoriapadre', 'padre', 'subcategoriadep', 'depende'],
            'descripcion' => ['descripcion', 'detalle'],
            'activo' => ['activo', 'estado', 'habilitado'],
        ]);

        $columnas = $vista['columnas'];

        /** @var Collection<int, Categoria> $categorias */
        $categorias = Categoria::query()->get();

        $pendientes = [];

        foreach ($vista['datos'] as $numero => $fila) {
            $nombre = $this->celda($fila, $columnas['nombre']);

            if ($this->esFilaIgnorable($nombre)) {
                continue;
            }

            if ($nombre === '') {
                continue;
            }

            if (mb_strlen($nombre) > 100) {
                $resumen['errores'][] = 'Categorias fila '.($numero + 2).': el nombre supera los 100 caracteres.';

                continue;
            }

            $pendientes[] = [
                'fila' => $numero + 2,
                'nombre' => $nombre,
                'padre' => $this->celda($fila, $columnas['padre']),
                'descripcion' => $this->celda($fila, $columnas['descripcion']),
                'activo' => $columnas['activo'] === null ? null : $this->celda($fila, $columnas['activo']),
            ];
        }

        // Varias pasadas hasta que ninguna fila resuelva a su padre: el hijo
        // puede venir antes que el padre en la hoja.
        $progreso = true;

        while ($pendientes !== [] && $progreso) {
            $progreso = false;
            $siguientes = [];

            foreach ($pendientes as $fila) {
                $padreId = null;

                if ($fila['padre'] !== '') {
                    $padre = $this->buscarCategoriaUnica($categorias, $fila['padre']);

                    if ($padre === null) {
                        $siguientes[] = $fila;

                        continue;
                    }

                    $padreId = $padre->id;
                }

                $categoria = $this->resolverCategoria($categorias, $padreId, $fila['nombre']);

                if ($categoria !== null) {
                    $this->actualizarCategoria($categoria, $fila);
                    $resumen['categorias_actualizadas']++;
                } else {
                    $categoria = $this->crearCategoria($categorias, $padreId, $fila);
                    $resumen['categorias_creadas']++;
                }

                $progreso = true;
            }

            $pendientes = $siguientes;
        }

        foreach ($pendientes as $fila) {
            $resumen['errores'][] = 'Categorias fila '.$fila['fila'].': no se encontró la categoría padre «'
                .$fila['padre'].'». Si hay más de una con ese nombre, renómbrala o regístrala antes.';
        }
    }

    /**
     * @param  Collection<int, Categoria>  $categorias
     */
    private function buscarCategoriaUnica(Collection $categorias, string $nombre): ?Categoria
    {
        $clave = mb_strtolower($nombre);

        $coincidencias = $categorias->filter(
            fn (Categoria $c) => mb_strtolower($c->nombre) === $clave
        );

        return $coincidencias->count() === 1 ? $coincidencias->first() : null;
    }

    /**
     * @param  Collection<int, Categoria>  $categorias
     */
    private function resolverCategoria(Collection $categorias, ?int $padreId, string $nombre): ?Categoria
    {
        $clave = mb_strtolower($nombre);

        return $categorias->first(
            fn (Categoria $c) => ($c->padre_id ?? null) === $padreId
                && mb_strtolower($c->nombre) === $clave
        );
    }

    /**
     * @param  Collection<int, Categoria>  $categorias
     */
    private function crearCategoria(Collection $categorias, ?int $padreId, array $fila): Categoria
    {
        $categoria = Categoria::create([
            'padre_id' => $padreId,
            'nombre' => $fila['nombre'],
            // withTrashed: el índice único del slug cubre también lo archivado.
            'slug' => $this->slugUnico(Categoria::withTrashed(), $fila['nombre']),
            'descripcion' => $fila['descripcion'] === '' ? null : $fila['descripcion'],
            'activo' => $fila['activo'] === null ? true : $this->aBooleano($fila['activo'], true),
            'posicion' => 0,
        ]);

        $categorias->push($categoria);

        return $categoria;
    }

    private function actualizarCategoria(Categoria $categoria, array $fila): void
    {
        $cambios = [
            'nombre' => $fila['nombre'],
        ];

        if ($fila['descripcion'] !== '') {
            $cambios['descripcion'] = $fila['descripcion'];
        }

        if ($fila['activo'] !== null && $fila['activo'] !== '') {
            $cambios['activo'] = $this->aBooleano($fila['activo'], true);
        }

        $categoria->update($cambios);
    }

    // ---- Productos -----------------------------------------------------------

    /**
     * @param  array<int, array<int, string>>  $filas
     * @param  array<string, mixed>  $resumen
     */
    private function importarProductos(array $filas, array &$resumen): void
    {
        $vista = $this->interpretar($filas, [
            'categoria' => ['categoria', 'categorias', 'rubro'],
            'subcategoria' => ['subcategoria', 'subcategorias'],
            'nombre' => ['nombre', 'producto', 'descripcionproducto'],
            'marca' => ['marca'],
            'modelo' => ['modelo'],
            'descripcion' => ['descripcion', 'detalle'],
            'precio' => ['precioventa', 'precio', 'preciolista'],
            'descuento' => ['descuentomaximo', 'descuento', 'rebajamaxima'],
            'stock' => ['stockminimo', 'stock', 'minimo'],
            'garantia' => ['mesesgarantia', 'garantia', 'meses'],
            'serial' => ['tieneserial', 'serial', 'conserial'],
            'activo' => ['activo', 'estado', 'habilitado'],
            'especificaciones' => ['especificaciones', 'caracteristicas', 'especificacion'],
        ]);

        $columnas = $vista['columnas'];

        if ($columnas['categoria'] === null) {
            $resumen['errores'][] = 'La hoja «Productos» no tiene la columna «Categoria». Descarga la plantilla.';

            return;
        }

        if ($columnas['nombre'] === null) {
            $resumen['errores'][] = 'La hoja «Productos» no tiene la columna «Nombre». Descarga la plantilla.';

            return;
        }

        /** @var Collection<int, Categoria> $categorias */
        $categorias = Categoria::query()->get();

        /** @var Collection<int, Marca> $marcas */
        $marcas = Marca::query()->get();

        foreach ($vista['datos'] as $numero => $fila) {
            $linea = $numero + 2;
            $nombre = $this->celda($fila, $columnas['nombre']);

            if ($this->esFilaIgnorable($nombre)) {
                continue;
            }

            if ($nombre === '') {
                continue;
            }

            if (mb_strlen($nombre) > 150) {
                $resumen['errores'][] = "Productos fila {$linea}: el nombre supera los 150 caracteres.";

                continue;
            }

            $precio = $this->aNumero($this->celda($fila, $columnas['precio']));

            if ($precio === null || $precio < 0) {
                $resumen['errores'][] = "Productos fila {$linea}: el precio de venta es obligatorio y debe ser un número.";

                continue;
            }

            $categoria = $this->resolverCategoriaDeProducto(
                $categorias,
                $this->celda($fila, $columnas['categoria']),
                $this->celda($fila, $columnas['subcategoria']),
            );

            if ($categoria === null) {
                $resumen['errores'][] = "Productos fila {$linea}: la categoría «"
                    .$this->celda($fila, $columnas['categoria'])
                    .'» no existe. Regístrala en la hoja «Categorias».';

                continue;
            }

            $descuento = $this->aNumero($this->celda($fila, $columnas['descuento'])) ?? 0.0;

            if ($descuento < 0 || $descuento > $precio) {
                $resumen['errores'][] = "Productos fila {$linea}: el descuento máximo no puede superar al precio de venta.";

                continue;
            }

            $marcaId = $this->resolverMarca(
                $marcas,
                $this->celda($fila, $columnas['marca']),
                $resumen,
            );

            $datos = [
                'categoria_id' => $categoria->id,
                'marca_id' => $marcaId,
                'nombre' => $nombre,
                'modelo' => $this->nuloSiVacio($this->celda($fila, $columnas['modelo'])),
                'descripcion' => $this->nuloSiVacio($this->celda($fila, $columnas['descripcion'])),
                'precio_venta' => $precio,
                'descuento_maximo' => $descuento,
                'stock_minimo' => max(0, (int) ($this->aNumero($this->celda($fila, $columnas['stock'])) ?? 0)),
                'meses_garantia' => max(0, (int) ($this->aNumero($this->celda($fila, $columnas['garantia'])) ?? 12)),
                'tiene_serial' => $this->aBooleano($this->celda($fila, $columnas['serial']), true),
                'activo' => $this->aBooleano($this->celda($fila, $columnas['activo']), true),
            ];

            $clave = mb_strtolower($nombre);

            $producto = Producto::query()
                ->where('categoria_id', $categoria->id)
                ->get()
                ->first(fn (Producto $p) => mb_strtolower($p->nombre) === $clave);

            if ($producto !== null) {
                $producto->update($datos);
                $resumen['productos_actualizados']++;
            } else {
                // withTrashed: el índice único del slug cubre lo archivado.
                $datos['slug'] = $this->slugUnico(Producto::withTrashed(), $nombre);
                $producto = Producto::create($datos);
                $resumen['productos_creados']++;
            }

            $especificaciones = $this->celda($fila, $columnas['especificaciones']);

            if ($especificaciones !== '') {
                $this->guardarEspecificaciones($producto, $especificaciones);
            }
        }
    }

    /**
     * Resuelve la categoría del producto. El nombre puede venir como ruta
     * («Electrónica / Audio / Parlantes») o suelto, y `subcategoria` permite
     * colgarlo de una hija concreta.
     *
     * @param  Collection<int, Categoria>  $categorias
     */
    private function resolverCategoriaDeProducto(
        Collection $categorias,
        string $categoria,
        string $subcategoria,
    ): ?Categoria {
        if ($categoria === '') {
            return null;
        }

        // Ruta completa separada por «>» o «/»: se baja por el árbol.
        if (preg_match('#[>/]#', $categoria)) {
            $partes = array_values(array_filter(array_map('trim', preg_split('#[>/]#', $categoria))));

            $padre = null;
            $actual = null;

            foreach ($partes as $parte) {
                $clave = mb_strtolower($parte);

                $actual = $categorias->first(
                    fn (Categoria $c) => ($c->padre_id ?? null) === $padre
                        && mb_strtolower($c->nombre) === $clave
                );

                if ($actual === null) {
                    return null;
                }

                $padre = $actual->id;
            }

            return $actual;
        }

        $base = $this->buscarCategoriaUnica($categorias, $categoria);

        if ($base === null) {
            return null;
        }

        if ($subcategoria === '') {
            return $base;
        }

        return $categorias->first(
            fn (Categoria $c) => ($c->padre_id ?? null) === $base->id
                && mb_strtolower($c->nombre) === mb_strtolower($subcategoria)
        );
    }

    /**
     * @param  Collection<int, Marca>  $marcas
     * @param  array<string, mixed>  $resumen
     */
    private function resolverMarca(Collection $marcas, string $nombre, array &$resumen): ?int
    {
        if ($nombre === '') {
            return null;
        }

        $clave = mb_strtolower($nombre);

        $marca = $marcas->first(fn (Marca $m) => mb_strtolower($m->nombre) === $clave);

        if ($marca === null) {
            $marca = Marca::create([
                'nombre' => $nombre,
                'slug' => $this->slugUnico(Marca::query(), $nombre),
                'activa' => true,
            ]);

            $marcas->push($marca);
            $resumen['marcas_creadas']++;
        }

        return $marca->id;
    }

    private function guardarEspecificaciones(Producto $producto, string $crudo): void
    {
        $producto->especificaciones()->delete();

        $posicion = 0;

        foreach (explode(';', $crudo) as $par) {
            $par = trim($par);

            if ($par === '') {
                continue;
            }

            $separador = strpos($par, '=');

            if ($separador === false) {
                $clave = $par;
                $valor = '';
            } else {
                $clave = trim(substr($par, 0, $separador));
                $valor = trim(substr($par, $separador + 1));
            }

            if ($clave === '') {
                continue;
            }

            $producto->especificaciones()->create([
                'clave' => mb_substr($clave, 0, 60),
                'valor' => $valor === '' ? null : mb_substr($valor, 0, 200),
                'posicion' => $posicion++,
            ]);
        }
    }

    // ---- Utilidades ----------------------------------------------------------

    /**
     * Las filas de ejemplo de la plantilla empiezan con «#» y se ignoran.
     */
    private function esFilaIgnorable(string $nombre): bool
    {
        return $nombre !== '' && str_starts_with($nombre, '#');
    }

    private function nuloSiVacio(string $valor): ?string
    {
        return $valor === '' ? null : mb_substr($valor, 0, self::MAX_CELDA);
    }

    /**
     * Normaliza un encabezado: sin tildes, sin espacios ni signos, en
     * minúsculas.
     */
    private function normalizar(string $valor): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii(trim($valor))));
    }

    private function aBooleano(string $valor, bool $defecto): bool
    {
        $valor = $this->normalizar($valor);

        if ($valor === '') {
            return $defecto;
        }

        return in_array($valor, ['si', 's', '1', 'true', 'x', 'yes', 'activo', 'activa'], true);
    }

    /**
     * Acepta «1.234,56» y «1,234.56». Devuelve null si no es un número.
     */
    private function aNumero(string $valor): ?float
    {
        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        $valor = (string) preg_replace('/[^0-9,.\-]/', '', $valor);

        if ($valor === '' || $valor === '-') {
            return null;
        }

        $coma = strrpos($valor, ',');
        $punto = strrpos($valor, '.');

        if ($coma !== false && $punto !== false) {
            if ($coma > $punto) {
                // 1.234,56 → formato europeo.
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            } else {
                // 1,234.56 → formato inglés.
                $valor = str_replace(',', '', $valor);
            }
        } elseif ($coma !== false) {
            $valor = str_replace(',', '.', $valor);
        }

        return is_numeric($valor) ? (float) $valor : null;
    }

    /**
     * Slug único para cualquier modelo con columna `slug`.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $consulta
     */
    private function slugUnico(object $consulta, string $nombre, ?int $ignorar = null): string
    {
        $base = Str::slug($nombre);

        if ($base === '') {
            $base = 'sin-nombre';
        }

        $slug = $base;
        $sufijo = 2;

        while ((clone $consulta)
            ->where('slug', $slug)
            ->when($ignorar !== null, fn ($q) => $q->whereKeyNot($ignorar))
            ->exists()
        ) {
            $slug = "{$base}-{$sufijo}";
            $sufijo++;
        }

        return $slug;
    }
}
