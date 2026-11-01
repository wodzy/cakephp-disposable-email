# Contributing

Thanks for your interest in the CakeDisposableEmail plugin!

## Where does my change belong?

- **Matching logic, normalization, the bundled list**: open it on
  [wodzy/disposable-email](https://github.com/wodzy/disposable-email), the
  framework-agnostic library this plugin is built on.
- **CakePHP integration** (validation rules, commands, configuration, container,
  cache): this repository.

## Development setup

```bash
git clone git@github.com:wodzy/cakephp-disposable-email.git
cd cakephp-disposable-email
composer install
composer check   # PHP_CodeSniffer, PHPStan and PHPUnit
```

To work on both packages at once, clone `wodzy/disposable-email` next to this
repository and add a local path repository, **without committing it**:

```bash
composer config repositories.disposable-email path ../disposable-email
composer update wodzy/disposable-email
```

## Standards

- Code follows the [CakePHP coding standard](https://book.cakephp.org/5/en/contributing/cakephp-coding-conventions.html),
  checked by `composer cs-check` (`composer cs-fix` repairs most issues).
- `composer stan` runs PHPStan at the `max` level with the CakePHP extension.
  Fix the cause of an error rather than ignoring it.
- Behaviour changes come with tests. Tests that need the application use the
  small app in `tests/test_app`.
- User-facing changes are listed in the "Unreleased" section of `CHANGELOG.md`.

## Pull requests

One topic per pull request, with a short description of the problem and the
solution. For a substantial change, open an issue first to agree on the approach.
