# php-src-cs

Verified PHPT fixers for `php-src`.

The tool has two jobs:

- apply small PHPT rewrites and keep only test-verified changes;
- generate and refresh fixture coverage for the fixers.

## Fixers

- `exception-output` — exception output in `catch` blocks.
- `final-newline` — final newline whitespace.

## Requirements

- PHP `>= 8.5`
- `composer install`
- a `php-src` checkout
- a built PHP CLI at `<php-src>/sapi/cli/php`, or `INTERNALS_CS_TEST_PHP_EXECUTABLE`

Optional CGI tests use `INTERNALS_CS_TEST_PHP_CGI_EXECUTABLE` or `<php-src>/sapi/cgi/php-cgi`.

## Fix php-src

```bash
php bin/php-src-cs.php fix --php-src-dir /path/to/php-src [options] [path ...]
```

Useful options:

- `--check` reports changes without writing.
- `--print` prints the rewritten content for exactly one PHPT file.

Paths are relative to `php-src`, or absolute paths inside it. No path means the whole checkout.

Fix runs write reports to `var/fix-runs/`.

## Prepare a PHP PR

```bash
bin/contribute --php-src-dir /path/to/php-src [options] [target ...]
```

Use this when a PR should have one commit per target:

```text
ext/intl: applied fixers to improve test robustness
```

Important behaviour:

- confirms the current `php-src` branch;
- requires a clean tracked `php-src` working tree;
- runs targets separately;
- creates one commit per changed target;
- with no targets, uses `ext/*`, `Zend`, `tests`, and `sapi/*`;
- with `--lfg --dry`, prints the PR body that would be submitted;
- with `--lfg`, pushes and updates the PR body;
- with `--nah`, resets contiguous generated commits at `HEAD`.

PR body updates replace only the table between:

```markdown
<!-- pr:targets:start -->
<!-- pr:targets:end -->
```

## Generate fixtures

```bash
php bin/php-src-cs.php generate --php-src-dir /path/to/php-src [options] [path ...]
```

`generate` scans `php-src` once and finds fixture candidates for every fixer. Do not pass fixer names.

Useful options:

- `--write` writes fixtures and reports.
- `--refresh-only` refreshes existing fixture pairs without importing new `old.phpt` files.
- `--allow-dirty` allows discovery from a dirty `php-src` checkout.
- `--force-php-binary-rebuild` rebuilds the managed PHP test runtime.

Without `--write`, generation is a dry run.

When writing fixtures, the command builds a managed PHP test runtime under `var/php-test-runtime/` unless `INTERNALS_CS_TEST_PHP_EXECUTABLE` is set. The managed build is intentionally small and does not cover every extension in `php-src`.

## Work on this tool

```bash
composer install
composer test
composer quality
```

When adding a fixer, put it under `src/Fixers/`, register it in `FixerRegistry`, add fixture discovery for `generate`, and cover it with source/target fixtures.
