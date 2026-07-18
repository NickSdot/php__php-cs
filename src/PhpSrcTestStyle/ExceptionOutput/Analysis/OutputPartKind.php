<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

enum OutputPartKind: string
{
    case Literal = 'literal';
    case Newline = 'newline';
    case OtherVariable = 'other_variable';
    case OtherExpression = 'other_expression';
    case ExceptionClass = 'exception_class';
    case ExceptionMessage = 'exception_message';
    case ExceptionCode = 'exception_code';
    case ExceptionFile = 'exception_file';
    case ExceptionLine = 'exception_line';
    case ExceptionTrace = 'exception_trace';
    case Unknown = 'unknown';
}
