<?php

declare(strict_types=1);

namespace InternalsCS\PhpSrcTestStyle\ExceptionOutput\Analysis;

enum OutputFamily: string
{
    case MessageOnly = 'message_only';
    case ClassMessage = 'class_message';
    case ClassMessageLocation = 'class_message_location';
    case PreviousException = 'previous_exception';
    case Unknown = 'unknown';
}
