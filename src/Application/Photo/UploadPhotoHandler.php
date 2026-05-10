<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Photo;

use ZenCoParent\Domain\Photo\Photo;
use ZenCoParent\Domain\Photo\PhotoRepositoryInterface;
use ZenCoParent\Domain\Storage\FileStorageInterface;
use ZenCoParent\Domain\Shared\Exception\ValidationException;

final class UploadPhotoHandler
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    public function __construct(
        private PhotoRepositoryInterface $photoRepo,
        private FileStorageInterface     $storage,
    ) {}

    public function handle(UploadPhotoCommand $command): PhotoDTO
    {
        if (!isset(self::ALLOWED_MIME_TYPES[$command->mimeType])) {
            throw ValidationException::withErrors([
                'file' => 'Only JPEG, PNG, GIF, and WebP images are allowed',
            ]);
        }

        if ($command->sizeBytes > self::MAX_SIZE_BYTES) {
            throw ValidationException::withErrors([
                'file' => 'File size exceeds the 10 MB limit',
            ]);
        }

        $ext        = self::ALLOWED_MIME_TYPES[$command->mimeType];
        $storageKey = "{$command->tenantId}/photos/" . \Ramsey\Uuid\Uuid::uuid4()->toString() . ".{$ext}";

        $this->storage->upload($storageKey, $command->content, $command->mimeType);

        $photo = Photo::create(
            tenantId:   $command->tenantId,
            childId:    $command->childId,
            storageKey: $storageKey,
            filename:   $command->filename,
            mimeType:   $command->mimeType,
            sizeBytes:  $command->sizeBytes,
            caption:    $command->caption,
            createdBy:  $command->uploadedBy,
        );

        $this->photoRepo->save($photo);

        return PhotoDTO::fromPhoto($photo, $this->storage->getPublicUrl($storageKey));
    }
}
