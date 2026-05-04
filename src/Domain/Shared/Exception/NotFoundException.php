<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Shared\Exception;

final class NotFoundException extends DomainException
{
    public static function forEntity(string $entity, string $id): self
    {
        return new self("{$entity} with id {$id} not found");
    }
}
