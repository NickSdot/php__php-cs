<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

enum OutputPartKind: string
{
    case Literal = 'literal';
    case Newline = 'newline';
    case ExceptionClass = 'exception_class';
    case ExceptionMessage = 'exception_message';
    case ExceptionFile = 'exception_file';
    case ExceptionLine = 'exception_line';
    case Unknown = 'unknown';
}
