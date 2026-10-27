Complementar ao documento comum — específico do PHP 8.4.

# Geradores, Iteradores e Fibers no PHP 8.4

Este documento cobre geradores e iteradores SPL (desde o PHP 5.x) e *fibers*
(8.1, disponíveis no 8.4) como primitivo avançado de concorrência. Todo o código
é válido e executável em 8.4. Nenhuma funcionalidade removida antes do 8.4 é
utilizada.

## 1. Geradores com `yield`

Um gerador produz valores sob demanda, sem materializar todo o array em memória.

```php
function contarAte(int $fim): \Generator
{
    for ($i = 1; $i <= $fim; $i++) {
        yield $i;
    }
}

foreach (contarAte(3) as $n) {
    echo $n; // 1 2 3
}
```

### `yield from`

Delega a um outro gerador/iterável:

```php
function parteA(): \Generator
{
    yield 'a';
    yield 'b';
}

function tudo(): \Generator
{
    yield from parteA();
    yield 'c';
}

// a b c
```

### `return` + `getReturn()`

`return` em gerador define um valor final acessível via `getReturn()`:

```php
function soma(): \Generator
{
    $total = 0;
    for ($i = 1; $i <= 3; $i++) {
        $total += $i;
        yield $i;
    }
    return $total;
}

$g = soma();
foreach ($g as $v) { /* 1 2 3 */ }
echo $g->getReturn(); // 6
```

## 2. Iteradores SPL

### `Iterator` / `IteratorAggregate`

```php
class Sequencia implements \IteratorAggregate
{
    public function __construct(private int $fim) {}

    public function getIterator(): \Traversable
    {
        return new class ($this->fim) implements \Iterator {
            public function __construct(private int $fim) { $this->rewind(); }
            private int $i = 0;
            public function rewind(): void { $this->i = 1; }
            public function current(): mixed { return $this->i; }
            public function key(): mixed { return $this->i; }
            public function next(): void { $this->i++; }
            public function valid(): bool { return $this->i <= $this->fim; }
        };
    }
}

foreach (new Sequencia(3) as $v) {
    echo $v; // 1 2 3
}
```

### `ArrayIterator`

```php
$it = new \ArrayIterator(['a' => 1, 'b' => 2]);
foreach ($it as $k => $v) {
    echo "$k=$v ";
}
```

## 3. Fibers (PHP 8.1)

*Fiber* é um primitivo de concorrência cooperativa: suspende e retoma a execução
em pontos definidos, sem threads do SO. Útil para construir *event loops* e
clientes não bloqueantes.

```php
$fiber = new \Fiber(function (): void {
    echo "fibra: início\n";
    $sinal = \Fiber::suspend('esperando'); // suspende e retorna 'esperando'
    echo "fibra: recebeu '$sinal'\n";
});

echo "main: status antes = " . ($fiber->isStarted() ? 'sim' : 'não') . "\n";
$primeiro = $fiber->resume();      // executa até o suspend; $primeiro = 'esperando'
echo "main: obteve '$primeiro'\n";
$fiber->resume('continua');        // retoma; $sinal = 'continua'
echo "main: finalizada = " . ($fiber->isTerminated() ? 'sim' : 'não') . "\n";
```

Saída aproximada:

```
main: status antes = não
fibra: início
main: obteve 'esperando'
fibra: recebeu 'continua'
main: finalizada = sim
```

`\Fiber::suspend()` só pode ser chamado de dentro de uma fibra. Fibers não dão
paralelismo real de CPU — para isso use extensões de processo/thread ou FFI/JIT.

## 4. Quando usar cada um

- **Gerador:** streaming de dados grandes, pipelines sob demanda.
- **Iterator/ArrayIterator:** encapsular coleções com semântica de iteração.
- **Fiber:** criar abstrações assíncronas (await, generators de I/O) sobre um
  *event loop*; raramente usado diretamente em aplicações web típicas.
