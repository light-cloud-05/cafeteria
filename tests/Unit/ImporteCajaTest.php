<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Caja\ImporteCaja;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ImporteCajaTest extends TestCase
{
    public function test_conversion_y_aritmetica_exactas_sin_floats(): void
    {
        $this->assertSame(10, ImporteCaja::centavos('0.10'));
        $this->assertSame(120, ImporteCaja::centavos('1.2'));
        $this->assertSame('0.30', ImporteCaja::decimal(ImporteCaja::sumar(10, 20)));
        $this->assertSame('-0.01', ImporteCaja::decimal(ImporteCaja::centavos('0.29') - ImporteCaja::centavos('0.30')));
        $this->assertSame('99999999999999.99', ImporteCaja::decimal(ImporteCaja::centavos('99999999999999.99')));
        $this->assertSame('0.00', ImporteCaja::decimal(ImporteCaja::centavos(0)));
    }

    public function test_rechaza_floats_formatos_ambiguos_negativos_y_excesos(): void
    {
        foreach ([0.1, 1.0, -1, '-0.01', '1.001', '1e2', '1,00', ' 1.00', '+1', '01.00',
            '100000000000000.00', null, [], true] as $invalido) {
            try {
                ImporteCaja::centavos($invalido);
                $this->fail('Debe rechazar el importe ambiguo o fuera de rango.');
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function test_rechaza_desbordamiento_del_acumulado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ImporteCaja::sumar(ImporteCaja::MAXIMO_CENTAVOS, 1);
    }

    public function test_rechaza_formatear_importe_fuera_de_capacidad(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ImporteCaja::decimal(ImporteCaja::MAXIMO_CENTAVOS + 1);
    }
}
