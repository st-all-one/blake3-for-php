Complementar ao documento comum — específico do PHP 8.4.

# POO Avançado no PHP 8.4

Este documento reúne recursos avançados de orientação a objetos disponíveis até
o PHP 8.4, incluindo os novos *property hooks*, *visibilidade assimétrica* e
*lazy objects* do 8.4. Todo o código é válido e executável em 8.4. Nenhuma
funcionalidade removida antes do 8.4 é utilizada. Veja também `04-poo-baseline.md`
e `13-poo-84.md`.

## 1. Classes anônimas

```php
$logger = new class {
    public function log(string $m): void { error_log($m); }
};
$logger->log('ok');
```

## 2. Overloading: `__get` / `__set` / `__call` / `__callStatic`

```php
class Dynamic
{
    private array $dados = [];

    public function __get(string $k): mixed { return $this->dados[$k] ?? null; }
    public function __set(string $k, mixed $v): void { $this->dados[$k] = $v; }

    public function __call(string $m, array $a): mixed
    {
        return "chamado $m com " . count($a) . " arg(s)";
    }

    public static function __callStatic(string $m, array $a): mixed
    {
        return "estático $m";
    }
}

$d = new Dynamic();
$d->nome = 'X';
echo $d->nome;        // X
echo $d->faz(1, 2);   // chamado faz com 2 arg(s)
echo Dynamic::x();    // estático x
```

## 3. Clonagem (`__clone`)

```php
class Node
{
    public function __construct(public string $v) {}

    public function __clone(): void
    {
        $this->v = "copia de {$this->v}";
    }
}

$a = new Node('a');
$b = clone $a;
```

## 4. Comparação de objetos

Objetos são iguais (`==`) se forem da mesma classe e tiverem propriedades
idênticas; idênticos (`===`) se forem a mesma instância.

```php
$x = new Node('a');
$y = new Node('a');
var_dump($x == $y);   // true
var_dump($x === $y);  // false
```

## 5. Serialização

```php
class Estado
{
    public function __construct(public string $nome, private string $segredo) {}

    public function __sleep(): array { return ['nome']; }
    public function __wakeup(): void {}

    public function __serialize(): array { return ['nome' => $this->nome]; }
    public function __unserialize(array $d): void { $this->nome = $d['nome']; }
}
```

Prefira `__serialize`/`__unserialize` (8.1) sobre `__sleep`/`__wakeup` quando
possível.

## 6. Late Static Bindings

```php
class Base
{
    public static function instancia(): static
    {
        return new static();
    }
}

class Filha extends Base {}

$f = Filha::instancia(); // instância de Filha (static)
```

## 7. Iteração de objetos

```php
class Colecao implements \IteratorAggregate
{
    public function __construct(private array $itens) {}
    public function getIterator(): \Traversable { return new \ArrayIterator($this->itens); }
}
```

## 8. Propriedades tipadas e promoção de construtor

```php
class Pessoa
{
    public function __construct(
        public string $nome,
        private int $idade,
    ) {}
}
```

## 9. Atributos (8.0)

```php
#[Attribute(Attribute::TARGET_METHOD)]
class Rota {
    public function __construct(public string $path) {}
}

class Api
{
    #[Rota('/usuarios')]
    public function usuarios(): void {}
}
```

## 10. `readonly` (8.1/8.2) e `enum`

```php
readonly class Config
{
    public function __construct(public string $ambiente) {}
}

enum Status: string
{
    case ATIVO = 'ativo';
    case INATIVO = 'inativo';
}
```

## 11. Property Hooks (PHP 8.4)

```php
class Produto
{
    public string $nome {
        set => strtoupper($value);
    }

    public float $preco {
        get => $this->precoInterno;
        set => $this->precoInterno = max(0.0, $value);
    }

    private float $precoInterno = 0.0;
}

$p = new Produto();
$p->nome = 'camisa';
echo $p->nome;  // CAMISA
```

## 12. Visibilidade assimétrica (PHP 8.4)

```php
class Conta
{
    public private(set) int $saldo = 0; // leitura pública, escrita privada

    public function depositar(int $v): void
    {
        $this->saldo += $v;
    }
}

$c = new Conta();
$c->depositar(100);
echo $c->saldo; // 100 (leitura pública)
// $c->saldo = 50; // Erro: escrita é privada
```

## 13. Lazy Objects (PHP 8.4)

```php
class ServicoPesado
{
    public function __construct(private string $cfg = 'x') {}
    public function rodar(): string { return "rodou com $this->cfg"; }
}

$initializer = static fn(): ServicoPesado => new ServicoPesado('cfg');

$rf = new \ReflectionClass(ServicoPesado::class);
$lo = $rf->newLazyProxy($initializer);

// A instância real só é criada no primeiro acesso:
echo $lo->rodar(); // rodou com cfg
```

`newLazyProxy` e `newLazyGhost` (via `\ReflectionClass`) adiam a construção do
objeto até o primeiro uso — útil para injeção de dependência e ORMs.

## 14. Interfaces com membros estáticos (8.2+)

```php
interface Fabrica
{
    public static function criar(): static;
}

class A implements Fabrica
{
    public static function criar(): static { return new self(); }
}
```

## 15. Boas práticas 8.4

- Use *property hooks* para validação e *visibilidade assimétrica* para
  invariants, reduzindo *getters/setters* manuais.
- Use *lazy objects* para dependências caras que podem não ser usadas.
- Prefira `readonly` para objetos de valor imutáveis (DTOs, configs).
