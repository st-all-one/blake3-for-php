Complementar ao documento comum — específico do PHP 8.4.

# Sintaxe e Recursos de Expressão no PHP 8.4

Este documento cobre os recursos de sintaxe introduzidos entre o PHP 7.4 e o
PHP 8.4, que se somam ao conjunto básico descrito no `01-sintaxe-basica.md`.
Todo o código aqui é válido e executável em PHP 8.4. Nenhuma funcionalidade
removida antes do 8.4 é recomendada.

> Nota: o PHP 8.4 **não** adicionou novas sintaxes de expressão além dos *property
> hooks* e da *visibilidade assimétrica* de propriedades (vistos em `13-poo-84.md`).
> Toda a sintaxe de expressão apresentada abaixo acumula-se desde o 7.4/8.0/8.1.

## 1. Expressão `match` (PHP 8.0)

A expressão `match` é semelhante a um `switch`, porém é uma **expressão**
(retorna um valor), faz comparação **estrita** (`===`) e não faz *fall-through*.

```php
$status = 404;

$mensagem = match ($status) {
    200 => 'OK',
    301, 302 => 'Redirecionado',
    404 => 'Não encontrado',
    500 => 'Erro interno',
    default => 'Status desconhecido',
};

echo $mensagem; // Não encontrado
```

Diferenciais importantes em relação ao `switch`:

- Usa `===` (não coerção de tipo).
- É uma expressão: o resultado é atribuído.
- Não continua para o próximo caso (sem *fall-through*).
- Exige `default` quando nem todos os casos estão cobertos, ou lança
  `UnhandledMatchError` em tempo de execução se nenhum caso bater.
- Suporta condições com expressões arbitrárias:

```php
$resultado = match (true) {
    $idade < 18 => 'menor de idade',
    $idade >= 18 && $idade < 60 => 'adulto',
    $idade >= 60 => 'idoso',
};
```

## 2. Argumentos nomeados (PHP 8.0)

Permitem passar argumentos a uma função pelo nome do parâmetro, independente
da ordem, e pular parâmetros opcionais.

```php
function criarUsuario(string $nome, string $email, bool $ativo = true, ?string $perfil = null): void
{
    // ...
}

criarUsuario(nome: 'Ana', email: 'ana@example.com', perfil: 'admin');
criarUsuario(email: 'bob@example.com', nome: 'Bob'); // ordem livre
```

- Podem ser combinados com argumentos posicionais (desde que nomeados venham
  **depois** dos posicionais).
- Parâmetros variádicos (`...$rest`) também podem receber o nome:

```php
funcao(nome: 'x', ...$extras);
```

- Ao usar argumentos nomeados com funções internas, prefira a compatibilidade:
  extensões podem mudar a ordem interna, mas o nome é estável.

## 3. Operador de nulidade segura `?->` (PHP 8.0)

Encadeia chamadas mesmo quando um elemento da cadeia é `null`, retornando
`null` automaticamente em vez de lançar um erro.

```php
$pais = $usuario?->endereco?->cidade?->nome;

// Equivalente a:
$pais = $usuario !== null
    ? ($usuario->endereco !== null
        ? ($usuario->endereco->cidade !== null
            ? $usuario->endereco->cidade->nome
            : null)
        : null)
    : null;
```

Funciona em métodos, propriedades e chamadas de método:

```php
$ultimoAcesso = $usuario?->getSessao()?->ultimoAcesso;
```

## 4. Vírgulas à direita (trailing commas) em declarações (PHP 8.0)

Vírgulas finais são permitidas em listas de parâmetros de função, fechamentos,
`use`, e em listas de propriedades/constantes de classe. Isso facilita o
versionamento e a leitura de diffs.

```php
function processar(
    string $entrada,
    array $opcoes = [],
    ?Logger $logger = null, // vírgula final permitida
) {
    // ...
}

class Config
{
    public const string PADRAO = 'x';
    private const array OPCOES = [
        'a' => 1,
        'b' => 2, // vírgula final permitida
    ];

    public function __construct(
        private string $host,
        private int $porta, // vírgula final permitida
    ) {}
}
```

> Observação: em PHP 7.2/7.3 as vírgulas à direita eram permitidas **apenas em
> literais de array**. A partir do 7.4 estenderam-se a chamadas de função, e no
> 8.0 a declarações. No 8.4 tudo isso é válido.

## 5. `throw` como expressão (PHP 8.0)

`throw` passou a ser uma expressão, podendo ser usado em qualquer contexto que
aceite expressões: operador de coalescência, ternário, arrow functions, etc.

```php
$valor = $entrada ?? throw new InvalidArgumentException('entrada obrigatória');

$divisor = $divisor !== 0 ? $divisor : throw new DivisionByZeroError();

$raiz = match (true) {
    $x >= 0 => sqrt($x),
    default => throw new ValueError('raiz de negativo'),
};
```

## 6. Promoção de propriedades no construtor (PHP 8.0)

declara e inicializa propriedades diretamente nos parâmetros do construtor,
reduzindo o código repetitivo.

```php
class Ponto
{
    public function __construct(
        public int $x,
        public int $y,
        private bool $normalizado = false,
    ) {}

    public function deslocar(int $dx, int $dy): void
    {
        $this->x += $dx;
        $this->y += $dy;
    }
}

$p = new Ponto(1, 2);
echo $p->x; // 1
```

Regras:

- Só em construtores (`__construct`).
- Não pode ser usada junto com a declaração separada da mesma propriedade.
- Pode misturar parâmetros promovidos e não promovidos.
- Suporta modificadores de visibilidade (`public`, `protected`, `private`,
  `readonly`), tipos e atributos.

```php
class Servico
{
    public function __construct(
        private readonly string $dsn,
        #[Log] private LoggerInterface $logger,
    ) {}
}
```

## 7. Sintaxe de callable de primeira classe `...$callable` (PHP 8.1)

Cria um callable referindo-se a uma função/método usando `...` (spread) sem
aspas. É mais seguro e refactoring-friendly que strings ou `Closure::fromCallable`.

```php
$fn = strlen(...);
echo $fn('oi'); // 2

$toUpper = strtoupper(...);
$nomes = array_map($toUpper, ['ana', 'bob']); // ['ANA', 'BOB']

// Métodos de instância e estáticos
$parse = DateTime::createFromFormat(...);
$obj = new Ponto(1, 2);
$getX = $obj->getX(...);
```

Pode ser combinado com argumentos nomeados. Não é *partial application*: os
argumentos ainda são fornecidos na chamada (para fixar argumentos, use uma closure).

## 8. Arrow functions (PHP 7.4)

Funções anônimas de uma única expressão, com **captura automática por valor**
das variáveis do escopo externo (sem `use`).

```php
$fator = 3;
$multiplica = fn(int $n): int => $n * $fator;

echo $multiplica(4); // 12
```

- O corpo deve ser uma **expressão** (sem múltiplas instruções nem `return`
  explícito, exceto retorno implícito).
- Variáveis capturadas são somente-leitura dentro da arrow function.
- Suporta tipos de parâmetro e de retorno.

## 9. Operadores de coalescência nula `??` e `??=` (PHP 7.4)

`??` retorna o operando da esquerda se não for `null` e estiver definido; caso
contrário, retorna o da direita. `??=` atribui apenas se a variável for `null`
ou não definida.

```php
$nome = $_GET['nome'] ?? 'convidado';

$config['timeout'] ??= 30; // define apenas se ausente/null
```

Diferente de `?:`, o `??` **não** emite aviso para variáveis indefinidas.

## 10. Operador nave espacial `<=>` (PHP 7.0 / estável)

Retorna `-1`, `0` ou `1` conforme a comparação de dois operandos.

```php
echo 1 <=> 1; // 0
echo 1 <=> 2; // -1
echo 2 <=> 1; // 1
```

Útil em callbacks de ordenação:

```php
usort($pessoas, fn($a, $b) => $a->idade <=> $b->idade);
```

## 11. Spread em literais de array e em chamadas (PHP 7.4 / 8.1)

Em literais de array (`...`), desde o 7.4; em chamadas de função/método (`...`),
desde o 8.1 (incluindo arrays associativos "unpack").

```php
$a = [1, 2];
$b = [3, 4];
$unido = [...$a, ...$b]; // [1, 2, 3, 4]

$assoc1 = ['a' => 1];
$assoc2 = ['b' => 2];
$total = [...$assoc1, ...$assoc2]; // ['a' => 1, 'b' => 2]

// Spread em chamadas (8.1+)
$nums = [1, 2, 3];
echo max(...$nums); // 3
```

- Em chamadas, o *spread* é avaliado em tempo de execução (a partir do 8.1),
  permitindo uso com iteradores e chamadas variádicas.
- Vírgulas à direita **não** são permitidas dentro do operador de spread de
  array (apenas em listas de argumentos posicionais, vide seção 4).

## 12. Encadeamento de `new` sem parênteses (PHP 8.4)

Expressões `new` com argumentos de construtor agora são *dereferenciáveis*,
permitindo encadear métodos/propriedades sem parênteses extras.

```php
// Antes (8.3-): new Classe(...)->metodo()
// 8.4:
$valor = new Ponto(1, 2)->distancia(new Ponto(4, 6));
$props = new ReflectionClass(Ponto::class)->getProperties();
```

## 13. Resumo comparativo

| Recurso | Versão | Tipo |
| --- | --- | --- |
| Arrow functions `fn () =>` | 7.4 | expressão |
| `??` / `??=` | 7.4 | operador |
| Spread em array literal | 7.4 | sintaxe |
| `match` | 8.0 | expressão |
| Argumentos nomeados | 8.0 | sintaxe de chamada |
| `?->` nullsafe | 8.0 | operador |
| Vírgulas finais em declarações | 8.0 | sintaxe |
| `throw` como expressão | 8.0 | expressão |
| Promoção de propriedades | 8.0 | sintaxe de classe |
| Callable de 1ª classe `...$x` | 8.1 | expressão |
| Spread em chamadas | 8.1 | sintaxe de chamada |
| `new` sem parênteses | 8.4 | sintaxe |
| Property hooks / visib. assimétrica | 8.4 | sintaxe de classe (ver `13-poo-84.md`) |

Todos os exemplos acima são executáveis em PHP 8.4.
