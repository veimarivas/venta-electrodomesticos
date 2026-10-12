<?php

namespace App\Support;

use App\Models\Asistencia;
use App\Models\Caja;
use App\Models\Gasto;
use App\Models\PagoCredito;
use App\Models\Tienda;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * En qué tienda está trabajando alguien ahora mismo.
 *
 * Decide, en este orden:
 *  1. la tienda donde **marcó entrada hoy** (la asistencia manda: no se puede
 *     cambiar a mano mientras dure el turno);
 *  2. la que **eligió** para trabajar (`users.tienda_id`), para quien no marca
 *     asistencia —el administrador— o todavía no la marcó;
 *  3. la **principal** de la tienda (ajuste), y si solo hay una, esa.
 *
 * Con eso se anota de qué tienda salió cada venta, cobro y gasto, y qué caja se
 * usa. El stock no se separa: las tiendas venden del mismo depósito.
 *
 * Sin tiendas registradas devuelve null y todo funciona como antes.
 */
class TiendaActual
{
    public const PRINCIPAL = 'tienda_principal_id';

    /** @var Collection<int, Tienda>|null */
    private ?Collection $activas = null;

    /** @return Collection<int, Tienda> */
    public function activas(): Collection
    {
        return $this->activas ??= Tienda::query()->where('activa', true)->orderBy('nombre')->get();
    }

    public function hayTiendas(): bool
    {
        return $this->activas()->isNotEmpty();
    }

    public function principal(): ?Tienda
    {
        $id = (int) app(Ajustes::class)->valor(self::PRINCIPAL);
        $activas = $this->activas();

        return $activas->firstWhere('id', $id) ?? ($activas->count() === 1 ? $activas->first() : null);
    }

    /** La tienda de hoy para esta persona, o null. */
    public function de(?User $usuario): ?Tienda
    {
        return $this->resolver($usuario)['tienda'];
    }

    /**
     * La tienda y de dónde salió: asistencia | elegida | principal.
     *
     * @return array{tienda: ?Tienda, fuente: ?string}
     */
    public function resolver(?User $usuario): array
    {
        if (! $this->hayTiendas()) {
            return ['tienda' => null, 'fuente' => null];
        }

        if ($usuario !== null) {
            $turno = Asistencia::query()
                ->with('tienda')
                ->where('user_id', $usuario->id)
                ->whereDate('fecha', today())
                ->whereNull('salida_en')
                ->latest('entrada_en')
                ->first();

            if ($turno?->tienda !== null) {
                return ['tienda' => $turno->tienda, 'fuente' => 'asistencia'];
            }

            // Leído de los atributos crudos: un usuario cargado sin esa columna
            // (o recién creado) no la trae, y el modo estricto lo trataría
            // como un error en vez de como «no eligió».
            $elegidaId = $usuario->getAttributes()['tienda_id'] ?? null;

            if ($elegidaId && ($elegida = $this->activas()->firstWhere('id', (int) $elegidaId))) {
                return ['tienda' => $elegida, 'fuente' => 'elegida'];
            }
        }

        $principal = $this->principal();

        return ['tienda' => $principal, 'fuente' => $principal ? 'principal' : null];
    }

    /**
     * Una tienda que llega en la petición (la app la manda con una venta que se
     * cobró sin conexión) si es válida; si no, la de la persona.
     */
    public function idPara(?User $usuario, mixed $pedida = null): ?int
    {
        if ($pedida !== null && $pedida !== '' && $this->activas()->contains('id', (int) $pedida)) {
            return (int) $pedida;
        }

        return $this->de($usuario)?->id;
    }

    /**
     * La persona elige en qué tienda trabaja. No se puede mientras tenga un
     * turno de asistencia abierto en otra: ahí manda la asistencia.
     */
    public function elegir(User $usuario, int $tiendaId): Tienda
    {
        $tienda = $this->activas()->firstWhere('id', $tiendaId);

        if ($tienda === null) {
            throw new RuntimeException('Esa tienda no existe o no está activa.');
        }

        $actual = $this->resolver($usuario);

        if ($actual['fuente'] === 'asistencia' && $actual['tienda']->id !== $tienda->id) {
            throw new RuntimeException(
                "Marcaste entrada en {$actual['tienda']->nombre}: mientras dure tu turno, trabajas ahí."
            );
        }

        $usuario->forceFill(['tienda_id' => $tienda->id])->save();

        return $tienda;
    }

    /**
     * Marca la tienda principal y le asigna lo anterior a las tiendas: ventas,
     * cajas, cobros de cuotas y gastos que no tienen tienda.
     *
     * @return array{ventas: int, cajas: int, cobros: int, gastos: int}
     */
    public function hacerPrincipal(Tienda $tienda, ?int $userId = null): array
    {
        app(Ajustes::class)->guardar(self::PRINCIPAL, (string) $tienda->id, $userId);

        return DB::transaction(fn (): array => [
            'ventas' => Venta::query()->whereNull('tienda_id')->update(['tienda_id' => $tienda->id]),
            'cajas' => Caja::query()->whereNull('tienda_id')->update(['tienda_id' => $tienda->id]),
            'cobros' => PagoCredito::query()->whereNull('tienda_id')->update(['tienda_id' => $tienda->id]),
            'gastos' => Gasto::withTrashed()->whereNull('tienda_id')->update(['tienda_id' => $tienda->id]),
        ]);
    }

    /** Lo que ven el panel y la app. */
    public function paraMostrar(?User $usuario): array
    {
        ['tienda' => $tienda, 'fuente' => $fuente] = $this->resolver($usuario);

        return [
            'tienda' => $tienda ? ['id' => $tienda->id, 'nombre' => $tienda->nombre] : null,
            'fuente' => $fuente,
            'puede_cambiar' => $fuente !== 'asistencia',
            'tiendas' => $this->activas()->map(fn (Tienda $t): array => ['id' => $t->id, 'nombre' => $t->nombre])->values()->all(),
        ];
    }
}
