<?php

namespace App\Livewire\Ventas;

use App\Models\Cliente;
use App\Models\Persona;
use App\Models\QrCobro;
use App\Models\Unidad;
use App\Models\Venta;
use App\Support\GeneradorCodigoCliente;
use App\Support\GeneradorCodigoVenta;
use App\Support\ProrrateoDeGastos;
use App\Support\RegistroDeVenta;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Punto de venta.
 *
 * El flujo está pensado para el mostrador: se escanea o teclea el serial del
 * aparato, entra al carrito con su precio de lista, y al cobrar se registra
 * todo de una vez. No hay borrador ni pasos intermedios — la venta ocurre en
 * el momento en que el cliente paga.
 *
 * El precio de lista es una REFERENCIA fija que el cajero no toca: lo que se
 * teclea es el precio realmente pactado, y el descuento sale de la resta. Así
 * la rebaja siempre queda registrada como tal (y contra el tope autorizado del
 * producto) en vez de disolverse en un precio bajito sin explicación.
 */
class Pos extends Component
{
    use WithFileUploads;

    /** Serial, código interno, SKU o nombre del producto. */
    public string $buscar = '';

    /**
     * Carrito, en memoria hasta cobrar.
     *
     * `precio_lista` y `tope_descuento` son copias del catálogo tomadas al
     * agregar: viajan en el estado del componente y NO se confían al cobrar,
     * donde se vuelven a leer de la base de datos.
     *
     * @var array<int, array{unidad_id: int, precio_lista: string, precio: string, tope_descuento: string}>
     */
    public array $carrito = [];

    // ---- Cabecera ---------------------------------------------------------

    public ?int $clienteId = null;

    /** Buscador del selector de cliente. */
    public string $buscarCliente = '';

    public string $metodoPago = 'efectivo';

    public string $notas = '';

    // ---- Cobro por QR -----------------------------------------------------

    /** QR que se le muestra al cliente. */
    public ?int $qrCobroId = null;

    /** Captura del comprobante del banco, subida en el momento del cobro. */
    public $comprobante = null;

    /** Reparto del pago mixto, como cadenas para conservar lo tecleado. */
    public string $montoEfectivo = '';

    public string $montoQr = '';

    // ---- Alta rápida de cliente -------------------------------------------

    public string $nuevoCarnet = '';

    public string $nuevoNombres = '';

    public string $nuevoApellidoPaterno = '';

    public string $nuevoApellidoMaterno = '';

    public string $nuevoCelular = '';

    /** Venta recién registrada, para mostrar el comprobante. */
    public ?int $ventaRegistradaId = null;

    /** Línea que el modal de confirmación está preguntando si se quita. */
    public ?int $quitarIndice = null;

    /** Campos del alta rápida de cliente. */
    private const CAMPOS_CLIENTE = [
        'nuevoCarnet',
        'nuevoNombres',
        'nuevoApellidoPaterno',
        'nuevoApellidoMaterno',
        'nuevoCelular',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'clienteId' => ['nullable', 'integer', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
            // Solo lo que el mostrador ofrece hoy: tarjeta y transferencia
            // siguen en el enum por el histórico, pero no se cobran así.
            'metodoPago' => ['required', Rule::in(Venta::METODOS_POS)],
            'notas' => ['nullable', 'string', 'max:1000'],
            'carrito' => ['required', 'array', 'min:1'],
            'carrito.*.precio' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'comprobante' => [
                $this->pagoUsaQr ? 'required' : 'nullable',
                'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
            ],
            'qrCobroId' => [
                $this->pagoUsaQr ? 'required' : 'nullable',
                'integer',
                Rule::exists('qrs_cobro', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'carrito.required' => 'Agrega al menos un aparato a la venta.',
            'carrito.min' => 'Agrega al menos un aparato a la venta.',
            'carrito.*.precio.required' => 'Indica el precio de venta.',
            'carrito.*.precio.min' => 'El precio debe ser mayor a cero.',
            'metodoPago.required' => 'Elige el método de pago.',
            'qrCobroId.required' => 'Elige el QR con el que va a pagar el cliente.',
            'comprobante.required' => 'Sube el respaldo del pago por QR.',
            'comprobante.image' => 'El respaldo debe ser una imagen del comprobante.',
            'comprobante.max' => 'El respaldo no puede pesar más de 5 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'clienteId' => 'cliente',
            'metodoPago' => 'método de pago',
            'carrito' => 'aparatos',
            'carrito.*.precio' => 'precio',
            'qrCobroId' => 'QR de cobro',
            'comprobante' => 'respaldo del pago',
            'montoEfectivo' => 'monto en efectivo',
            'montoQr' => 'monto por QR',
            'nuevoCarnet' => 'carnet',
            'nuevoNombres' => 'nombres',
            'nuevoApellidoPaterno' => 'apellido paterno',
            'nuevoApellidoMaterno' => 'apellido materno',
            'nuevoCelular' => 'celular',
        ];
    }

    public function updated(string $campo): void
    {
        if (str_starts_with($campo, 'carrito.')) {
            $this->validateOnly($campo, $this->rules());

            // El precio tecleado se contrasta contra la referencia y el tope
            // del producto en cuanto se escribe: enterarse al cobrar, con el
            // cliente delante, llega tarde.
            if (preg_match('/^carrito\.(\d+)\.precio$/', $campo, $coincidencia)) {
                $this->revisarPrecio((int) $coincidencia[1]);
            }

            // Cambiar un precio mueve el total, y con él el reparto del mixto.
            $this->reajustarMixto();
        }

        if (in_array($campo, self::CAMPOS_CLIENTE, true)) {
            $this->validateOnly($campo, $this->reglasCliente(), $this->messages(), $this->validationAttributes());

            // Los apellidos se validan juntos: al llenar uno debe desaparecer
            // al momento el error de "al menos un apellido" del otro.
            if ($campo === 'nuevoApellidoPaterno' || $campo === 'nuevoApellidoMaterno') {
                $this->validateOnly(
                    $campo === 'nuevoApellidoPaterno' ? 'nuevoApellidoMaterno' : 'nuevoApellidoPaterno',
                    $this->reglasCliente(),
                    $this->messages(),
                    $this->validationAttributes()
                );
            }
        }
    }

    /**
     * Comprueba una línea contra su precio de referencia y el tope de rebaja.
     * El error se cuelga del propio input para que se vea en la fila.
     */
    private function revisarPrecio(int $indice): void
    {
        $linea = $this->carrito[$indice] ?? null;

        if ($linea === null || ! is_numeric($linea['precio'] ?? '')) {
            return;
        }

        $precio = ProrrateoDeGastos::aCentavos($linea['precio']);
        $lista = ProrrateoDeGastos::aCentavos($linea['precio_lista']);
        $tope = ProrrateoDeGastos::aCentavos($linea['tope_descuento']);

        if ($precio > $lista) {
            $this->addError(
                "carrito.{$indice}.precio",
                'El precio de referencia es el máximo: no se cobra por encima de Bs '.
                ProrrateoDeGastos::aDecimal($lista).'.'
            );

            return;
        }

        if ($lista - $precio > $tope) {
            $this->addError(
                "carrito.{$indice}.precio",
                $tope === 0
                    ? 'Este producto no admite descuento: cóbralo a Bs '.ProrrateoDeGastos::aDecimal($lista).'.'
                    : 'El descuento máximo de este producto es Bs '.ProrrateoDeGastos::aDecimal($tope).
                        ' (precio mínimo Bs '.ProrrateoDeGastos::aDecimal($lista - $tope).').'
            );
        }
    }

    // =======================================================================
    // Búsqueda de aparatos
    // =======================================================================

    /**
     * Aparatos que coinciden con lo buscado y no están ya en el carrito.
     *
     * Devuelve TODOS los estados, no solo los vendibles: cuando se escanea la
     * etiqueta de un aparato ya vendido, «no hay resultados» deja al cajero
     * sin saber si tecleó mal o si el aparato salió esta mañana. Con la unidad
     * delante, la respuesta es «este se vendió el martes en la VTA-…», que es
     * lo que hay que decirle al cliente.
     *
     * @return Collection<int, Unidad>
     */
    #[Computed]
    public function coincidencias(): Collection
    {
        $termino = trim($this->buscar);

        // Con menos de dos caracteres devolvería medio almacén.
        if (mb_strlen($termino) < 2) {
            return new Collection;
        }

        return Unidad::query()
            ->with(['producto.marca', 'ventaDetalle.venta'])
            ->whereNotIn('id', $this->unidadesEnCarrito())
            ->where(function ($q) use ($termino) {
                $q->where('serial', 'like', "%{$termino}%")
                    ->orWhere('codigo_interno', 'like', "%{$termino}%")
                    ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', "%{$termino}%")
                        ->orWhere('sku', 'like', "%{$termino}%"));
            })
            // Los vendibles primero: son los que se pueden cobrar.
            ->orderByRaw("estado = 'en_stock' desc")
            ->orderBy('codigo_interno')
            ->limit(12)
            ->get();
    }

    /** ¿Se buscó algo y no apareció ningún aparato, en ningún estado? */
    #[Computed]
    public function busquedaSinResultados(): bool
    {
        return mb_strlen(trim($this->buscar)) >= 2 && $this->coincidencias->isEmpty();
    }

    /** @return array<int, int> */
    private function unidadesEnCarrito(): array
    {
        return array_column($this->carrito, 'unidad_id');
    }

    /**
     * Unidades del carrito, para pintarlo sin una consulta por fila.
     *
     * @return Collection<int, Unidad>
     */
    #[Computed]
    public function unidadesDelCarrito(): Collection
    {
        $ids = $this->unidadesEnCarrito();

        return $ids === []
            ? new Collection
            // Con la ficha entera del producto: el cajero tiene que poder
            // comprobar que el aparato del carrito es el que tiene en la mano.
            : Unidad::with(['producto.marca', 'producto.categoria'])
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');
    }

    /**
     * Clientes que coinciden con el buscador. Vender sin cliente es lo
     * habitual en tienda, así que el campo es opcional.
     *
     * @return Collection<int, Cliente>
     */
    #[Computed]
    public function clientesEncontrados(): Collection
    {
        $termino = trim($this->buscarCliente);

        if (mb_strlen($termino) < 2) {
            return new Collection;
        }

        return Cliente::query()
            ->with('persona')
            ->buscar($termino)
            ->limit(8)
            ->get();
    }

    /**
     * Personas que ya están en el sistema pero todavía no son clientes.
     *
     * Es el segundo peldaño de la búsqueda: mucha gente está en `personas`
     * porque trabaja aquí, o porque alguien la registró antes por otro motivo.
     * Volver a teclear su carnet y sus apellidos duplicaría a la misma persona
     * —y el índice único del carnet lo rechazaría—, así que en vez de eso se le
     * abre la ficha de cliente con los datos que ya tiene.
     *
     * Solo se consulta cuando no hay clientes que ofrecer: si ya aparece como
     * cliente, esta lista sobra.
     *
     * @return Collection<int, Persona>
     */
    #[Computed]
    public function personasEncontradas(): Collection
    {
        $termino = trim($this->buscarCliente);

        if (mb_strlen($termino) < 2 || $this->clientesEncontrados->isNotEmpty()) {
            return new Collection;
        }

        return Persona::query()
            ->with('cliente')
            ->buscar($termino)
            // Las que ya son clientes salen por el camino de arriba. Las que
            // tienen la ficha archivada sí aparecen aquí: al elegirlas se
            // restaura la suya en vez de crear otra (lo rechazaría el índice
            // único de `persona_id`).
            ->whereDoesntHave('cliente')
            ->orderBy('apellido_paterno')
            ->orderBy('nombres')
            ->limit(6)
            ->get();
    }

    /**
     * ¿Se buscó a alguien y no apareció ni como cliente ni como persona?
     * Entonces sí toca registrarlo de cero.
     */
    #[Computed]
    public function clienteSinResultados(): bool
    {
        return mb_strlen(trim($this->buscarCliente)) >= 2
            && $this->clientesEncontrados->isEmpty()
            && $this->personasEncontradas->isEmpty();
    }

    /** ¿Hay algo escrito en el buscador? El alta no se ofrece sin buscar. */
    #[Computed]
    public function buscandoCliente(): bool
    {
        return mb_strlen(trim($this->buscarCliente)) >= 2;
    }

    #[Computed]
    public function clienteElegido(): ?Cliente
    {
        return $this->clienteId === null
            ? null
            : Cliente::with('persona')->find($this->clienteId);
    }

    // =======================================================================
    // Carrito
    // =======================================================================

    public function agregar(int $unidadId): void
    {
        $this->autorizar('ventas.crear');

        $unidad = Unidad::with('producto')->find($unidadId);

        if ($unidad === null || ! $unidad->esVendible()) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Ese aparato ya no está disponible.');

            return;
        }

        if (in_array($unidad->id, $this->unidadesEnCarrito(), true)) {
            return;
        }

        $precio = number_format((float) $unidad->precio_venta, 2, '.', '');

        $this->carrito[] = [
            'unidad_id' => $unidad->id,
            // Referencia intocable: es el precio con el que salió la unidad.
            'precio_lista' => $precio,
            // Lo que se va a cobrar. Arranca en el de lista, sin descuento.
            'precio' => $precio,
            'tope_descuento' => number_format(
                (float) ($unidad->producto?->descuento_maximo ?? 0), 2, '.', ''
            ),
        ];

        // El buscador se limpia para escanear el siguiente aparato.
        $this->buscar = '';
        $this->resetValidation('carrito');
        $this->reajustarMixto();
    }

    /**
     * Pide confirmación antes de sacar un aparato del carrito.
     *
     * Un toque de más en el mostrador borraba la línea sin aviso, y con el
     * carrito medio armado no siempre se nota cuál faltaba.
     */
    public function confirmarQuitar(int $indice): void
    {
        if (! isset($this->carrito[$indice])) {
            return;
        }

        $this->quitarIndice = $indice;

        $this->dispatch('abrir-modal-quitar-linea');
    }

    /** Aparato que el modal está preguntando si se quita. */
    #[Computed]
    public function lineaAQuitar(): ?Unidad
    {
        if ($this->quitarIndice === null) {
            return null;
        }

        $linea = $this->carrito[$this->quitarIndice] ?? null;

        return $linea === null ? null : ($this->unidadesDelCarrito[$linea['unidad_id']] ?? null);
    }

    public function quitar(): void
    {
        $indice = $this->quitarIndice;

        $this->quitarIndice = null;
        $this->dispatch('cerrar-modal-quitar-linea');

        if ($indice === null || ! isset($this->carrito[$indice])) {
            return;
        }

        unset($this->carrito[$indice]);

        // Reindexar: con huecos, Livewire deja de casar cada fila con sus
        // inputs y el usuario ve precios en la fila equivocada.
        $this->carrito = array_values($this->carrito);

        $this->resetValidation('carrito');
        $this->reajustarMixto();
    }

    /** Devuelve una línea a su precio de referencia, sin descuento. */
    public function quitarDescuento(int $indice): void
    {
        if (! isset($this->carrito[$indice])) {
            return;
        }

        $this->carrito[$indice]['precio'] = $this->carrito[$indice]['precio_lista'];

        $this->resetValidation("carrito.{$indice}.precio");
        $this->reajustarMixto();
    }

    /** Aplica el descuento máximo autorizado del producto. */
    public function aplicarDescuentoMaximo(int $indice): void
    {
        $linea = $this->carrito[$indice] ?? null;

        if ($linea === null) {
            return;
        }

        $minimo = ProrrateoDeGastos::aCentavos($linea['precio_lista'])
            - ProrrateoDeGastos::aCentavos($linea['tope_descuento']);

        $this->carrito[$indice]['precio'] = ProrrateoDeGastos::aDecimal(max($minimo, 0));

        $this->resetValidation("carrito.{$indice}.precio");
        $this->reajustarMixto();
    }

    public function confirmarVaciar(): void
    {
        if ($this->carrito === []) {
            return;
        }

        $this->dispatch('abrir-modal-vaciar-carrito');
    }

    /** Vaciar desde el modal: además de limpiar, lo cierra. */
    public function vaciarCarrito(): void
    {
        $this->vaciar();

        $this->dispatch('cerrar-modal-vaciar-carrito');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Carrito vacío. Puedes empezar de nuevo.');
    }

    /**
     * Deja la caja como recién abierta. También se usa tras cobrar, por eso no
     * toca ningún modal: de eso se encarga quien la llama.
     */
    public function vaciar(): void
    {
        $this->reset([
            'carrito', 'clienteId', 'buscarCliente', 'notas', 'buscar',
            'qrCobroId', 'comprobante', 'montoEfectivo', 'montoQr', 'quitarIndice',
        ]);

        $this->metodoPago = 'efectivo';
        $this->resetValidation();
    }

    public function elegirCliente(int $clienteId): void
    {
        $this->clienteId = $clienteId;
        $this->buscarCliente = '';
    }

    public function quitarCliente(): void
    {
        $this->clienteId = null;
        $this->buscarCliente = '';
    }

    /**
     * Convierte en cliente a alguien que ya estaba en `personas` y lo deja
     * elegido en la venta en curso.
     *
     * No se piden datos: los tiene todos. Registrarlos otra vez duplicaría a la
     * misma persona y el índice único del carnet lo rechazaría.
     */
    public function registrarPersonaComoCliente(int $personaId): void
    {
        $this->autorizar('clientes.crear');

        $persona = Persona::with('cliente')->find($personaId);

        if ($persona === null) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Esa persona ya no existe.');

            return;
        }

        // Puede haber llegado aquí con la ficha ya creada en otra pestaña.
        if ($persona->cliente !== null) {
            $this->elegirCliente($persona->cliente->id);

            return;
        }

        $archivada = Cliente::withTrashed()->where('persona_id', $persona->id)->first();

        if ($archivada !== null) {
            // Restaurar, no crear otra: la ficha conserva su código y su
            // historial de compras, y el índice único rechazaría la segunda.
            $archivada->restore();

            $this->elegirCliente($archivada->id);

            $this->dispatch('toast', tipo: 'success', mensaje:
                "{$persona->nombre_completo} vuelve al listado con su código {$archivada->codigo}.");

            return;
        }

        $cliente = app(GeneradorCodigoCliente::class)->crearCon(['persona_id' => $persona->id]);

        $this->elegirCliente($cliente->id);

        $this->dispatch('toast', tipo: 'success', mensaje:
            "{$persona->nombre_completo} ahora es cliente con el código {$cliente->codigo}.");
    }

    // =======================================================================
    // Alta rápida de cliente
    // =======================================================================

    /**
     * Reglas del alta rápida. Son las mismas del módulo de clientes recortadas
     * a lo que se puede pedir en mostrador sin frenar la cola: si aquí fueran
     * más laxas, se colarían datos que el otro formulario rechaza.
     *
     * @return array<string, mixed>
     */
    private function reglasCliente(): array
    {
        $soloLetras = '/^[\p{L}\s\'\-]+$/u';

        return [
            'nuevoCarnet' => [
                'required', 'string', 'regex:/^[0-9]{7,11}$/',
                Rule::unique('personas', 'carnet')->whereNull('deleted_at'),
            ],
            'nuevoNombres' => ['required', 'string', 'min:2', 'max:100', "regex:$soloLetras"],
            'nuevoApellidoPaterno' => ['required_without:nuevoApellidoMaterno', 'string', 'min:2', 'max:60', "regex:$soloLetras"],
            'nuevoApellidoMaterno' => ['required_without:nuevoApellidoPaterno', 'string', 'min:2', 'max:60', "regex:$soloLetras"],
            'nuevoCelular' => ['nullable', 'string', 'regex:/^[0-9]{8}$/'],
        ];
    }

    #[Computed]
    public function clienteNuevoValido(): bool
    {
        return Validator::make(
            $this->only(self::CAMPOS_CLIENTE),
            $this->reglasCliente(),
            $this->messages(),
            $this->validationAttributes()
        )->passes();
    }

    /**
     * Abre el alta rápida aprovechando lo tecleado en el buscador: si son solo
     * números es un carnet, si no, el nombre. Evita escribirlo dos veces.
     *
     * Solo se puede registrar a alguien DESPUÉS de haberlo buscado sin
     * encontrarlo. Sin esa condición, la prisa del mostrador acabaría creando
     * un cliente nuevo cada vez que el mismo señor vuelve a comprar, y su
     * historial quedaría repartido entre varias fichas.
     */
    public function abrirNuevoCliente(): void
    {
        $this->autorizar('clientes.crear');

        // La vista ya deshabilita el botón; esto es la defensa del servidor,
        // porque el método es invocable directamente.
        if (! $this->clienteSinResultados) {
            $this->dispatch('toast', tipo: 'warning', mensaje: 'Busca primero al cliente por su carnet o nombre.');

            return;
        }

        $termino = trim($this->buscarCliente);

        $this->reset(self::CAMPOS_CLIENTE);

        if (preg_match('/^[0-9]{7,11}$/', $termino)) {
            $this->nuevoCarnet = $termino;
        } elseif ($termino !== '') {
            $this->nuevoNombres = $termino;
        }

        $this->resetValidation();
        $this->dispatch('abrir-modal-cliente-pos');
    }

    /**
     * Registra a la persona y su ficha de cliente, y la deja ya elegida en la
     * venta que está en curso: quien abre este modal está a media venta.
     */
    public function guardarCliente(): void
    {
        $this->autorizar('clientes.crear');

        $datos = $this->validate(
            $this->reglasCliente(),
            $this->messages(),
            $this->validationAttributes()
        );

        // Persona y ficha se crean juntas: si falla la segunda, no debe quedar
        // una persona suelta que el usuario cree que no registró.
        $cliente = DB::transaction(function () use ($datos): Cliente {
            $persona = Persona::create([
                'carnet' => $datos['nuevoCarnet'],
                'nombres' => $datos['nuevoNombres'],
                // Los opcionales vacíos van como NULL: dos personas sin
                // apellido materno chocarían contra las reglas del otro módulo.
                'apellido_paterno' => $datos['nuevoApellidoPaterno'] ?: null,
                'apellido_materno' => $datos['nuevoApellidoMaterno'] ?: null,
                'celular' => $datos['nuevoCelular'] ?: null,
            ]);

            return app(GeneradorCodigoCliente::class)->crearCon(['persona_id' => $persona->id]);
        });

        $this->clienteId = $cliente->id;
        $this->buscarCliente = '';
        $this->reset(self::CAMPOS_CLIENTE);
        $this->resetValidation();

        $this->dispatch('cerrar-modal-cliente-pos');
        $this->dispatch('toast', tipo: 'success', mensaje: "Cliente {$cliente->codigo} registrado y asignado a esta venta.");
    }

    // =======================================================================
    // Cobro por QR y pago mixto
    // =======================================================================

    /** ¿El método elegido pasa por el banco? */
    #[Computed]
    public function pagoUsaQr(): bool
    {
        return in_array($this->metodoPago, Venta::METODOS_CON_QR, true);
    }

    /**
     * QR que se pueden mostrar hoy: activos y sin caducar.
     *
     * @return Collection<int, QrCobro>
     */
    #[Computed]
    public function qrsVigentes(): Collection
    {
        return QrCobro::vigentes()->orderBy('fecha_limite')->get();
    }

    #[Computed]
    public function qrElegido(): ?QrCobro
    {
        return $this->qrCobroId === null
            ? null
            : $this->qrsVigentes->firstWhere('id', $this->qrCobroId);
    }

    /**
     * Al cambiar de método se rehace el cobro entero: los montos y el QR de
     * un método no significan lo mismo en otro, y arrastrarlos deja importes
     * fantasma que el cajero no ve.
     */
    public function updatedMetodoPago(): void
    {
        $this->reset(['montoEfectivo', 'montoQr', 'comprobante']);
        $this->resetValidation(['comprobante', 'qrCobroId', 'montoEfectivo', 'montoQr']);

        if (! $this->pagoUsaQr) {
            $this->qrCobroId = null;

            return;
        }

        // Con un solo QR vigente no hay nada que elegir: se preselecciona.
        $this->qrCobroId ??= $this->qrsVigentes->first()?->id;

        // Los dos campos arrancan vacíos a propósito. Un reparto propuesto por
        // el sistema (mitad y mitad, por ejemplo) es un importe que nadie
        // contó: el cajero teclea lo que el cliente le entregó en mano y el
        // otro campo se completa con la diferencia.
    }

    /** Teclear el efectivo completa el QR con la diferencia, y al revés. */
    public function updatedMontoEfectivo(): void
    {
        $this->completarMixtoDesde('efectivo');
    }

    public function updatedMontoQr(): void
    {
        $this->completarMixtoDesde('qr');
    }

    private function completarMixtoDesde(string $origen): void
    {
        if ($this->metodoPago !== 'mixto') {
            return;
        }

        $campo = $origen === 'efectivo' ? 'montoEfectivo' : 'montoQr';

        // Borrar el campo vacía los dos: el reparto vuelve a estar sin hacer,
        // y dejar el otro con un importe daría por cobrada una parte que ya no
        // se sabe de dónde sale.
        if (trim((string) $this->{$campo}) === '') {
            $this->montoEfectivo = '';
            $this->montoQr = '';

            return;
        }

        if (! is_numeric($this->{$campo})) {
            return;
        }

        $tecleado = ProrrateoDeGastos::aCentavos($this->{$campo});

        // Ni negativo ni por encima del total: la parte que se teclea acota a
        // la otra, así que dejar pasar un exceso obligaría a mostrar una
        // diferencia negativa que no significa nada en caja.
        $tecleado = max(0, min($tecleado, $this->totalEnCentavos));
        $resto = $this->totalEnCentavos - $tecleado;

        $this->montoEfectivo = ProrrateoDeGastos::aDecimal($origen === 'efectivo' ? $tecleado : $resto);
        $this->montoQr = ProrrateoDeGastos::aDecimal($origen === 'efectivo' ? $resto : $tecleado);
    }

    /**
     * Recalcula el reparto del mixto cuando cambia el total (se agregó,
     * quitó o rebajó un aparato). Se conserva lo cobrado en efectivo, que es
     * lo que ya está físicamente en la caja, y el QR absorbe la diferencia.
     */
    private function reajustarMixto(): void
    {
        if ($this->metodoPago !== 'mixto') {
            return;
        }

        // Los totales están cacheados por petición y el carrito acaba de
        // cambiar: sin soltar la caché se repartiría el total anterior.
        $this->olvidarTotales();

        // Reparto todavía sin hacer: no hay nada que reajustar, y rellenarlo
        // ahora pondría en los campos un importe que el cajero no tecleó.
        if (! is_numeric($this->montoEfectivo)) {
            return;
        }

        $efectivo = min(ProrrateoDeGastos::aCentavos($this->montoEfectivo), $this->totalEnCentavos);

        $this->montoEfectivo = ProrrateoDeGastos::aDecimal(max($efectivo, 0));
        $this->montoQr = ProrrateoDeGastos::aDecimal(max($this->totalEnCentavos - $efectivo, 0));
    }

    /** Suelta la caché de los totales tras tocar el carrito. */
    private function olvidarTotales(): void
    {
        unset($this->subtotalEnCentavos, $this->descuentoEnCentavos, $this->totalEnCentavos);
    }

    public function quitarComprobante(): void
    {
        $this->reset('comprobante');
        $this->resetValidation('comprobante');
    }

    // =======================================================================
    // Totales en vivo
    // =======================================================================

    /** Todo en centavos enteros: en float los totales no cuadrarían. */
    #[Computed]
    public function subtotalEnCentavos(): int
    {
        return collect($this->carrito)->sum(
            fn (array $l): int => ProrrateoDeGastos::aCentavos($l['precio_lista'] ?? '0')
        );
    }

    /** Lo rebajado: la distancia entre el precio de referencia y el cobrado. */
    #[Computed]
    public function descuentoEnCentavos(): int
    {
        return collect($this->carrito)->sum(fn (array $l): int => $this->descuentoDeLinea($l));
    }

    /**
     * Descuento de una línea. Nunca negativo: cobrar por encima del precio de
     * referencia no es un "descuento negativo", es un error de tecleo que la
     * validación marca aparte.
     *
     * @param  array<string, string|int>  $linea
     */
    public function descuentoDeLinea(array $linea): int
    {
        if (! is_numeric($linea['precio'] ?? '')) {
            return 0;
        }

        return max(
            ProrrateoDeGastos::aCentavos($linea['precio_lista'] ?? '0')
                - ProrrateoDeGastos::aCentavos($linea['precio']),
            0
        );
    }

    #[Computed]
    public function totalEnCentavos(): int
    {
        return $this->subtotalEnCentavos - $this->descuentoEnCentavos;
    }

    /**
     * Ganancia estimada de la venta. Se muestra solo a quien puede ver costos.
     */
    #[Computed]
    public function gananciaEnCentavos(): int
    {
        $costos = $this->unidadesDelCarrito;

        $costoTotal = collect($this->carrito)->sum(
            fn (array $l): int => ProrrateoDeGastos::aCentavos(
                $costos[$l['unidad_id']]->costo_unitario ?? '0'
            )
        );

        return $this->totalEnCentavos - $costoTotal;
    }

    /** Lo que falta por repartir en un pago mixto; 0 cuando cuadra. */
    #[Computed]
    public function diferenciaMixtoEnCentavos(): int
    {
        $efectivo = is_numeric($this->montoEfectivo) ? ProrrateoDeGastos::aCentavos($this->montoEfectivo) : 0;
        $porQr = is_numeric($this->montoQr) ? ProrrateoDeGastos::aCentavos($this->montoQr) : 0;

        return $this->totalEnCentavos - $efectivo - $porQr;
    }

    /** ¿Se puede cobrar ya? */
    #[Computed]
    public function ventaValida(): bool
    {
        if ($this->carrito === [] || $this->totalEnCentavos <= 0) {
            return false;
        }

        foreach ($this->carrito as $linea) {
            if (! is_numeric($linea['precio'] ?? '') || (float) $linea['precio'] <= 0) {
                return false;
            }

            $precio = ProrrateoDeGastos::aCentavos($linea['precio']);
            $lista = ProrrateoDeGastos::aCentavos($linea['precio_lista']);

            // Ni por encima de la referencia ni por debajo del tope de rebaja.
            if ($precio > $lista || $lista - $precio > ProrrateoDeGastos::aCentavos($linea['tope_descuento'])) {
                return false;
            }
        }

        if (! $this->pagoUsaQr) {
            return true;
        }

        // Cobrar por QR sin respaldo dejaría la venta sin forma de conciliarse
        // contra el extracto del banco.
        if ($this->qrElegido === null || $this->comprobante === null) {
            return false;
        }

        return $this->metodoPago !== 'mixto'
            || ($this->diferenciaMixtoEnCentavos === 0 && ProrrateoDeGastos::aCentavos($this->montoQr) > 0);
    }

    #[Computed]
    public function codigoPrevisto(): string
    {
        return app(GeneradorCodigoVenta::class)->siguiente();
    }

    /** Venta recién cobrada, para el comprobante. */
    #[Computed]
    public function ventaRegistrada(): ?Venta
    {
        return $this->ventaRegistradaId === null
            ? null
            : Venta::with(['detalles.unidad', 'detalles.producto', 'cliente.persona', 'qrCobro'])
                ->find($this->ventaRegistradaId);
    }

    // =======================================================================
    // Cobro
    // =======================================================================

    /**
     * Repasa la venta antes de cobrarla.
     *
     * Registrar una venta es irreversible en la práctica —solo se anula, y la
     * anulación deja su rastro—, así que entre el botón y el cobro va un
     * resumen de lo que se va a guardar: aparatos, total, cliente y cómo paga.
     */
    public function confirmarCobro(): void
    {
        $this->autorizar('ventas.crear');

        $this->validate();

        if (! $this->ventaValida) {
            $this->addError('carrito', 'Revisa los precios y el cobro antes de registrar la venta.');

            return;
        }

        $this->dispatch('abrir-modal-confirmar-cobro');
    }

    public function cobrar(): void
    {
        $this->autorizar('ventas.crear');

        $this->validate();

        if (! $this->ventaValida) {
            $this->dispatch('cerrar-modal-confirmar-cobro');
            $this->addError('carrito', 'Revisa los precios y el cobro antes de registrar la venta.');

            return;
        }

        // El respaldo se guarda antes de abrir la transacción: escribir un
        // archivo no se puede deshacer con un rollback, así que si la venta
        // falla queda una imagen huérfana (inofensiva) en vez de una venta
        // registrada sin comprobante.
        $comprobante = $this->pagoUsaQr
            ? $this->comprobante->store('comprobantes-qr', 'public')
            : null;

        try {
            $venta = app(RegistroDeVenta::class)->registrar(
                lineas: array_map(fn (array $l): array => [
                    'unidad_id' => (int) $l['unidad_id'],
                    // Se registra el precio de referencia y la rebaja por
                    // separado: en el histórico tiene que verse qué se dejó
                    // de cobrar, no solo cuánto entró.
                    'precio_unitario' => $l['precio_lista'],
                    'descuento' => ProrrateoDeGastos::aDecimal($this->descuentoDeLinea($l)),
                ], $this->carrito),
                cabecera: [
                    'cliente_id' => $this->clienteId,
                    'metodo_pago' => $this->metodoPago,
                    'notas' => trim($this->notas) !== '' ? trim($this->notas) : null,
                    'qr_cobro_id' => $this->pagoUsaQr ? $this->qrCobroId : null,
                    'monto_efectivo' => $this->montoEfectivo,
                    'monto_qr' => $this->montoQr,
                    'comprobante_qr' => $comprobante,
                ],
                userId: auth()->id(),
            );
        } catch (RuntimeException $e) {
            // El servicio distingue los casos de negocio (aparato ya vendido,
            // descuento no autorizado, cobro que no cuadra) de los fallos
            // técnicos, que se propagan. El carrito se queda como estaba para
            // poder corregir y reintentar.
            $this->dispatch('cerrar-modal-confirmar-cobro');
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->vaciar();
        $this->ventaRegistradaId = $venta->id;

        $this->dispatch('cerrar-modal-confirmar-cobro');
        $this->dispatch('toast', tipo: 'success', mensaje: "Venta {$venta->codigo} registrada.");
        $this->dispatch('abrir-modal-venta-registrada');
    }

    /** Cierra el comprobante y deja la caja lista para la siguiente venta. */
    public function nuevaVenta(): void
    {
        $this->ventaRegistradaId = null;
        $this->vaciar();

        $this->dispatch('cerrar-modal-venta-registrada');
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.ventas.pos', [
            // Los tres del mostrador para elegir; el mapa completo para poder
            // nombrar el método de una venta vieja en el comprobante.
            'metodosPos' => Venta::METODOS_POS,
            'metodosPago' => Venta::METODOS_PAGO,
            'puedeVerCostos' => auth()->user()?->can('reportes.ver_costos') ?? false,
            'puedeCrearClientes' => auth()->user()?->can('clientes.crear') ?? false,
        ]);
    }
}
