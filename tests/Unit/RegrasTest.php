<?php

namespace Tests\Unit;

use App\Rules\LoginSeisLetras;
use App\Rules\SenhaValida;
use App\Support\Formato;
use App\Support\Texto;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/*
 * Regras de validação: CPF, CNPJ, login de 6 letras, senha e código de barras.
 */
class RegrasTest extends TestCase
{
    protected bool $seed = false;

    public function test_cpf_com_digito_verificador(): void
    {
        $this->assertTrue(Texto::cpfValido('529.982.247-25'));
        $this->assertTrue(Texto::cpfValido('11144477735'));
        $this->assertFalse(Texto::cpfValido('52998224724'));   // dígito errado
        $this->assertFalse(Texto::cpfValido('11111111111'));   // todos iguais
        $this->assertFalse(Texto::cpfValido('123'));
    }

    public function test_cnpj_com_digitos_verificadores(): void
    {
        $this->assertTrue(Texto::cnpjValido('11.222.333/0001-81'));
        $this->assertTrue(Texto::cnpjValido('44555666000181'));
        $this->assertFalse(Texto::cnpjValido('11222333000182'));
        $this->assertFalse(Texto::cnpjValido('00000000000000'));
        $this->assertFalse(Texto::cnpjValido('1122233300018'));
    }

    public function test_login_exatamente_seis_letras(): void
    {
        $ok = fn ($v) => Validator::make(['login' => $v], ['login' => [new LoginSeisLetras()]])->passes();
        $this->assertTrue($ok('anasil'));
        $this->assertTrue($ok('joãozé'));
        $this->assertFalse($ok('ana12a'));
        $this->assertFalse($ok('anasilv'));
        $this->assertFalse($ok('ana s'));
    }

    public function test_senha_regra_do_enunciado_e_regra_forte(): void
    {
        $passa = fn ($v, $regra = null) => Validator::make(['s' => $v], ['s' => [new SenhaValida($regra)]])->passes();

        // enunciado (SENHA_REGRA=enunciado): exatamente 8 letras
        $this->assertTrue($passa('abcdefgh', 'enunciado'));
        $this->assertTrue($passa('AbcdéfgH', 'enunciado'));
        $this->assertFalse($passa('abcdefg1', 'enunciado'));
        $this->assertFalse($passa('abcdefghi', 'enunciado'));

        // padrão = forte (a mesma regra do front)
        $this->assertSame('forte', config('ecotech.senha_regra'));
        $this->assertTrue($passa('Senha123'));
        $this->assertFalse($passa('abcdefgh'));

        // forte (empresa sempre): 8+, maiúscula, minúscula e número
        $this->assertTrue($passa('Empresa123', 'forte'));
        $this->assertFalse($passa('empresa123', 'forte'));
        $this->assertFalse($passa('Empresas', 'forte'));
        $this->assertFalse($passa('Em1', 'forte'));
    }

    public function test_codigo_de_barras_ean8_upca_ean13(): void
    {
        $this->assertTrue(Texto::codigoBarrasValido('7891910000197'));  // EAN-13
        $this->assertTrue(Texto::codigoBarrasValido('7896004004778'));  // EAN-13 corrigido
        $this->assertFalse(Texto::codigoBarrasValido('7896004004777')); // EAN-13 do PDF (dígito errado)
        $this->assertTrue(Texto::codigoBarrasValido('036000291452'));   // UPC-A
        $this->assertTrue(Texto::codigoBarrasValido('96385074'));       // EAN-8
        $this->assertFalse(Texto::codigoBarrasValido('12345'));
        $this->assertFalse(Texto::codigoBarrasValido('abcdefghijklm'));
    }

    public function test_formatos_brasileiros(): void
    {
        $this->assertSame('1.250', Formato::inteiro(1250));
        $this->assertSame('+30', Formato::comSinal(30));
        $this->assertSame('-80', Formato::comSinal(-80));
        $this->assertSame('12,5 kg', Formato::kg(12.5));
        $this->assertSame('***.982.247-**', Formato::cpfMascarado('52998224725'));
        $this->assertSame('(21) 99999-1234', Formato::telefone('21999991234'));
        $this->assertSame('(21) 3333-1234', Formato::telefone('2133331234'));
        $this->assertSame('20040-020', Formato::cep('20040020'));
        $this->assertSame('Ana M.', Formato::nomeAbreviado('Ana Maria da Silva'));
        $this->assertSame('10/11/2026 às 14:32', Formato::dataHora(\Illuminate\Support\Carbon::parse('2026-11-10 14:32:00', 'America/Sao_Paulo')));
        $this->assertSame('2026-11-10T17:32:00Z', Formato::iso(\Illuminate\Support\Carbon::parse('2026-11-10 14:32:00', 'America/Sao_Paulo')));
    }

    public function test_percentuais_inteiros_somam_100(): void
    {
        $p = \App\Services\EstatisticaService::percentuaisInteiros(['a' => 1, 'b' => 1, 'c' => 1]);
        $this->assertSame(100, array_sum($p));
        $this->assertSame(['a' => 34, 'b' => 33, 'c' => 33], $p);

        $p = \App\Services\EstatisticaService::percentuaisInteiros(['metal' => 0.52, 'papel' => 0.2, 'vidro' => 0.0]);
        $this->assertSame(100, array_sum($p));
        $this->assertSame(0, $p['vidro']);

        $this->assertSame(['x' => 0, 'y' => 0], \App\Services\EstatisticaService::percentuaisInteiros(['x' => 0, 'y' => 0]));
    }

    public function test_ultima_atividade_em_texto_curto(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-20 15:00:00', 'America/Sao_Paulo'));

        $this->assertSame('Hoje, 08h30', Formato::quando(\Illuminate\Support\Carbon::parse('2026-11-20 08:30:00', 'America/Sao_Paulo')));
        $this->assertSame('Ontem, 16h40', Formato::quando(\Illuminate\Support\Carbon::parse('2026-11-19 16:40:00', 'America/Sao_Paulo')));
        $this->assertSame('Há 3 dias', Formato::quando(\Illuminate\Support\Carbon::parse('2026-11-17 10:00:00', 'America/Sao_Paulo')));
        $this->assertSame('02/11/2026', Formato::quando(\Illuminate\Support\Carbon::parse('2026-11-02 10:00:00', 'America/Sao_Paulo')));
        $this->assertSame('Sem atividade ainda', Formato::quando(null));

        $this->assertSame('1.150', Formato::numero(1150));
        $this->assertSame('0,52', Formato::numero(0.52));
    }
}
