<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Photo;

use ZenCoParent\Domain\Photo\PhotoRepositoryInterface;
use ZenCoParent\Domain\Storage\FileStorageInterface;

final class ListPhotosHandler
{
    public function __construct(
        private PhotoRepositoryInterface $photoRepo,
        private FileStorageInterface     $storage,
    ) {}

    /** @return PhotoDTO[] */
    public function handle(string $tenantId, ?string $childId = null): array
    {
        $photos = $this->photoRepo->findByTenantId($tenantId, $childId);

        return array_map(
            fn($p) => PhotoDTO::fromPhoto($p, $this->storage->getPublicUrl($p->getStorageKey())),
            $photos,
        );
    }
}
