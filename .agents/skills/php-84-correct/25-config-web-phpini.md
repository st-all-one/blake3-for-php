Complementar ao documento comum — específico do PHP 8.4.

# Configuração Web e php.ini para PHP 8.4 (SAPI Web)

Guia dedicado de configuração do `php.ini` para o SAPI web (FPM/CGI/mod_php) no
PHP 8.4, organizado em **Segurança**, **Performance** e **Estabilidade**. Todos
os diretivos abaixo são válidos no 8.4. Não são recomendados recursos removidos
em versões anteriores (ex.: bibliotecas/extensões descontinuadas). Ajuste os
valores ao seu ambiente; os listados são recomendações gerais de produção.

> Sempre teste em staging. Recarregue o serviço (FPM/`reload`) após alterações.

---

## 1. SEGURANÇA

| Diretiva | Recomendado | Justificativa |
| --- | --- | --- |
| `expose_php` | `Off` | Oculta a versão do PHP no cabeçalho `X-Powered-By`. |
| `display_errors` | `Off` (prod) | Nunca exiba erros ao usuário; vazamento de caminhos/estrutura. |
| `log_errors` | `On` | Garanta registro para diagnóstico. |
| `error_log` | caminho de log | Destino dos erros quando `log_errors=On`. |
| `allow_url_fopen` | `Off` (se não usar) | Bloqueia `fopen()` em URLs remotas. |
| `allow_url_include` | `Off` | Impede `include`/`require` de URLs remotas (inclui RCE). |
| `open_basedir` | diretório app | Restringe acesso a arquivos do filesystem ao diretório. |
| `disable_functions` | lista restritiva | Desabilita funções perigosas (`exec`,`shell_exec`,`system`,`passthru`,`proc_open`,`popen` etc.). |
| `session.cookie_secure` | `On` (HTTPS) | Cookie de sessão só via HTTPS. |
| `session.cookie_httponly` | `On` | Impede acesso ao cookie via JavaScript (XSS). |
| `session.cookie_samesite` | `Lax` (ou `Strict`) | Mitiga CSRF; `None` exige `Secure`. |
| `session.use_strict_mode` | `On` | Rejeita IDs de sessão não gerados pelo servidor. |
| `cgi.fix_pathinfo` | `0` | Evita path-info abusivo em setups FPM+nginx. |

Exemplo de trecho:

```ini
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/php-error.log
allow_url_fopen = Off
allow_url_include = Off
open_basedir = /var/www/app:/tmp
session.cookie_secure = On
session.cookie_httponly = On
session.cookie_samesite = Lax
session.use_strict_mode = On
cgi.fix_pathinfo = 0
disable_functions = "exec,shell_exec,system,passthru,proc_open,popen,pcntl_exec"
```

### Hashing de senhas (8.4)

Use as APIs nativas; prefira `PASSWORD_ARGON2ID`:

```php
$hash = password_hash($senha, PASSWORD_ARGON2ID);
// ou PASSWORD_DEFAULT / PASSWORD_BCRYPT
if (password_verify($senha, $hash)) { /* ok */ }
```

### Sodium (criptografia)

```php
$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
$chave = sodium_crypto_secretbox_keygen();
$cifrado = sodium_crypto_secretbox($mensagem, $nonce, $chave);
$aberto = sodium_crypto_secretbox_open($cifrado, $nonce, $chave);
```

---

## 2. PERFORMANCE

| Diretiva | Recomendado | Justificativa |
| --- | --- | --- |
| `opcache.enable` | `1` | Ativa o OPcache (bytecode cache). |
| `opcache.memory_consumption` | `128` (MB) | Memória para bytecode; ajuste conforme app. |
| `opcache.max_accelerated_files` | `10000`+ | Arquivos cacheados; cubra o total da app. |
| `opcache.revalidate_freq` | `60` | Frequência de checagem de timestamp (prod). |
| `opcache.validate_timestamps` | `0` (prod) | Não recheca arquivo em disco; deploy limpa cache. |
| `opcache.preload` | script pré-carga | Pré-carrega classes/funções no startup do worker. |
| `opcache.preload_user` | usuário do FPM | Dono do script de preload. |
| `opcache.jit` | `tracing` (CPU-bound) | JIT; ver nota abaixo. |
| `opcache.jit_buffer_size` | `64M` | Tamanho do buffer JIT (padrão 8.4). |
| `realpath_cache_size` | `4096K` | Cache de caminhos de arquivo. |
| `realpath_cache_ttl` | `600` | TTL do cache de caminhos. |
| `zend.assertions` | `-1` (prod) | Remove asserções em produção (zero custo). |

Exemplo:

```ini
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 60
opcache.validate_timestamps = 0
opcache.preload = /var/www/app/preload.php
opcache.preload_user = www-data
opcache.jit = tracing
opcache.jit_buffer_size = 64M
realpath_cache_size = 4096K
realpath_cache_ttl = 600
zend.assertions = -1
```

### JIT (opcache.jit) no 8.4

O JIT compila bytecode para código de máquina. Ajuda cargas **CPU-bound**
(processamento numérico, image/FFT, regex pesadas), mas **pouco** em web I/O
típica (DB/bloqueio de rede). No 8.4, `opcache.jit` pode ser `"tracing"` ou
`"function"`. Se a aplicação for I/O-bound, manter `disable` também é aceitável.
Sempre meça com benchmark real.

---

## 3. ESTABILIDADE

| Diretiva | Recomendado | Justificativa |
| --- | --- | --- |
| `memory_limit` | `256M` (ajuste) | Teto de memória por requisição. |
| `max_execution_time` | `30` | Tempo máx. de CPU por script. |
| `max_input_time` | `60` | Tempo de leitura de entrada (POST/upload). |
| `max_input_vars` | `1000` | Limita variáveis de entrada (mitiga abuso). |
| `post_max_size` | `8M` (ajuste) | Tamanho máx. do POST. |
| `upload_max_filesize` | `2M` (ajuste) | Tamanho máx. por arquivo enviado. |
| `max_file_uploads` | `20` | Número máx. de uploads simultâneos. |
| `default_socket_timeout` | `60` | Timeout de sockets/streams de rede. |

Exemplo:

```ini
memory_limit = 256M
max_execution_time = 30
max_input_time = 60
max_input_vars = 1000
post_max_size = 8M
upload_max_filesize = 2M
max_file_uploads = 20
default_socket_timeout = 60
```

> Mantenha `post_max_size` >= `upload_max_filesize` e ajuste conforme formulários
> reais. Monitore logs de `Allowed memory size` e aumente `memory_limit` com
> base em perfis reais, não por chute.

---

## 4. Verificação

Após alterar, valide a sintaxe e a configuração ativa:

```bash
php -l /etc/php/8.4/fpm/php.ini
php-fpm8.4 -t
php -i | grep -E 'opcache.jit|memory_limit'
```
