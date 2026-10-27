Complementar ao documento comum — específico do PHP 8.4.

# Erros e Exceções no PHP 8.4

Este documento cobre o tratamento de erros e exceções no PHP 8.4, incluindo a
hierarquia `Throwable`, manipuladores customizados e boas práticas. Todo o código
é válido e executável em 8.4. Nenhuma funcionalidade removida antes do 8.4 é
utilizada. Para logging, veja `06-logs.md` e `17-logs-84.md`.

## 1. Tipos de erro

- `E_ERROR`, `E_WARNING`, `E_PARSE`, `E_NOTICE`
- `E_DEPRECATED` / `E_USER_DEPRECATED`
- `E_STRICT` (depreciado desde 8.4 — não defina como alvo)
- `E_ALL` (30719 no 8.4 = soma de todas as máscaras relevantes)

> No 8.4, `E_STRICT` não é mais emitido; `E_USER_ERROR` está depreciado. Use
> exceções em vez de `trigger_error(..., E_USER_ERROR)`.

## 2. `error_reporting` e exibição

```php
error_reporting(E_ALL);
ini_set('display_errors', '0');  // produção: nunca exibir
ini_set('log_errors', '1');
ini_set('error_log', '/var/log/php/php-error.log');
```

## 3. Manipulador de erro customizado

```php
set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false; // não tratar se mascarado
    }
    error_log("Erro [$errno]: $errstr em $errfile:$errline");
    return true; // impedir handler interno
});
```

Em 8.4, certos erros são convertidos em exceções (ex.: `TypeError`,
`ValueError`, `ArithmeticError`); `set_error_handler` não captura exceções.

## 4. Hierarquia `Throwable`

```
Throwable
├── Error
│   ├── TypeError, ValueError, ArithmeticError, DivisionByZeroError,
│   │   ArgumentCountError, CompileError, ParseError, UnhandledMatchError, ...
└── Exception
    ├── RuntimeException, LogicException, InvalidArgumentException, ...
```

Toda exceção e erro lançável implementa `Throwable`.

## 5. Exceções customizadas

```php
class DominioException extends \Exception
{
    public readonly string $codigoNegocio;

    public function __construct(string $msg, string $codigo)
    {
        $this->codigoNegocio = $codigo;
        parent::__construct($msg);
    }

    public const REGRA_INVALIDA = 'REGRA_INVALIDA';
}

throw new DominioException('Regra violada', DominioException::REGRA_INVALIDA);
```

## 6. `try` / `catch` / `finally`

```php
try {
    $valor = perigoso();
} catch (\TypeError $e) {
    error_log($e->getMessage());
} catch (\Throwable $e) {
    error_log($e::class . ': ' . $e->getMessage());
} finally {
    fecharRecurso(); // sempre executa
}
```

## 7. `throw` como expressão (8.0)

```php
$f = $arquivo ?? throw new \InvalidArgumentException('arquivo ausente');
```

## 8. `exit` / `throw` e fluxo

- `throw` interrompe e propaga até um `catch` ou torna-se erro fatal se não
  capturado.
- `exit`/`die` encerra o script imediatamente (não é capturável). Evite em
  bibliotecas; prefira lançar exceções.

## 9. Atributos sobre exceções (nota)

É possível marcar classes de exceção com atributos (8.0+), úteis para frameworks
de mapeamento de erro HTTP:

```php
#[HttpStatus(404)]
class NaoEncontrado extends \Exception {}
```

## 10. Boas práticas

- Capture tipos específicos antes de `Throwable`.
- Logue com contexto (`$e->getTraceAsString()`).
- Use `#[\Deprecated]` (8.4) em vez de `trigger_error` para APIs obsoletas.
- Não use exceções para controle de fluxo normal.
