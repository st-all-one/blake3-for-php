Documento comum — válido para PHP 7.2, 7.4 e 8.4. Recortado para as pastas de cada versão.

# Configuração do PHP

Como configurar o PHP via php.ini, `.user.ini` e funções de runtime. Tudo aqui
é válido em 7.2, 7.4 e 8.4.

## 1. Arquivos php.ini

O PHP é configurado por um ou mais arquivos `php.ini`. Locais típicos:

- Arquivo global (ex.: `/etc/php/7.4/cli/php.ini` ou equivalente do SAPI).
- Diretivas podem ser consultadas com `php --ini` e listadas com `phpinfo()`.

Sintaxe:

```ini
; comentário
error_reporting = E_ALL
display_errors = Off
memory_limit = 128M
```

Valores de tipo: `On`/`Off` (bool), números com sufixos `K`/`M`/`G`, strings.

## 2. Leitura e escrita em runtime

### `ini_get()`

```php
<?php
$limite = ini_get('memory_limit');
```

### `ini_set()`

Define uma diretiva para o script atual (algumas só são alteráveis no php.ini
ou em `.user.ini`, conforme o nível de alterabilidade `PHP_INI_USER`/`PHP_INI_PERDIR`).

```php
<?php
ini_set('display_errors', '0');
ini_set('error_log', '/var/log/app.log');
```

### `get_cfg_var()`

Retorna o valor de uma diretiva tal como definida no php.ini (mesmo que
modificada em runtime por `ini_set`).

```php
<?php
$original = get_cfg_var('memory_limit');
```

## 3. `.user.ini` (CGI/FastCGI)

Em SAPIs CGI/FastCGI, diretivas com nível `PHP_INI_PERDIR` ou `PHP_INI_USER`
podem ser definidas por diretório via arquivo `.user.ini`, que é processado por
requisição no diretório e seus filhos.

```ini
; .user.ini
display_errors = Off
log_errors = On
error_log = /var/log/app.user.ini.log
```

A varredura é controlada por `user_ini.filename` (padrão `.user.ini`) e
`user_ini.cache_ttl` (frequência de releitura em segundos). Não funciona em
SAPIs como CLI ou mod_php (Apache) — nessas, use `php.ini` ou `ini_set`.

## 4. Diretivas importantes (baseline)

| Diretiva | Descrição |
|---|---|
| `error_reporting` | nível de erros reportados (ex.: `E_ALL`) |
| `display_errors` | exibe erros na saída (dev=`On`, prod=`Off`) |
| `display_startup_errors` | exibe erros de inicialização |
| `log_errors` | registra erros em log (`On`) |
| `error_log` | destino do log |
| `short_open_tag` | habilita tags `<?` curtas (prefira `<?php`) |
| `expose_php` | expõe versão no header `X-Powered-By` (desligue em prod) |
| `max_execution_time` | tempo máximo de execução (s) |
| `memory_limit` | limite de memória por script |
| `variables_order` | ordem de população de EGPCS (`E,G,P,C,S`) |
| `request_order` | ordem de `$_REQUEST` (desde 5.3) |
| `default_charset` | charset padrão (UTF-8) (desde 5.6) |
| `enable_dl` | permite `dl()` (desligue em prod) |
| `zend.assertions` | 1=gerar, 0=omitir, -1=omitir+zerar (desde 7.0) |
| `assert.exception` | **deprecada no 8.3**; `assert()` já lança `AssertionError` |
| `session.cookie_secure` | cookie só via HTTPS |
| `session.cookie_httponly` | cookie inacessível a JS |
| `session.use_strict_mode` | recusa IDs não gerados pelo servidor |

## 5. `declare`

`declare` configura diretivas de execução por arquivo.

### `declare(strict_types=1)`

Escopo por arquivo; deve ser a primeira instrução. Habilita checagem estrita de
tipos (vide "Tipagem").

```php
<?php
declare(strict_types=1);
```

### `declare(ticks=1)`

Define um "tick" a cada N instruções de baixo nível, usado com
`register_tick_function()` (ex.: profiling, timeout por sinal). Cuidado: tem
custo de performance.

```php
<?php
declare(ticks=1);
register_tick_function(function () {
    // executado periodicamente
});
```

> `strict_types` e `ticks` têm escopo **por arquivo** (afetam apenas o arquivo
> onde aparecem), não propagam para arquivos incluídos.

## 6. Diretivas de assert (depuração)

```php
<?php
// zend.assertions só pode ser alterada no php.ini/linha de comando; não use ini_set().
// Em dev/test: zend.assertions=1; em produção: zend.assertions=-1.
assert($x > 0, 'x deve ser positivo');
```

Em produção, `zend.assertions = -1` remove os asserts completamente (zero
custo).

## 7. Diretrizes

- Em produção: `display_errors=Off`, `log_errors=On`, `expose_php=Off`,
  `enable_dl=Off`.
- Use `default_charset=UTF-8` para consistência de escape/validação.
- Prefira definições explícitas no php.ini para configurações globais; reserve
  `ini_set` para ajustes pontuais em runtime.
- `.user.ini` é conveniente em hospedagens CGI/FastCGI por diretório.
