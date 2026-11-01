# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.1] - 2026-10-31

### Fixed
- The "new issue" page linked to GitHub Discussions, which are not enabled. It now
  links to the usage section of the README. No change to the installed package.

## [1.0.0] - 2026-10-31

First release. Requires PHP 8.2+, CakePHP 5.1+ and `wodzy/disposable-email` ^1.0.

### Added
- `notDisposable` validation rule, registered as the `disposableEmail` provider.
- `allowedDomain` validation rule: accepts only strictly valid addresses on the listed
  domains and their subdomains (fails closed).
- `notDisposable` also checks addresses that CakePHP's `email` rule accepts but that are
  not strictly valid, and refuses values that could hide another address
  (whitespace, control characters, `,;<>`, several `@`).
- `DisposableEmail` configuration key, read by `DisposableEmailConfig`: bundled list,
  downloaded list, extra domains, extra files, `neverBlock` domains, cache configuration.
  Unknown options throw an exception.
- `neverBlock` domains always win, including subdomains of a blocked domain.
- The merged list is stored with `Cache::remember()` and invalidated when a source changes.
- `bin/cake disposable_email update` command with safety checks (HTTP status, size limit,
  minimum size, protected providers, atomic write).
- `bin/cake disposable_email check <email>` command (exit codes: 0 allowed, 1 blocked, 2 invalid).
- `Wodzy\DisposableEmail\BlocklistInterface` and `DisposableEmailConfig` registered in the
  DI container; both commands receive their dependencies through their constructor.

[Unreleased]: https://github.com/wodzy/cakephp-disposable-email/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/wodzy/cakephp-disposable-email/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/wodzy/cakephp-disposable-email/releases/tag/v1.0.0
