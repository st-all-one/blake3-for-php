Complementar ao documento comum — específico do PHP 8.4.

# Programação Funcional no PHP 8.4

Este documento complementa `03-programacao-funcional-baseline.md` com os
recursos funcionais modernos disponíveis até o PHP 8.4: arrow functions (7.4),
callable de primeira classe (8.1), fechamentos (closures), funções de array
`array_map`/`array_filter`/`array_reduce`/`array_walk`, geradores e fibers
(8.1), além das **novas funções de array do 8.4**: `array_all`, `array_any`,
`array_find`, `array_find_key`.

Todo o código é válido em PHP 8.4.

## 1. Arrow functions (PHP 7.4)

Com captura automática das variáveis do escopo externo (por valor).

```php
$imposto = 0.2;
$comImposto = fn(float $preco): float => $preco * (1 + $imposto);

echo $comImposto(100); // 120
```

- Corpo é uma única expressão (retorno implícito).
- Variáveis capturadas são read-only.
- Podem usar tipos e argumentos nomeados (8.0+).

## 2. Callable de primeira classe `...$callable` (PHP 8.1)

```php
$strlen = strlen(...);
$mapear = array_map(...);

$numeros = [1, 2, 3];
$dobros = array_map(fn($n) => $n * 2, $numeros);

// Referência a método como callable
$parse = DateTime::createFromFormat(...);
$obj = new Servico();
$acao = $obj->executar(...);
```

Vantagens sobre strings/callables legados: type-safe e refatorável.

## 3. Closures (fechamentos)

Disponíveis desde o PHP 5.3, com `use` para captura por referência ou valor.

```php
$limite = 10;
$contador = function () use (&$limite): int {
    return ++$limite;
};

echo $contador(); // 11
echo $limite;     // 11 (captura por referência)
```

- `Closure::fromCallable(callable $callable)` cria um objeto `Closure` a partir
  de qualquer callable (útil para decorar/interceptar).

```php
$cl = Closure::fromCallable('strtoupper');
echo $cl('oi'); // OI
```

- `Closure::bind()` / `Closure::bindTo()` permitem alterar o escopo (`$this`) e
  a visibilidade de um closure — útil para acessar membros privados em testes.

```php
class Segredo
{
    private $valor = 42;
}

$fn = function () { return $this->valor; };
$ligado = Closure::bind($fn, new Segredo(), Segredo::class);
echo $ligado(); // 42
```

## 4. Funções de array funcionais

### `array_map`, `array_filter`, `array_reduce`, `array_walk`

```php
$nums = [1, 2, 3, 4, 5];

$dobros = array_map(fn($n) => $n * 2, $nums); // [2,4,6,8,10]

$pares = array_filter($nums, fn($n) => $n % 2 === 0); // [2,4]

$soma = array_reduce($nums, fn($acc, $n) => $acc + $n, 0); // 15

array_walk($nums, function ($v, $k) { echo "$k => $v\n"; });
```

- `array_filter` preserva chaves; use `array_values()` para reindexar.
- `array_reduce` recebe acumulador inicial.
- `array_walk` modifica no lugar (via referência no callback) ou apenas itera.

## 5. Novas funções de array do PHP 8.4

### `array_all()` e `array_any()`

Testam predicados sobre todos/algum elemento. Substituem a necessidade de
`array_filter` + checagem de contagem.

```php
$numeros = [2, 4, 6];

$todosPares = array_all($numeros, fn($n) => $n % 2 === 0); // true
$algumImpar = array_any($numeros, fn($n) => $n % 2 === 1); // false

$usuarios = [
    ['ativo' => true],
    ['ativo' => false],
];
$algumAtivo = array_any($usuarios, fn($u) => $u['ativo']); // true
```

### `array_find()` e `array_find_key()`

Retornam o **primeiro** elemento (ou chave) cujo predicado é verdadeiro, ou
`null` se nenhum.

```php
$produtos = [
    ['nome' => 'Teclado', 'preco' => 100],
    ['nome' => 'Mouse',   'preco' => 50],
    ['nome' => 'Webcam',  'preco' => 200],
];

$caro = array_find($produtos, fn($p) => $p['preco'] > 150);
// ['nome' => 'Webcam', 'preco' => 200]

$chave = array_find_key($produtos, fn($p) => $p['preco'] < 80);
// 1 (índice do Mouse)
```

> Importante: o PHP 8.4 **não** adicionou `array_first`/`array_last`. Os nomes
> oficiais são exatamente `array_all`, `array_any`, `array_find`,
> `array_find_key` (conforme `migration84.new-functions.html`).

### Comparativo

| Função | Retorna | Se nenhum bater |
| --- | --- | --- |
| `array_all` | `true`/`false` | `false` |
| `array_any` | `true`/`false` | `false` |
| `array_find` | valor | `null` |
| `array_find_key` | chave | `null` |

## 6. Geradores (generators)

Introduzidos no PHP 5.5; produzem iteráveis sob demanda (lazy) com `yield`,
economizando memória.

```php
function linhasArquivo(string $arquivo): Generator
{
    $fh = fopen($arquivo, 'r');
    while (($linha = fgets($fh)) !== false) {
        yield rtrim($linha);
    }
    fclose($fh);
}

foreach (linhasArquivo('grande.txt') as $linha) {
    echo $linha, "\n";
}
```

- `yield from` delega para outro gerador/iterável.
- Geradores são ótimos para streams e processamento de grandes coleções.

```php
function contaAte(int $n): Generator
{
    for ($i = 1; $i <= $n; $i++) {
        yield $i;
    }
}

function pares(Genereator|iterable $src): Generator
{
    yield from $src;
}
```

## 7. Fibers (PHP 8.1)

`Fiber` é uma primitiva de concorrência cooperativa de baixo nível: permite
pausar e retomar a execução de um bloco de código em qualquer ponto, sem
bloquear a thread. É a base de frameworks assíncronos (ReactPHP, Amp).

```php
$fiber = new Fiber(function (): void {
    $valor = Fiber::suspend('pausado'); // retorna controle ao caller
    echo "Retomado com: $valor\n";
});

echo $fiber->start();        // imprime "pausado"
$fiber->resume('olá');       // imprime "Retomado com: olá"
```

- `Fiber::suspend($value)` pausa a fiber e devolve `$value` a quem chamou
  `start()`/`resume()`.
- `resume($value)` retoma e o valor vira o retorno de `suspend`.
- `Fiber::isSuspended()`, `Fiber::isStarted()`, `Fiber::isTerminated()`.
- No PHP 8.4, troca de fiber durante execução de destrutores é permitida e o
  GC usa um `gc_destructor_fiber` dedicado (ver `migration84.other-changes.html`).

Fiber não é paralelismo (ainda é single-thread), mas permite escrever código
assíncrono de forma síncrona/legível.

## 8. Variádicos e `callable`

```php
function somar(int ...$nums): int
{
    return array_sum($nums);
}

somar(1, 2, 3); // 6

function invocar(callable $cb, ...$args)
{
    return $cb(...$args); // spread em chamada (8.1+)
}
```

- `callable` aceita closures, strings, arrays `[$obj, 'metodo']`,
  `[Classe::class, 'metodoEstatico']` e callables de 1ª classe (`fn(...)`).

## 9. Exemplo integrado

```php
$dados = [
    ['nome' => 'Ana',  'score' => 8],
    ['nome' => 'Bob',  'score' => 3],
    ['nome' => 'Caio', 'score' => 9],
];

// Todos com score >= 5?
$todosAprovados = array_all($dados, fn($d) => $d['score'] >= 5); // false

// Primeiro reprovado?
$reprovado = array_find($dados, fn($d) => $d['score'] < 5);
// ['nome' => 'Bob', 'score' => 3]

// Scores dobrados via arrow + map
$dobrados = array_map(fn($d) => $d['score'] * 2, $dados);
```

Todos os exemplos acima são executáveis em PHP 8.4.
