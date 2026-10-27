# Plano — BLAKE3 em PHP 8.4 puro

> Documento de execução sequencial. Cada fase tem critério de aceite verificável.
> Referências normativas: `REF/BLAKE3-specs/` (especificação) e `REF/BLAKE3/`
> (implementação de referência e vetores oficiais). Skill de linguagem:
> `.agents/skills/php-84-correct/`.

## 1. Objetivo

Publicar `st-all-one/blake3`, uma biblioteca leve, determinística e agnóstica de framework que
implementa BLAKE3 em PHP 8.4 puro, com:

- `blake3_hash(string $data, int $length = 32, bool $rawOutput = false): string` como
  entrada principal (hex minúsculo por padrão);
- hash keyed, derivação de chave e saída estendida (XOF);
- modo incremental (`Hasher::update()` / `Hasher::finalize()`);
- zero dependências de runtime (somente `php` e `php-64bit`);
- validação nos dois runtimes exigidos: glibc (`php:8.4.27-fpm-trixie`) e musl
  (`php:8.4.27-fpm-alpine3.24`), ambos fixados por digest;
- PHPStan no nível `max` sem baseline, Mago, PHPUnit estrito e cobertura de linhas 100%.

## 2. Status da execução (2026-10-27)

- Pacote `st-all-one/blake3`, namespace `StAllOne\Blake3`.
- 35 vetores oficiais verdes em hash, keyed e derive (XOF de 131 bytes).
- 354 testes / 693 asserções verdes; 343/343 statements cobertos (100%).
- PHPStan `level: max` sem baseline, Mago sem ocorrências, Composer válido e audit limpo.
- Suíte completa verde em `php:8.4.27-fpm-trixie` (glibc) e
  `php:8.4.27-fpm-alpine3.24` (musl).
- Consumo externo via repositório `path` validado.
- Baseline de performance: 1 MiB ≈ 102–113 ms (≈ 8.9–9.8 MiB/s) nas duas bases, PCOV/assertions desligados; A/B contra a versão inicial ~2,2–3,2×.
- Evidências detalhadas em `BENCHMARKS.md`.

## 3. Não objetivos

- Substituir `password_hash`/Argon2id. BLAKE3 não é função de senha.
- Fornecer bindings para `ext-blake3` ou qualquer extensão nativa.
- Paralelismo/SIMD. PHP puro é escalar; o foco é correção, portabilidade e API estável.
- Compatibilidade com PHP < 8.4 ou builds de 32 bits.
- Abstrações de framework, traits, interfaces cerimoniais, containers, facades.

## 4. Contrato público (congelado antes da implementação)

Namespace `StAllOne\Blake3`, autoload PSR-4 em `src/` e funções em `src/functions.php`.

```php
use StAllOne\Blake3\Hasher;
use function StAllOne\Blake3\blake3_hash;
use function StAllOne\Blake3\blake3_keyed_hash;
use function StAllOne\Blake3\blake3_derive_key;

blake3_hash('abc');                  // 64 caracteres hex (32 bytes)
blake3_hash('abc', 131);             // XOF: 262 caracteres hex
blake3_hash('abc', 32, true);        // 32 bytes binários
blake3_keyed_hash($key32, 'abc');    // 64 caracteres hex
blake3_derive_key($context, $material);
Hasher::new()->update('a')->update('b')->finalize();
Hasher::newKeyed($key32);
Hasher::newDeriveKey($context);
```

Regras:

- `$length >= 1`; caso contrário `ValueError`.
- Chave keyed com exatamente 32 bytes; caso contrário `InvalidArgumentException`.
- `finalize()` é não destrutivo: pode ser chamado várias vezes e `update()` continua válido.
- Saída padrão é hexadecimal minúscula; `$rawOutput = true` devolve bytes binários.
- O sufixo `_raw` não existe: um único parâmetro booleano evita duplicação de API.
- A função global `blake3_hash` **não** é declarada, para não colidir com a extensão
  `ext-blake3`; o consumidor importa `use function StAllOne\Blake3\blake3_hash;`.

## 5. Invariantes técnicos

- `declare(strict_types=1)` em todos os arquivos PHP.
- Somente PHP 8.4; constantes de classe tipadas, `readonly` e promoção de construtor.
- Nenhuma alocação evitável no caminho quente: palavras em arrays locais, sem objetos por
  bloco, sem closures por compressão, sem `yield` no núcleo.
- Palavras de 32 bits em `int` de 64 bits com máscara `0xFFFFFFFF` em cada adição/rotação.
- Contadores de 64 bits separados em `counter_low` e `counter_high` na compressão.
- Estrutura de árvore idêntica à implementação de referência (pilha de CVs, mescla por
  zeros à direita do total de chunks).
- Vetores oficiais são imutáveis e ficam versionados em `tests/fixtures/test_vectors.json`.

## 6. Estrutura final

```
composer.json          pacote, autoload e scripts
phpstan.neon           nível max + strict + deprecation + PHPUnit + dead code
phpunit.xml            suíte estrita, cobertura e metadados obrigatórios
mago.toml              lint + formatter
Makefile               interface única (build, install, check, coverage, bench)
docker/                trixie.Dockerfile e alpine.Dockerfile (bases fixadas por digest)
src/Hasher.php         núcleo BLAKE3 + API incremental
src/functions.php      API funcional de uma chamada
tests/Unit/            vetores oficiais, streaming, API, erros e bordas
tests/fixtures/        vetores oficiais (CC0/Apache-2.0, créditos no README)
tools/                 coverage-check.php e consumer-smoke.sh
benchmarks/bench.php   medição local (não é gate)
.github/workflows/     CI em Docker, duas bases
AGENTS.md              constituição para humanos e agentes
PLAN.md                este documento
README.md              instalação, API, segurança e desenvolvimento
BENCHMARKS.md          baseline de performance e candidatos de otimização
CHANGELOG.md           histórico a partir de 0.1.0
```

## 7. Fases sequenciais

### Fase 0 — Baseline e determinismo

- [x] Criar `composer.json` com `require` mínimo (`php ^8.4`, `php-64bit`) e autoload.
- [x] Fixar a plataforma de resolução em PHP 8.4.27.
- [x] Criar `phpstan.neon` (`level: max`, sem baseline, strict/deprecation/PHPUnit/dead code).
- [x] Criar `phpunit.xml` com `failOnWarning/Deprecation/Notice/Risky`, ordem determinística
      e `requireCoverageMetadata`.
- [x] Criar `docker/trixie.Dockerfile` e `docker/alpine.Dockerfile` a partir dos digests
      exigidos; instalar Composer fixado por digest e PCOV 1.0.12.
- [x] Criar `Makefile` com alvos `build`, `install`, `lint`, `static`, `test`, `check`,
      `coverage`, `bench`, `smoke`.
- [x] Gerar `composer.lock` e versioná-lo.
- [x] Criar `.gitignore`, `.gitattributes`, `.editorconfig` e `.dockerignore`.

Aceite: `make install` conclui nas duas imagens; `php -v` reporta 8.4.27 em ambas.

### Fase 1 — Núcleo de compressão

- [x] Constantes IV, flags, tamanhos, permutação de mensagem e máscara de 32 bits.
- [x] `compress()` com 7 rodadas, `G`, rotações à direita e separação de contador 64 bits.
- [x] `chainingValue()` (primeiras 8 palavras) e `rootOutputBytes()` (XOF com flag ROOT).
- [x] Teste de sanidade contra o vetor de entrada vazia antes de seguir.

Aceite: `blake3_hash('')` = `af1349b9...cce14d` (131 bytes) e prefixo de 32 bytes
`af1349b9f5f9a1a6a0404dea36dcc9499bcb25c9adc112b7cc9a93cae41f3262`.

### Fase 2 — Chunks, árvore e streaming

- [x] Estado de chunk: CV, contador, buffer de 64 bytes, `block_len`, `blocks_compressed`.
- [x] `update()` com transição de chunk em 1024 bytes e compressão de blocos não finais.
- [x] Pilha de CVs e `addChunkChainingValue()` com mescla de subárvores.
- [x] `finalize()` não destrutivo, montagem da borda direita até a raiz e XOF.
- [x] Fábricas `new()`, `newKeyed()`, `newDeriveKey()` com validação de chave.

Aceite: todos os vetores oficiais passam nos três modos e em XOF de 131 bytes.

### Fase 3 — API pública

- [x] `src/functions.php`: `blake3_hash`, `blake3_keyed_hash`, `blake3_derive_key`.
- [x] Documentação PHPDoc com tipos, exceções e semântica de `$rawOutput`.
- [x] Garantir que `finalize()` repetido não altera estado e que `update()` pós-finalize
      produz a concatenação correta.
- [x] Testar `ValueError` para comprimento < 1 e `InvalidArgumentException` para chave
      diferente de 32 bytes.

Aceite: API funcional e incremental produzem exatamente a mesma saída.

### Fase 4 — Vetores e testes

- [x] Copiar `test_vectors.json` oficial para `tests/fixtures/` com atribuição.
- [x] Teste data-driven dos 35 casos oficiais (hash, keyed, derive; 131 bytes).
- [x] Verificar prefixo de 32 bytes e modo raw contra cada vetor.
- [x] Testar streaming com divisões 1, 63, 64, 65, 1023, 1024, 1025 e 4096 bytes.
- [x] Testar bordas de XOF: 1, 2, 3, 31, 32, 33, 63, 64, 65, 128, 131, 1000.
- [x] Testar bordas de árvore: 0, 1, 63, 64, 65, 1023, 1024, 1025, 2048, 2049,
      3072, 4096, 8192, 31744, 102400.
- [x] Atingir 100% de cobertura de linhas em `src/` com PCOV.
- [x] Criar `tools/coverage-check.php` lendo Clover e falhando abaixo de 100%.

Aceite: `make coverage` verde e `tools/coverage-check.php` reporta 100%.

### Fase 5 — Mago, PHPStan e estilo

- [x] Instalar `carthage-software/mago` 1.59.x e gerar `mago.toml`.
- [x] Rodar `mago fmt`, `mago lint` e corrigir todas as ocorrências sem ignores amplos.
- [x] Rodar `phpstan analyse` com `level: max` e zero erros, zero baseline.
- [x] Rodar `composer validate --strict`.
- [x] Rodar `composer audit` (quando houver rede) e registrar o resultado.

Aceite: `make lint`, `make static` e `make validate` verdes; nenhum ignore de erro.

### Fase 6 — Dupla base glibc/musl

- [x] Build das duas imagens com os digests exatos fornecidos.
- [x] `make test-trixie` e `make test-alpine` verdes.
- [x] Executar a suíte completa em ambas as bases; confirmar `PHP 8.4.27`.
- [x] Verificar que PCOV não é necessário em produção e que a biblioteca não usa
      extensões além das padrão.

Aceite: mesma suíte, mesmo resultado, `PHP 8.4.27` em glibc e musl.

### Fase 7 — Empacotamento e consumo externo

- [x] `composer dump-autoload --optimize --classmap-authoritative` sem avisos.
- [x] `tools/consumer-smoke.sh`: projeto temporário com repositório `path`, instala
      `st-all-one/blake3` e chama `StAllOne\Blake3\blake3_hash('abc')`, keyed e derive.
- [x] Confirmar que `vendor/` não é necessário para consumir e que `files` autoload
      carrega as funções.
- [x] Benchmark de 1 MiB documentado no README (sem promessa de throughput nativo).

Aceite: um projeto externo instala e obtém o hash correto sem configuração extra.

### Fase 8 — Documentação e fechamento

- [x] `README.md` com instalação, API, segurança, performance, testes e licença.
- [x] `AGENTS.md` com regras do projeto e comandos canônicos.
- [x] `CHANGELOG.md` com 0.1.0.
- [x] Atualizar este plano com os checkboxes concluídos e evidências.
- [x] Revisão final: `make check` nas duas bases + `make coverage` + `make smoke`.

Aceite: repositório limpo, sem artefatos temporários, com todos os gates verdes.

### Fase 9 — Otimização de performance (revisão)

- [x] Revisar o caminho quente à luz da skill PHP 8.4 e da implementação de referência.
- [x] Substituir o estado em array e as chamadas `round`/`mix`/`rotateRight`/`permute`
      por estado local, Gs inline e permutação in-place.
- [x] Evitar a cópia de chunk e o array intermediário de chaining value por chunk.
- [x] Medir A/B na mesma CPU/processo, PCOV desligado, em 64 B, 1 KiB, 64 KiB, 1 MiB,
      1.000 hashes pequenos, 1.000 de 1 KiB, 100 de 64 KiB e streaming de 1 MiB.
- [x] Validar equivalência em 2.361 casos (vetores oficiais, comprimentos 0..2100,
      matriz de streaming, 1 MiB+1 e XOF) além da suíte permanente.
- [x] Rejeitar experimentalmente full unroll, caminho direto de blocos, índice 1-based,
      retorno de 8 palavras, hoisting do XOF e JIT tracing quando não houve ganho.
- [x] Marcar chave e key material com `#[\SensitiveParameter]` e testar a redação no trace.
- [x] Cobrir XOF de 96/160/224 bytes e streaming keyed/derive contra o one-shot.
- [x] Medir performance com `zend.assertions=-1`, como em produção.
- [x] Atualizar `BENCHMARKS.md`, `README.md`, `CHANGELOG.md` e `AGENTS.md`.

Aceite: `make check` verde nas duas bases, 100% de cobertura, vetores oficiais verdes e
ganho A/B consistente em todos os cenários.

### Fase 10 — Release oficial

- [x] Workflow `release.yml` disparado por tag anotada `v*`.
- [x] Validar SemVer, tag anotada, ancestralidade em `main` e primeiro heading do
      `CHANGELOG.md`.
- [x] Rodar `make check`, `make smoke` e `make audit` antes de publicar.
- [x] Gerar `.tar.gz`/`.zip` com `git archive` e `SHA256SUMS`; validar os arquivos.
- [x] Publicar GitHub Release via `GITHUB_TOKEN`, com corpo gerado do changelog.
- [x] Documentar o fluxo de tag em `README.md` e `AGENTS.md`.

Aceite: uma tag anotada `vX.Y.Z` em `main` cria uma release com artefatos e checksums,
sem intervenção manual e sem secrets além do `GITHUB_TOKEN`.

## 8. Matriz de verificação

| Gate | Comando | Critério |
|---|---|---|
| Sintaxe | `php -l` via suíte/CI | zero erros |
| Estilo | `mago fmt --check` | zero diffs |
| Lint | `mago lint` | zero ocorrências |
| Estático | `phpstan analyse` | nível max, zero erros, sem baseline |
| Contrato | `composer validate --strict` | válido |
| Testes | `phpunit` | 100% verde, sem warnings/notices/deprecations |
| Cobertura | `phpunit --coverage-clover` + `coverage-check.php` | 100% de linhas em `src/` |
| glibc | suíte na trixie | verde |
| musl | suíte na alpine | verde |
| Consumo | `tools/consumer-smoke.sh` | instala e executa via `path` |
| Audit | `composer audit` | sem advisories conhecidos |
| Performance | `make bench` + A/B | sem regressão; cenários pequenos/grandes/sequenciais |
| Release | tag anotada `vX.Y.Z` | workflow publica artefatos + `SHA256SUMS` |

## 9. Riscos e mitigação

| Risco | Mitigação |
|---|---|
| Aritmética de 32 bits em PHP | máscara `0xFFFFFFFF` em toda adição/rotação; exigir `php-64bit` |
| Erro sutil na árvore/streaming | implementação espelhada da referência + 35 vetores + divisões de update |
| Colisão com `ext-blake3` | função namespaced; nenhuma função global declarada |
| Performance insuficiente | compressor escalar com estado local e Gs inline; A/B documentado; recomendação de extensão nativa para hotspots |
| Divergência de lint entre ferramentas | Mago formatter como fonte de estilo; PHPStan sem baseline; sem ignores amplos |
| Mago sem binário musl | lint/formatação na trixie; testes completos na alpine; registrar limitação se ocorrer |
| Dependências não determinísticas | `composer.lock`, versões fixas, imagens e Composer por digest |

## 10. Ordem de execução (comandos)

```bash
make build
make install
make lint
make static
make test
make coverage
make check
make smoke
make bench
```

`make check` deve ser o caminho único de validação: ele cobre as duas bases e falha se
qualquer gate falhar.
