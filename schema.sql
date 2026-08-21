CREATE TABLE IF NOT EXISTS clientes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(180) NOT NULL,
  email VARCHAR(254) NULL,
  telefone VARCHAR(40) NULL,
  token CHAR(48) NOT NULL,
  status ENUM('pendente','concluido') NOT NULL DEFAULT 'pendente',
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  concluido_em TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_clientes_token (token),
  KEY idx_clientes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS respostas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id BIGINT UNSIGNED NOT NULL,
  respostas_json LONGTEXT NOT NULL,
  enviado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_hash CHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  PRIMARY KEY (id),
  KEY idx_respostas_cliente (cliente_id, enviado_em),
  CONSTRAINT fk_respostas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
