<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\User;

enum UserRole: string
{
    case Parent = 'parent';
    case Child  = 'child';
    case Admin  = 'admin';
}
