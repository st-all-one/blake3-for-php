# PHP 8.4 — Dossiê de Uso Efetivo

Este dossiê reúne a documentação de uso do **PHP 8.4**, a versão mais recente
coberta por este guia. Ele é estritamente **escopo 8.4**: todo o código e toda
a recomendação são válidos e executáveis em PHP 8.4, sem funcionalidades
removidas antes dessa versão e sem recursos de versões posteriores ao 8.4.

## Abrangência

O PHP 8.4 **inclui a totalidade dos recursos acumulados** desde o PHP 7.2,
passando por 7.3, 7.4 e por toda a linha 8.0, 8.1, 8.2, 8.3, mais as adições
próprias do 8.4. Portanto, este é o guia **mais completo** da série: cobre
arrow functions (7.4), typed properties (7.4), atributos (8.0), enums (8.1),
readonly (8.1/8.2), fibers (8.1), tipos de interseção/DNF (8.1/8.2),
`#[\Override]` (8.3) e os novos *property hooks*, *visibilidade assimétrica*
e *lazy objects* do 8.4, entre outros.

## Estrutura dos documentos

Os documentos estão divididos em duas faixas:

- **01–09 (comuns):** válidos para 7.2 / 7.4 / 8.4 — a base estável e comum.
- **10–25 (específicos do 8.4):** complementam a base com o conjunto moderno de
  recursos disponíveis até o 8.4. Cada um inicia com a marca
  *"Complementar ao documento comum — específico do PHP 8.4."*

## Índice — Documentos comuns (base 7.2/7.4/8.4)

| Arquivo | Tópico |
| --- | --- |
| [01-sintaxe-basica.md](01-sintaxe-basica.md) | Sintaxe básica |
| [02-tipagem-baseline.md](02-tipagem-baseline.md) | Tipagem básica |
| [03-programacao-funcional-baseline.md](03-programacao-funcional-baseline.md) | Programação funcional básica |
| [04-poo-baseline.md](04-poo-baseline.md) | POO básica |
| [05-seguranca-baseline.md](05-seguranca-baseline.md) | Segurança básica |
| [06-logs.md](06-logs.md) | Logs e erros básicos |
| [07-performance-baseline.md](07-performance-baseline.md) | Performance básica |
| [08-configuracao.md](08-configuracao.md) | Configuração básica |
| [09-boas-praticas.md](09-boas-praticas.md) | Boas práticas |

## Índice — Documentos específicos do PHP 8.4

| Arquivo | Tópico | Destaques (versão de origem) |
| --- | --- | --- |
| [10-sintaxe-recursos-84.md](10-sintaxe-recursos-84.md) | Sintaxe e expressões | `match`, argumentos nomeados, `?->`, vírgulas finais, `throw` expr, promoção, callable 1ª classe, arrow fns, `??/??=` (7.4–8.4) |
| [11-tipagem-84.md](11-tipagem-84.md) | Tipagem | union, `mixed`, `static`, `never`, `true/false/null`, intersection, readonly, DNF, `new` em inicializador, covariância (7.4–8.4) |
| [12-programacao-funcional-84.md](12-programacao-funcional-84.md) | Programação funcional | arrow fns, callable 1ª classe, closures, `array_all/any/find/find_key` (8.4), geradores, fibers (8.1) |
| [13-poo-84.md](13-poo-84.md) | POO | atributos, enums, readonly, `#[\Override]` (8.3), `#[\Deprecated]` (8.4), `WeakMap`, property hooks, visibilidade assimétrica, lazy objects (8.4) |
| [14-seguranca-84.md](14-seguranca-84.md) | Segurança | `password_*` (BCrypt/Argon2), `Random\Randomizer` (8.1), `sodium`/`openssl`, prepared statements, sessão samesite |
| [15-performance-84.md](15-performance-84.md) | Performance | JIT (`opcache.jit` padrão `"disable"`, buffer 64M no 8.4), preloading (7.4), OPcache, fibers |
| [16-configuracao-84.md](16-configuracao-84.md) | Configuração | `opcache.jit` (padrões 8.4), `opcache.preload`, `error_log_mode` (8.2), `error_reporting` default `E_ALL`, `declare(strict_types=1)` |
| [17-logs-84.md](17-logs-84.md) | Logs e erros | `E_USER_ERROR` depreciado, `E_STRICT` depreciado, `#[\Deprecated]` → `E_USER_DEPRECATED`, `error_log_mode`, `E_ALL` = 30719 |
| [18-namespaces.md](18-namespaces.md) | Namespaces | declaração, aninhados, global, `use`/agrupamento/`as`, fallback, resolução dinâmica (8.0), regras de resolução |
| [19-references.md](19-references.md) | Referências | alias, atribuição/passagem/retorno por ref, `unset`, `foreach` por ref, caveats |
| [20-operators.md](20-operators.md) | Operadores e sintaxe | aritméticos, `??=`, `<=>`, `??`, `@`, `` ` `` , `instanceof`, `match` (8.0), `?->` (8.0), juggling, constantes mágicas, callable 1ª classe |
| [21-generators-iterators.md](21-generators-iterators.md) | Geradores, iteradores e fibers | `yield`/`yield from`/`getReturn()`, SPL `Iterator`/`ArrayIterator`, `Fiber` (8.1) |
| [22-input-superglobals.md](22-input-superglobals.md) | Superglobais e entrada | `$_GET/$_POST/$_REQUEST/$_COOKIE/$_SERVER/$_ENV/$_FILES/$_SESSION`, `filter_*`, funções variáveis, callable 1ª classe |
| [23-oop-avancado.md](23-oop-avancado.md) | POO avançado | classes anônimas, overloading, `__clone`, serialização, LSB, property hooks, visibilidade assimétrica, lazy objects (8.4) |
| [24-errors-exceptions.md](24-errors-exceptions.md) | Erros e exceções | `error_reporting`, `set_error_handler`, hierarquia `Throwable`/`Error`/`Exception`, `try/catch/finally`, `throw` expr |
| [25-config-web-phpini.md](25-config-web-phpini.md) | Configuração web/php.ini | segurança, performance (OPcache/JIT), estabilidade — tabelas ini + recomendação |

## Como usar

1. Leia primeiro os documentos **01–09** para a base estável.
2. Consulte os **10–25** para o conjunto completo de recursos modernos do 8.4.
3. Para dúvidas de migração de versões anteriores, veja as notas de mudança
   oficiais (migration80 a migration84) — aqui documentamos o estado final do 8.4.

> Este dossiê é autocontido para PHP 8.4: nada do que está aqui foi removido no
> 8.4, e nada além do 8.4 é abordado.

## Exemplo prático

- [exemplo-completo.php](exemplo-completo.php) — arquivo único e executável
  demonstrando diretivas, tipagem, programação funcional, POO e logs no PHP
  8.4, incluindo os recursos modernos (match, nullsafe, enums, atributos,
  readonly, constructor promotion, first-class callable, property hooks,
  visibilidade assimétrica e lazy objects).
