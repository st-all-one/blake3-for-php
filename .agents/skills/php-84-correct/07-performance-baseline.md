Documento comum — válido para PHP 7.2, 7.4 e 8.4. Recortado para as pastas de cada versão.

# Performance (Baseline)

Otimizações e recursos de desempenho disponíveis em PHP 7.2, 7.4 e 8.4. Tudo
aqui é seguro e estável nessas versões — não mencione preloading/JIT (8.0+) ou
WeakReference (7.4+) neste documento comum.

## 1. OPcache

OPcache armazena em cache os opcodes compilados do PHP, eliminando a
recompilação a cada requisição. É a otimização de maior impacto.

### Diretivas principais (php.ini)

| Diretiva | Descrição |
|---|---|
| `opcache.enable` | habilita o OPcache (1) |
| `opcache.memory_consumption` | memória para o cache de scripts (MB) |
| `opcache.interned_strings_buffer` | memória para strings internas (MB) |
| `opcache.max_accelerated_files` | nº máximo de arquivos em cache |
| `opcache.validate_timestamps` | revalida o timestamp do arquivo (1 em dev, 0 em prod) |
| `opcache.revalidate_freq` | frequência (s) de revalidação quando `validate_timestamps=1` |
| `opcache.optimization_level` | máscara de nível de otimização |

### Recomendação

- **Desenvolvimento:** `opcache.validate_timestamps=1`, `revalidate_freq=0` (recarrega mudanças).
- **Produção:** `opcache.validate_timestamps=0` (máxima velocidade); ao implantar,
  limpe o cache (reinicialize o SAPI ou use `opcache_reset()` via ferramenta de deploy).

```php
<?php
// Verificar status (apenas para diagnóstico/ferramentas internas)
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status(false);
    // $status['opcache_enabled'], $status['cache_full'], etc.
}
```

> Mantenha o cache do OPcache invalidado a cada deploy em produção para
> garantir que o código novo seja recompilado.

## 2. Geradores

Geradores processam grandes volumes sem materializar tudo em memória (vide
"Programação Funcional"). Use-os para leitura/escrita de streams e pipelines.

```php
<?php
function linhas(string $arquivo): Generator {
    $h = fopen($arquivo, 'r');
    while (($linha = fgets($h)) !== false) {
        yield rtrim($linha);
    }
    fclose($h);
}
```

## 3. Autoloading

Carregue classes sob demanda em vez de `require` em massa. Use
`spl_autoload_register()` (PSR-4 na prática). Ver "Boas Práticas" para detalhes.

```php
<?php
spl_autoload_register(function (string $classe) {
    $arquivo = __DIR__ . '/' . str_replace('\\', '/', $classe) . '.php';
    if (is_file($arquivo)) {
        require $arquivo;
    }
});
```

## 4. `foreach`: por valor vs. por referência

- `foreach ($arr as $v)` copia o valor (em 7+, itera sobre uma cópia interna —
  modificar `$arr` durante a iteração não afeta a iteração).
- `foreach ($arr as &$v)` itera por referência, permitindo modificar o array
  original. **Cuidado:** a variável `$v` permanece vinculada ao último elemento
  após o loop — desvincule com `unset($v)`.

```php
<?php
foreach ($nomes as &$nome) {
    $nome = strtoupper($nome);
}
unset($nome); // evita efeito colateral acidental
```

Prefira `foreach` a `for` para arrays associativos. Para modificar sem
referência, use `array_map`/`array_walk`.

## 5. Coleta de lixo (Garbage Collection)

O PHP gerencia memória automaticamente. Para ciclos de referência (objetos que
se referenciam mutuamente), a GC pode ser controlada:

- `gc_enable()` / `gc_disable()` — liga/desliga a coleta.
- `gc_collect_cycles()` — força uma rodada (útil em loops longos que criam
  muitos ciclos).
- `zend.enable_gc` (php.ini) — habilita a GC.

```php
<?php
gc_enable();
// ... processamento que cria muitos ciclos de referência ...
gc_collect_cycles();
```

Em geral, deixe a GC automática ativa; chame `gc_collect_cycles()` apenas em
rotinas de longa duração com alto volume de objetos interligados.

## 6. Outras diretrizes de performance

- Evite `count()`/funções caras dentro de condições de loop; pré-calcule.
- Prefira arrays para conjuntos simples. `SplFixedArray` pode reduzir overhead em
  coleções grandes e duradouras, mas em working sets numéricos pequenos e quentes
  (por bloco) ele adiciona um objeto e dispatch de método; meça antes.
- Use tipos escalares + `strict_types` para evitar coerções em hot paths.
- Cache resultados de I/O e consultas; evite trabalho redundante por requisição.
- Mantenha OPcache ativo e bem dimensionado (`max_accelerated_files` alto o
  suficiente para todos os arquivos do projeto).

## 7. Resumo

1. OPcache ativado (validate_timestamps=0 em produção).
2. Geradores para fluxos grandes.
3. Autoloading via `spl_autoload_register`.
4. `foreach` por referência com `unset` posterior; cuidado com cópias.
5. GC automática; `gc_collect_cycles()` em rotinas pesadas.
