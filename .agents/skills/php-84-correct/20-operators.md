Complementar ao documento comum — específico do PHP 8.4.

# Operadores e Sintaxe Relacionada no PHP 8.4

Este documento reúne operadores e construções de sintaxe disponíveis até o PHP
8.4, incluindo recursos modernos como `match` (8.0) e o operador *null-safe*
`?->` (8.0). Todo o código é válido e executável em 8.4. Nenhuma funcionalidade
removida antes do 8.4 é mencionada.

## 1. Aritméticos

`+ - * / % **` (exponenciação `**` desde 5.6).

```php
$a = 10 + 2;   // 12
$b = 10 % 3;   // 1
$c = 2 ** 8;   // 256
```

## 2. Atribuição

`= += -= *= /= %= **= .= &= |= ^= <<= >>= ??=`

O `??=` (null coalescing assignment, 7.4) atribui apenas se a variável for `null`:

```php
$config['cache'] ??= 'memoria';
```

## 3. Bitwise

`& | ^ ~ << >>` e as versões de atribuição (`&=` etc.).

## 4. Comparação

`== != === !== < > <= >= <=> ??`

- `<=>` (spaceship, 7.0) retorna `-1`, `0` ou `1`.
- `??` (null coalescing, 7.0) retorna o operando esquerdo se não for `null`.

```php
echo 1 <=> 2;   // -1
echo 2 <=> 2;   // 0
$nome = $_GET['nome'] ?? 'anônimo';
```

### `===` vs `==` — armadilhas

`==` faz *coerção* de tipos; `===` compara tipo e valor (estrito). Prefira `===`.

```php
var_dump(0 == '0');      // true  (coerção)
var_dump(0 === '0');     // false
var_dump('' == 0);       // true  ← armadilha comum
var_dump([] == false);   // true
```

## 5. Controle de erro `@`

Suprime avisos na expressão. Evite em produção; prefira tratamento explícito.

```php
$valor = @arquivo_inexistente(); // suprime warning (não recomendado)
```

## 6. Execução (backticks)

Os *backticks* executam um comando do shell e retornam a saída. Requer que o
*shell exec* esteja habilitado; evite por segurança.

```php
$saida = `ls -1`; // equivalente a shell_exec()
```

## 7. Incremento/decremento

`++$x` (pré) e `$x++` (pós); `--$x` / `$x--`. Válidos para int e float.

## 8. Lógicos

`&& || ! and or xor`. Atenção à precedência: `&&`/`||` têm precedência maior
que `and`/`or`.

```php
if ($a && $b or $c) { }   // (($a && $b) or $c)
```

## 9. String

`.` (concatenação), `.=`, e interpolação em aspas duplas/heredoc.

## 10. Array

`+` (união), `==`/`===` (igualdade/identidade), e operadores de spread `...`
(em array literals, 7.4).

```php
$a = ['x' => 1];
$b = ['y' => 2];
$c = $a + $b; // ['x'=>1,'y'=>2]
```

## 11. `instanceof`

```php
if ($obj instanceof \Traversable) { }
```

Com tipos de união/interseção e `is_a()` também válidos em 8.4.

## 12. `match` (8.0) — expressão de comparação

`match` é uma **expressão** com comparação estrita e sem *fall-through*:

```php
$http = 404;
$msg = match ($http) {
    200 => 'OK',
    404 => 'Não encontrado',
    default => 'Outro',
};
```

## 13. Null-safe `?->` (8.0)

Encadeia chamadas sem erro quando um elo é `null`:

```php
$rua = $usuario?->endereco?->rua;        // null se $usuario ou endereco for null
$nome = $cliente?->perfil()?->nome ?? '—';
```

## 14. Ternário e Elvis

```php
$idade = $pessoa['idade'] ?? null;
$desc = $idade !== null ? "$idade anos" : 'desconhecido';
$curto = $idade ?: 'desconhecido'; // elvis
```

## 15. Precedência (resumo alto nível)

`clone new` > `**` > `unary + - ~ (int)` > `instanceof` > `!` > `* / %` >
`+ - .` > `<< >>` > `<=>` > `< > <= >=` > `== != === !==` > `&` > `^` > `|` >
`&&` > `||` > `?->` > `??` > `?:` > `and` > `xor` > `or` > `yield`.

Use parênteses para deixar a intenção clara.

## 16. Juggling / coerção de tipos

O PHP converte tipos implicitamente em contextos esperados. Para coerção
explícita use casts válidos em 8.4: `(int) (float) (string) (bool) (array)
(object)`.

```php
$num = (int) '42';        // 42
$texto = (string) 42;     // "42"
$bin = (bool) 1;          // true
```

## 17. Constantes mágicas

| Constante | Valor |
| --- | --- |
| `__LINE__` | Linha atual |
| `__FILE__` | Caminho completo do arquivo |
| `__DIR__` | Diretório do arquivo |
| `__FUNCTION__` | Nome da função |
| `__CLASS__` | Nome da classe (com namespace) |
| `__TRAIT__` | Nome do trait |
| `__METHOD__` | Classe::método |
| `__NAMESPACE__` | Namespace atual |
| `__PROPERTY__` / `__CLASS__` (em hook) | contexto (8.4) |

```php
namespace App;
echo __NAMESPACE__;  // App
echo __DIR__;        // /var/www/app
```

## 18. Callable de primeira classe (8.1)

A sintaxe `strlen(...)` cria um `Closure` chamável sem `Closure::fromCallable`:

```php
$fn = strlen(...);
echo $fn('oi'); // 2

$map = array_map((fn(int $n) => $n * 2)(...), [1, 2, 3]);
```

## 19. Lembretes de sintaxe básica

- Ponto e vírgula encerra instruções; vírgula à direita permitida (8.0+) em
  listas (`use`, arrays, `match`).
- Argumentos nomeados (8.0): `str_pad($s, length: 10, pad_string: '.')`.
- `throw` é expressão (8.0): `$x ?? throw new \Exception();`.
