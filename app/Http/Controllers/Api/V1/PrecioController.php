<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Support\PreciosDelDia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Precios del día desde el teléfono.
 *
 * Al empezar la jornada se revisan los precios de los productos con stock. El
 * último registrado es el que ofrece el punto de venta; el que trae cada unidad
 * queda solo como respaldo. Es lo mismo que la pantalla del panel.
 */
class PrecioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $servicio = app(PreciosDelDia::class);

        $filas = $servicio->paraRevisar()->map(fn ($fila): array => [
            'producto_id' => $fila->producto->id,
            'nombre' => $fila->producto->nombre,
            'categoria' => $fila->producto->categoria?->nombre,
            'imagen_url' => $fila->producto->imagen
                ? asset('storage/'.$fila->producto->imagen)
                : null,
            'disponibles' => $fila->disponibles,
            'costo' => $fila->costo,
            'precio_inicial' => $fila->precio_inicial,
            'precio_anterior' => $fila->precio_anterior,
            'precio_hoy' => $fila->precio_hoy,
        ])->values();

        return response()->json([
            'data' => $filas,
            'meta' => [
                'pendientes' => $servicio->pendientes(),
                'definidos' => $servicio->definidos(),
            ],
        ]);
    }

    /**
     * Estado liviano para el punto de venta: si los precios de hoy están
     * listos. Lo consulta cualquiera que pueda vender, sin necesidad de poder
     * administrar el catálogo, para saber si el cobro está habilitado.
     */
    public function estado(): JsonResponse
    {
        $servicio = app(PreciosDelDia::class);

        return response()->json([
            'data' => [
                'listos' => $servicio->listos(),
                'definidos' => $servicio->definidos(),
                'pendientes' => $servicio->pendientes(),
            ],
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'precios' => ['required', 'array', 'min:1', 'max:500'],
            'precios.*.producto_id' => [
                'required', 'integer',
                Rule::exists('productos', 'id')->whereNull('deleted_at'),
            ],
            'precios.*.precio' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
        ]);

        $mapa = [];

        foreach ($datos['precios'] as $fila) {
            $mapa[(int) $fila['producto_id']] = $fila['precio'];
        }

        // El precio tiene que superar al costo: vender por debajo sería regalar
        // el aparato. Se compara contra el mayor costo en stock.
        $servicio = app(PreciosDelDia::class);

        foreach ($mapa as $productoId => $precio) {
            $producto = Producto::find($productoId);

            if ($producto === null) {
                continue;
            }

            $costo = $servicio->costoReferencia($producto);

            if ($costo > 0 && (float) $precio <= $costo) {
                throw ValidationException::withMessages([
                    'precios' => "El precio de «{$producto->nombre}» tiene que ser mayor que su costo (Bs "
                        .number_format($costo, 2, ',', '.').').',
                ]);
            }
        }

        $guardados = $servicio->guardar($mapa, (int) $request->user()->id);

        return response()->json([
            'mensaje' => "Precios del día guardados ({$guardados} productos).",
        ]);
    }
}
