Documento comum — válido para PHP 7.2, 7.4 e 8.4. Recortado para as pastas de cada versão.

# Sintaxe Básica do PHP

Este documento descreve a sintaxe básica do PHP que é comum e estável entre o
PHP 7.2, 7.4 e 8.4. Todo o código aqui apresentado executa corretamente em
qualquer uma essas versões.

## 1. Tags de abertura e fechamento

O PHP suporta várias formas de delimitar o código. Para código puramente PHP,
a tag de fechamento `?>` deve ser omitida (evita saída acidental de espaços em
branco).

```php
<?php
// Código PHP puro — a tag de fechamento é omitida intencionalmente.
echo "Olá";
```

A tag curta de saída `<?=` (echo curto) está sempre disponível desde o PHP 5.4
e não depende de `short_open_tag`:

```php
<?= htmlspecialchars($nome) ?>
```

> Nota: a diretiva `short_open_tag` habilita as tags `<?` e `<?php`, porém a
> tag de echo `<?=` é independente dessa configuração. Para máxima
> portabilidade, prefira sempre `<?php` e `<?=`.

## 2. Comentários

O PHP suporta três estilos de comentário:

```php
<?php
// Comentário de uma linha (estilo C++)

# Comentário de uma linha (estilo shell)

/* Comentário
   de múltiplas
   linhas (estilo C) */
```

Atenção: um comentário `//` termina no fim da linha ou no primeiro `?>`
literal. O estilo bloco `/* ... */` não pode ser aninhado.

## 3. Separação de instruções

As instruções são terminadas por ponto e vírgula (`;`). O encerramento de uma
tag PHP `?>` também funciona como terminador de instrução.

```php
<?php
echo "uma";
echo "duas";
echo "três" // terminada pela tag de fechamento
?>
```

## 4. Estruturas de controle e sintaxe alternativa

O PHP oferece `if`, `else`, `elseif`, `switch`, `while`, `do-while`, `for`,
`foreach`, `break`, `continue` e `goto`, além da sintaxe alternativa (útil em
templates HTML):

```php
<?php if ($logado): ?>
    <p>Bem-vindo, <?= htmlspecialchars($usuario) ?>.</p>
<?php else: ?>
    <p>Faça login.</p>
<?php endif; ?>

<?php foreach ($itens as $item): ?>
    <li><?= htmlspecialchars($item) ?></li>
<?php endforeach; ?>
```

A sintaxe alternativa usa dois pontos (`:`) após a condição e as palavras
`endif;`, `endwhile;`, `endfor;`, `endforeach;`, `endswitch;`.

## 5. Operadores modernos (disponíveis desde o PHP 7.0)

### Coalescência nula `??`

Retorna o operando da esquerda se ele existir e não for `null`; caso
contrário, retorna o da direita. É seguro mesmo que a variável não esteja
definida (não emite aviso).

```php
<?php
$usuario = $_GET['usuario'] ?? 'convidado';
$cor    = $config['cor'] ?? $_GET['cor'] ?? 'azul';
```

### Operador nave (spaceship) `<=>`

Retorna `-1`, `0` ou `1` quando o operando da esquerda é menor, igual ou maior
que o da direita, respectivamente.

```php
<?php
function comparar(int $a, int $b): int {
    return $a <=> $b;
}

$valores = [3, 1, 2];
usort($valores, function ($a, $b) { return $a <=> $b; });
```

## 6. Desempacotamento de argumentos em chamadas de função `...`

Desde o PHP 5.6 é possível "espalhar" os elementos de um array como argumentos
de uma função usando `...` na chamada:

```php
<?php
function soma(int $a, int $b, int $c): int {
    return $a + $b + $c;
}

$nums = [1, 2, 3];
echo soma(...$nums); // 6
```

Em versões mais recentes também é possível usar `...` na própria definição
(variádicas) — vide documento de programação funcional.

## 7. Strings, heredoc e nowdoc

### Aspas simples e duplas

```php
<?php
$simples = 'texto literal, sem interpolação de $var';
$dupla   = "valor de \$var interpolado: $var e quebra\n";
```

Aspas duplas interpolam variáveis (`$var`, `{$obj->prop}`, `{$arr['k']}`) e
sequências de escape (`\n`, `\t`, `\"`, `\\`). Aspas simples só reconhecem `\\`
e `\'`.

### Heredoc

O heredoc permite strings multilinha com interpolação. O identificador de
fechamento deve estar no início da linha, seguido apenas de `;`:

```php
<?php
$texto = <<<FIM
Este é um texto de múltiplas linhas.
Valor interpolado: $variavel
FIM;
```

### Nowdoc

Idêntico ao heredoc, mas com o identificador entre aspas simples — não há
interpolação nem escape:

```php
<?php
$texto = <<<'FIM'
Texto literal, $variavel NÃO é interpolada.
FIM;
```

## 8. Type juggling (coerção de tipos)

O PHP é uma linguagem de tipagem dinâmica: o tipo de um valor é determinado em
tempo de execução e conversões automáticas ocorrem conforme o contexto.

```php
<?php
$foo = "10 gatos";   // string
$foo += 2;           // 12 (int) — string convertida para número
$foo = $foo + 1.5;   // 13.5 (float)
```

Regras importantes:

| Origem \ Contexto | bool | int | float | string |
|---|---|---|---|---|
| `""`, `"0"` | `false` | 0 | 0.0 | — |
| demais strings não vazias | `true` | valor numérico ou 0 | — | — |
| `null` | `false` | 0 | 0.0 | `""` |
| `true` | — | 1 | 1.0 | `"1"` |

Para comparação, lembre-se da diferença entre `==` (igualdade com coerção) e
`===` (identidade de tipo e valor). Prefira `===`/`!==` para evitar surpresas
de coerção.

Use as funções de conversão explícita quando necessário:

```php
<?php
$int    = (int) $valor;
$float  = (float) $valor;
$str    = (string) $valor;
$bool   = (bool) $valor;
$array  = (array) $valor;
$obj    = (object) $valor;
```

> Evite casts obsoletos que já não existem nas versões modernas do PHP.

## 9. Variáveis variáveis

```php
<?php
$a = 'ola';
$$a = 'mundo';
echo $ola; // "mundo"
```

## 10. Resumo de boas práticas de sintaxe

- Use sempre `<?php` e `<?=`; omita `?>` em arquivos puramente PHP.
- Prefira `===` a `==` para comparações críticas.
- Use `??` em vez de `isset()` aninhado para valores padrão.
- Use `<?php ...: ?>`/`<?php endforeach; ?>` em templates para legibilidade.
- Evite depender de coerção implícita: faça conversões explícitas quando o
  tipo importar para a lógica de negócio.
