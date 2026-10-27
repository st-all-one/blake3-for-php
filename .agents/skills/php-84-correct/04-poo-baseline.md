Documento comum — válido para PHP 7.2, 7.4 e 8.4. Recortado para as pastas de cada versão.

# Programação Orientada a Objetos (Baseline)

Recursos de OOP presentes em PHP 7.2 e ainda válidos em 7.4 e 8.4.

## 1. Classes, propriedades e métodos

```php
<?php
class Pessoa {
    private $nome;
    protected $idade;

    public function __construct(string $nome, int $idade) {
        $this->nome = $nome;
        $this->idade = $idade;
    }

    public function nome(): string {
        return $this->nome;
    }
}
```

Use `public`, `protected` e `private` para visibilidade. Propriedades públicas
são permitidas, mas preferência por encapsulamento (getters/setters) é uma boa
prática.

## 2. Classes abstratas, interfaces e traits

### Classes abstratas

Não podem ser instanciadas; podem conter métodos implementados e métodos
abstratos (sem corpo) que subclasses devem implementar.

```php
<?php
abstract class Animal {
    abstract public function emitirSom(): string;
    public function dormir(): void {
        echo "zzz";
    }
}
```

### Interfaces

Contratos puramente de assinatura (desde o PHP 7.2, sem tipos de retorno
anuláveis conflitantes). Uma classe pode implementar várias interfaces.

```php
<?php
interface Repositorio {
    public function salvar(array $dados): void;
    public function buscar(int $id): ?array;
}
```

### Traits

Mecanismo de reuso horizontal de código, independente de hierarquia.

```php
<?php
trait Identificavel {
    private $id;
    public function id(): ?int { return $this->id; }
}

class Produto {
    use Identificavel;
}
```

Use `insteadof` e `as` para resolver conflitos de nome entre traits. Métodos
de trait podem ser sobrescritos pela classe que os usa.

## 3. `final`

`final` em classe impede herança; `final` em método impede sobrescrita.

```php
<?php
final class Configuracao { /* não pode ser estendida */ }

class Base {
    final public function id(): int { return 1; }
}
```

## 4. Métodos mágicos

Lista dos métodos mágicos disponíveis no baseline (7.2+):

| Método | Propósito |
|---|---|
| `__construct()` | construtor |
| `__destruct()` | destrutor |
| `__call($nome, $args)` | chamada de método inexistente (instância) |
| `__callStatic($nome, $args)` | chamada de método estático inexistente |
| `__get($nome)` / `__set($nome, $valor)` | leitura/escrita de propriedade inacessível |
| `__isset($nome)` / `__unset($nome)` | `isset()`/`unset()` em propriedade inacessível |
| `__toString()` | representação em string |
| `__invoke(...$args)` | objeto invocado como função |
| `__set_state($array)` | usado por `var_export()` |
| `__clone()` | personaliza a clonagem |
| `__debugInfo()` | personaliza `var_dump()` |
| `__sleep()` / `__wakeup()` | serialização (ver abaixo) |

```php
<?php
class Colecao {
    private $itens = [];

    public function __get(string $nome) {
        if ($nome === 'total') return count($this->itens);
        return null;
    }

    public function __toString(): string {
        return 'Colecao(' . count($this->itens) . ')';
    }

    public function __invoke(int $n) {
        return $n * 2;
    }
}

$c = new Colecao();
echo $c->total;   // 0
echo $c(21);      // 42
```

> O método mágico `__clone()` é chamado no objeto **clonado** após a cópia
> rasa. Para clonar profundamente sub-objetos, faça-o dentro de `__clone()`.

## 5. Serialização: `__sleep`/`__wakeup` e (7.4+) `__serialize`/`__unserialize`

### Baseline (`__sleep` / `__wakeup`) — disponível em 7.2

`__sleep()` deve retornar um array com os nomes das propriedades a persistir;
`__wakeup()` reconstrói recursos após a desserialização.

```php
<?php
class Conexao {
    private $link;
    private $dsn;

    public function __sleep(): array {
        return ['dsn']; // não serializa o recurso de conexão
    }

    public function __wakeup(): void {
        $this->link = null; // reconectar sob demanda
    }
}
```

> Nota de versão: a partir do **PHP 7.4**, também existem os métodos mágicos
> `__serialize()` e `__unserialize()`, que retornam/recebem um array associativo
> completo e oferecem controle mais robusto (evitam armadilhas de nomes em
> `__sleep`). Eles **têm precedência** sobre `__sleep`/`__wakeup` quando
> definidos. Como esse documento é o "comum" (válido em 7.2, 7.4 e 8.4), a
> abordagem `__sleep`/`__wakeup` funciona em todas as versões; você pode
> adotar `__serialize`/`__unserialize` livremente a partir do PHP 7.4. **Não**
> implemente a interface `Serializable` como abordagem preferencial (está
> obsoleta/removida em versões recentes).

## 6. Late Static Binding (LSB)

`static::` resolve para a classe efetivamente chamada em uma hierarquia,
diferente de `self::` (fixa na classe onde foi definido).

```php
<?php
class Base {
    public static function criar(): self {
        return new static(); // LSB: instancia a subclasse
    }
}

class Filha extends Base {}

$f = Filha::criar(); // instância de Filha
```

## 7. Classes anônimas (PHP 7.0+)

Úteis para implementações únicas/curtas (ex.: stubs de teste, adapters).

```php
<?php
$logger = new class {
    public function info(string $msg): void {
        error_log($msg);
    }
};

$logger->info("inicializado");
```

Classes anônimas podem implementar interfaces, estender classes e usar `use`
para capturar variáveis.

## 8. Exceções e erros

Desde o PHP 7, erros fatais tornaram-se objetos da hierarquia `Throwable`
(`Error` e `Exception`). Use `try`/`catch`/`finally` (disponíveis desde 5.5) e
`catch` múltiplo (desde 7.1).

```php
<?php
try {
    $valor = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    // exceção específica (PHP 7.3+)
    error_log($e->getMessage());
} catch (Exception $e) {
    error_log($e->getMessage());
} catch (Error $e) {
    // erros de tipo, chamadas inválidas, etc.
    error_log('Erro: ' . $e->getMessage());
} finally {
    // sempre executado
}
```

`Throwable` é a interface raiz; capture `Exception` para erros de aplicação e
`Error` para erros do motor quando precisar distingui-los. Em geral, capturar
`Throwable` no topo da aplicação para log centralizado é seguro.

## 9. Diretrizes

- Prefira composição (traits/interfaces/injeção) a herança profunda.
- Use métodos mágicos com parcimônia: `get`/`set`/`isset`/`unset` para
  propriedades virtuais; `__toString`/`__invoke` com semântica clara.
- Para serialização de objetos com recursos, use `__sleep`/`__wakeup` (ou
  `__serialize`/`__unserialize` no 7.4+).
- Trate falhas com exceções; capte `Throwable` no nível superior para registro.
