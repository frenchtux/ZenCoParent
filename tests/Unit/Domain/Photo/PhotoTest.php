<?php
declare(strict_types=1);

namespace ZenCoParent\Tests\Unit\Domain\Photo;

use PHPUnit\Framework\TestCase;
use ZenCoParent\Domain\Photo\Photo;

final class PhotoTest extends TestCase
{
    private string $tenantId  = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';
    private string $createdBy = 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    public function test_create_builds_photo_with_required_fields(): void
    {
        $photo = Photo::create(
            tenantId:   $this->tenantId,
            childId:    null,
            storageKey: 'tenant/photos/abc.jpg',
            filename:   'photo.jpg',
            mimeType:   'image/jpeg',
            sizeBytes:  204800,
            caption:    'First day of school',
            createdBy:  $this->createdBy,
        );

        $this->assertNotEmpty($photo->getId());
        $this->assertSame($this->tenantId, $photo->getTenantId());
        $this->assertNull($photo->getChildId());
        $this->assertSame('tenant/photos/abc.jpg', $photo->getStorageKey());
        $this->assertSame('image/jpeg', $photo->getMimeType());
        $this->assertSame(204800, $photo->getSizeBytes());
        $this->assertSame('First day of school', $photo->getCaption());
    }

    public function test_from_array_hydrates_correctly(): void
    {
        $now  = date('Y-m-d H:i:s');
        $data = [
            'id'          => 'c0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            'tenant_id'   => $this->tenantId,
            'child_id'    => null,
            'storage_key' => 'tenant/photos/test.png',
            'filename'    => 'test.png',
            'mime_type'   => 'image/png',
            'size_bytes'  => 512,
            'caption'     => null,
            'created_by'  => $this->createdBy,
            'created_at'  => $now,
        ];

        $photo = Photo::fromArray($data);

        $this->assertSame('c0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', $photo->getId());
        $this->assertSame('image/png', $photo->getMimeType());
        $this->assertSame(512, $photo->getSizeBytes());
        $this->assertNull($photo->getCaption());
    }

    public function test_to_array_contains_expected_keys(): void
    {
        $photo = Photo::create(
            tenantId:   $this->tenantId,
            childId:    null,
            storageKey: 'tenant/photos/key.jpg',
            filename:   'image.jpg',
            mimeType:   'image/jpeg',
            sizeBytes:  1024,
            caption:    null,
            createdBy:  $this->createdBy,
        );

        $arr = $photo->toArray();

        foreach (['id', 'tenant_id', 'storage_key', 'filename', 'mime_type', 'created_at'] as $key) {
            $this->assertArrayHasKey($key, $arr);
        }
        $this->assertStringContainsString('T', $arr['created_at']);
    }
}
