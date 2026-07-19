<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Fixing;

use InternalsCS\RewriteResult;

interface RewriteRule
{
    public function rewrite(RewriteContext $context): ?RewriteResult;
}
