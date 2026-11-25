<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Ecoponto;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/*
 * QR CODE DA PLACA DO ECOPONTO (assinado, não dá para forjar)
 * ---------------------------------------------------------------
 * Conteúdo:  ECO-01|emp=1|ep=3|sig=<assinatura>
 * A assinatura é um HMAC-SHA256 feito com o QR_SECRET do .env + o segredo_qr do ponto.
 * Sem esses segredos, ninguém consegue montar um QR válido.
 *
 * Modo dinâmico (opcional, para o Ecoponto simulado num celular na apresentação):
 *            ECO-01|emp=1|ep=3|exp=1763300000|sig=<assinatura>
 * O "exp" entra na assinatura e o QR só vale até esse horário (60 segundos).
 */
class EcopontoQrService
{
    public const SEGUNDOS_DINAMICO = 60;

    public function payload(Ecoponto $ecoponto, bool $dinamico = false): array
    {
        $mensagem = "{$ecoponto->codigo}|emp={$ecoponto->empresa_id}|ep={$ecoponto->id}";
        $expira = null;
        if ($dinamico) {
            $expira = now()->addSeconds(self::SEGUNDOS_DINAMICO);
            $mensagem .= '|exp=' . $expira->getTimestamp();
        }

        return [
            'payload'   => $mensagem . '|sig=' . $this->assinatura($ecoponto, $mensagem),
            'expira_em' => $expira,
        ];
    }

    public function assinatura(Ecoponto $ecoponto, string $mensagem): string
    {
        // Chave = QR_SECRET combinado com o segredo do ponto
        $chave = hash_hmac('sha256', (string) $ecoponto->segredo_qr, (string) config('ecotech.qr_secret'), true);
        return substr(hash_hmac('sha256', $mensagem, $chave), 0, 32);
    }

    // Lê o texto do QR e devolve o Ecoponto, ou 422 se o QR for inválido/expirado
    public function validar(string $qr): Ecoponto
    {
        $invalido = fn () => new ApiException(422, 'qr_invalido', 'QR inválido. Leia o QR Code da placa do Ecoponto.');

        $partes = explode('|', trim($qr));
        if (count($partes) < 4) {
            throw $invalido();
        }

        $codigo = array_shift($partes);
        $campos = [];
        foreach ($partes as $parte) {
            [$chave, $valor] = array_pad(explode('=', $parte, 2), 2, '');
            $campos[$chave] = $valor;
        }
        if (! isset($campos['emp'], $campos['ep'], $campos['sig']) || ! ctype_digit($campos['emp']) || ! ctype_digit($campos['ep'])) {
            throw $invalido();
        }

        $ecoponto = Ecoponto::query()->whereKey((int) $campos['ep'])
            ->where('empresa_id', (int) $campos['emp'])
            ->where('codigo', $codigo)
            ->first();
        if (! $ecoponto) {
            throw $invalido();
        }

        $mensagem = "{$codigo}|emp={$campos['emp']}|ep={$campos['ep']}" . (isset($campos['exp']) ? "|exp={$campos['exp']}" : '');
        if (! hash_equals($this->assinatura($ecoponto, $mensagem), $campos['sig'])) {
            throw $invalido();
        }

        if (isset($campos['exp']) && (! ctype_digit($campos['exp']) || (int) $campos['exp'] < now()->getTimestamp())) {
            throw new ApiException(422, 'qr_expirado', 'Este QR Code expirou. Leia o código de novo na tela do Ecoponto.');
        }

        if ($ecoponto->status !== 'ativo') {
            throw new ApiException(422, 'qr_invalido', 'Este Ecoponto não está funcionando agora.');
        }

        return $ecoponto;
    }

    // Imagem SVG do QR (gerada no servidor com a biblioteca bacon/bacon-qr-code)
    public function svg(string $payload, int $tamanho = 320): string
    {
        $renderer = new ImageRenderer(new RendererStyle($tamanho, 2), new SvgImageBackEnd());
        return (new Writer($renderer))->writeString($payload);
    }
}
