<?php
/*
 * VALIDAÇÕES DO LADO DO SERVIDOR
 * ---------------------------------------------------------------
 * O front-end também valida, mas qualquer pessoa pode pular o front
 * e mandar dados direto para a API (pelo Postman, por exemplo).
 * Por isso o back-end SEMPRE confere de novo.
 *
 * Cada função devolve true/false (ou o valor limpo), sem mexer no banco.
 */

/* ---------- Funções de limpeza ---------- */

// Tira espaços duplicados e das pontas: "  Ana   Maria " -> "Ana Maria"
function limparEspacos(string $texto): string
{
    return trim(preg_replace('/\s+/u', ' ', $texto));
}

// Deixa só os números: "123.456.789-09" -> "12345678909"
function somenteNumeros(string $texto): string
{
    return preg_replace('/\D/', '', $texto);
}

// Para comparar respostas do 2FA sem ligar para acento/maiúscula:
// "  Maria  José " e "maria jose" ficam iguais.
function normalizarTexto(string $texto): string
{
    $mapa = [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
    ];
    return strtr(mb_strtolower(limparEspacos($texto), 'UTF-8'), $mapa);
}

// Aceita "25/12/2000" ou "2000-12-25" e devolve sempre "2000-12-25" (ou null se inválida)
function normalizarData(string $data): ?string
{
    $data = trim($data);
    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
        $d = DateTime::createFromFormat('!' . $formato, $data);
        if ($d && $d->format($formato) === $data) {
            return $d->format('Y-m-d');
        }
    }
    return null;
}

/* ---------- Regras do enunciado ---------- */

// Regra 2: nome com 15 a 80 caracteres, só letras (com acento) e espaços
function validarNome(string $nome): bool
{
    $tamanho = mb_strlen($nome, 'UTF-8');
    return $tamanho >= 15 && $tamanho <= 80 && preg_match('/^[\p{L} ]+$/u', $nome) === 1;
}

// Regra 3: CPF com dígito verificador
function validarCPF(string $cpf): bool
{
    $cpf = somenteNumeros($cpf);

    // Precisa ter 11 números e não pode ser tudo igual (111.111.111-11 passa na conta, mas é inválido)
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    // Calcula os 2 dígitos verificadores (posições 10 e 11)
    for ($posicao = 9; $posicao < 11; $posicao++) {
        $soma = 0;
        for ($i = 0; $i < $posicao; $i++) {
            $soma += (int) $cpf[$i] * (($posicao + 1) - $i);
        }
        $digito = ((10 * $soma) % 11) % 10;
        if ((int) $cpf[$posicao] !== $digito) {
            return false;
        }
    }
    return true;
}

// Regra 4: CEP com 8 números (aceita 20000-000 ou 20000000)
function validarCEP(string $cep): bool
{
    return preg_match('/^\d{5}-?\d{3}$/', trim($cep)) === 1;
}

// Regra 5: telefone no formato (+55)XX-XXXXXXXX
// Aceitamos 8 ou 9 números depois do traço, porque celular hoje tem 9.
function validarTelefone(string $telefone): bool
{
    return preg_match('/^\(\+55\)\d{2}-\d{8,9}$/', trim($telefone)) === 1;
}

// Regra 6: login com exatamente 6 letras (sem número, sem acento)
function validarLogin(string $login): bool
{
    return preg_match('/^[A-Za-z]{6}$/', $login) === 1;
}

// Regra 7: senha com exatamente 8 letras (a criptografia é feita depois, com password_hash)
function validarSenha(string $senha): bool
{
    return preg_match('/^[A-Za-z]{8}$/', $senha) === 1;
}

function validarEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Data de nascimento válida e que não esteja no futuro
function validarDataNascimento(string $data): bool
{
    $normalizada = normalizarData($data);
    return $normalizada !== null && $normalizada <= date('Y-m-d');
}

// Sexo: aceita "M", "F", "O" ou as palavras. Devolve a letra (ou null se inválido).
function normalizarSexo(string $sexo): ?string
{
    $mapa = ['m' => 'M', 'masculino' => 'M', 'f' => 'F', 'feminino' => 'F', 'o' => 'O', 'outro' => 'O'];
    return $mapa[normalizarTexto($sexo)] ?? null;
}
