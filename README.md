# Briefing S3 Mídia

Sistema em PHP e MariaDB para enviar briefings personalizados por link individual.

## Recursos

- link exclusivo e não previsível para cada cliente;
- rascunho salvo apenas no dispositivo do cliente;
- respostas gravadas no banco de dados;
- bloqueio automático depois do envio;
- painel protegido para criar clientes, copiar links, consultar, reabrir e exportar respostas;
- credenciais mantidas fora do repositório, na pasta privada da hospedagem.

## Publicação

Use `config.example.php` apenas como referência. A configuração real deve ser salva em `.private/briefing-config.php` e nunca enviada ao GitHub.
