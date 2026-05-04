<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Auth;

interface OAuthAccountRepositoryInterface
{
    public function findByProviderAndProviderId(string $provider, string $providerId): ?array;

    public function save(string $userId, string $provider, string $providerId): void;
}
