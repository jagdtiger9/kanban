<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\Service;

use App\Board\Domain\Entity\Attachment;
use App\Board\Domain\Exception\AttachmentForbiddenException;
use App\Board\Domain\Service\AttachmentPolicy;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class AttachmentPolicyTest extends TestCase
{
    private AttachmentPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new AttachmentPolicy();
    }

    public function testCardAttachmentCanBeRemovedByAnyMember(): void
    {
        $this->expectNotToPerformAssertions();

        $this->policy->assertCanRemove(
            $this->attachment(),
            Uuid::uuid4(),
            Uuid::uuid4(),
            false,
        );
    }

    public function testCommentAttachmentCanBeRemovedByCommentAuthor(): void
    {
        $this->expectNotToPerformAssertions();

        $authorId = Uuid::uuid4();
        $this->policy->assertCanRemove(
            $this->attachment(Uuid::uuid4()),
            $authorId,
            $authorId,
            false,
        );
    }

    public function testCommentAttachmentCanBeRemovedByProjectAdmin(): void
    {
        $this->expectNotToPerformAssertions();

        $this->policy->assertCanRemove(
            $this->attachment(Uuid::uuid4()),
            Uuid::uuid4(),
            Uuid::uuid4(),
            true,
        );
    }

    public function testCommentAttachmentCannotBeRemovedByAnotherMember(): void
    {
        $this->expectException(AttachmentForbiddenException::class);

        $this->policy->assertCanRemove(
            $this->attachment(Uuid::uuid4()),
            Uuid::uuid4(),
            Uuid::uuid4(),
            false,
        );
    }

    public function testCommentAttachmentWithoutAuthorCannotBeRemovedByNonAdmin(): void
    {
        $this->expectException(AttachmentForbiddenException::class);

        $this->policy->assertCanRemove(
            $this->attachment(Uuid::uuid4()),
            Uuid::uuid4(),
            null,
            false,
        );
    }

    private function attachment(?UuidInterface $commentId = null): Attachment
    {
        $attachment = Attachment::create(
            Uuid::uuid4(),
            Uuid::uuid4(),
            $commentId,
            Uuid::uuid4(),
            'report.pdf',
            'attachments/abc/report.pdf',
            null,
            'application/pdf',
            1234,
        );
        $attachment->releaseEvents();

        return $attachment;
    }
}
