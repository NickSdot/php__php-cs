<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

enum ClassificationSafety: string
{
    case Canonicalizable = 'canonicalizable';
    case AlreadyCanonical = 'already_canonical';
    case DescriptiveContext = 'descriptive_context';
    case MixedSemantics = 'mixed_semantics';
    case NoExceptionMessage = 'no_exception_message';
    case UnsupportedExpression = 'unsupported_expression';
}
