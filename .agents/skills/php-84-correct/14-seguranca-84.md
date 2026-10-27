Complementar ao documento comum — específico do PHP 8.4.

# Segurança no PHP 8.4

Este documento complementa `05-seguranca-baseline.md` com o conjunto moderno de
APIs de segurança disponíveis até o PHP 8.4. Todo o código é válido em 8.4.

> Observação de 8.4: o custo padrão de `PASSWORD_BCRYPT` em `password_hash()`
> subiu de `10` para `12` (`migration84.other-changes.html`). O OpenSSL 3.2+
> (build NTS) passa a suportar `PASSWORD_ARGON2` via OpenSSL. `lcg_value()` foi
> deprecado — use `Random\Randomizer::getFloat()`.

## 1. Hash e verificação de senhas

Algoritmos suportados: `PASSWORD_DEFAULT`, `PASSWORD_BCRYPT`,
`PASSWORD_ARGON2I`, `PASSWORD_ARGON2ID`. (Argon2 exige suporte do sistema; no
8.4, OpenSSL 3.2+ NTS adiciona `PASSWORD_ARGON2` pelo OpenSSL.)

```php
$hash = password_hash('senha123', PASSWORD_ARGON2ID);

if (password_verify('senha123', $hash)) {
    // senha correta
    if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
        $hash = password_hash('senha123', PASSWORD_ARGON2ID);
    }
}
```

- Nunca armazene senhas em texto limpo. Use sempre `password_hash` + `password_verify`.
- O *cost* (custo) pode ser ajustado: `password_hash($s, PASSWORD_BCRYPT, ['cost' => 12])`.
- Para `PASSWORD_BCRYPT`, o padrão de cost passou a ser `12` no 8.4.

## 2. Geração de números e bytes aleatórios

### `random_bytes()` / `random_int()` (PHP 7.0)

Criptograficamente seguros.

```php
$token = bin2hex(random_bytes(32));   // token hex de 64 chars
$num = random_int(1, 100);            // int seguro no intervalo
```

### `Random\Randomizer` & `Random\Engine` (PHP 8.1)

API orientada a objeto para geração aleatória, com engine selecionável
(`Random\Engine\Secure`, `Random\Engine\Xoshiro256StarStar`,
`Random\Engine\PcgOneseq128XslRr64`).

```php
use Random\Randomizer;
use Random\Engine\Secure;

$r = new Randomizer(new Secure());

$bytes = $r->getBytes(16);                       // bytes seguros
$letra = $r->getBytesFromString('ABCDEFG', 1);   // sorteio de string
$float = $r->getFloat(0.0, 1.0, \Random\IntervalBoundary::ClosedOpen); // float seguro
$int = $r->getInt(1, 6);                         // dado
$embaralhado = $r->shuffleArray([1, 2, 3, 4]);   // embaralha
$str = $r->shuffleBytes('abcdef');               // embaralha string
```

- `Random\Engine\Secure` usa a fonte CSPRNG do SO (equivalente a
  `random_bytes`). Para uso geral, `Secure` é o recomendado.
- Substitui `lcg_value()` que foi deprecado no 8.4.

## 3. Extensão `sodium` (PHP 7.2+)

Criptografia moderna de alto nível:

```php
// Cifra autenticada (secretbox)
$chave = sodium_crypto_secretbox_keygen();
$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
$cripto = sodium_crypto_secretbox('mensagem', $nonce, $chave);
$original = sodium_crypto_secretbox_open($cripto, $nonce, $chave);

// Assinatura
$chavePub; $chavePriv;
$chavePriv = sodium_crypto_sign_keypair();
$chavePub = sodium_crypto_sign_publickey($chavePriv);
$assinatura = sodium_crypto_sign_detached('doc', $chavePriv);
$ok = sodium_crypto_sign_verify_detached($assinatura, 'doc', $chavePub);

// Hashing seguro (argon2-like) e password
$hash = sodium_crypto_pwhash_str('senha', SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
$ok = sodium_crypto_pwhash_str_verify($hash, 'senha');
```

- No 8.4, adicionados `sodium_crypto_aead_aegis128l_*` e
  `sodium_crypto_aead_aegis256_*` (AEGIS).

## 4. OpenSSL (PHP 8.4)

- Curvas Curve25519/Curve448 (x25519, ed25519, x448, ed448) suportadas em
  `openssl_pkey_new`, `openssl_pkey_get_details`, `openssl_sign`,
  `openssl_verify`.
- Implementação `PASSWORD_ARGON2` via OpenSSL 3.2 (NTS).

```php
$config = ['curve_name' => 'ed25519', 'private_key_type' => OPENSSL_KEYTYPE_EC];
$res = openssl_pkey_new($config);
```

## 5. Validação e sanitização de entrada — `filter_var` / `filter_input`

```php
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
$int = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

$ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
$sanitizado = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
```

- Use `FILTER_VALIDATE_*` para validar e `FILTER_SANITIZE_*` para limpar.
- Sempre valide entradas do usuário antes de usá-las.

## 6. Comparação e hashing de dados

```php
// Comparação segura contra ataques de temporização
if (hash_equals($hashEsperado, $hashRecebido)) { /* ... */ }

// HMAC
$mac = hash_hmac('sha256', $mensagem, $chaveSecreta);

// Hash genérico
$digest = hash('sha256', $dados);
```

## 7. Saída segura (XSS)

```php
echo htmlspecialchars($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
// Ou htmlentities para converter todos os caracteres aplicáveis
```

- `default_charset` é UTF-8 (padrão desde o 5.6; mantenha explícito).
- Em contextos HTML, sempre escape a saída. Para JSON em APIs, use
  `json_encode($d, JSON_THROW_ON_ERROR)` e defina o content-type correto.

## 8. PDO e MySQLi — prepared statements

### PDO

```php
$pdo = new PDO('mysql:host=localhost;dbname=app;charset=utf8mb4', $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? AND ativo = ?');
$stmt->execute([$id, 1]);
$usuario = $stmt->fetch();

// Named params
$stmt = $pdo->prepare('INSERT INTO log (msg) VALUES (:msg)');
$stmt->execute(['msg' => $texto]);
```

### MySQLi

```php
$mysqli = new mysqli($host, $user, $pass, $db);
$stmt = $mysqli->prepare('SELECT nome FROM usuarios WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($nome);
$stmt->fetch();
```

- Statements preparados (parametrizados) previnem SQL injection.
- `charset=utf8mb4` é essencial para suporte completo a Unicode (emoji, etc.).
- No 8.4, PDO/MySQLi ganharam subclasses específicas de driver e parser SQL
  próprio por driver (ver `migration84.new-features.html`).

## 9. Segurança de sessão

```php
ini_set('session.cookie_secure', '1');     // apenas HTTPS
ini_set('session.cookie_httponly', '1');    // inacessível a JS
ini_set('session.cookie_samesite', 'Lax');  // 'Strict' | 'Lax' | 'None'
ini_set('session.use_strict_mode', '1');    // protege contra fixação de ID
ini_set('session.cookie_path', '/');
// Para SameSite=None é obrigatório cookie_secure=1
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', '1');

session_start();
session_regenerate_id(true); // regenera ID a cada login (previne fixação)
```

- `session.cookie_samesite` (disponível desde 7.3) controla envio cross-site;
  `None` exige `Secure`.
- `session.use_strict_mode` rejeita IDs de sessão não gerados pelo servidor.
- `session_regenerate_id(true)` destrói a sessão antiga ao regenerar.
- `SID` constante e várias INIs de session foram deprecados no 8.4
  (ver `17-logs-84.md`).

## 10. Bibliotecas criptográficas

- Marque parâmetros que recebem chaves ou material secreto com
  `#[\SensitiveParameter]` (PHP 8.2+). O PHP substitui o valor por
  `SensitiveParameterValue` em `getTrace()`/`getTraceAsString()`, evitando vazamento
  em logs, error trackers e dumps de teste.
- Não faça `var_dump()`/`print_r()`/`serialize()` de um hasher ou contexto que contenha
  chave; se precisar depurar, implemente `__debugInfo()` redigido.
- Saídas XOF de comprimento arbitrário podem esgotar memória quando o tamanho vem de
  entrada do usuário: limite e documente o máximo aceito.
- Verificação de tags/MACs usa `hash_equals()`; nunca `===` ou `==`.

## 11. Resumo de práticas

| Área | Recomendação (8.4) |
| --- | --- |
| Senhas | `password_hash` (ARGON2ID/BCrypt), `password_verify` |
| Aleatório | `random_int`, `Random\Randomizer` (engine `Secure`) |
| Criptografia | `sodium_*`, `openssl_*` (curvas modernas) |
| Entrada | `filter_var`/`filter_input` |
| Comparação | `hash_equals` |
| Chaves | `#[\SensitiveParameter]` em parâmetros secretos |
| Saída | `htmlspecialchars`/`htmlentities` |
| DB | PDO/MySQLi prepared statements (`utf8mb4`) |
| Sessão | `cookie_secure`, `cookie_httponly`, `cookie_samesite`, `use_strict_mode`, `regenerate_id` |

Todos os exemplos acima são executáveis em PHP 8.4.
