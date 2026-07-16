<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Fixing;

use InternalsCS\RewriteResult;

interface RewriteRule
{
    public function rewrite(RewriteContext $context): ?RewriteResult;
}
