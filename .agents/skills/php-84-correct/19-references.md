Complementar ao documento comum — específico do PHP 8.4.

# References (Referências) no PHP 8.4

Este documento cobre o modelo de referências do PHP, estável em toda a linha 8.x.
Todo o código aqui é válido e executável em PHP 8.4. Nenhuma funcionalidade
removida antes do 8.4 é utilizada.

## 1. Semântica de alias

Uma referência **não** é um ponteiro para endereço de memória no estilo C; é um
*alias* para o mesmo conteúdo (zval) de uma variável. Alterar um lado reflete no
outro.

```php
$a = 1;
$b = &$a;     // $b é alias de $a
$b = 2;
echo $a;      // 2
```

## 2. Atribuição por referência

```php
$original = 'foo';
$ref = &$original;
$ref = 'bar';
echo $original; // bar
```

## 3. Passagem por referência

```php
function acrescentar(int &$valor, int $inc): void
{
    $valor += $inc;
}

$x = 10;
acrescentar($x, 5);
echo $x; // 15
```

## 4. Retorno por referência

```php
class Contador
{
    private int $n = 0;

    public function &obter(): int
    {
        return $this->n;
    }
}

$c = new Contador();
$v = &$c->obter();
$v = 42;
```

Use retorno por referência com cuidado: ele pode expor estado interno. Em 8.4,
prefira propriedades `readonly` ou *property hooks* (ver `13-poo-84.md`) quando o
objetivo for controle de acesso.

## 5. `unset` em referências

`unset` quebra apenas o vínculo, não destrói o valor das demais variáveis:

```php
$a = 1;
$b = &$a;
unset($b);
$a = 5;
echo $a; // 5 (b já não é alias)
```

## 6. Referências em `foreach`

```php
$itens = [1, 2, 3];
foreach ($itens as &$item) {
    $item *= 2;
}
unset($item); // importante: rompe o alias final
print_r($itens); // [2, 4, 6]
```

> Cuidado: `$item` permanece como alias do último elemento após o loop. Sempre
> `unset($item)` ao terminar, ou evite referências no `foreach` quando possível.

## 7. Caveats importantes

- Referências **não** funcionam com retorno de funções que retornam por valor
  temporário:
  ```php
  // func() retorna por valor; não é possível referenciar:
  // $r = &func(); // Erro/aviso em tempo de compilação
  ```
- Arrays por referência podem criar "referências circulares" difíceis de depurar.
- Usar referência apenas para micro-otimização de arrays grandes costuma não
  compensar a complexidade; em 8.4 preferia cópias e estruturas imutáveis.
- Em parâmetros, uma referência não anula a tipagem: `int &$x` ainda exige `int`.

## 8. Exemplo combinado

```php
function mapa(array &$lista, callable $fn): void
{
    foreach ($lista as &$v) {
        $v = $fn($v);
    }
    unset($v);
}

$dados = [1, 2, 3];
mapa($dados, fn(int $n) => $n ** 2);
print_r($dados); // [1, 4, 9]
```
