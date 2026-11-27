/*
 * EcoTech — envio do código por e-mail pelo seu Gmail (Google Apps Script)
 *
 * 1. Abra https://script.google.com e clique em "Novo projeto".
 * 2. Apague o que estiver lá e cole este arquivo inteiro.
 * 3. Troque o SEGREDO abaixo por uma senha longa sua (a mesma vai no Railway).
 * 4. Clique em "Implantar" > "Nova implantação" > tipo "App da Web":
 *      Executar como: Eu
 *      Quem pode acessar: Qualquer pessoa
 *    Autorize o acesso ao Gmail quando o Google pedir.
 * 5. Copie a "URL do app da Web" (termina em /exec) e coloque no Railway:
 *      APPS_SCRIPT_URL=https://script.google.com/macros/s/.../exec
 *      APPS_SCRIPT_SEGREDO=a mesma senha do passo 3
 *
 * Limite do Gmail comum: cerca de 100 destinatários por dia.
 */
const SEGREDO = 'TROQUE-POR-UMA-SENHA-LONGA';

function doPost(e) {
  let dados = {};
  try {
    dados = JSON.parse(e.postData.contents);
  } catch (erro) {
    return responder({ ok: false, erro: 'json_invalido' });
  }

  if (!dados.segredo || dados.segredo !== SEGREDO) {
    return responder({ ok: false, erro: 'segredo_invalido' });
  }
  if (!dados.para || !dados.assunto) {
    return responder({ ok: false, erro: 'faltam_dados' });
  }

  try {
    MailApp.sendEmail({
      to: dados.para,
      subject: dados.assunto,
      body: dados.texto || '',
      htmlBody: dados.html || undefined,
      name: 'EcoTech',
    });
    return responder({ ok: true, restantes_hoje: MailApp.getRemainingDailyQuota() });
  } catch (erro) {
    return responder({ ok: false, erro: String(erro) });
  }
}

function responder(objeto) {
  return ContentService.createTextOutput(JSON.stringify(objeto)).setMimeType(ContentService.MimeType.JSON);
}

// Para testar dentro do editor: troque o e-mail e clique em "Executar" com esta função selecionada
function testar() {
  MailApp.sendEmail('seuemail@gmail.com', 'Teste EcoTech', 'Se chegou, o envio está funcionando.');
}
