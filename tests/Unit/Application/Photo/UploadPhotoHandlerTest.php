<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Application\Photo;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use ZenCoParent\Application\Photo\PhotoDTO;
use ZenCoParent\Application\Photo\UploadPhotoCommand;
use ZenCoParent\Application\Photo\UploadPhotoHandler;
use ZenCoParent\Domain\Photo\PhotoRepositoryInterface;
use ZenCoParent\Domain\Shared\Exception\ValidationException;
use ZenCoParent\Domain\Storage\FileStorageInterface;

final class UploadPhotoHandlerTest extends TestCase
{
    private MockInterface $photoRepo;
    private MockInterface $storage;
    private UploadPhotoHandler $handler;

    private string $tenantId = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $userId   = 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    protected function setUp(): void
    {
        $this->photoRepo = Mockery::mock(PhotoRepositoryInterface::class);
        $this->storage   = Mockery::mock(FileStorageInterface::class);
        $this->handler   = new UploadPhotoHandler($this->photoRepo, $this->storage);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_uploads_jpeg_image_successfully(): void
    {
        $this->storage->shouldReceive('upload')->once();
        $this->storage->shouldReceive('getPublicUrl')->andReturn('http://storage/photo.jpg');
        $this->photoRepo->shouldReceive('save')->once();

        $result = $this->handler->handle(new UploadPhotoCommand(
            tenantId:   $this->tenantId,
            childId:    null,
            filename:   'photo.jpg',
            mimeType:   'image/jpeg',
            sizeBytes:  51200,
            content:    'fake-image-bytes',
            caption:    'Summer 2026',
            uploadedBy: $this->userId,
        ));

        $this->assertInstanceOf(PhotoDTO::class, $result);
        $this->assertSame('image/jpeg', $result->mimeType);
        $this->assertSame('Summer 2026', $result->caption);
        $this->assertSame('http://storage/photo.jpg', $result->url);
    }

    public function test_throws_validation_for_disallowed_mime_type(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler->handle(new UploadPhotoCommand(
            tenantId:   $this->tenantId,
            childId:    null,
            filename:   'document.pdf',
            mimeType:   'application/pdf',
            sizeBytes:  1024,
            content:    'pdf-bytes',
            caption:    null,
            uploadedBy: $this->userId,
        ));
    }

    public function test_throws_validation_when_file_exceeds_10mb(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler->handle(new UploadPhotoCommand(
            tenantId:   $this->tenantId,
            childId:    null,
            filename:   'huge.jpg',
            mimeType:   'image/jpeg',
            sizeBytes:  11 * 1024 * 1024,
            content:    str_repeat('x', 100),
            caption:    null,
            uploadedBy: $this->userId,
        ));
    }

    public function test_storage_key_contains_tenant_id_and_extension(): void
    {
        $capturedKey = null;

        $this->storage->shouldReceive('upload')->once()
            ->andReturnUsing(function (string $key) use (&$capturedKey) {
                $capturedKey = $key;
            });
        $this->storage->shouldReceive('getPublicUrl')->andReturn('http://storage/key');
        $this->photoRepo->shouldReceive('save')->once();

        $this->handler->handle(new UploadPhotoCommand(
            tenantId:   $this->tenantId,
            childId:    null,
            filename:   'snap.png',
            mimeType:   'image/png',
            sizeBytes:  1024,
            content:    'png-bytes',
            caption:    null,
            uploadedBy: $this->userId,
        ));

        $this->assertStringStartsWith($this->tenantId . '/photos/', $capturedKey);
        $this->assertStringEndsWith('.png', $capturedKey);
    }

    public function test_webp_is_accepted(): void
    {
        $this->storage->shouldReceive('upload')->once();
        $this->storage->shouldReceive('getPublicUrl')->andReturn('http://storage/img.webp');
        $this->photoRepo->shouldReceive('save')->once();

        $result = $this->handler->handle(new UploadPhotoCommand(
            tenantId:   $this->tenantId,
            childId:    null,
            filename:   'img.webp',
            mimeType:   'image/webp',
            sizeBytes:  2048,
            content:    'webp-bytes',
            caption:    null,
            uploadedBy: $this->userId,
        ));

        $this->assertSame('image/webp', $result->mimeType);
    }
}
