Documento comum — válido para PHP 7.2, 7.4 e 8.4. Recortado para as pastas de cada versão.

# Logs e Tratamento de Erros

Mecanismos de log e tratamento de erros disponíveis em PHP 7.2, 7.4 e 8.4.

## 1. Registro de mensagens: `error_log()`

```php
<?php
error_log('Algo aconteceu');                 // tipo 0: enviado ao log do PHP/Servidor
error_log('msg', 3, '/var/log/app.log');    // tipo 3: anexa a um arquivo
```

Tipos de `error_log()`:

| Tipo | Comportamento |
|---|---|
| `0` (padrão) | envia para o mecanismo de log do PHP (php.ini `error_log` ou syslog) |
| `1` | envia por e-mail (requer `additional_headers`) |
| `3` | anexa a um arquivo definido em `message`/`destination` |
| `4` | envia diretamente ao handler do SAPI (não expõe no script) |

## 2. Syslog

```php
<?php
openlog('minha-app', LOG_PID | LOG_ODELAY, LOG_LOCAL0);
syslog(LOG_INFO, 'Iniciando processo');
syslog(LOG_ERR, 'Falha ao conectar');
closelog();
```

Constantes de prioridade: `LOG_EMERG`, `LOG_ALERT`, `LOG_CRIT`, `LOG_ERR`,
`LOG_WARNING`, `LOG_NOTICE`, `LOG_INFO`, `LOG_DEBUG`.

## 3. Manipuladores de erro personalizados

### `set_error_handler()`

Registra uma função para tratar erros que não sejam fatais (E_WARNING, E_NOTICE,
etc.). **Não** inclua o parâmetro `$errcontext` (depreciado no PHP 7.2 e
removido no 8.0) — use apenas `($errno, $errstr, $errfile, $errline)`.

```php
<?php
set_error_handler(function (int $errno, string $errstr, string $errfile = '', int $errline = 0) {
    if (!(error_reporting() & $errno)) {
        return false; // não manipular se suprimido via @
    }
    error_log(sprintf('[%d] %s em %s:%d', $errno, $errstr, $errfile, $errline));
    return true; // impede o handler interno do PHP
});
```

> Aviso: erros do tipo `E_ERROR`, `E_PARSE`, `E_CORE_*` e `E_COMPILE_*` não são
> capturados por `set_error_handler`.

### `set_exception_handler()`

Captura exceções não tratadas (não derivam de `Throwable` em todos os casos —
em 7+ praticamente todas são `Throwable`).

```php
<?php
set_exception_handler(function (Throwable $e) {
    error_log('Exceção não tratada: ' . $e->getMessage());
    http_response_code(500);
});
```

### `register_shutdown_function()`

Executado no encerramento do script — útil para capturar erros fatais via
`error_get_last()`.

```php
<?php
register_shutdown_function(function () {
    $erro = error_get_last();
    if ($erro !== null && in_array($erro['type'], [E_ERROR, E_PARSE], true)) {
        error_log('Erro fatal: ' . $erro['message']);
    }
});
```

## 4. Disparo de erros: `trigger_error()`

```php
<?php
if ($condicaoInvalida) {
    trigger_error('Condição inválida detectada', E_USER_WARNING);
}
```

Níveis usáveis com `trigger_error`: `E_USER_ERROR`, `E_USER_WARNING`,
`E_USER_NOTICE`, `E_USER_DEPRECATED`.

## 5. Níveis de erro

| Constante | Significado |
|---|---|
| `E_ERROR` | erro fatal em tempo de execução |
| `E_WARNING` | aviso em tempo de execução (não fatal) |
| `E_NOTICE` | nota (possível problema) |
| `E_PARSE` | erro de sintaxe em tempo de compilação |
| `E_DEPRECATED` | uso de recurso obsoleto |
| `E_USER_ERROR` / `E_USER_WARNING` / `E_USER_NOTICE` / `E_USER_DEPRECATED` | disparados pelo usuário |
| `E_RECOVERABLE_ERROR` | erro capturável (quase fatal) |
| `E_STRICT` | sugestões de compatibilidade (inyectadas) |
| `E_ALL` | todos os níveis (máscara) |

`error_reporting()` controla quais níveis são reportados.

## 6. Inspeção de erros em runtime

- `error_get_last(): ?array` — retorna o último erro (ou `null`).
- `error_clear_last()` (PHP 7.0+) — limpa o último erro registrado.

```php
<?php
$ultimo = error_get_last();
error_clear_last();
```

## 7. Diretivas de configuração (php.ini)

| Diretiva | Descrição |
|---|---|
| `error_reporting` | nível de erros reportados |
| `display_errors` | exibe erros na saída (desligue em produção) |
| `display_startup_errors` | exibe erros de inicialização |
| `log_errors` | registra erros em log |
| `error_log` | destino do log (arquivo ou syslog) |

Exemplo em produção:

```php
<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/var/log/php-app.log');
error_reporting(E_ALL);
```

Em desenvolvimento, ative `display_errors`.

## 8. Diretrizes

- Nunca exiba erros brutos para o usuário final em produção (`display_errors=0`).
- Centralize logs em `error_log`/`syslog` e trate exceções não capturadas com
  `set_exception_handler`.
- Use `register_shutdown_function` + `error_get_last` para detectar erros fatais.
- Prefira exceções (`throw`) a `trigger_error` para fluxos de erro esperados da
  aplicação; use `E_USER_*` para avisos de depreciação/uso incorreto.
- Não use o parâmetro `$errcontext` em handlers (removido no 8.0).
