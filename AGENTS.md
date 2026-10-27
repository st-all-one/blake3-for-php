# AGENTS.md — st-all-one/blake3

> Constituição do projeto para humanos e agentes. O plano de execução está em `PLAN.md`.

## 0. Regra de ouro — o agente não commita

**Somente o humano commita.** O agente nunca executa `git commit`, `git tag`, `git push`,
`git add` seguido de commit, `amend`, `rebase`, `cherry-pick`, `reset` ou qualquer reescrita
de histórico. Git é somente leitura (`status`, `diff`, `log`, `show`, `blame`).

## 1. Identidade e escopo

- Pacote Composer: `st-all-one/blake3`.
- Namespace PHP: `StAllOne\Blake3` (PSR-4 em `src/`).
- Namespace de testes: `StAllOne\Blake3\Tests` (PSR-4 em `tests/`).
- Biblioteca pura, sem framework, sem dependências de runtime além de `php ^8.4` e
  `php-64bit`.
- Implementa BLAKE3: hash, keyed hash, derive-key, XOF e streaming.
- Entrada principal: `StAllOne\Blake3\blake3_hash(string $data, int $length = 32, bool $rawOutput = false): string`.

## 2. Contratos invioláveis

- `declare(strict_types=1)` em todo arquivo PHP.
- Nenhuma função global `blake3_hash`; a API é namespaced para não colidir com
  `ext-blake3`.
- Saída padrão hexadecimal minúscula; `$rawOutput = true` devolve bytes.
- `$length >= 1`; `ValueError` caso contrário.
- Chave keyed com exatamente 32 bytes; `InvalidArgumentException` caso contrário.
- `Hasher::finalize()` é não destrutivo e pode ser chamado várias vezes.
- Nenhuma extensão é obrigatória. `ext-blake3` é apenas uma sugestão de performance.
- Builds de 32 bits não são suportados.

## 3. Arquitetura

```
src/Hasher.php       compressão, chunk, árvore, pilha de CVs, XOF, streaming
src/functions.php    blake3_hash, blake3_keyed_hash, blake3_derive_key
tests/Unit/          vetores oficiais, streaming, estado, validação, funções
tests/Support/       carregador dos vetores oficiais
benchmarks/bench.php baseline de performance
tools/               coverage-check.php, consumer-smoke.sh
```

Regras de código:

- classe `final`, construtor privado, fábricas estáticas explícitas;
- sem traits, interfaces cerimoniais, containers, facades ou camadas de abstração;
- sem alocação de objetos por bloco no caminho quente;
- palavras de 32 bits mascaradas com `0xFFFF_FFFF`; contador de 64 bits separado em
  `counter_low`/`counter_high`;
- nenhuma dependência de JIT, OPcache ou extensão para correção;
- qualquer mudança no algoritmo exige os 35 vetores oficiais verdes e as duas bases.

## 4. Comandos canônicos

O caminho único de validação é `make check`, que roda na trixie (glibc) e na alpine (musl):

```bash
make build          # constrói as duas imagens de desenvolvimento
make install        # composer install com composer.lock
make check          # validate + Mago lint/format + PHPStan max + PHPUnit (duas bases)
make coverage       # PCOV; falha abaixo de 100% de linhas em src/
make smoke          # instala o pacote em um projeto externo temporário
make bench          # baseline de performance nas duas bases
make bench-json     # baseline em JSON
```

- Não rode PHP, Composer ou ferramentas direto no host: use os alvos do `Makefile`.
- `make check` é o gate; não valide com comandos avulsos que não cobrem as duas bases.
- As imagens e o binário do Composer são fixados por digest em `docker/*.Dockerfile`.
- `composer.lock` é versionado; não remova.
- PCOV e assertions ficam desligados em benchmark (`-d pcov.enabled=0 -d zend.assertions=-1`).

## 5. Ferramentas e rigor

- **PHPStan** nível `max`, sem baseline, com `strict-rules`, `deprecation-rules`,
  `phpstan-phpunit` e `bleedingEdge`; `paths` inclui `src`, `tests`, `tools`.
- **Mago** 1.59.0: `mago lint`, `mago format --check`; configuração em `mago.toml`.
  Os thresholds ajustados (complexidade, métodos, parâmetros, kan) são justificados no
  próprio arquivo. Não adicione ignores amplos.
- **PHPUnit** 13.4.5 com `failOnWarning`, `failOnDeprecation`, `failOnNotice`,
  `failOnRisky`, `requireCoverageMetadata` e ordem determinística.
- **Cobertura** 100% de linhas em `src/` via PCOV + `tools/coverage-check.php`.
- **Composer** `validate --strict`, `audit` sem advisories.
- **Versões** de PHPUnit, PHPStan e Mago fixadas; PHP 8.4.27 nas imagens.

## 6. Testes

- Os vetores oficiais em `tests/fixtures/test_vectors.json` são imutáveis.
- Cubra hash, keyed, derive, XOF, raw output, streaming, bordas de 64/1024 bytes,
  transições de árvore, reutilização de estado e caminhos de erro.
- Testes determinísticos: nada de aleatoriedade, relógio, rede ou ordem implícita.
- Não use mocks para o algoritmo; os vetores são a autoridade.
- Toda nova otimização deve manter a suíte verde e comparar `make bench` antes/depois.

## 7. Performance

- Meça antes de otimizar; registre o número no `BENCHMARKS.md`.
- Faça A/B na mesma imagem/host e, se possível, no mesmo processo; throughput absoluto
  varia com CPU. Cubra hash pequeno, hash grande, hashes sequenciais, streaming e XOF.
- PCOV e assertions ficam desligados no A/B (`-d pcov.enabled=0 -d zend.assertions=-1`) e os dois lados usam o mesmo input.
- Não introduza dependência de extensão, FFI ou JIT para ganhar performance. JIT tracing
  foi medido e não trouxe ganho neste código; não o assuma como melhoria.
- Mantenha o caminho quente sem objetos, closures por bloco ou cópias desnecessárias.
- `Hasher::compress()` é intencionalmente grande: os Gs inline eliminam chamadas no
  caminho quente. Não o divida sem um A/B que prove ganho; o `@mago-expect lint:halstead`
  é específico e justificado nesse método.

## 8. Documentação e release

- Atualize `README.md`, `PLAN.md` e `CHANGELOG.md` quando o contrato público mudar.
- Mudança incompatível na API exige major.
- O agente entrega mudanças e evidências; o humano revisa, commita e publica.
- Release oficial somente por tag **anotada** `vX.Y.Z` em `main`, com o primeiro heading
  do `CHANGELOG.md` igual à versão. O workflow `.github/workflows/release.yml` roda
  `make check`, `make smoke` e `make audit`, gera arquivos `.tar.gz`/`.zip` + `SHA256SUMS`
  e publica via `GITHUB_TOKEN`; não exige secrets adicionais.
- Nunca mova ou apague uma tag já publicada; publique uma nova versão patch.

## 9. Definição de pronto

- `make check` verde nas duas bases.
- `make coverage` em 100%.
- `make smoke` verde.
- `make bench` executado e `BENCHMARKS.md` atualizado quando houver mudança de performance.
- Nenhum artefato temporário (`build/`, `vendor/`, caches) versionado.
