<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Photo;

use ZenCoParent\Domain\Photo\PhotoRepositoryInterface;
use ZenCoParent\Domain\Storage\FileStorageInterface;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;

final class DeletePhotoHandler
{
    public function __construct(
        private PhotoRepositoryInterface $photoRepo,
        private FileStorageInterface     $storage,
    ) {}

    public function handle(string $photoId, string $tenantId): void
    {
        $photo = $this->photoRepo->findById($photoId)
            ?? throw NotFoundException::forEntity('Photo', $photoId);

        if ($photo->getTenantId() !== $tenantId) {
            throw NotFoundException::forEntity('Photo', $photoId);
        }

        $this->storage->delete($photo->getStorageKey());
        $this->photoRepo->delete($photoId);
    }
}
