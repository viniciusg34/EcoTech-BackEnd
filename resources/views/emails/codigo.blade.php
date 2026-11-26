<!doctype html>
<html lang="pt-BR">
  <body style="margin:0;padding:0;background:#F4EFE6;font-family:Arial,Helvetica,sans-serif;color:#1F2A1F;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4EFE6;padding:32px 12px;">
      <tr>
        <td align="center">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:16px;overflow:hidden;">
            <tr>
              <td style="background:#3E5A3F;padding:20px 28px;color:#F4EFE6;font-size:20px;font-weight:bold;">
                🌿 EcoTech
              </td>
            </tr>
            <tr>
              <td style="padding:28px;">
                <p style="margin:0 0 12px;font-size:16px;">Olá, {{ $nome }}!</p>
                <p style="margin:0 0 20px;font-size:15px;line-height:1.5;">{{ $motivo }}</p>
                <p style="margin:0 0 20px;text-align:center;">
                  <span style="display:inline-block;background:#EEF3EE;border:2px solid #8CA98C;border-radius:12px;padding:14px 22px;font-size:32px;letter-spacing:8px;font-weight:bold;color:#1F2A1F;">{{ $codigo }}</span>
                </p>
                <p style="margin:0 0 8px;font-size:14px;color:#3E5A3F;">O código vale por {{ $minutos }} minutos.</p>
                <p style="margin:0;font-size:13px;color:#6B7A6B;line-height:1.5;">
                  Se não foi você, ignore este e-mail. Nunca passe este código para ninguém:
                  a equipe EcoTech não pede códigos por telefone ou mensagem.
                </p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
