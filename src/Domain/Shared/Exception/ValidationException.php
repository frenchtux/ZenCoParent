<?php

declare(strict_types=1);

namespace ZenCoParent\Domain\Shared\Exception;

final class ValidationException extends DomainException
{
    private array $errors;

    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct('Validation failed.');
    }

    public static function withErrors(array $errors): self
    {
        return new self($errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
