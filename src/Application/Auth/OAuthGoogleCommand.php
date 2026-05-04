<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Auth;

final readonly class OAuthGoogleCommand
{
    public function __construct(
        public string $code,
        public string $tenantSlug,
    ) {}
}
