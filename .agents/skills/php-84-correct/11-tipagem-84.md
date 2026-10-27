Complementar ao documento comum — específico do PHP 8.4.

# Tipagem no PHP 8.4

Este documento descreve os recursos de tipagem de PHP 8.4 que se somam aos
tipos básicos (`int`, `float`, `string`, `bool`, `array`, `callable`,
`iterable`, `object`, `void`, `null`, `self`/`parent`, anotações de tipo
anulável `?T` e declarações de tipo com `declare(strict_types=1)`), cobertos
em `02-tipagem-baseline.md`. Todo o código aqui é válido em PHP 8.4.

> Observação: o PHP 8.4 não introduziu novos tipos de declaração de tipo
> "simples". Os avanços de tipagem do 8.4 residem em *property hooks* e
> *visibilidade assimétrica* (ver `13-poo-84.md`). Os tipos abaixo acumulam-se
> desde o 7.4/8.0/8.1/8.2.

## 1. Propriedades tipadas (PHP 7.4)

Declaração de tipo diretamente em propriedades de classe (já coberto no básico,
mas fundamental para todos os recursos a seguir).

```php
class Conta
{
    public int $numero;
    private float $saldo = 0.0;
    public array $historico = [];
}
```

## 2. Union types `A|B` (PHP 8.0)

Um parâmetro, propriedade ou retorno pode aceitar mais de um tipo.

```php
function processar(int|string $id): int|false
{
    // ...
}

class Repositorio
{
    public array|null $cache = null;
}
```

Regras:

- `null` em uniões deve vir como `null` explícito (`A|null`), não como `?A` junto
  de outra união (use `?A|B` é inválido; prefira `A|B|null`).
- Uniões não podem conter `false` junto com `bool` (a menos que seja `false` +
  outros tipos, sem `bool`); `bool` já inclui `false`.
- `mixed` não pode participar de uniões (pois já abrange tudo).
- A partir do 8.2, uniões redundantes são erro (ex.: `int|INT`, `bool|false`,
  `iterable|array`). `int|float` é legal e idiomático.

## 3. Tipo `mixed` (PHP 8.0)

Representa "qualquer tipo". Equivale a `array|string|int|float|bool|object|null`
e **deve** ser usado explicitamente quando uma função aceita anything, em vez de
omitir o tipo (omitir significa tipagem dinâmica sem checagem).

```php
function registrar(mixed $valor): void
{
    var_dump($valor);
}
```

- `mixed` não pode ser anulável (`?mixed` é erro).
- `mixed` em parâmetro não força `strict_types` a rejeitar tipos, pois aceita
  todos.

## 4. Tipo de retorno `static` (PHP 8.0)

Permite que um método retorne uma instância da classe concreta na qual foi
chamado (útil para *fluent interfaces*).

```php
class Modelo
{
    public function definir(string $nome, mixed $valor): static
    {
        $this->$nome = $valor;
        return $this;
    }
}

class Usuario extends Modelo {}

$u = (new Usuario())->definir('nome', 'Ana'); // tipo: Usuario
```

## 5. Tipo `never` (PHP 8.1)

Indica que a função **nunca retorna** (sempre lança uma exceção ou encerra o
script com `exit()`/`die()`). Útil para contratos de código que não retornam.

```php
function falhar(string $msg): never
{
    throw new RuntimeException($msg);
}

function redirecionar(string $url): never
{
    header("Location: $url");
    exit;
}
```

- `never` é o tipo de retorno oposto a `void`.
- Uma função com retorno `never` não pode retornar valor (erro de compilação).

## 6. `true`, `false` e `null` como tipos autônomos (PHP 8.2)

Antes só apareciam em uniões; agora são tipos válidos por si.

```php
function ligado(): bool
{
    return true;
}

function estaAtivo(): false|true
{
    return true;
}

function enviar(): true
{
    // retorna apenas true; qualquer outro valor é TypeError
    return true;
}

function getValor(): null
{
    return null;
}
```

- `true` e `false` como autônomos são raros; mais comum em uniões como
  `false|string` para sinalizar erro (estilo `strpos()` que retorna `false` ou
  `int`).
- `null` autônomo só é útil em uniões/contextos específicos.

## 7. Intersection types `A&B` (PHP 8.1)

Exige que o valor implemente **todos** os tipos (só para tipos de classe e
interfaces; não pode misturar com uniões diretamente — use DNF para isso).

```php
function salvar(Countable&ArrayAccess $colecao): void
{
    // $colecao deve ser, ao mesmo tempo, Countable e ArrayAccess
}

interface A {}
interface B {}
class C implements A, B {}

function foo(A&B $x): void {}
foo(new C()); // ok
```

- Intersection types **não** podem conter `int`, `string`, etc. (apenas
  classes/interfaces, por agora). Não podem ser anuláveis com `?` direto.
- `mixed` e `never` são incompatíveis com interseções.

## 8. DNF types — Disjunctive Normal Form (PHP 8.2)

Combina uniões e interseções, colocando interseções entre parênteses dentro de
uniões:

```php
function processar((Countable&ArrayAccess)|string $entrada): void
{
    // ...
}

class Servico
{
    private (LoggerInterface&PsrLogLoggerInterface)|null $logger = null;
}
```

Regras:

- A forma permitida é `(Interseção1)|(Interseção2)|...|TipoSimples`.
- Não se pode escrever `A&B|C` (precisa de parênteses: `(A&B)|C`).
- Interseções dentro da DNF devem ser envoltas em parênteses.

## 9. Propriedades `readonly` (PHP 8.1) e classes `readonly` (PHP 8.2)

`readonly` em propriedade: só pode ser atribuída **uma vez** (tipicamente no
construtor ou hook de set, no 8.4).

```php
class Ponto
{
    public function __construct(
        public readonly int $x,
        public readonly int $y,
    ) {}
}

$p = new Ponto(1, 2);
// $p->x = 3; // Error: Cannot modify readonly property
```

Classe `readonly` (8.2): todas as propriedades são implicitamente `readonly`.

```php
readonly class Configuracao
{
    public string $host;
    public int $porta;

    public function __construct(string $host, int $porta)
    {
        $this->host = $host;
        $this->porta = $porta;
    }
}
```

- Classe `readonly` não pode ter propriedades `static`, nem propriedades
  sem tipo, nem ser estendida por uma classe não-readonly (a subclasse também
  deve ser readonly se herdar).
- No 8.4, hooks de `set` podem reatribuir internamente dentro da classe, mas a
  regra de "atribuição única do lado de fora" permanece (ver `13-poo-84.md`).

## 10. `new` em inicializadores (PHP 8.1)

Permite usar `new` em valores padrão de parâmetros, propriedades, argumentos
nomeados e argumentos de atributos.

```php
class Logger
{
    public function __construct(public string $canal = 'app') {}
}

function configurar(Logger $logger = new Logger('default')): void {}

class Servico
{
    public function __construct(
        private Cache $cache = new ArrayCache(),
    ) {}
}

#[Anotacao(metadados: new Metadados('x'))]
class Exemplo {}
```

- O objeto criado deve ser de uma classe não-interna ou interna sem
  restrições; o `new` em inicializador é avaliado por chamada.

## 11. Covariância e contravariância (PHP 7.4)

- **Covariância** de retorno: uma subclasse pode afinar o tipo de retorno para
  um subtipo.
- **Contravariância** de parâmetro: uma subclasse pode ampliar o tipo de um
  parâmetro para um supertipo.

```php
class Animal {}
class Cachorro extends Animal {}

interface Repositorio
{
    public function buscar(int $id): ?Animal;
    public function salvar(Animal $a): void;
}

class CachorroRepo implements Repositorio
{
    public function buscar(int $id): ?Cachorro // covariante (Cachorro < Animal)
    {
        // ...
    }

    public function salvar(Cachorro|Animal $a): void // contravariante
    {
        // ...
    }
}
```

- `void` é invariante (não pode mudar para outro tipo). `never` também segue
  regras de variância.
- Tipos embutidos (`int`, `string`) não são relacionáveis por herança, logo não
  variam.

## 12. Constantes em interfaces (PHP 8.1) e em traits (PHP 8.1)

```php
interface Status
{
    public const int ATIVO = 1;
    public const int INATIVO = 2;
}

trait Identificavel
{
    public const string PREFIXO = 'id_';
}
```

- Interfaces podem declarar constantes públicas (já era possível em versões
  anteriores em alguns casos, mas a tipagem/visibilidade moderna é do 8.1+).

## 13. Constantes de classe `final` (PHP 8.1)

Impede sobrescrita da constante em subclasses.

```php
class Base
{
    public final const string VERSAO = '1.0';
}

class Filha extends Base
{
    // public const string VERSAO = '2.0'; // Error: cannot override final constant
}
```

## 14. Tabela de tipos por versão

| Tipo / recurso | Versão | Exemplo |
| --- | --- | --- |
| Propriedades tipadas | 7.4 | `public int $x` |
| Union `A\|B` | 8.0 | `int\|string` |
| `mixed` | 8.0 | `mixed $v` |
| `static` (retorno) | 8.0 | `: static` |
| Intersection `A&B` | 8.1 | `A&B $x` |
| `never` | 8.1 | `: never` |
| `readonly` (prop) | 8.1 | `public readonly int $x` |
| `new` em inicializador | 8.1 | `= new Foo()` |
| Constantes em interface/trait | 8.1 | `const X = 1` |
| `final const` | 8.1 | `final const X` |
| `true`/`false`/`null` autônomos | 8.2 | `: true` |
| DNF `(A&B)\|C` | 8.2 | `(A&B)\|string` |
| Classe `readonly` | 8.2 | `readonly class` |
| Tipagem de property hooks/visib. | 8.4 | ver `13-poo-84.md` |

Todos os exemplos acima são executáveis em PHP 8.4.
