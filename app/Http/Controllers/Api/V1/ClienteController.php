<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClienteResource;
use App\Http\Resources\PersonaResource;
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
 * Clientes desde la app.
 *
 * La consulta es de solo lectura; lo único que escribe es el **alta dentro de
 * una venta**, que es cuando la persona está delante para dar su carnet.
 *
 * Esa alta tiene dos caminos, los mismos que el POS web: si quien compra ya
 * está en `personas` se le crea la ficha con los datos que tiene
 * ([desdePersona]), y solo si no aparece por ningún lado se registra de cero
 * ([store]).
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

    /**
     * Segundo peldaño del buscador de cliente: gente que YA está en `personas`
     * pero todavía no tiene ficha de cliente.
     *
     * Mucha gente está en `personas` porque trabaja aquí o porque alguien la
     * registró antes por otro motivo. Sin este paso, el mostrador teclea otra
     * vez su carnet, el índice único lo rechaza y la venta se atasca con el
     * cliente delante. Con él, se le abre la ficha con los datos que ya tiene.
     *
     * Se consulta solo cuando la búsqueda de clientes no devolvió nada; de eso
     * se encarga la app, igual que el POS web.
     */
    public function personasSinFicha(Request $request): AnonymousResourceCollection
    {
        $datos = $request->validate([
            'termino' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $personas = Persona::query()
            ->buscar($datos['termino'])
            // Las que ya son clientes salen por el buscador normal. Las que
            // tienen la ficha archivada sí aparecen: al elegirlas se restaura
            // la suya en vez de crear otra.
            ->whereDoesntHave('cliente')
            ->orderBy('apellido_paterno')
            ->orderBy('nombres')
            ->limit(6)
            ->get();

        return PersonaResource::collection($personas);
    }

    /**
     * Convierte en cliente a alguien que ya estaba en `personas`.
     *
     * No se piden datos porque ya los tiene todos: volver a registrarlos
     * duplicaría a la misma persona y el índice único del carnet lo rechazaría.
     */
    public function desdePersona(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'persona_id' => ['required', 'integer', Rule::exists('personas', 'id')->whereNull('deleted_at')],
        ]);

        $persona = Persona::with('cliente')->findOrFail($datos['persona_id']);

        $creada = false;

        if ($persona->cliente !== null) {
            // Pudo crearse desde el panel mientras esta pantalla estaba
            // abierta: se devuelve la que hay en vez de fallar.
            $cliente = $persona->cliente;
        } else {
            $archivada = Cliente::withTrashed()->where('persona_id', $persona->id)->first();

            if ($archivada !== null) {
                // Restaurar, no crear otra: la ficha conserva su código y su
                // historial de compras, y el índice único de `persona_id`
                // rechazaría la segunda.
                $archivada->restore();

                $cliente = $archivada;
            } else {
                $cliente = app(GeneradorCodigoCliente::class)->crearCon(['persona_id' => $persona->id]);
                $creada = true;
            }
        }

        // Se vuelve a consultar por `consultaBase` en vez de devolver el modelo
        // que tenemos a mano: `ClienteResource` lee `compras_count` y sus
        // hermanos, que solo existen si la consulta los agregó. Un modelo
        // recién creado lo perdona —Laravel no exige atributos en modelos que
        // acaba de insertar—, pero uno recuperado de la base lanza
        // `MissingAttributeException` y la respuesta se cae con un 500.
        $ficha = $this->consultaBase($request)->withTrashed()->findOrFail($cliente->id);

        return (new ClienteResource($ficha))
            ->response()
            ->setStatusCode($creada ? 201 : 200);
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
