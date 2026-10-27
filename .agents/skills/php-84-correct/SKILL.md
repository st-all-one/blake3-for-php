---
name: php-84-correct
description: Authoritative, version-isolated guidance for writing correct, secure, performant, and maintainable PHP 8.4 code — modern typing (enums, readonly, property hooks, asymmetric visibility, union/intersection/DNF), functional/OOP idioms, security, error handling, logging, JIT, and php.ini hardening. Trigger when writing, reviewing, or refactoring PHP 8.4 code, or when a task must target PHP 8.4 specifically.
category: languages
version: "8.4"
tags: [php, php84, enums, readonly, property-hooks, asymmetric-visibility, dnf, jit, security]
license: MIT
---

# PHP 8.4 — Correct, Secure, Performant, Maintainable

## 1. Scope & Hard Isolation Rule
Target **PHP 8.4** only. Use the full language surface accumulated through 8.4 (base 7.2 + 7.3/7.4 + 8.0–8.4). **Never use features removed before 8.4** — they are gone and must not appear as recommendations. Do not frame the doc as "what was removed"; simply use the modern 8.4 idiom.

## 2. Allowed Language Surface (≤ 8.4)
Base: scalar/return types, `declare(strict_types=1)`, nullable `?T`, `void`, `iterable`, `object`, `callable`, anonymous classes (7.0), closures, `??`/`?:`/`<=>` (7.0), `Throwable`, generators, CSPRNG `random_bytes`/`random_int` (7.0), `filter_*`, `password_*`, `sodium_*`.
Added 7.4: typed properties, arrow `fn`, `??=`, preloading, `WeakReference`, `PASSWORD_ARGON2ID`, covariance/contravariance, `__serialize`/`__unserialize`.
Added 8.0: `match`, named args, nullsafe `?->`, constructor promotion, attributes `#[…]`, `union A|B`, `mixed`, `static` return, `throw` expr, trailing commas in declarations, `WeakMap`, `E_ALL` default.
Added 8.1: `enum`, `readonly` prop, `intersection A&B`, `never`, first-class callable `strlen(...)`, `Random\Randomizer`, fibers, `final const`, const in interface/trait, `new` in initializers, `true`/`false`/`null` as stand-alone types.
Added 8.2: `readonly class`, DNF `(A&B)|C`, `#[\SensitiveParameter]`.
Added 8.3: `#[\Override]`.
Added 8.4: **property hooks**, **asymmetric visibility** `public private(set)`, **lazy objects** (`ReflectionClass::newLazyGhost/newLazyProxy`), `#[\Deprecated]`, `array_all`/`array_any`/`array_find`/`array_find_key`, `new` dereferencing without parens, JIT default changed to disabled with a 64M buffer (see §5).

## 3. Removed / Forbidden in 8.4 (use the modern replacement)
| Removed (do NOT use) | Modern 8.4 replacement |
|---|---|
| `create_function()` (removed 8.0) | arrow `fn` / closures / first-class callable |
| `each()` (removed 8.0) | `foreach` / `array_key_first` / generator |
| `mcrypt_*` (removed 8.0) | `sodium_*` / `openssl_*` |
| `(real)` cast (removed 8.0) | `(float)` |
| `(unset)` cast (removed 8.0) | `unset($v)` statement |
| `define(..., ..., true)` case-insensitive (removed 8.0) | plain `define` / `const` |
| `FILTER_SANITIZE_STRING` (removed 8.1) | `FILTER_SANITIZE_FULL_SPECIAL_CHARS` / `htmlspecialchars` |
| `lcg_value()` (deprecated 8.4) | `Random\Randomizer` / `random_int` |
| `E_USER_ERROR` via `trigger_error()` (deprecated 8.4) | `throw new RuntimeException` / `exit(1)` |
| `E_STRICT` (deprecated/no-op 8.4) | drop it; `E_ALL` already covers needed |
| `session.use_only_cookies`/`use_trans_sid`/`SID` etc. (deprecated 8.4) | rely on default cookie sessions; avoid trans-sid |

## 4. Security
- **Passwords:** `password_hash($pw, PASSWORD_ARGON2ID)` (or `PASSWORD_ARGON2I`/`PASSWORD_BCRYPT`/`PASSWORD_DEFAULT`); verify with `password_verify`; rehash when `password_needs_rehash`. In 8.4 `PASSWORD_BCRYPT` default cost is **12**. Never md5/sha1/plain.
- **Random:** `random_int`, `random_bytes`, or `new Random\Randomizer(new Random\Engine\Secure())` → `getBytes`/`getInt`/`getFloat`/`shuffleArray`. Never `rand()`/`mt_rand()`/`lcg_value()` for secrets.
- **Crypto:** `sodium_crypto_secretbox*` (authenticated), `sodium_crypto_sign*`, `sodium_crypto_pwhash_str*`; OpenSSL 3.2+ supports `ed25519`/`x25519`/`PASSWORD_ARGON2`.
- **Input:** `filter_var`/`filter_input` (`FILTER_VALIDATE_*` / `FILTER_SANITIZE_*`). Never trust `$_GET`/`$_POST`/`$_REQUEST`/`$_COOKIE`/`$_FILES`/`$_SERVER`/`$_ENV`. Prefer `$_GET`/`$_POST` over `$_REQUEST`.
- **Output (XSS):** `htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8')`; for JSON APIs `json_encode($d, JSON_THROW_ON_ERROR)` + correct `Content-Type`.
- **SQLi:** PDO/MySQLi **prepared statements** with `charset=utf8mb4`; `PDO::ATTR_EMULATE_PREPARES=false`, `ERRMODE_EXCEPTION`. Never interpolate variables into SQL.
- **Timing-safe compare:** `hash_equals` for tokens/HMAC. HMAC via `hash_hmac('sha256', …)`.
- **Sessions:** `session.cookie_secure=1`, `cookie_httponly=1`, `cookie_samesite=Lax|Strict`, `use_strict_mode=1`; `session_regenerate_id(true)` after auth. Avoid `allow_url_include`; set `allow_url_fopen` off if unused.
- **Secrets:** never log raw secrets/tokens; redact.

```php
$hash = password_hash($pw, PASSWORD_ARGON2ID);
if (password_verify($pw, $hash) && password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
    $hash = password_hash($pw, PASSWORD_ARGON2ID);
}
$token = bin2hex(random_bytes(32));
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
echo htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$pdo = new PDO('mysql:host=localhost;dbname=app;charset=utf8mb4', $u, $p, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$stmt = $pdo->prepare('SELECT id FROM u WHERE email = ?');
$stmt->execute([$email]);
```

## 5. Performance
- **OPcache (always on):** `opcache.enable=1`, `validate_timestamps=0` in prod (deploy clears cache), `max_accelerated_files` ≥ total files, `interned_strings_buffer`, `memory_consumption` sized to app.
- **JIT (8.4):** default `opcache.jit="disable"` with `opcache.jit_buffer_size=64M` reserved. For **CPU-bound** workloads, *measure* `opcache.jit=tracing` (or `function`); long flat integer loops can regress, and typical I/O-bound web apps see little gain. (`opcache_jit_blacklist()` exists if a function regresses.)
- **Preloading (7.4):** `opcache.preload=/path/preload.php` + `opcache.preload_user` loads classes into worker memory at startup; requires SAPI restart to update.
- **Lazy objects (8.4):** `ReflectionClass::newLazyGhost($init)` defers expensive construction until first access — ideal for DI/ORM heavy services.
- **WeakMap (8.0):** cache keyed by object without leaking (`$wm[$obj] = meta`; entry vanishes when `$obj` collected).
- **Generators:** stream large datasets with `yield`/`yield from` to cap memory.
- **fibers (8.1):** cooperative concurrency primitive for async frameworks (single-threaded; not parallelism).
- **Syntax wins:** arrow fns over closures (no capture cost), `match` over `switch` (strict, expression), first-class callable `strlen(...)` over strings.
- **realpath cache:** `realpath_cache_size=4096K`, `realpath_cache_ttl=600`.
- **Assertions off in prod:** `zend.assertions=-1`.
- Measure with `hrtime(true)`; never optimize prematurely.

## 6. Maintainability
- Follow **PSR-12**; one class per file (PSR-4 autoload via Composer).
- Program to **interfaces**; inject dependencies via constructor (DI).
- Replace constant sets with **enums**; replace boilerplate getters/setters with **property hooks**; replace "read public / write private" with **asymmetric visibility**.
- Prefer **readonly** (props/classes) for immutability; value objects return new instances.
- Use **attributes** `#[…]` for metadata (routes, validation) instead of docblocks; read via Reflection.
- `declare(strict_types=1)` in libraries; explicit types everywhere.
- Small functions, no global state, no `goto`.

## 7. Typing
- `declare(strict_types=1)` at top of file; affects argument type checks (returns always strict).
- Scalars, `?T`, `void`, `iterable`, `object`, `callable`; return types required for clarity.
- 8.4 types: `union A|B`, `mixed` (use explicitly, not `?mixed`), `static` return (fluent), `never` (always throws/exits), `true`/`false`/`null` stand-alone, `intersection A&B` (classes/interfaces only), **DNF** `(Countable&ArrayAccess)|string`, `readonly` props, `new` in initializers, covariance/contravariance.
- Property hooks refine typed props; asymmetric visibility `public private(set) string $id` controls who may write.

```php
function route((Countable&ArrayAccess)|string $in): int|false { return 0; }
readonly class Point { public function __construct(public int $x, public int $y) {} }
function fail(string $m): never { throw new RuntimeException($m); }
class User {
    public private(set) string $name;
    public string $full { get => $this->first . ' ' . $this->last; }
    public function __construct(public string $first, public string $last) { $this->name = $first; }
}
```

## 8. Functional Idioms
- `array_map`/`array_filter`/`array_reduce`/`array_walk` with arrow fns.
- **8.4 array functions:** `array_all($a, fn)` (bool), `array_any($a, fn)` (bool), `array_find($a, fn)` (value|null), `array_find_key($a, fn)` (key|null).
- First-class callable: `$len = strlen(...); $up = array_map(strtoupper(...), $arr);`
- `match (true) { … }` for predicate branching; `throw` as expression in `??`/ternary/`match`.
- Generators for lazy pipelines; fibers for async.

```php
$aprov = array_all($rows, fn($r) => $r['score'] >= 5);
$primeiro = array_find($rows, fn($r) => $r['score'] < 5);
$dobro = array_map(fn($n) => $n * 2, $nums);
$msg = match ($status) { 200 => 'OK', 404 => 'NF', default => '?' };
```

## 9. OOP Idioms
- **Enums:** `enum Status: string { case A = 'a'; public function label(): string { return match($this){…}; } }`; `from()`/`tryFrom()`; implement interfaces, use traits.
- **Attributes:** `#[Route('/x')]`, `#[\Override]`, `#[\Deprecated('msg', since:'2.0')]`; declare with `#[\Attribute(...)]`; read via `ReflectionClass::getAttributes`.
- **Constructor promotion:** `public function __construct(public string $n, private readonly int $id) {}`.
- **readonly class** for immutable DTOs.
- **WeakMap** for object-keyed caches.
- **Lazy objects (8.4):** `new ReflectionClass(Svc::class)->newLazyGhost(static fn(Svc $g) => $g->__construct(...));` — initializer runs on first access.
- **Property hooks (8.4):** `get` (virtual, no backing), `set` (transform/validate).
- **Asymmetric visibility (8.4):** `public protected(set) string $x;` — wider read, narrower write.
- Anonymous classes for one-off implementations; `__serialize`/`__unserialize` for control.
- Catch specific exceptions, use `Throwable` base.

```php
enum Role: string { case Admin = 'admin'; case User = 'user'; }
#[Route('/u', method:'GET')] class UC { #[\Override] public function list(): void {} }
readonly class Config { public function __construct(public string $h, public int $p) {} }
$wm = new WeakMap();
$ref = new ReflectionClass(Heavy::class);
$heavy = $ref->newLazyGhost(static fn(Heavy $g) => $g->init());
```

## 10. Error & Exception Handling
- Hierarchy: `Throwable` → `Error` (`TypeError`,`ValueError`,`ArithmeticError`,`DivisionByZeroError`,`ArgumentCountError`,`ParseError`,`UnhandledMatchError`,…) and `Exception` (`RuntimeException`,`LogicException`,`InvalidArgumentException`,…).
- `try { … } catch (ValueError $e) { … } catch (Throwable $e) { … } finally { … }` — specific before broad.
- Convert legacy errors: `set_error_handler(static fn(int $no, string $str, string $file='', int $line=0): bool => …)` (no `$errcontext` — removed 8.0). Return `false` when `@`-suppressed.
- `set_exception_handler`, `register_shutdown_function`+`error_get_last` for fatals.
- `throw` is an expression: `$x = $_GET['x'] ?? throw new InvalidArgumentException();`
- `E_ALL` = **30719** in 8.4; default `error_reporting=E_ALL`. `E_USER_ERROR`/`E_STRICT` deprecated — use exceptions.
- `#[\Deprecated]` emits `E_USER_DEPRECATED`; capture in error handler.
- Never swallow silently; log with `$e->getTraceAsString()`; never use exceptions for normal flow.

## 11. Logging
- `error_log($msg)` (type 3 appends to file), `syslog(LOG_INFO, …)`; `error_log_mode=0640` (8.2) for safe perms.
- PSR-3 logger in apps; critical alerts to `error_log`. `error_reporting=E_ALL`, `log_errors=On`, `display_errors=Off` in prod.
- Levels: `LOG_EMERG…LOG_DEBUG`; user levels `E_USER_WARNING`/`E_USER_NOTICE` valid; `E_USER_DEPRECATED` for `#[\Deprecated]`.
- Redact secrets/PII; include context; rotate logs; do not log raw passwords/tokens.

## 12. php.ini / Web Config Hardening (8.4 SAPI)
Security:
```
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/php-error.log
error_log_mode = 0640
allow_url_fopen = Off          ; if unused
allow_url_include = Off
open_basedir = /var/www/app:/tmp
disable_functions = "exec,shell_exec,system,passthru,proc_open,popen,pcntl_exec"
session.cookie_secure = On
session.cookie_httponly = On
session.cookie_samesite = Lax
session.use_strict_mode = On
cgi.fix_pathinfo = 0
```
Performance:
```
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 60
opcache.validate_timestamps = 0
opcache.preload = /var/www/app/preload.php
opcache.preload_user = www-data
opcache.jit = tracing          ; CPU-bound; "disable" ok for I/O-bound
opcache.jit_buffer_size = 64M
realpath_cache_size = 4096K
realpath_cache_ttl = 600
zend.assertions = -1
```
Stability:
```
memory_limit = 256M
max_execution_time = 30
max_input_time = 60
max_input_vars = 1000
post_max_size = 8M            ; >= upload_max_filesize
upload_max_filesize = 2M
max_file_uploads = 20
default_socket_timeout = 60
```
Validate: `php -l /etc/php/8.4/fpm/php.ini` and `php-fpm8.4 -t`.

## 13. Correct Patterns (copy-ready 8.4)
```php
<?php
declare(strict_types=1);
namespace App;

enum Status: string { case Ativo = 'ativo'; case Inativo = 'inativo'; }

readonly class User {
    public private(set) string $name;
    public string $full { get => $this->first . ' ' . $this->last; }
    public function __construct(public string $first, public string $last,
                                public readonly int $id) { $this->name = $first; }
}

class Repo {
    public function __construct(private \PDO $pdo) {}
    public function byId(int $id): ?User {
        $s = $this->pdo->prepare('SELECT id,first,last FROM users WHERE id = ?');
        $s->execute([$id]);
        $r = $s->fetch(\PDO::FETCH_ASSOC);
        return $r ? new User($r['first'], $r['last'], (int)$r['id']) : null;
    }
}

set_error_handler(static function (int $no, string $str, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $no)) return false;
    error_log("[$no] $str in $file:$line");
    return true;
});

try {
    $hash = password_hash('s3cret', PASSWORD_ARGON2ID);
    $u = (new Repo($pdo))->byId(1) ?? throw new \InvalidArgumentException('no user');
    echo htmlspecialchars($u->full, ENT_QUOTES | ENT_HTML5, 'UTF-8');
} catch (\Throwable $e) {
    error_log($e::class . ': ' . $e->getMessage());
    http_response_code(500);
}
```

## 14. Anti-Patterns (never)
- Use removed features: `create_function`, `each()`, `mcrypt_*`, `(real)`/`(unset)` casts, case-insensitive `define(...,true)`, `FILTER_SANITIZE_STRING`, `lcg_value()`, `trigger_error(...,E_USER_ERROR)`, `E_STRICT`.
- Unescaped output, md5/sha1 passwords, trusting `unserialize` on untrusted data, swallowing exceptions, global state, `eval` on input, `allow_url_include=On`.
- Disabling OPcache/JIT in prod without measurement; `display_errors=On` in prod.
- `??=`/arrow/`match`/enums etc. are all VALID here — do use them; the anti-pattern is the opposite (sticking to obsolete idioms).

## 15. Validation Checklist (pre-commit / review)
- [ ] No removed feature from §3 anywhere (grep for `create_function`, `each(`, `mcrypt`, `(real)`, `(unset)`, `FILTER_SANITIZE_STRING`, `lcg_value`).
- [ ] All code passes `php -l` on PHP 8.4.
- [ ] `declare(strict_types=1)` + explicit types; no dynamic typing where a type fits.
- [ ] Passwords via `password_hash`/`password_verify`; tokens via `random_int`/`Randomizer`.
- [ ] Input validated with `filter_*`; output escaped with `htmlspecialchars`; SQL parameterized.
- [ ] Sessions secure (secure/httponly/samesite/strict/regenerate).
- [ ] `display_errors=Off`, `log_errors=On`, `expose_php=Off`, `disable_functions` set.
- [ ] OPcache + JIT/preload tuned for the workload; `zend.assertions=-1` in prod.
- [ ] Exceptions specific→`Throwable`; no silent catch; no `E_USER_ERROR`.
- [ ] Enums/readonly/property hooks/asymmetric visibility used where they reduce boilerplate.
