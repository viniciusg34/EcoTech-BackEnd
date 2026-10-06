<?php
/*
 * As 3 perguntas do 2FA (exatamente as do enunciado).
 * A chave (mae, nascimento, cep) é o que guardamos na sessão.
 */
const PERGUNTAS_2FA = [
    'mae'        => 'Qual o nome da sua mãe?',
    'nascimento' => 'Qual a data do seu nascimento?',
    'cep'        => 'Qual o CEP do seu endereço?',
];

const MAX_TENTATIVAS_2FA = 3;
const TEMPO_LIMITE_2FA   = 300; // segundos (5 minutos) para responder

const MENSAGEM_3_TENTATIVAS = '3 tentativas sem sucesso! Favor realizar Login novamente.';
