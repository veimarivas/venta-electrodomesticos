<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnidadResource;
use App\Models\Unidad;
use App\Support\AjusteDeUnidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Unidades físicas del almacén, desde el teléfono.
 *
 * El inventario es lo que más se consulta **de pie**: con el aparato en la mano
 * y sin un ordenador cerca. Escanear la etiqueta y ver qué es, en qué estado
 * está, de qué compra vino y por dónde ha pasado es justo lo que el teléfono
 * hace mejor que el panel.
 *
 * Lo que sí se queda en el panel: **el alta y la baja de unidades, y sus
 * precios**. Un aparato se da de alta al recepcionar su compra, contando cajas;
 * crear unidades sueltas desde el teléfono sería inventar stock. Los importes
 * se revisan con calma y con la factura delante, no en un pasillo.
 */
class UnidadController extends Controller
{
    /**
     * Registra una nueva unidad desde el teléfono.
     *
     * El teléfono escanea el código de barras y crea la unidad con los datos
     * básicos: producto, serial y precio de venta. El costo y la compra se
     * asignan después al recepcionar la compra desde el panel.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'integer', Rule::exists('productos', 'id')->whereNull('deleted_at')],
            'serial' => ['nullable', 'string', 'max:100'],
            'precio_venta' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        // Si no se envía precio, se usa el precio de venta del producto.
        $producto = \App\Models\Producto::findOrFail($datos['producto_id']);
        $precioVenta = $datos['precio_venta'] ?? $producto->precio_venta;

        $unidad = app(\App\Support\GeneradorCodigoUnidad::class)->crearCon([
            'producto_id' => $datos['producto_id'],
            'serial' => $datos['serial'] ?? null,
            'estado' => 'en_stock',
            'precio_venta' => $precioVenta,
            'costo_unitario' => 0,
        ]);

        return response()->json([
            'mensaje' => 'Unidad registrada.',
            'data' => new UnidadResource($unidad->load('producto')),
        ], 201);
    }

    /**
     * Listado del inventario, con los mismos filtros que el panel.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(array_keys(Unidad::ESTADOS))],
            'producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $termino = trim($datos['buscar'] ?? '');

        $unidades = Unidad::query()
            ->with('producto')
            ->when(isset($datos['producto_id']), fn ($q) => $q->where('producto_id', $datos['producto_id']))
            ->when(isset($datos['estado']), fn ($q) => $q->where('estado', $datos['estado']))
            ->when($termino !== '', fn ($q) => $q->where(function ($q2) use ($termino) {
                $q2->where('codigo_interno', 'like', "%{$termino}%")
                    ->orWhere('serial', 'like', "%{$termino}%")
                    ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', "%{$termino}%"));
            }))
            ->orderBy('codigo_interno')
            // Desempate estable: sin él dos unidades del mismo código pueden
            // saltar de página y aparecer duplicadas al desplazarse.
            ->orderBy('id')
            ->paginate($datos['por_pagina'] ?? 20);

        return UnidadResource::collection($unidades)->additional([
            'meta' => ['resumen' => $this->resumen($datos['producto_id'] ?? null)],
        ]);
    }

    /**
     * Ficha del aparato: lo que se abre al escanear su etiqueta.
     *
     * El kardex viaja dentro y no en su propia ruta —al revés que las unidades
     * de una compra—: la historia de UNA unidad son unas pocas filas, y
     * pedirla aparte obligaría a una segunda vuelta al servidor justo cuando
     * el almacenero ya está mirando la pantalla.
     */
    public function show(Request $request, Unidad $unidad): UnidadResource
    {
        $unidad->load([
            'producto.marca',
            'producto.categoria',
            'compra.proveedor',
            'ventaDetalle.venta',
            'movimientos.user',
            'movimientos.origen',
        ]);

        return (new UnidadResource($unidad))->conDetalle();
    }

    /**
     * Ajusta el estado, la ubicación o las notas del aparato.
     *
     * Son los tres campos que se corrigen con el aparato delante: «esto está
     * dañado», «esto no está en el pasillo 3 sino en el 5». Todo lo demás
     * —precio, costo, fecha de ingreso— se queda en el panel.
     *
     * El cambio de estado pasa por `AjusteDeUnidad`, que es quien lo escribe en
     * el kardex y quien impide marcar «vendido» a mano.
     */
    public function actualizar(Request $request, Unidad $unidad): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['nullable', Rule::in(array_keys(Unidad::ESTADOS))],
            'ubicacion' => ['nullable', 'string', 'max:120'],
            'notas' => ['nullable', 'string', 'max:1000'],
            // Por qué se movió. Va al kardex, no a la unidad: es el motivo de
            // ESTE cambio, no una nota permanente del aparato.
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $cambios = [];

        foreach (['estado', 'ubicacion', 'notas'] as $campo) {
            if (! $request->has($campo)) {
                continue;
            }

            $valor = $datos[$campo] ?? null;

            // Vacío es NULL, nunca cadena vacía: una ubicación en blanco y una
            // ubicación sin poner son la misma cosa, y guardarlas distinto
            // llena los filtros de valores fantasma.
            $cambios[$campo] = is_string($valor) && trim($valor) === '' ? null : $valor;
        }

        if ($cambios === []) {
            throw ValidationException::withMessages([
                'estado' => 'No se envió ningún cambio.',
            ]);
        }

        // El estado es obligatorio en la tabla: mandarlo vacío lo dejaría sin
        // valor en vez de «sin cambio», que es lo que se quiso decir.
        if (array_key_exists('estado', $cambios) && $cambios['estado'] === null) {
            unset($cambios['estado']);
        }

        try {
            app(AjusteDeUnidad::class)->aplicar(
                $unidad,
                $cambios,
                notas: $datos['motivo'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $unidad->refresh()->load([
            'producto.marca',
            'producto.categoria',
            'compra.proveedor',
            'ventaDetalle.venta',
            'movimientos.user',
            'movimientos.origen',
        ]);

        return response()->json([
            'mensaje' => 'Unidad actualizada.',
            'data' => (new UnidadResource($unidad))->conDetalle()->resolve($request),
        ]);
    }

    /**
     * Cuántas unidades hay en cada estado, para las pestañas del listado.
     *
     * Se calcula con una sola consulta agrupada y no con siete `count()`: en un
     * inventario grande, siete viajes a la base para pintar unas pestañas se
     * notan en el teléfono.
     *
     * @return array<string, int>
     */
    private function resumen(?int $productoId): array
    {
        $porEstado = Unidad::query()
            ->when($productoId !== null, fn ($q) => $q->where('producto_id', $productoId))
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        // Todos los estados, también los que están a cero: una pestaña que
        // aparece y desaparece según el stock del día desconcierta más de lo
        // que ahorra.
        return collect(array_keys(Unidad::ESTADOS))
            ->mapWithKeys(fn (string $estado): array => [$estado => (int) ($porEstado[$estado] ?? 0)])
            ->all();
    }

    /**
     * Guarda el serial leído por la cámara.
     *
     * El serial es único en toda la tabla, así que un duplicado no es un error
     * técnico que haya que esconder: casi siempre significa que **este aparato
     * ya se registró antes**, o que se está escaneando el código de barras
     * equivocado. Se responde con un 422 y un mensaje que dice cuál es la otra
     * unidad, que es lo que el almacenero necesita para resolverlo.
     */
    public function registrarSerial(Request $request, Unidad $unidad): JsonResponse
    {
        $datos = $request->validate([
            'serial' => ['required', 'string', 'max:100'],
        ]);

        $serial = trim($datos['serial']);

        // `trim` puede dejarlo vacío aunque `required` haya pasado (un serial
        // de solo espacios). Vacío tiene que ser NULL, nunca cadena vacía: el
        // índice único rechazaría la segunda unidad sin serial.
        if ($serial === '') {
            throw ValidationException::withMessages([
                'serial' => 'El serial no puede estar en blanco.',
            ]);
        }

        // El duplicado se comprueba a mano y no con `Rule::unique`, por dos
        // razones: se mira DESPUÉS del trim («ABC123 » y «ABC123» son el mismo
        // serial) y sin distinguir mayúsculas, y así el mensaje puede decir en
        // qué unidad está ya ese serial en vez de un «ya está registrado» que
        // deja al almacenero sin saber dónde buscar.
        $ocupado = Unidad::query()
            ->whereKeyNot($unidad->id)
            ->whereRaw('LOWER(serial) = ?', [mb_strtolower($serial)])
            ->first();

        if ($ocupado !== null) {
            throw ValidationException::withMessages([
                'serial' => "Ese serial ya está registrado en la unidad {$ocupado->codigo_interno}.",
            ]);
        }

        $unidad->update(['serial' => $serial]);

        return response()->json([
            'mensaje' => 'Serial registrado.',
            'data' => new UnidadResource($unidad->fresh()),
        ]);
    }
}
