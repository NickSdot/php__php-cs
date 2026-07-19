<?php

declare(strict_types=1);

namespace InternalsCS\Fixers\ExceptionOutput\Analysis;

enum ClassificationSafety: string
{
    case Fixable = 'fixable';
    case DescriptiveContext = 'descriptive_context';
    case NoExceptionMessage = 'no_exception_message';
    case UnsupportedExpression = 'unsupported_expression';
}
