# CakeDisposableEmail plugin for CakePHP

[![CI](https://github.com/wodzy/cakephp-disposable-email/actions/workflows/ci.yml/badge.svg)](https://github.com/wodzy/cakephp-disposable-email/actions/workflows/ci.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/wodzy/cakephp-disposable-email.svg)](https://packagist.org/packages/wodzy/cakephp-disposable-email)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Block disposable (throwaway) email addresses such as `@yopmail.com` or
`@mailinator.com` in your CakePHP 5 application.

Built on [`wodzy/disposable-email`](https://github.com/wodzy/disposable-email),
a dependency-free PHP library that does the actual matching (subdomains, case,
IDN). This plugin adds the CakePHP integration:

- a `notDisposable` validation rule, available on every `Validator`,
- an `allowedDomain` rule to accept only your own domains (e.g. `@company.com`),
- `bin/cake disposable_email update` to download a community list of 10,000+ domains,
- `bin/cake disposable_email check <email>` to test an address,
- extra domains, extra files and never-blocked domains through `Configure`,
- `Wodzy\DisposableEmail\BlocklistInterface` in the dependency injection container,
- the merged list stored with CakePHP's `Cache`.

## Requirements

- PHP 8.2+
- CakePHP 5.1+
- `ext-intl` recommended (internationalized domain names)

## Installation

```bash
composer require wodzy/cakephp-disposable-email
bin/cake plugin load CakeDisposableEmail
```

Then download the community list (optional but recommended):

```bash
bin/cake disposable_email update
```

Without this step the plugin uses the smaller curated list shipped with
`wodzy/disposable-email` (about 200 well-known providers).

## Usage

### Validation rule

```php
// src/Model/Table/UsersTable.php
public function validationDefault(Validator $validator): Validator
{
    $validator
        ->email('email')
        ->add('email', 'notDisposable', [
            'rule' => 'notDisposable',
            'provider' => 'disposableEmail',
            'message' => __('Disposable email addresses are not allowed.'),
        ]);

    return $validator;
}
```

The rule checks the domain; keep the `email` rule for the syntax. A value
without `@` passes `notDisposable`, so that only the `email` rule reports it.

CakePHP's `email` rule accepts some addresses that are not strictly valid, such
as `"jane@yopmail.com\n"` (trailing newline) or a local part over 64 characters.
`notDisposable` still checks the domain of such addresses, and refuses any value
that could hide another address or header data: whitespace, control characters,
`,` `;` `<` `>`, or more than one `@`.

### Only accept some domains

For a back-office or an internal tool, `allowedDomain` accepts **only** the
domains you list (and their subdomains: `paris.company.com` matches
`company.com`). Everything else, Gmail included, is rejected.

```php
$validator
    ->email('email')
    ->add('email', 'allowedDomain', [
        'rule' => ['allowedDomain', ['company.com', 'company.fr']],
        'provider' => 'disposableEmail',
        'message' => __('Please use your company email address.'),
    ]);
```

The list is set per rule, so each form or table can have its own. For a single
domain, `'rule' => ['allowedDomain', 'company.com']` works too.

`allowedDomain` fails closed: only a strictly valid address whose domain is in
the list passes. A value such as `attacker@evil.test,jane@company.com` is rejected.

| | `notDisposable` rule | `allowedDomain` rule |
|---|---|---|
| `jane@gmail.com` | accepted | rejected |
| `jane@yopmail.com` | rejected | rejected |
| `jane@company.com` | accepted | accepted |
| Purpose | block throwaway providers | accept only the listed domains |

To exempt a domain from `notDisposable` (for example a partner that a list
blocks by mistake), use the `neverBlock` option below.

### Outside of validation

Ask the container for `BlocklistInterface`, for example in a controller action
or a service constructor. It is the same instance the validation rules use.

```php
use Wodzy\DisposableEmail\BlocklistInterface;

public function signup(BlocklistInterface $blocklist)
{
    if ($blocklist->blocks($this->request->getData('email'))) {
        // ...
    }
}
```

`blocks()` throws `Wodzy\DisposableEmail\Exception\InvalidEmailException` for a
malformed address. Without the container, use
`CakeDisposableEmail\DisposableEmailRegistry::get()`.

### Commands

```bash
bin/cake disposable_email update            # refresh the downloaded list
bin/cake disposable_email update --url URL  # use another source once
bin/cake disposable_email check a@yopmail.com
```

`check` exits with `0` when the address is allowed, `1` when it is blocked and
`2` when it is not a valid email address.

Run `update` regularly, for example from a weekly cron job. The command is
safe to automate:

- the current file is kept if the download fails, is larger than 20 MB, or
  returns fewer than `minimumDomains` entries,
- well-known providers (`gmail.com`, `outlook.com`, `orange.fr`, ...) are always
  left out of the downloaded list,
- the file is replaced atomically,
- the cached list is invalidated automatically.

## Configuration

Every option is optional. Add the ones you need to `config/app_local.php`:

```php
'DisposableEmail' => [
    'useBundledList' => true,             // list shipped with wodzy/disposable-email
    'domains' => ['competitor.test'],     // extra domains to block
    'neverBlock' => ['partner.yopmail.com'], // never blocked, even if a parent domain is
    'files' => [],                        // extra JSON files: {"domains": [...]}
    'downloadedListPath' => CONFIG . 'disposable_email_domains.json', // null to disable
    'updateUrl' => \CakeDisposableEmail\DisposableEmailConfig::DEFAULT_UPDATE_URL,
    'minimumDomains' => 1000,             // update refused below this count
    'cacheConfig' => 'default',           // null to disable caching
],
```

See [`config/disposable_email.example.php`](config/disposable_email.example.php)
for the full reference. An unknown option throws an exception, so a typo cannot
silently disable a setting.

Blocking a domain also blocks its subdomains. Never add a public suffix such as
`co.uk` or `github.io`: it would block every domain registered under it.

### Performance and caching

Merging and normalizing about 10,000 domains takes a few milliseconds, so the
merged list is stored with `Cache::remember()` in the `cacheConfig` cache
configuration. The cache key changes whenever a source file or the `domains`
option changes, so `update` never needs a manual cache clear.

With the `File` engine of the app skeleton, a check costs about 3 ms instead
of 4 ms on the first use in a request. A memory engine (APCu, Redis, Memcached)
avoids most of the remaining cost:

```php
// config/app.php
'Cache' => [
    'disposable_email' => ['className' => 'Apcu', 'duration' => '+1 week'],
],
'DisposableEmail' => ['cacheConfig' => 'disposable_email'],
```

### Long-running processes

The blocklist is built once per PHP process. With PHP-FPM that is one request,
so a new list is picked up immediately. Long-running workers (queue consumers,
RoadRunner, Swoole...) keep the list they started with: restart them after
`update`, or call `DisposableEmailRegistry::reset()`.

## Where the list comes from

`update` downloads the [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains)
community list by default, from its `main` branch. Its entries are not reviewed
by this plugin before use. The safety nets above (protected providers, minimum
size, size limit, atomic write) limit the damage of a bad upstream change, but
you should:

- pin a reviewed copy if you need full control: point `updateUrl` to your own
  copy, or commit the downloaded file and drop the cron job,
- add any wrongly blocked domain to `neverBlock`.

## Testing your application

Use a fixed list in your tests:

```php
use CakeDisposableEmail\DisposableEmailRegistry;
use Wodzy\DisposableEmail\Blocklist;

DisposableEmailRegistry::set(Blocklist::fromDomains(['blocked.test']));
// ...
DisposableEmailRegistry::reset(); // in tearDown()
```

## Limitations

This plugin does not check whether a mailbox exists or can receive mail (no MX
or SMTP checks). It only answers one question: does this address belong to a
blocked domain?

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Security issues: see [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).
