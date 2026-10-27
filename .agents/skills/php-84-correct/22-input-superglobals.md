Complementar ao documento comum — específico do PHP 8.4.

# Superglobais, Entrada Externa e Funções Variáveis no PHP 8.4

Este documento cobre as superglobais, o tratamento **seguro** de entrada externa
e as *funções variáveis*. Todo o código é válido e executável em 8.4. Nenhuma
funcionalidade removida antes do 8.4 é utilizada. Para princípios de segurança,
veja `05-seguranca-basica.md` e `14-seguranca-84.md`.

## 1. Superglobais

Superglobais são arrays disponíveis em qualquer escopo:

| Superglobal | Conteúdo |
| --- | --- |
| `$_GET` | Parâmetros da query string |
| `$_POST` | Corpo de requisições `application/x-www-form-urlencoded` ou `multipart` |
| `$_REQUEST` | Mescla GET/POST/COOKIE (depende de `request_order`) — evite |
| `$_COOKIE` | Cookies enviados |
| `$_SERVER` | Cabeçalhos, caminhos e metadados da requisição |
| `$_ENV` | Variáveis de ambiente (se `variables_order` incluir `E`) |
| `$_FILES` | Uploads de arquivos |
| `$_SESSION` | Dados de sessão (após `session_start()`) |

```php
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
```

## 2. Tratamento seguro de entrada

**Nunca confie em entrada externa.** Valide e filtre:

### `filter_input` / `filter_var`

```php
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
if ($email === false || $email === null) {
    http_response_code(400);
    exit('E-mail inválido');
}

$idade = filter_input(INPUT_GET, 'idade', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0, 'max_range' => 150],
]);

$limpo = filter_var($valorBruto, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
```

### Constantes `FILTER_*`

- `FILTER_VALIDATE_INT`, `FILTER_VALIDATE_FLOAT`, `FILTER_VALIDATE_EMAIL`,
  `FILTER_VALIDATE_URL`, `FILTER_VALIDATE_BOOL`
- `FILTER_SANITIZE_STRING` (removido em 8.1) — **não use**; prefira
  `FILTER_SANITIZE_FULL_SPECIAL_CHARS` ou `htmlspecialchars()`.
- `FILTER_SANITIZE_FULL_SPECIAL_CHARS`, `FILTER_SANITIZE_ENCODED`,
  `FILTER_SANITIZE_NUMBER_INT`

```php
$num = filter_var('12a3', FILTER_SANITIZE_NUMBER_INT); // "123"
```

### Uploads

```php
if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
    $nome = basename($_FILES['arquivo']['name']);
    move_uploaded_file($_FILES['arquivo']['tmp_name'], "/tmp/$nome");
}
```

> Valide tipo de MIME e tamanho; nunca use o nome original sem sanitização.

## 3. Funções variáveis

### Chamada por nome de string

```php
function cumprimentar(string $n): string
{
    return "Olá, $n";
}

$fn = 'cumprimentar';
echo $fn('Ana'); // Olá, Ana
```

### `call_user_func` / `call_user_func_array`

```php
echo call_user_func('cumprimentar', 'Bia');

$params = ['soma', 1, 2];
echo call_user_func_array('call_user_func_array', []); // exemplo de composição
```

### Callable de primeira classe (8.1)

```php
$strlen = strlen(...);
echo $strlen('oi'); // 2

$result = array_map(strtoupper(...), ['a', 'b']); // ['A','B']
```

### Métodos e invocáveis

```php
class Servico
{
    public function acao(int $x): int { return $x * 2; }
}

$s = new Servico();
$callable = [$s, 'acao'];
echo $callable(3); // 6
```

## 4. Boas práticas

- Não use `$_REQUEST`; seja explícito com `$_GET`/`$_POST`.
- Use `filter_*` antes de qualquer uso de entrada externa.
- Para HTML de saída, `htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8')`.
- Prefira o callable de primeira classe a `call_user_func*` quando possível
  (melhor performance e tipagem).
