Documento comum — válido para PHP 7.2, 7.4 e 8.4. Recortado para as pastas de cada versão.

# Programação Funcional (Baseline)

Recursos funcionais disponíveis em todas as versões 7.2, 7.4 e 8.4.

## 1. Closures (funções anônimas)

Closures são objetos da classe `Closure` que encapsulam código e estado do
escopo circundante via palavra-chave `use`.

```php
<?php
$multiplicador = 3;
$vezes = function (int $n) use ($multiplicador): int {
    return $n * $multiplicador;
};
echo $vezes(4); // 12
```

Você pode capturar por referência com `use (&$var)`. Para modificar uma
variável do escopo externo a partir da closure, use referência.

## 2. A classe `Closure`

### `Closure::bind()` e `Closure::bindTo()`

Permitem alterar o escopo (`$this`) e a visibilidade de uma closure, útil para
acessar membros protegidos/privados em testes ou ferramentas.

```php
<?php
class Segredo {
    private $valor = 42;
}

$fn = function () {
    return $this->valor;
};

$acesso = $fn->bindTo(new Segredo(), Segredo::class);
echo $acesso(); // 42
```

`Closure::bind($closure, $novoThis, $classe)` é o equivalente estático de
`bindTo`.

### `Closure::fromCallable()` (PHP 7.1+)

Converte um callable (string, `[obj, 'metodo']`, `[Classe::class, 'm']`) em um
objeto `Closure`, uniformizando o tratamento.

```php
<?php
function soma(int $a, int $b): int { return $a + $b; }

$c = Closure::fromCallable('soma');
echo $c(2, 3); // 5
```

## 3. Funções variádicas (`...`)

`...` na definição reúne os argumentos restantes em um array; `...` na chamada
desempacota um array (vide Sintaxe Básica).

```php
<?php
function media(...$nums): float {
    if (count($nums) === 0) return 0.0;
    return array_sum($nums) / count($nums);
}

echo media(1, 2, 3);        // 2
echo media(...[10, 20, 30]); // 20
```

## 4. O tipo `callable`

Qualquer valor invocável pode ser passado como `callable`. Veja exemplos em
"Tipagem (Baseline)".

## 5. Funções de array de ordem superior

### `array_map`

Aplica um callback a cada elemento, retornando um novo array.

```php
<?php
$dobros = array_map(function (int $n): int { return $n * 2; }, [1, 2, 3]);
// [2, 4, 6]
```

Pode receber múltiplos arrays; o callback então recebe um elemento de cada:

```php
<?php
$soma = array_map(
    function ($a, $b) { return $a + $b; },
    [1, 2, 3],
    [10, 20, 30]
);
// [11, 22, 33]
```

### `array_filter`

Filtra elementos para os quais o callback retorna `true`. Índices são
preservados; use `array_values()` para reindexar.

```php
<?php
$pares = array_filter([1, 2, 3, 4], function ($n) {
    return $n % 2 === 0;
});
// [2, 4] com chaves 1 e 3
```

O segundo argumento `ARRAY_FILTER_USE_KEY` ou `ARRAY_FILTER_USE_BOTH` altera o
que é passado ao callback.

### `array_reduce`

Reduz o array a um único valor, acumulando um resultado.

```php
<?php
$total = array_reduce(
    [1, 2, 3, 4],
    function ($acum, $n) { return $acum + $n; },
    0
);
// 10
```

### `array_walk`

Aplica um callback a cada elemento por referência (para modificação in-place),
sem retornar um novo array.

```php
<?php
$nomes = ['ana', 'bia'];
array_walk($nomes, function (&$v, $k) {
    $v = ucfirst($v);
});
// ['Ana', 'Bia']
```

## 6. Geradores (`yield`)

Geradores fornecem uma maneira simples de implementar iteradores sem o
sobrecargo de criar classes. `yield` devolve um valor e pausa a execução; a
próxima iteração continua de onde parou.

```php
<?php
function contarAte(int $limite): Generator {
    for ($i = 1; $i <= $limite; $i++) {
        yield $i;
    }
}

foreach (contarAte(3) as $n) {
    echo $n; // 1 2 3
}
```

### `yield from` (PHP 7.0+)

Delega para outro gerador (ou array/Traversable), "achatando" a iteração.

```php
<?php
function uma(): Generator {
    yield 1;
    yield 2;
}

function todas(): Generator {
    yield from uma();
    yield from [3, 4];
}

foreach (todas() as $v) { echo $v; } // 1234
```

Geradores são eficientes em memória: processam sequências grandes (ou
infinitas) sem materializar tudo em array. `yield` também aceita chaves:
`yield $chave => $valor`.

## 7. Exemplo combinado (pipeline funcional)

```php
<?php
$numeros = [5, 12, 8, 3, 20];

$resultado = array_reduce(
    array_filter($numeros, function ($n) { return $n > 5; }),
    function ($acc, $n) { return $acc + $n; },
    0
);
// 12 + 8 + 20 = 40

$dobrados = array_map(function ($n) { return $n * 2; }, $numeros);
```

## 8. Diretrizes

- Prefira `array_map`/`array_filter`/`array_reduce` a loops mutáveis para
  transformações puras — código mais declarativo e testável.
- Use geradores para fluxos grandes ou preguiçosos (lazy).
- Capture variáveis do escopo externo com `use` apenas quando necessário;
  prefira parâmetros explícitos para clareza.
- `Closure::fromCallable()` uniformiza callables antes de passá-los adiante.
