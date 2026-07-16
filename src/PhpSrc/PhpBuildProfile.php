<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrc;

use function implode;
use function sha1;

final readonly class PhpBuildProfile
{
    /** @return list<string> */
    public function configureArgs(): array
    {
        return [
            '--disable-all',
            '--enable-debug',
            '--enable-cgi',
            '--enable-zend-test',
            '--enable-tokenizer',
            '--enable-bcmath',
            '--enable-calendar',
            '--with-libxml',
            '--enable-dom',
            '--enable-fileinfo',
            '--enable-intl',
            '--with-ldap',
            '--enable-pcntl',
            '--enable-posix',
            '--enable-phar',
            '--enable-session',
            '--enable-simplexml',
            '--enable-soap',
            '--enable-xml',
            '--enable-xmlreader',
            '--enable-xmlwriter',
            '--enable-pdo',
            '--with-pdo-sqlite',
            '--with-sqlite3',
        ];
    }

    /** @return list<string> */
    public function pkgConfigPackages(): array
    {
        return [
            'icu-io',
            'icu-i18n',
            'icu-uc',
            'ldap',
            'libxml-2.0',
            'sqlite3',
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
}
