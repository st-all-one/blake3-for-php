Documento comum — válido para PHP 7.2, 7.4 e 8.4. Recortado para as pastas de cada versão.

# Segurança (Baseline)

Práticas e APIs de segurança disponíveis em PHP 7.2 e ainda válidas em 7.4 e
8.4. Estas são a base comum segura — evite construir hashing/aleatoriedade
próprios.

## 1. Hashing de senhas

Use sempre `password_hash()` e `password_verify()`. Nunca armazene senhas em
texto limpo nem com `md5`/`sha1` puros.

### Algoritmos disponíveis no baseline

- `PASSWORD_BCRYPT` (sempre disponível) — usa o algoritmo Blowfish (`$2y$`);
  produz hash de 60 caracteres.
- `PASSWORD_ARGON2I` (disponível a partir do **PHP 7.2**) — algoritmo Argon2i.

> `PASSWORD_DEFAULT` também é utilizável, mas pode mudar de
> algoritmo em versões futuras — armazene em coluna de ao menos 255 bytes se
> usá-lo. Para o baseline, prefira `PASSWORD_BCRYPT` (portável) ou
> `PASSWORD_ARGON2I` (quando presente).

```php
<?php
$hash = password_hash('senha-do-usuario', PASSWORD_BCRYPT, [
    'cost' => 12,
]);

// Armazene $hash no banco de dados.

if (password_verify('senha-do-usuario', $hash)) {
    echo "Senha correta";
} else {
    echo "Senha incorreta";
}
```

### Rehash quando necessário

Aumente o custo ou troque o algoritmo ao longo do tempo sem quebrar logins
existentes:

```php
<?php
if (password_verify($senha, $hash) && password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 14])) {
    $novoHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 14]);
    // atualize $novoHash no banco
}
```

Armazene os hashes em colunas `VARCHAR(255)` por segurança. O `salt` é gerado
automaticamente e deve **nunca** ser fornecido manualmente (a opção `salt` de
`password_hash` está obsoleta desde o PHP 7.0).

## 2. Geração de valores aleatórios criptograficamente seguros

Nunca use `rand()` ou `mt_rand()` para segurança (previsíveis). Use:

- `random_bytes(int $length): string` — bytes crus.
- `random_int(int $min, int $max): int` — inteiro uniforme.

```php
<?php
$token = bin2hex(random_bytes(32)); // 64 caracteres hex
$codigo = random_int(100000, 999999);
```

### `openssl_random_pseudo_bytes()` (fallback)

```php
<?php
$bytes = openssl_random_pseudo_bytes(16);
```

### Extensão `sodium` (disponível no PHP 7.2)

A extensão libsodium (`sodium_*`) oferece primitivas modernas (cifração
autenticada, assinaturas, derivação de chaves). Disponível como parte do core
no PHP 7.2.

```php
<?php
$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
$chave = sodium_crypto_secretbox_keygen();
$criptado = sodium_crypto_secretbox($mensagem, $nonce, $chave);
```

## 3. Filtragem e validação de entrada (`filter`)

Use `filter_var()` / `filter_input()` em vez de regex manuais para entradas
comuns. Constantes válidas no baseline (7.2+):

| Filtro | Uso |
|---|---|
| `FILTER_VALIDATE_INT` | inteiro válido |
| `FILTER_VALIDATE_FLOAT` | número de ponto flutuante |
| `FILTER_VALIDATE_BOOLEAN` (alias `FILTER_VALIDATE_BOOL` é 8.0+) | booleano |
| `FILTER_VALIDATE_EMAIL` | e-mail |
| `FILTER_VALIDATE_URL` | URL |
| `FILTER_VALIDATE_IP` | endereço IP |
| `FILTER_VALIDATE_DOMAIN` | domínio |
| `FILTER_SANITIZE_EMAIL` | limpa e-mail |
| `FILTER_SANITIZE_URL` | limpa URL |
| `FILTER_SANITIZE_NUMBER_INT` | mantém dígitos +/- |
| `FILTER_SANITIZE_NUMBER_FLOAT` | números (com flags) |
| `FILTER_SANITIZE_FULL_SPECIAL_CHARS` | equivalente a `htmlspecialchars(..., ENT_QUOTES)` |
| `FILTER_SANITIZE_STRING` (obsoleto no 8.1 — evite) | removia tags |

```php
<?php
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
if ($email === false) {
    // entrada inválida
}

$idade = filter_input(INPUT_POST, 'idade', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 0, 'max_range' => 150],
]);

$ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
```

`FILTER_NULL_ON_FAILURE` retorna `null` em vez de `false` em falhas de
validação booleana. Para sanitização de saída HTML, prefira
`htmlspecialchars()` (ver seção 5).

## 4. Comparação temporalmente segura de hashes

Use `hash_equals()` para comparar tokens/macs e evitar ataques de temporização.

```php
<?php
$hashCalculado = hash_hmac('sha256', $dados, $chaveSecreta);
if (hash_equals($hashEsperado, $hashCalculado)) {
    // autenticado
}
```

`hash()`, `hash_hmac()` e `hash_equals()` estão disponíveis no core.

## 5. Escape de saída (prevenção de XSS)

Sempre escape dados antes de inseri-los em HTML:

- `htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')` — escapa `&`, `"`,
  `'`, `<`, `>`.
- `htmlentities()` — converte todos os caracteres com equivalente HTML.
- `bin2hex()` — para saída hex.

```php
<?php
echo htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8');
```

Defina `default_charset` no php.ini (UTF-8) para que `htmlspecialchars` use o
charset correto por padrão.

## 6. Instruções preparadas (prevenção de SQL Injection)

### PDO

```php
<?php
$pdo = new PDO('mysql:host=localhost;dbname=app;charset=utf8mb4', $usr, $pw, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$stmt = $pdo->prepare('SELECT id, nome FROM usuarios WHERE email = ?');
$stmt->execute([$email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
```

### MySQLi

```php
<?php
$mysqli = new mysqli('localhost', $usr, $pw, 'app');
$stmt = $mysqli->prepare('INSERT INTO log (msg) VALUES (?)');
$stmt->bind_param('s', $msg);
$stmt->execute();
```

Nunca interpole variáveis diretamente na SQL. Use sempre parâmetros.

## 7. Segurança de sessão

```php
<?php
ini_set('session.cookie_secure', '1');     // apenas HTTPS
ini_set('session.cookie_httponly', '1');   // não acessível via JS
// session.cookie_samesite disponível a partir do PHP 7.3 — usar se suportado
ini_set('session.use_strict_mode', '1');    // recusa IDs de sessão não gerados pelo servidor
session_start();

// Regenera o ID após login para evitar fixation:
session_regenerate_id(true);
```

Diretrizes:

- Chame `session_regenerate_id(true)` após autenticação (e periodicamente).
- `session.cookie_secure` + `session.cookie_httponly` + `session.use_strict_mode`
  formam a base. `session.cookie_samesite` (ex.: `'Lax'`/`'Strict'`) está
  disponível a partir do PHP 7.3 e pode ser adicionado onde suportado.
- Armazene dados de sessão no servidor; não coloque dados sensíveis no cookie.

## 8. Resumo

- Senhas: `password_hash` (BCRYPT/ARGON2I) + `password_verify` + rehash.
- Aleatoriedade: `random_bytes`/`random_int` (ou `sodium`).
- Entrada: `filter_var`/`filter_input` para validar/sanitizar.
- Segredos/tokens: compare com `hash_equals`.
- Saída: `htmlspecialchars`.
- SQL: instruções preparadas (PDO ou MySQLi).
- Sessão: cookies seguros + httponly + strict mode + regenerate.
