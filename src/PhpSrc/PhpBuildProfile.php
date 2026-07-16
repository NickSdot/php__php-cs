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
            '--enable-dom',
            '--enable-fileinfo',
            '--enable-intl',
            '--with-ldap',
            '--enable-pcntl',
            '--enable-phar',
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
