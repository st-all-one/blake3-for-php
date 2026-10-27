<?php
/**
 * Exemplo abrangente — PHP 8.4
 * ---------------------------------------------------------------------------
 * Demonstra, num único arquivo, as capacidades disponíveis até o PHP 8.4:
 *   diretivas, tipagem, programação funcional, POO e logs — incluindo os
 *   recursos modernos (match, nullsafe, enums, atributos, readonly,
 *   constructor promotion, first-class callable, property hooks,
 *   visibilidade assimétrica e lazy objects).
 *
 * O CÓDIGO abaixo é 100% executável em PHP 8.4. Recursos removidos em
 * versões anteriores (ex.: create_function, each(), mcrypt) NÃO são usados.
 *
 * Execute com: php exemplo-completo.php
 */

declare(strict_types=1);

namespace Exemplo\V84;

/* ============================ TIPAGEM ============================ */

// Union types (8.0), mixed (8.0) e never (8.1).
function normalizar(string|int $v): string
{
    return (string) $v;
}

// Constructor property promotion (8.0) + readonly (8.1).
readonly class Ponto
{
    public function __construct(
        public int $x,
        public int $y,
    ) {}
}

/* ===================== PROGRAMAÇÃO FUNCIONAL ===================== */

$nums = [1, 2, 3, 4, 5];

// Arrow functions (7.4) + first-class callable (8.1): strlen(...).
$dobro  = array_map(fn(int $n): int => $n * 2, $nums);
$len    = array_map(strlen(...), ['a', 'bb', 'ccc']);   // 1,2,3
$somaTot = array_reduce($nums, fn(int $a, int $n): int => $a + $n, 0);

/* ============================ POO ============================ */

// Enumerações (8.1).
enum Status: string
{
    case ATIVO = 'ativo';
    case INATIVO = 'inativo';

    public function label(): string
    {
        return match ($this) {            // match (8.0)
            self::ATIVO   => 'Ativo',
            self::INATIVO => 'Inativo',
        };
    }
}

// Atributos (8.0).
#[\Attribute(\Attribute::TARGET_METHOD)]
class Rota
{
    public function __construct(public string $path) {}
}

interface Repositorio
{
    public function salvar(string $dado): void;
}

trait Auditavel
{
    /** @var list<string> */
    private array $log = [];

    public function registrar(string $evento): void
    {
        $this->log[] = $evento;
    }

    public function historico(): array
    {
        return $this->log;
    }
}

class Usuario implements Repositorio
{
    use Auditavel;

    // Visibilidade assimétrica (8.4): leitura pública, escrita privada.
    public private(set) int $id = 0;

    // Property hooks (8.4): propriedade virtual derivada.
    public string $nome {
        get => $this->nomeNormalizado;
        set => $this->nomeNormalizado = strtolower(trim($value));
    }

    private string $nomeNormalizado = '';

    // Constructor promotion (8.0).
    public function __construct(string $nome)
    {
        $this->nome = $nome;
        $this->registrar("criado: {$this->nome}");
    }

    public function salvar(string $dado): void
    {
        $this->registrar("salvo: {$dado}");
    }

    public function __toString(): string
    {
        return "Usuario({$this->nome})";
    }

    #[Rota('/perfil')]                    // atributo aplicado
    public function perfil(): string
    {
        return $this->nome;
    }
}

// Nullsafe operator (8.0) + argumentos nomeados (8.0).
$user = new Usuario(nome: 'Ana');         // argumento nomeado
$rotulo = $user?->perfil();               // nullsafe

// Classe anônima (7.0+).
$servico = new class {
    public function ping(): string { return 'pong'; }
};

// Lazy objects (8.4) — inicialização preguiçosa via ghost.
class ServicoPesado
{
    public function calcular(): int
    {
        return 42;
    }
}

$ref = new \ReflectionClass(ServicoPesado::class);
$proxy = $ref->newLazyGhost(function (ServicoPesado $o): void {
    // inicialização sob demanda
});

/* ===================== GENERATORS / ITERAÇÃO ===================== */

function contagem(int $ate): \Generator
{
    for ($i = 1; $i <= $ate; $i++) {
        yield $i;
    }
}

foreach (contagem(3) as $n) {
    echo "gen: {$n}" . PHP_EOL;
}

/* ============================ LOGS ============================ */

class ArquivoLogger
{
    /** @var resource */
    private $handle;

    public function __construct(string $arquivo)
    {
        $this->handle = fopen($arquivo, 'a');
        if ($this->handle === false) {
            throw new \RuntimeException("Não foi possível abrir {$arquivo}");
        }
    }

    public function info(string $msg): void
    {
        $this->escrever('INFO', $msg);
    }

    public function erro(string $msg): void
    {
        $this->escrever('ERRO', $msg);
        error_log("[ERRO] {$msg}");
    }

    private function escrever(string $nivel, string $msg): void
    {
        fwrite($this->handle, date('c') . " [{$nivel}] {$msg}" . PHP_EOL);
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}

set_error_handler(function (int $cod, string $msg): bool {
    if (!(error_reporting() & $cod)) {
        return false;
    }
    throw new \ErrorException($msg, 0, $cod);
});

$logger = new ArquivoLogger(sys_get_temp_dir() . '/exemplo-84.log');

try {
    $p = new Ponto(1, 2);
    echo "{$p->x},{$p->y}" . PHP_EOL;     // 1,2

    $s = new Usuario(nome: 'Ana');
    $s->salvar('perfil');
    $logger->info((string) $s);
    echo Status::ATIVO->label() . PHP_EOL;   // Ativo
    echo implode(',', $len) . PHP_EOL;       // 1,2,3
    echo $servico->ping() . PHP_EOL;         // pong
    echo $proxy->calcular() . PHP_EOL;       // 42 (lazy)
    echo $rotulo . PHP_EOL;                  // ana

    try {
        throw new \InvalidArgumentException('exemplo');
    } catch (\InvalidArgumentException $e) {
        $logger->erro($e->getMessage());
    } finally {
        $logger->info('bloco finally executado');
    }
} catch (\Throwable $t) {
    $logger->erro($t->getMessage());
}

/* ================ TUDO DISPONÍVEL ATÉ 8.4 ================
 * Este arquivo usa: typed properties, arrow functions, constructor
 * promotion, readonly, enums, atributos, match, nullsafe, argumentos
 * nomeados, union types, first-class callable, property hooks,
 * visibilidade assimétrica e lazy objects. JIT (8.4) é opção de OPcache
 * em php.ini, não sintaxe — ver 25-config-web-phpini.md.
 */
