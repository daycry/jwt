# Contributing

Thanks for your interest in `daycry/jwt`. This document describes how to set up the project and contribute changes.

## Reporting Issues

- Use [GitHub Issues](https://github.com/daycry/jwt/issues) for bug reports and feature requests.
- For security issues, follow [SECURITY.md](SECURITY.md) instead.
- When reporting a bug, include PHP version, CodeIgniter version, `lcobucci/jwt` version, and a minimal reproduction.

## Development Setup

```bash
git clone https://github.com/daycry/jwt.git
cd jwt
composer install
cp .env.example .env   # if present, otherwise create one
```

Generate a signing key for testing locally:

```bash
php vendor/bin/phpunit
```

## Coding Standards

This project follows the CodeIgniter 4 coding standard via `php-cs-fixer`.

```bash
composer cs-check   # report issues without changing files
composer cs-fix     # auto-fix where possible
```

## Static Analysis

```bash
composer analyze    # phpstan level 8 + strict rules
```

PRs that introduce new phpstan violations will not pass CI.

## Tests

```bash
composer test                     # run full PHPUnit suite
vendor/bin/phpunit --no-coverage  # faster local runs
```

When you add a feature or fix a bug, please add a test that fails before your change and passes after.

## Pull Requests

1. Fork the repository and create a feature branch from `master`.
2. Make focused commits with clear messages — one logical change per commit.
3. Run `composer test`, `composer analyze`, and `composer cs-check` locally before pushing.
4. Open a PR against `master` describing the motivation and behaviour change.
5. Reference any related issue (`Fixes #123`).

## Commit Messages

Imperative mood, present tense. Keep the first line under 72 characters. Example:

```
Validate signing key length before encoding
```

## Releases

Releases follow [Semantic Versioning](https://semver.org/). Breaking changes are reserved for major versions and require a migration note in `CHANGELOG.md`.
