Complementar ao documento comum — específico do PHP 8.4.

# Namespaces no PHP 8.4

Este documento cobre *namespaces* (introduzidos no PHP 5.3 e presentes em toda a
linha 8.x). Todo o código aqui é válido e executável em PHP 8.4. Nenhuma
funcionalidade removida antes do 8.4 é utilizada.

> Nota: namespaces no 8.4 mantêm o mesmo modelo desde o PHP 7.x. O que muda no
> ecossistema é o uso combinado com *namespaced* functions/constants e a
> resolução dinâmica de nomes (possível desde o PHP 8.0).

## 1. Declaração de namespace

Cada arquivo pode conter uma única declaração de namespace (estilo "bloco"):

```php
<?php

namespace App\Dominio;

class Produto
{
    public function __construct(public string $nome) {}
}
```

A declaração deve aparecer antes de qualquer código (exceto `declare`).

## 2. Namespaces aninhados/compostos

O separador `\` define a hierarquia. Os dois blocos abaixo são equivalentes:

```php
namespace App\Dominio\Modelo;

class Usuario {}
```

```php
namespace App\Dominio {
    namespace Modelo {
        class Usuario {}
    }
}
```

A forma composta (`App\Dominio\Modelo`) é a recomendada e mais legível.

## 3. Namespace global

Código fora de qualquer `namespace` pertence ao namespace global. Para referenciar
um elemento global a partir de um namespace, use o prefixo `\`:

```php
namespace App;

$agora = \time();          // chama time() do namespace global
$json = \json_encode($x);  // função global
```

No namespace global, classes/funções/constantes definidas sem prefixo também são
globais:

```php
namespace {
    function auxiliar() {}
}
```

## 4. Importação e alias com `use`

`use` importa um nome completo para uso abreviado no arquivo:

```php
namespace App\Controlador;

use App\Dominio\Modelo\Usuario;
use App\Servico\Repositorio;

$u = new Usuario('Ana');
$r = new Repositorio();
```

### Agrupamento de `use` (desde 7.0)

```php
use App\Dominio\Modelo\{Usuario, Pedido, Categoria};
use function App\Util\{formatar, sanitizar};
use const App\Config\{LIMITE, VERSAO};
```

### Alias com `as`

```php
use App\Dominio\Modelo\Usuario as ModeloUsuario;
use App\Infra\Modelo\Usuario as InfraUsuario;

$a = new ModeloUsuario('X');
$b = new InfraUsuario('Y');
```

## 5. Fallback para o global

Se um nome não for encontrado no namespace atual, o PHP tenta o namespace global
para **funções e constantes** (mas não para classes). Por isso `strlen()` funciona
mesmo dentro de um namespace — a não ser que você defina `App\strlen()`.

```php
namespace App;

echo strlen('oi'); // resolve para \strlen() (global)
```

Para classes, o nome é sempre relativo ao namespace atual, salvo prefixo `\`.

## 6. Resolução de nomes

- **Nome não qualificado** (`Usuario`): resolvido relativo ao namespace atual.
- **Nome qualificado** (`Modelo\Usuario`): prefixado ao namespace atual.
- **Nome totalmente qualificado** (`\App\Usuario`): absoluto, ignorando o atual.

```php
namespace App\Controlador;

use App\Dominio\Modelo\Usuario;

new Usuario();        // App\Dominio\Modelo\Usuario (importado)
new Modelo\Pedido();  // App\Controlador\Modelo\Pedido (relativo)
new \Outro\X();       // \Outro\X (absoluto)
```

## 7. Resolução dinâmica de namespace (PHP 8.0+)

Desde o PHP 8.0, nomes de classes/funções/constantes podem ser construídos a
partir de variáveis, e `::class` resolve o nome completo mesmo para classes
importadas:

```php
namespace App\Controlador;

use App\Dominio\Modelo\Usuario;

$nome = Usuario::class;       // "App\Dominio\Modelo\Usuario"
$classe = $nome;
$instancia = new $classe('Z'); // new App\Dominio\Modelo\Usuario('Z')

$fn = '\strlen';
echo $fn('texto');            // usa a função global dinamicamente
```

Isso é útil para fábricas e containers. Evite montar nomes a partir de entrada
do usuário sem validação estrita.

## 8. `::class` e reflexão

`::class` retorna o nome totalmente qualificado sem instanciar a classe — seguro
e válido em 8.4:

```php
use App\Dominio\Modelo\Usuario;

echo Usuario::class; // App\Dominio\Modelo\Usuario
```

## 9. Boas práticas no 8.4

- Padronize um namespace por pacote (PSR-4).
- Prefira `use` explícito a nomes totalmente qualificados repetidos.
- Use `as` apenas quando houver colisão real de nomes.
- Combine com atributos e enums (ver `13-poo-84.md`) usando o mesmo namespace.
