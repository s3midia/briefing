<?php
// Exemplo apenas. Na Hostinger, salve a configuração real dentro de .private.
return [
    'base_url' => 'https://briefing.s3midiadigital.com.br',
    'db' => [
        'host' => 'localhost',
        'name' => 'NOME_DO_BANCO',
        'user' => 'USUARIO_DO_BANCO',
        'pass' => 'SENHA_DO_BANCO',
    ],
    'admin_password_salt' => 'SALT_ALEATORIO',
    'admin_password_hash' => 'HASH_PBKDF2_SHA256',
    'admin_password_iterations' => 240000,
    'ip_hash_key' => 'CHAVE_ALEATORIA_PRIVADA',
];
