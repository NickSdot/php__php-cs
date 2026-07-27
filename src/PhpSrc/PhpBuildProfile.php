<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrc;

use function array_filter;
use function array_unique;
use function array_values;
use function getenv;
use function implode;
use function is_dir;
use function is_file;
use function is_string;
use function mb_strtoupper;
use function sha1;
use function str_replace;

final readonly class PhpBuildProfile
{
    /** @return list<string> */
    public function configureArgs(): array
    {
        return [
            '--enable-debug',
            '--enable-bcmath',
            '--enable-calendar',
            $this->optionWithOptionalPrefix('--with-bz2', 'bzip2', ['include/bzlib.h']),
            '--with-curl',
            '--enable-dba',
            '--enable-dl-test',
            '--enable-exif',
            '--with-ffi',
            '--enable-ftp',
            '--enable-gd',
            '--with-external-gd',
            $this->optionWithOptionalPrefix('--with-gettext', 'gettext', ['include/libintl.h']),
            $this->optionWithOptionalPrefix('--with-gmp', 'gmp', ['include/gmp.h']),
            '--enable-intl',
            $this->optionWithOptionalPrefix('--with-ldap', 'openldap', ['include/ldap.h']),
            '--enable-mbstring',
            '--with-mysqli=mysqlnd',
            '--enable-mysqlnd',
            $this->optionWithOptionalPrefix('--with-unixODBC', 'unixodbc', ['include/sql.h']),
            '--enable-pcntl',
            $this->pdoDblibOption(),
            '--with-pdo-mysql=mysqlnd',
            $this->pdoOdbcOption(),
            $this->optionWithOptionalPrefix('--with-pdo-pgsql', 'libpq', ['include/libpq-fe.h', 'include/postgresql/libpq-fe.h']),
            '--with-pdo-sqlite',
            $this->optionWithOptionalPrefix('--with-pgsql', 'libpq', ['include/libpq-fe.h', 'include/postgresql/libpq-fe.h']),
            $this->optionWithOptionalPrefix('--with-readline', 'readline', ['include/readline/readline.h']),
            '--enable-shmop',
            '--enable-soap',
            $this->optionWithOptionalPrefix('--with-snmp', 'net-snmp', ['include/net-snmp/net-snmp-config.h']),
            '--enable-sockets',
            '--with-sqlite3',
            '--with-sodium',
            '--enable-sysvmsg',
            '--enable-sysvsem',
            '--enable-sysvshm',
            $this->optionWithOptionalPrefix('--with-tidy', 'tidy-html5', ['include/tidy/tidy.h', 'include/tidy.h']),
            '--enable-zend-test',
            $this->optionWithOptionalPrefix('--with-zlib', 'zlib', ['include/zlib.h']),
            '--with-openssl',
            '--with-zip',
            '--enable-opcache',
        ];
    }

    /** @return list<string> */
    public function pkgConfigPackages(): array
    {
        return [
            'bzip2',
            'libcurl',
            'gdlib',
            'gmp',
            'icu-io',
            'icu-i18n',
            'icu-uc',
            'ldap',
            'libxml-2.0',
            'libzip',
            'netsnmp',
            'oniguruma',
            'openssl',
            'libpq',
            'libsodium',
            'sqlite3',
            'tidy',
            'zlib',
        ];
    }

    /** @return list<string> */
    public function makeTargets(): array
    {
        return [
            'sapi/cli/php',
            'sapi/cgi/php-cgi',
        ];
    }

    public function signature(): string
    {
        return sha1(implode("\n", $this->configureArgs()));
    }

    /** @param list<string> $markers */
    private function optionWithOptionalPrefix(string $option, string $dependency, array $markers): string
    {
        $prefix = $this->dependencyPrefix($dependency, $markers);

        return null === $prefix ? $option : $option . '=' . $prefix;
    }

    private function pdoOdbcOption(): string
    {
        $prefix = $this->dependencyPrefix('unixodbc', ['include/sql.h']);

        return '--with-pdo-odbc=unixODBC,' . ($prefix ?? '/usr');
    }

    private function pdoDblibOption(): string
    {
        $prefix = $this->dependencyPrefix('freetds', ['include/sybdb.h']);

        return null === $prefix || '/usr' === $prefix
            ? '--with-pdo-dblib'
            : '--with-pdo-dblib=' . $prefix;
    }

    /** @param list<string> $markers */
    private function dependencyPrefix(string $dependency, array $markers): ?string
    {
        $environmentPrefix = getenv('INTERNALS_CS_' . $this->environmentName($dependency) . '_PREFIX');

        if (is_string($environmentPrefix) && is_dir($environmentPrefix)) {
            return $environmentPrefix;
        }

        foreach ($this->prefixCandidates($dependency) as $prefix) {
            foreach ($markers as $marker) {
                if (is_file($prefix . '/' . $marker)) {
                    return $prefix;
                }
            }
        }

        return null;
    }

    private function environmentName(string $dependency): string
    {
        return str_replace(['-', '.'], '_', mb_strtoupper($dependency));
    }

    /** @return list<string> */
    private function prefixCandidates(string $dependency): array
    {
        $candidates = [];

        foreach (['HOMEBREW_PREFIX', 'CONDA_PREFIX', 'PREFIX'] as $name) {
            $prefix = getenv($name);

            if (is_string($prefix) && '' !== $prefix) {
                $candidates[] = $prefix . '/opt/' . $dependency;
                $candidates[] = $prefix;
            }
        }

        foreach (['/opt/homebrew', '/opt/local', '/usr/local', '/usr'] as $prefix) {
            $candidates[] = $prefix . '/opt/' . $dependency;
            $candidates[] = $prefix;
        }

        return array_filter($candidates, is_string(...))
            |> array_unique(...)
            |> array_values(...);
    }
}
