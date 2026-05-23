<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\License;

interface LicenseRepositoryInterface
{
    public function get(): ?License;
    public function save(License $license): void;
    public function update(License $license): void;
}
