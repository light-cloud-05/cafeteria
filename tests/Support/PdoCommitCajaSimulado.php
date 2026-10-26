<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PDOException;

/** PDO SQLite real en memoria; pérdida de respuesta sintética, sin red ni servidor. */
final class PdoCommitCajaSimulado extends PDO
{
    public string $fallo = 'ninguno';
    public int $intentosCommit = 0;

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->exec('PRAGMA foreign_keys = ON');
    }

    public function commit(): bool
    {
        $this->intentosCommit++;
        if ($this->fallo === 'antes') throw new PDOException('Lost connection during commit; password=SECRETO_SIMULADO');
        $resultado = parent::commit();
        if ($this->fallo === 'despues') throw new PDOException('Lost connection during commit; token=SECRETO_SIMULADO');
        return $resultado;
    }
}
