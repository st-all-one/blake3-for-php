Complementar ao documento comum — específico do PHP 8.4.

# Configuração do PHP 8.4

Este documento complementa `08-configuracao.md` com as diretivas e comportamentos
de configuração relevantes até o PHP 8.4, destacando as **mudanças de padrão do
8.4**. Todo o conteúdo é aplicável em 8.4.

## 1. Arquivos de configuração

- `php.ini` — configuração principal (por SAPI: `php.ini`, `php-fpm.ini`, CLI).
- `.user.ini` — diretivas por diretório (suportadas por SAPIs como FPM/CGI;
  reaplicadas por requisição).
- `declare(strict_types=1)` — declarado **por arquivo** no topo do script (não
  é global; afeta apenas aquele arquivo e os tipos de argumento em chamadas de
  funções definidas nele).

```php
<?php
declare(strict_types=1);

function somar(int $a, int $b): int
{
    return $a + $b;
}

somar(1, 2);      // ok
somar('1', '2');  // TypeError (strict) — sem coercão
```

- `declare(strict_types=1)` afeta apenas declarações de tipo de **argumentos**
  nas funções do arquivo; o tipo de retorno não é afetado por strict Types (ele
  sempre é estrito).

## 2. Mudanças de padrão no 8.4 (OPcache/JIT)

| Diretiva | Padrão no 8.4 | Nota |
| --- | --- | --- |
| `opcache.jit` | `"disable"` | era `"tracing"` até 8.3 |
| `opcache.jit_buffer_size` | `64M` | era `0` até 8.3 |

Veja detalhes e recomendações em `15-performance-84.md`. Para ativar o JIT:

```ini
opcache.enable=1
opcache.jit=tracing
opcache.jit_buffer_size=64M
```

## 3. `opcache.preload` (PHP 7.4)

Carrega scripts em memória no startup do SAPI.

```ini
opcache.preload=/var/www/app/preload.php
opcache.preload_user=www-data
```

Ver `15-performance-84.md` (seção 2) para exemplo de `preload.php`.

## 4. Novas diretivas / comportamentos do 8.4

O PHP 8.4 **não** introduziu muitas novas INIs de uso geral; as mudanças
relevantes são os padrões de JIT acima e deprecações de INIs de sessão (ver
`17-logs-84.md`). Diretivas confirmadas estáveis em 8.4:

- `error_log_mode` (introduzida no 8.2) — controla a máscara de permissão do
  arquivo definido em `error_log` quando o PHP o cria. Útil para que o log não
  fique com `0644` indevido em ambientes compartilhados.

```ini
error_log=/var/log/php/php-error.log
error_log_mode=0640
```

- `zend.assertions` — controle de asserções (desde 7.0): `-1` desliga e remove em
  produção; `1` liga. Desde o PHP 8.0, `assert()` lança `AssertionError` por padrão.
  As demais INIs `assert.*` (incluindo `assert.exception`) foram **deprecadas no 8.3**;
  não as configure.

```ini
zend.assertions=-1
```

- `default_charset` — UTF-8 (padrão desde 5.6). Mantenha para saída consistente.

## 5. Diretrizes de error reporting (8.4)

- O padrão de `error_reporting` passou a ser `E_ALL` desde o PHP 8.0 (inclui
  todas as categorias). Em 8.4, o valor numérico de `E_ALL` é **30719**
  (ver `17-logs-84.md`).
- `display_startup_errors` passou a `1` por padrão no 8.0; em produção, defina
  `display_errors=0` e `display_startup_errors=0`, enviando erros ao log.

```ini
error_reporting=E_ALL
display_errors=0
display_startup_errors=0
log_errors=1
error_log=/var/log/php/php-error.log
```

## 6. Configurações de sessão (8.4)

Várias INIs de sessão foram **depreciadas** no 8.4 (mudança desencorajada):
`session.use_only_cookies`, `session.use_trans_sid`, `session.trans_sid_tags`,
`session.trans_sid_hosts`, `session.referer_check`, e alterar
`session.sid_length`/`session.sid_bits_per_character`. A constante `SID` também
foi deprecada. Veja `17-logs-84.md` e `14-seguranca-84.md`.

## 7. Exemplo de `php.ini` de produção (8.4)

```ini
; Engine
engine = On
short_open_tag = Off
precision = 14
output_buffering = 4096
zend.assertions = -1

; Erros
error_reporting = E_ALL
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/php-error.log
error_log_mode = 0640

; Charset
default_charset = "UTF-8"

; OPcache
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
opcache.preload = /var/www/app/preload.php
opcache.jit = tracing
opcache.jit_buffer_size = 64M

; Sessão (segura)
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = "Lax"
session.use_strict_mode = 1
```

## 8. Resumo de versões de diretivas

| Diretiva / recurso | Versão |
| --- | --- |
| `error_reporting` default `E_ALL` | 8.0 |
| `display_startup_errors` default `1` | 8.0 |
| `opcache.preload` | 7.4 |
| `opcache.jit` / `jit_buffer_size` | 8.0 |
| `opcache.jit` default `"disable"` / buffer `64M` | 8.4 |
| `error_log_mode` | 8.2 |
| `declare(strict_types=1)` por arquivo | 7.0 (estável) |

Todas as diretivas e exemplos acima são aplicáveis em PHP 8.4.
