<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Event;

enum EventType: string
{
    case Custody  = 'custody';
    case Activity = 'activity';
    case Medical  = 'medical';
}
