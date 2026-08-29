<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VentaResource;
use App\Models\QrCobro;
use App\Models\Unidad;
use App\Models\Venta;
use App\Support\ProrrateoDeGastos;
use App\Support\RegistroDeVenta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Punto de venta desde el teléfono.
 *
 * Es la única parte de la API que ESCRIBE, y existe por una razón concreta: en
 * el mostrador se vende con el aparato en la mano, y la cámara del teléfono lee
 * su etiqueta mucho más rápido de lo que se teclea un serial de doce
 * caracteres.
 *
 * Toda la lógica de negocio sigue viviendo en `RegistroDeVenta`, el mismo
 * servicio que usa el POS web: descuento máximo por producto, guardia de la
 * doble venta, reparto del pago mixto y kardex. Este controlador solo traduce
 * la petición del teléfono a lo que ese servicio espera.
 */
class PosController extends Controller
{
    /**
     * Busca aparatos vendibles por serial, código interno, SKU o nombre.
     *
     * Es lo que consume el escáner: al leer un código de barras se manda tal
     * cual y, si coincide exactamente con un serial o un código interno, la
     * respuesta lo marca como `exacto` para que la app lo agregue sola al
     * carrito sin pedir confirmación.
     */
    public function buscar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'termino' => ['required', 'string', 'min:2', 'max:100'],
            // Lo manda el escáner. Tecleando no se envía: quien escribe media
            // palabra no espera que le digan que «no existe en el inventario».
            'escaneado' => ['nullable', 'boolean'],
        ]);

        $termino = trim($datos['termino']);
        $escaneado = (bool) ($datos['escaneado'] ?? false);

        // La coincidencia exacta se busca APARTE, con su propia consulta, y no
        // filtrando la lista de abajo. Antes se hacía sobre lo ya filtrado, y
        // eso tenía un agujero: la lista se corta en 12 resultados ordenados
        // por código interno, así que un serial de fabricante que además fuese
        // parte del nombre de un producto popular podía quedarse fuera del
        // corte y el aparato correcto no se agregaba al carrito. Escanear tiene
        // que dar siempre el mismo resultado, dependa o no de cuántos aparatos
        // parecidos haya en stock.
        $exacta = Unidad::query()
            ->with('producto.marca')
            ->disponibles()
            ->where(fn ($q) => $q->whereRaw('LOWER(serial) = ?', [mb_strtolower($termino)])
                ->orWhereRaw('LOWER(codigo_interno) = ?', [mb_strtolower($termino)]))
            ->first();

        $unidades = Unidad::query()
            ->with('producto.marca')
            ->disponibles()
            // La exacta se antepone a mano más abajo: sacarla de la lista evita
            // que salga dos veces.
            ->when($exacta !== null, fn ($q) => $q->whereKeyNot($exacta->id))
            ->where(function ($q) use ($termino) {
                $q->where('serial', 'like', "%{$termino}%")
                    ->orWhere('codigo_interno', 'like', "%{$termino}%")
                    ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', "%{$termino}%")
                        ->orWhere('sku', 'like', "%{$termino}%"));
            })
            ->orderBy('codigo_interno')
            ->limit(12)
            ->get();

        // El aparato leído encabeza la lista: es el que se está vendiendo.
        if ($exacta !== null) {
            $unidades = $unidades->prepend($exacta);
        }

        return response()->json([
            'data' => $unidades->map(fn (Unidad $u): array => $this->aparato($u))->values(),
            'meta' => [
                'exacto' => $exacta?->id,
                'total' => $unidades->count(),
                // Solo cuando la cámara leyó algo y ese algo no se puede
                // vender: explica por qué, en vez de dejar la pantalla vacía.
                'diagnostico' => $escaneado && $exacta === null
                    ? $this->diagnosticar($termino)
                    : null,
            ],
        ]);
    }

    /**
     * Explica por qué un código escaneado no entró al carrito.
     *
     * Sin esto, escanear la etiqueta de un aparato ya vendido y escanear una
     * etiqueta de otra tienda dan exactamente el mismo resultado —una lista
     * vacía—, y desde el mostrador no hay forma de saber cuál de las dos cosas
     * pasó. Son problemas distintos: uno se resuelve buscando la venta, el otro
     * revisando si el aparato llegó a darse de alta.
     *
     * La búsqueda es sobre el código EXACTO y sin filtrar por estado: aquí
     * interesan justamente las unidades que el buscador de arriba descarta.
     *
     * @return array<string, mixed>
     */
    private function diagnosticar(string $termino): array
    {
        $unidad = Unidad::query()
            ->with(['producto', 'ventaDetalle.venta'])
            ->where(fn ($q) => $q->whereRaw('LOWER(serial) = ?', [mb_strtolower($termino)])
                ->orWhereRaw('LOWER(codigo_interno) = ?', [mb_strtolower($termino)]))
            ->first();

        if ($unidad === null) {
            return [
                'tipo' => 'desconocido',
                'titulo' => 'Código no registrado',
                'detalle' => "Ningún aparato del inventario tiene el código «{$termino}». "
                    .'Puede ser el código de barras del fabricante en vez de la '
                    .'etiqueta de la tienda, o un aparato que todavía no se '
                    .'recepcionó en su compra.',
            ];
        }

        $venta = $unidad->ventaDetalle?->venta;

        // Una venta anulada devuelve la unidad al stock. Si aparece aquí como
        // vendida con su venta anulada, el estado se quedó desincronizado y
        // decirle «ya se vendió» al cajero lo mandaría a buscar un recibo que
        // no existe.
        $detalle = match ($unidad->estado) {
            'vendido' => $venta === null
                ? 'Este aparato figura como vendido, pero su venta no aparece. Revísalo en el panel antes de entregarlo.'
                : ($venta->estado === 'anulada'
                    ? "Se vendió en la venta {$venta->codigo} y esa venta está anulada, "
                        .'pero el aparato no volvió al stock. Hay que corregirlo en el panel.'
                    : "Ya se vendió el {$venta->vendida_en?->format('d/m/Y')} "
                        ."en la venta {$venta->codigo}."),
            'reservado' => 'Está reservado para un cliente. Libéralo en el panel si la reserva ya no vale.',
            'devuelto' => 'Fue devuelto y todavía no se revisó. Hay que darlo de alta otra vez antes de venderlo.',
            'danado' => 'Está marcado como dañado, así que no se puede vender.',
            'garantia' => 'Está en garantía: salió a reparación y no es vendible mientras tanto.',
            'perdido' => 'Está dado por perdido en el inventario.',
            default => 'No está disponible para la venta.',
        };

        return [
            'tipo' => 'no_vendible',
            'titulo' => Unidad::ESTADOS[$unidad->estado] ?? 'No disponible',
            'detalle' => $detalle,
            'codigo_interno' => $unidad->codigo_interno,
            'serial' => $unidad->serial,
            'producto' => $unidad->producto?->nombre,
            'estado' => $unidad->estado,
            // Para que la app pueda ofrecer «ver la venta» de un toque.
            'venta_id' => $venta?->estado === 'completada' ? $venta->id : null,
        ];
    }

    /**
     * QR de cobro vigentes, para mostrárselos al cliente en la pantalla.
     */
    public function qrs(Request $request): JsonResponse
    {
        return response()->json([
            'data' => QrCobro::vigentes()
                ->orderBy('fecha_limite')
                ->get()
                ->map(fn (QrCobro $qr): array => [
                    'id' => $qr->id,
                    'nombre' => $qr->nombre,
                    'banco' => $qr->banco,
                    'titular' => $qr->titular,
                    'imagen_url' => $qr->imagen_url,
                    'fecha_limite' => $qr->fecha_limite?->toDateString(),
                    'dias_restantes' => $qr->dias_restantes,
                ])
                ->values(),
        ]);
    }

    /**
     * Cobra y registra la venta.
     *
     * La app manda el precio PACTADO de cada aparato, no el descuento: el
     * precio de lista lo pone el servidor desde la unidad, y la rebaja es la
     * resta. Así el teléfono no puede inventarse un precio de lista más alto
     * para colar un descuento que el producto no autoriza.
     */
    public function cobrar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'lineas' => ['required', 'array', 'min:1', 'max:50'],
            'lineas.*.unidad_id' => ['required', 'integer', 'distinct', Rule::exists('unidades', 'id')->whereNull('deleted_at')],
            'lineas.*.precio' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'cliente_id' => ['nullable', 'integer', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
            // METODOS_POS, no METODOS_PAGO: `tarjeta` y `transferencia` siguen
            // en la lista histórica para que el listado pueda mostrar ventas
            // viejas cobradas así, pero el mostrador ya no los ofrece. Validar
            // contra la lista larga dejaba cobrar desde el teléfono con un
            // método que en la web está retirado.
            'metodo_pago' => ['required', Rule::in(Venta::METODOS_POS)],
            'notas' => ['nullable', 'string', 'max:1000'],
            'qr_cobro_id' => ['nullable', 'integer', Rule::exists('qrs_cobro', 'id')->whereNull('deleted_at')],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'monto_qr' => ['nullable', 'numeric', 'min:0'],
            'comprobante' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $usaQr = in_array($datos['metodo_pago'], Venta::METODOS_CON_QR, true);

        if ($usaQr && ! $request->hasFile('comprobante')) {
            return response()->json([
                'message' => 'Falta el respaldo del pago por QR.',
                'errors' => ['comprobante' => ['Sube la foto del comprobante del banco.']],
            ], 422);
        }

        $unidades = Unidad::whereIn('id', array_column($datos['lineas'], 'unidad_id'))
            ->get()
            ->keyBy('id');

        $lineas = [];

        foreach ($datos['lineas'] as $linea) {
            $unidad = $unidades->get($linea['unidad_id']);

            if ($unidad === null || ! $unidad->esVendible()) {
                return response()->json([
                    'message' => 'Uno de los aparatos ya no está disponible. Revisa el carrito.',
                ], 422);
            }

            $lista = ProrrateoDeGastos::aCentavos($unidad->precio_venta);
            $cobrado = ProrrateoDeGastos::aCentavos($linea['precio']);

            // El precio de lista es el techo: cobrar por encima sería un
            // recargo, y este sistema no los maneja.
            if ($cobrado > $lista) {
                return response()->json([
                    'message' => "El aparato {$unidad->codigo_interno} no se puede cobrar por encima de su precio de referencia.",
                ], 422);
            }

            $lineas[] = [
                'unidad_id' => $unidad->id,
                'precio_unitario' => ProrrateoDeGastos::aDecimal($lista),
                // El tope autorizado lo vuelve a comprobar RegistroDeVenta.
                'descuento' => ProrrateoDeGastos::aDecimal($lista - $cobrado),
            ];
        }

        // El respaldo se guarda antes de abrir la transacción: escribir un
        // archivo no se deshace con un rollback, así que si la venta falla
        // queda una imagen huérfana (inofensiva) en vez de una venta sin
        // comprobante.
        $comprobante = $usaQr
            ? $request->file('comprobante')->store('comprobantes-qr', 'public')
            : null;

        try {
            $venta = app(RegistroDeVenta::class)->registrar(
                lineas: $lineas,
                cabecera: [
                    'cliente_id' => $datos['cliente_id'] ?? null,
                    'metodo_pago' => $datos['metodo_pago'],
                    'notas' => trim($datos['notas'] ?? '') !== '' ? trim($datos['notas']) : null,
                    'qr_cobro_id' => $usaQr ? ($datos['qr_cobro_id'] ?? null) : null,
                    'monto_efectivo' => $datos['monto_efectivo'] ?? '0',
                    'monto_qr' => $datos['monto_qr'] ?? '0',
                    'comprobante_qr' => $comprobante,
                ],
                userId: $request->user()->id,
            );
        } catch (RuntimeException $e) {
            // El servicio distingue los casos de negocio (aparato ya vendido,
            // descuento no autorizado, cobro que no cuadra) de los fallos
            // técnicos, que se propagan como 500.
            if ($comprobante !== null) {
                Storage::disk('public')->delete($comprobante);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new VentaResource(
            $venta->load(['detalles.unidad', 'detalles.producto', 'cliente.persona', 'user'])
        ))->response()->setStatusCode(201);
    }

    /**
     * @return array<string, mixed>
     */
    private function aparato(Unidad $unidad): array
    {
        $precio = (float) $unidad->precio_venta;
        $tope = (float) ($unidad->producto?->descuento_maximo ?? 0);

        return [
            'unidad_id' => $unidad->id,
            'codigo_interno' => $unidad->codigo_interno,
            'serial' => $unidad->serial,
            'producto' => $unidad->producto?->nombre,
            'producto_id' => $unidad->producto_id,
            'sku' => $unidad->producto?->sku,
            'marca' => $unidad->producto?->marca?->nombre,
            // El precio de lista es la referencia; el tope, lo máximo que el
            // mostrador puede rebajar de él.
            'precio_venta' => $precio,
            'descuento_maximo' => $tope,
            'precio_minimo' => round(max($precio - $tope, 0), 2),
        ];
    }
}
