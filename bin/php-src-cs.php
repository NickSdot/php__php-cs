#!/usr/bin/env php
<?php

declare(strict_types=1);

require \dirname(__DIR__) . '/vendor/autoload.php';

use InternalsCS\Console\Application;
use InternalsCS\Console\StreamConsoleIo;

exit(new Application(new StreamConsoleIo())->run($argv));
