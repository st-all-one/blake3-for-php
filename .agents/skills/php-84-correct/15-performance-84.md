Complementar ao documento comum — específico do PHP 8.4.

# Performance no PHP 8.4

Este documento complementa `07-performance-baseline.md` com os recursos de
performance modernos até o PHP 8.4, com ênfase nas mudanças do **JIT no 8.4**.
Todo o conteúdo é aplicável em 8.4.

## 1. OPcache e o JIT

O OPcache (desde 5.5) armazena em memória os opcodes compilados, eliminando a
recompilação a cada requisição. O **JIT** (Just-In-Time, desde 8.0) compila
opcodes para código de máquina nativo em tempo de execução, acelerando cargas
computacionalmente intensivas (math, loops, processamento de imagem, etc.).

### Mudança importante no PHP 8.4

No PHP 8.4, os **valores padrão mudaram**:

| Diretiva | Antes (8.0–8.3) | PHP 8.4 |
| --- | --- | --- |
| `opcache.jit` | `"tracing"` | `"disable"` |
| `opcache.jit_buffer_size` | `0` | `64M` |

Ou seja, no 8.4 o JIT vem **desligado por padrão** (`disable`), mas o buffer de
64 MB já está reservado. Para ativar, configure explicitamente:

```ini
opcache.enable=1
opcache.jit=tracing        ; ou "function"
opcache.jit_buffer_size=64M
```

> Recomendação: para aplicações web típicas (I/O bound), o ganho do JIT é
> pequeno. Para workloads CPU bound, **meça** `opcache.jit=tracing`; laços longos
> de inteiros podem regredir com o trace. Não ative JIT por suposição.

### Modos do JIT

- `opcache.jit=disable` — desligado.
- `opcache.jit=function` — compila função por função.
- `opcache.jit=tracing` — compila "tracelets" (caminhos quentes de execução);
  geralmente o melhor equilíbrio.

A diretiva aceita também uma máscara de bits `CRTO` (0–5 dígitos) para controle
fino de otimização, registro e geração de código, mas `"tracing"`/`"function"`
são os atalhos práticos.

## 2. Preloading (PHP 7.4)

O preloading carrega scripts (classes, funções, interfaces) na inicialização do
OPcache (ao subir o PHP-FPM/SAPI), mantendo-os em memória para todas as
requisições, eliminando o include/require em runtime.

```ini
opcache.preload=/var/www/app/preload.php
opcache.preload_user=www-data
```

`preload.php` faz os includes desejados:

```php
// preload.php
require_once '/var/www/app/vendor/autoload.php';
opcache_compile_file('/var/www/app/src/MeuServico.php');
// ou varrer um diretório e incluir tudo
```

- Scripts preloadados não podem ser alterados em runtime (exigem reinício do
  SAPI para atualizar).
- Use com cautela: aumenta o uso de memória e o tempo de startup.

## 3. Sintaxe que ajuda a performance

- **Arrow functions** (7.4) e **closures**: evitam a criação de classes; closures
  têm custo de captura, prefira arrow quando possível.
- **Promoção de propriedades** (8.0): menos código, sem impacto negativo.
- **`match`** (8.0): ligeiramente mais eficiente que `switch` e seguro por tipo.
- **Generators** (5.5): processamento lazy de grandes coleções, baixo uso de
  memória.
- **Fibers** (8.1): permitem concorrência cooperativa; usadas por frameworks
  async para alto throughput de I/O sem threads.

## 4. Coleta de lixo e referências fracas

- `WeakMap` (8.0) e `WeakReference` (7.4) evitam vazamento de memória em caches
  e registros associados a objetos.
- No 8.4, a troca de Fiber durante destrutores é permitida e o GC usa um
  `gc_destructor_fiber` dedicado, melhorando a robustez de código assíncrono.

```php
$wm = new WeakMap();
foreach ($muitosObjetos as $o) {
    $wm[$o] = metadata($o); // liberado junto com $o
}
```

## 5. Dicas de tuning do OPcache

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60      ; frequência de checagem de timestamp
opcache.validate_timestamps=0    ; em produção: 0 (deploy reinicia OPcache)
opcache.jit=tracing
opcache.jit_buffer_size=64M
opcache.preload=/var/www/app/preload.php
```

- Em produção, `validate_timestamps=0` evita stat() a cada requisição; o deploy
  deve limpar o OPcache (ex.: `opcache_reset()` ou reinício do SAPI).
- Monitore via `opcache_get_status()`.

## 6. Benchmark e medição

```php
$inicio = hrtime(true);
// ... código ...
$fim = hrtime(true);
echo 'Levou ', ($fim - $inicio) / 1e6, ' ms', PHP_EOL;
```

- Use `opcache_jit_blacklist()` (nova no 8.4) para excluir funções específicas
  do JIT se causarem regressão.
- Ferramentas: `phpbench`, `ApacheBench`, perf/strace para análise real.

## 7. Melhorias de performance do 8.4 (selecionadas)

Conforme `migration84.other-changes.html`:

- Parsing/formatação de floats em builds ZTS sob alta concorrência.
- `sprintf()` com apenas `%s`/`%d` compilado para interpolação nativa.
- Hash SHA-256 com SSE2/SHA-NI (~1.3x a 5x em CPUs suportadas).
- `strspn`/`strcspn` em tempo linear; `get_browser()` até 2.5x mais rápido.
- DOM/XML e MBString mais rápidos; PCRE com named groups melhorado.
- `Random\Randomizer` mais rápido (`getBytes`/`getBytesFromString`).

Todos os exemplos e diretivas acima são aplicáveis em PHP 8.4.
