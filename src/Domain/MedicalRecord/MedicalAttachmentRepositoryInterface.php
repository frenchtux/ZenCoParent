<?php
declare(strict_types=1);

namespace ZenCoParent\Domain\MedicalRecord;

interface MedicalAttachmentRepositoryInterface
{
    /** @return MedicalAttachment[] */
    public function findByRecordId(string $recordId): array;

    public function findById(string $id): ?MedicalAttachment;

    public function save(MedicalAttachment $attachment): void;

    public function delete(string $id): void;
}
