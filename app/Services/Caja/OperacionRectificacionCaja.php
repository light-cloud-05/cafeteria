<?php

declare(strict_types=1);

namespace App\Services\Caja;

use Illuminate\Support\Str;

/** Estado de una sola invocación; nunca almacena request, tokens, SQL ni excepciones. */
final class OperacionRectificacionCaja
{
    public readonly string $id;
    public ?int $actorId = null;
    public bool $cierreVerificado = false;
    public ?string $fecha = null;
    public ?string $motivo = null;
    public array $antes = [];
    public array $despues = [];
    public bool $iniciada = false;
    public bool $cuerpoFallo = false;
    public bool $activaAlFallar = false;
    public bool $rollbackConfirmado = false;
    public bool $commitConfirmado = false;
    public bool $incidenteIntentado = false;

    public function __construct(public readonly int $cierreId, public readonly int $versionEsperada)
    {
        $this->id = (string) Str::uuid();
    }
}
