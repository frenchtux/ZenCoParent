<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Messaging;

enum ThreadType: string
{
    case Parents = 'parents';
    case Family  = 'family';
}
