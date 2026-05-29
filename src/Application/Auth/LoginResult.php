<?php
declare(strict_types=1);

namespace ZenCoParent\Application\Auth;

final readonly class LoginResult
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public \ZenCoParent\Application\User\UserDTO $user,
        /** @var array<array{id:string,title:string,start_at:string,child_id:string|null}> */
        public array $pendingMedicalReports = [],
    ) {}
}
