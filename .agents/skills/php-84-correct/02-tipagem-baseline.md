Documento comum — válido para PHP 7.2, 7.4 e 8.4. Recortado para as pastas de cada versão.

# Tipagem (Baseline)

Este documento cobre os recursos de tipagem disponíveis em todas as versões
7.2, 7.4 e 8.4. Nada aqui foi removido ou tornado obsoleto nesse intervalo.

## 1. Declarações escalares de tipo (PHP 7.0+)

Funções e métodos podem declarar o tipo dos parâmetros como `int`, `float`,
`string` ou `bool` (e também `array`, `callable`, `iterable`, `object`,
`self`, `parent`, classes e interfaces).

```php
<?php
function somar(int $a, float $b): float {
    return $a + $b;
}
```

Sem `strict_types`, ocorre coerção ("weak mode"): um `float` passado como
`int` é truncado, uma `string` numérica vira número, etc. Com
`strict_types=1`, apenas valores do tipo exato são aceitos (caso contrário,
`TypeError`).

### Modo estrito por arquivo

`declare(strict_types=1)` afeta **apenas o arquivo em que é declarado** (escopo
por arquivo, não por chamada) e deve ser a primeira instrução do arquivo:

```php
<?php
declare(strict_types=1);

function idade(int $anos): int {
    return $anos;
}

idade("42"); // TypeError: argumento string, esperado int
```

## 2. Declarações de tipo de retorno (PHP 7.0+)

```php
<?php
function dobro(int $n): int {
    return $n * 2;
}

function fabricar(): stdClass {
    return new stdClass();
}
```

Em modo estrito, o valor retornado também é verificado. Em modo fraco, há
coerção de retorno. O tipo de retorno `void` não permite nenhum retorno (nem
`return null;`).

## 3. Tipos anuláveis `?Tipo` (PHP 7.1+)

```php
<?php
function buscar(string $id): ?array {
    // retorna array ou null
    return $cache[$id] ?? null;
}
```

## 4. Tipo de retorno `void` (PHP 7.1+)

```php
<?php
function logar(string $msg): void {
    error_log($msg);
    // nenhum "return" com valor
}
```

## 5. `iterable` (PHP 7.1+)

`iterable` aceita um `array` ou um objeto que implemente `Traversable`
(incluindo geradores). Útil para tipar parâmetros/retornos que serão percorridos.

```php
<?php
function total(iterable $colecao): int {
    $soma = 0;
    foreach ($colecao as $v) {
        $soma += $v;
    }
    return $soma;
}
```

## 6. Tipo `object` (PHP 7.2+)

O tipo `object` aceita qualquer objeto (independente da classe). Disponível a
partir do PHP 7.2, portanto válido neste baseline.

```php
<?php
function processar(object $alvo): void {
    // $alvo é qualquer instância de classe
}
```

## 7. Tipo `callable`

Indica que o argumento deve ser invocável: closure, string com nome de função,
`[$objeto, 'metodo']`, `[Classe::class, 'metodoEstatico']`, etc.

```php
<?php
function executar(callable $cb, array $args) {
    return $cb(...$args);
}
```

## 8. Type juggling e conversão explícita

Consulte o documento "Sintaxe Básica" para a tabela de coerção. Em resumo:
- Em modo fraco, escalares são coagidos; em modo estrito, lançam `TypeError`.
- `null` é aceito apenas em tipos anuláveis (`?int`).
- Compare com `===` para evitar coerção em igualdade.

## 9. Inspeção de tipos: `is_*()` e `gettype()`

Use as funções `is_*` para verificar o tipo em runtime:

| Função | Verifica |
|---|---|
| `is_int()` / `is_integer()` | int |
| `is_float()` / `is_double()` | float |
| `is_string()` | string |
| `is_bool()` | bool |
| `is_array()` | array |
| `is_object()` | object |
| `is_callable()` | invocável |
| `is_iterable()` | array ou Traversable |
| `is_null()` | null |
| `is_numeric()` | número ou string numérica |

`gettype()` retorna o nome do tipo como string (`"integer"`, `"double"`,
`"string"`, `"boolean"`, `"array"`, `"object"`, `"NULL"`, `"resource"`,
`"resource (closed)"`, `"unknown type"`). Prefira as funções `is_*` a comparar
a string de `gettype()` para robustez.

```php
<?php
function descrever($v): string {
    if (is_int($v))    return "inteiro";
    if (is_string($v)) return "texto";
    if (is_array($v))  return "vetor";
    if (is_iterable($v)) return "iterável";
    if (is_callable($v)) return "chamável";
    return "outro: " . gettype($v);
}
```

## 10. Diretrizes

- Habilite `declare(strict_types=1)` em arquivos de biblioteca para contratos
  de tipo previsíveis.
- Use tipos escalares e de retorno sempre que possível: melhoram a documentação
  e pegam erros cedo.
- Use `?Tipo` para valores que podem estar ausentes; reserve `null` para
  "sem valor", não para representar erro.
- `iterable` e `object` ampliam a expressividade sem acoplar a classes
  concretas.
