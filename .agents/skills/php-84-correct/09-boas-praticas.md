Documento comum — válido para PHP 7.2, 7.4 e 8.4. Recortado para as pastas de cada versão.

# Boas Práticas (Baseline)

Convenções e padrões seguros e portáveis entre PHP 7.2, 7.4 e 8.4.

## 1. Namespaces

Use namespaces (desde 5.3) para organizar código e evitar colisões.

```php
<?php
namespace App\Servico;

class Email {
    public function enviar(string $para, string $corpo): void {}
}
```

Referencie com nome completo (`\App\Servico\Email`), `use` ou alias:

```php
<?php
use App\Servico\Email;
use App\Repositorio\Usuario as RepoUsuario;

$email = new Email();
```

## 2. Autoloading e PSR-4

Nunca use `require`/`include` manuais para cada classe. Registre um autoloader
com `spl_autoload_register()` seguindo o mapeamento PSR-4
(namespace → diretório).

```php
<?php
spl_autoload_register(function (string $classe): void {
    $prefixo = 'App\\';
    $baseDir = __DIR__ . '/src/';
    $len = strlen($prefixo);
    if (strncmp($prefixo, $classe, $len) !== 0) {
        return;
    }
    $relativo = substr($classe, $len);
    $arquivo = $baseDir . str_replace('\\', '/', $relativo) . '.php';
    if (is_file($arquivo)) {
        require $arquivo;
    }
});
```

Em projetos reais, use Composer (`composer.json` com `autoload.psr-4`), que
gera um autoloader PSR-4 eficiente e compatível com OPcache.

```json
{
  "autoload": {
    "psr-4": { "App\\": "src/" }
  }
}
```

## 3. Interfaces como contratos

Programe voltado a interfaces, não a implementações. Isso desacopla e facilita
testes.

```php
<?php
interface RepositorioUsuario {
    public function salvar(array $dados): void;
    public function buscar(int $id): ?array;
}

class RepositorioUsuarioBanco implements RepositorioUsuario {
    public function salvar(array $dados): void {}
    public function buscar(int $id): ?array { return []; }
}
```

## 4. Traits para reuso

Use traits para compartilhar comportamento entre classes não relacionadas por
herança (vide "POO").

```php
<?php
trait Timestampavel {
    private $criadoEm;
    public function criadoEm(): ?DateTime { return $this->criadoEm; }
}
```

## 5. Injeção de dependência (DI)

Em vez de instanciar dependências dentro das classes, receba-as via
construtor. Torna o código testável e flexível.

```php
<?php
class ServicoPedido {
    private $repo;
    private $email;

    public function __construct(RepositorioUsuario $repo, Email $email) {
        $this->repo = $repo;
        $this->email = $email;
    }

    public function concluir(int $id): void {
        $this->repo->salvar(['id' => $id, 'status' => 'concluido']);
        $this->email->enviar('pedido@exemplo.com', 'Concluído');
    }
}
```

Use um container de DI (ex.: o do Composer/Symfony) para compor objetos em
aplicações maiores — mas a prática acima (construtor explícito) já é suficiente
e portátil.

## 6. Tratamento de erros com Throwable/Error/Exception

Capture `Exception` para erros de aplicação e `Error` para erros do motor;
`Throwable` é a raiz comum (vide "POO"). No topo da aplicação, registre um
handler global:

```php
<?php
set_exception_handler(function (Throwable $e): void {
    error_log($e);
    http_response_code(500);
});
```

Lance exceções específicas em vez de retornar códigos de erro:

```php
<?php
function dividir(int $a, int $b): float {
    if ($b === 0) {
        throw new InvalidArgumentException('Divisor zero');
    }
    return $a / $b;
}
```

## 7. Imutabilidade e objetos de valor

Para objetos imutáveis, use setters privados / construtores completos e não
exponha mutação:

```php
<?php
final class Dinheiro {
    private $centavos;

    public function __construct(int $centavos) {
        if ($centavos < 0) {
            throw new InvalidArgumentException('Valor negativo');
        }
        $this->centavos = $centavos;
    }

    public function centavos(): int {
        return $this->centavos;
    }

    public function somar(Dinheiro $outro): Dinheiro {
        return new Dinheiro($this->centavos + $outro->centavos());
    }
}
```

Padrões de imutabilidade no baseline:

- `final class` para evitar subclasses que quebrem a invariante.
- Construtor valida o estado; nenhum setter público.
- Métodos que "alteram" retornam uma **nova** instância.
- Tipos escalares + `strict_types` garantem contratos de entrada.

## 8. Convenções gerais

- Siga PSR-12 de estilo de código (indentação, nomes, chaves).
- Um arquivo por classe; nome da classe = nome do arquivo (PSR-4).
- Prefira tipos explícitos (vide "Tipagem").
- Evite variáveis globais; passe dependências.
- Use `declare(strict_types=1)` em bibliotecas.
- Escreva código que roda igual em 7.2, 7.4 e 8.4: nada de recursos
  version-specific.

## 9. Resumo

- Namespaces + PSR-4 para organização e autoload.
- Interfaces como contratos; traits para reuso.
- Injeção de dependência via construtor.
- Erros via exceções (`Throwable`); handler global para log.
- Imutabilidade por convenção (construtor + sem setters públicos), já que
  `readonly` não existe no 7.2.
