<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClienteResource;
use App\Models\Cliente;
use App\Models\Persona;
use App\Support\GeneradorCodigoCliente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Consulta de clientes desde la app.
 *
 * Solo lectura: registrar a un cliente se hace en el mostrador, dentro de la
 * venta, que es cuando la persona está delante para dar su carnet.
 */
class ClienteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'in:activos,archivados,todos'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $estado = $datos['estado'] ?? 'activos';

        $clientes = $this->consultaBase($request)
            ->when($estado === 'archivados', fn ($q) => $q->onlyTrashed())
            ->when($estado === 'todos', fn ($q) => $q->withTrashed())
            ->buscar($datos['buscar'] ?? null)
            ->orderBy('codigo')
            // Desempate estable para que no salten filas entre páginas.
            ->orderBy('id')
            ->paginate($datos['por_pagina'] ?? 20);

        return ClienteResource::collection($clientes);
    }

    /**
     * Alta rápida desde el mostrador, dentro de una venta.
     *
     * Mismas reglas que el modal del POS web, recortadas a lo que se puede
     * pedir sin frenar la cola: carnet, nombres, un apellido y celular. Si aquí
     * fueran más laxas se colarían datos que el panel rechaza.
     */
    public function store(Request $request): JsonResponse
    {
        $soloLetras = '/^[\p{L}\s\'\-]+$/u';

        $datos = $request->validate([
            'carnet' => [
                'required', 'string', 'regex:/^[0-9]{7,11}$/',
                Rule::unique('personas', 'carnet')->whereNull('deleted_at'),
            ],
            'nombres' => ['required', 'string', 'min:2', 'max:100', "regex:{$soloLetras}"],
            'apellido_paterno' => ['required_without:apellido_materno', 'nullable', 'string', 'min:2', 'max:60', "regex:{$soloLetras}"],
            'apellido_materno' => ['required_without:apellido_paterno', 'nullable', 'string', 'min:2', 'max:60', "regex:{$soloLetras}"],
            'celular' => ['nullable', 'string', 'regex:/^[0-9]{8}$/'],
        ], [
            'carnet.regex' => 'El carnet debe contener entre 7 y 11 números.',
            'carnet.unique' => 'Ya existe una persona registrada con este carnet.',
            'celular.regex' => 'El celular debe tener 8 números.',
            'apellido_paterno.required_without' => 'Debes registrar al menos un apellido.',
            'apellido_materno.required_without' => 'Debes registrar al menos un apellido.',
        ]);

        // Persona y ficha se crean juntas: si falla la segunda, no debe quedar
        // una persona suelta que el usuario cree que no registró.
        $cliente = DB::transaction(function () use ($datos): Cliente {
            $persona = Persona::create([
                'carnet' => $datos['carnet'],
                'nombres' => $datos['nombres'],
                'apellido_paterno' => $datos['apellido_paterno'] ?? null,
                'apellido_materno' => $datos['apellido_materno'] ?? null,
                'celular' => $datos['celular'] ?? null,
            ]);

            return app(GeneradorCodigoCliente::class)->crearCon(['persona_id' => $persona->id]);
        });

        return (new ClienteResource($cliente->load('persona')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $cliente): ClienteResource
    {
        // Con `withTrashed` a mano y no por route model binding: un cliente
        // archivado sigue teniendo historial y su ficha debe poder abrirse.
        $ficha = $this->consultaBase($request)
            ->withTrashed()
            ->when(
                $request->user()?->can('ventas.ver') ?? false,
                fn ($q) => $q->with([
                    // `cliente.persona` también: VentaResource lo lee, y con
                    // el modo estricto una relación sin cargar revienta.
                    'ventas' => fn ($v) => $v->with(['user', 'cliente.persona'])
                        ->withCount('detalles')
                        ->orderByDesc('vendida_en')
                        ->limit(10),
                ])
            )
            ->findOrFail((int) $cliente);

        return (new ClienteResource($ficha))->conDetalle();
    }

    /**
     * Base común. El resumen de compras se calcula con subconsultas y no con
     * una relación cargada: la lista solo necesita los tres números, y traer
     * las ventas de cada cliente para contarlas sería traer medio histórico.
     */
    private function consultaBase(Request $request): Builder
    {
        $consulta = Cliente::query()->with('persona');

        if (! ($request->user()?->can('ventas.ver') ?? false)) {
            return $consulta;
        }

        return $consulta
            // Solo las completadas: una venta anulada no es una compra.
            ->withCount(['ventas as compras_count' => fn ($q) => $q->completadas()])
            ->withSum(['ventas as importe_comprado' => fn ($q) => $q->completadas()], 'total')
            ->withMax(['ventas as ultima_compra' => fn ($q) => $q->completadas()], 'vendida_en');
    }
}
