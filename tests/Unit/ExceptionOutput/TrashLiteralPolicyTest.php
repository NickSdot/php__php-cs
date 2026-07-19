<?php

declare(strict_types=1);

namespace Tests\Unit\ExceptionOutput;

use InternalsCS\Fixers\ExceptionOutput\Analysis\TrashLiteralPolicy;
use PHPUnit\Framework\TestCase;

final class TrashLiteralPolicyTest extends TestCase
{
    public function testRemovesLeadingTrashCaseInsensitively(): void
    {
        $candidates = new TrashLiteralPolicy()->withoutLeadingTrashCandidates('CAUGHT: message');

        self::assertContains('message', $candidates);
    }

    public function testDoesNotTreatUnexpectedExceptionAsTrash(): void
    {
        $candidates = new TrashLiteralPolicy()->withoutLeadingTrashCandidates('Unexpected exception: message');

        self::assertNotContains('message', $candidates);
    }

    public function testRemovesTrashAfterStableContextPrefix(): void
    {
        $candidates = new TrashLiteralPolicy()->withoutLeadingTrashCandidates('printf test 30:Error found: message');

        self::assertContains('printf test 30:message', $candidates);
    }
}
