<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Domain\MedicalRecord\MedicalAttachment;
use ZenCoParent\Domain\MedicalRecord\MedicalAttachmentRepositoryInterface;
use ZenCoParent\Domain\MedicalRecord\MedicalRecordRepositoryInterface;
use ZenCoParent\Domain\Storage\FileStorageInterface;

final class MedicalAttachmentController
{
    public function __construct(
        private readonly MedicalAttachmentRepositoryInterface $attachRepo,
        private readonly MedicalRecordRepositoryInterface     $recordRepo,
        private readonly FileStorageInterface                  $storage,
    ) {}

    /** GET /medical-records/{id}/attachments */
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $recordId = (string) $args['id'];

        $record = $this->recordRepo->findById($recordId);
        if ($record === null || $record->getTenantId() !== $tenantId) {
            return ApiResponse::error($response, 'Antécédent médical introuvable.', 404);
        }

        $attachments = $this->attachRepo->findByRecordId($recordId);
        $result      = array_map(fn($a) => $this->toPublicArray($a), $attachments);

        return ApiResponse::success($response, $result);
    }

    /** POST /medical-records/{id}/attachments — multipart/form-data */
    public function upload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId   = (string) $request->getAttribute('tenantId');
        $userId     = (string) $request->getAttribute('userId');
        $recordId   = (string) $args['id'];

        $record = $this->recordRepo->findById($recordId);
        if ($record === null || $record->getTenantId() !== $tenantId) {
            return ApiResponse::error($response, 'Antécédent médical introuvable.', 404);
        }

        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['file'] ?? null;

        if ($file === null) {
            return ApiResponse::error($response, "Le champ 'file' est requis.", 400);
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return ApiResponse::error($response, 'Erreur lors du téléversement du fichier.', 400);
        }

        $size     = $file->getSize();
        $mimeType = $file->getClientMediaType() ?? 'application/octet-stream';
        $filename = $file->getClientFilename() ?? 'fichier';
        $filename = basename($filename);

        if ($size > MedicalAttachment::MAX_SIZE_BYTES) {
            return ApiResponse::error($response, 'Fichier trop volumineux (max 10 Mo).', 413);
        }

        if (!in_array($mimeType, MedicalAttachment::ALLOWED_MIME_TYPES, true)) {
            return ApiResponse::error($response, 'Type de fichier non autorisé.', 415);
        }

        $ext        = pathinfo($filename, PATHINFO_EXTENSION);
        $storageKey = "medical/{$tenantId}/{$recordId}/" . \Ramsey\Uuid\Uuid::uuid4()->toString() . ($ext ? ".{$ext}" : '');

        try {
            $content = (string) $file->getStream();
            $this->storage->upload($storageKey, $content, $mimeType);
        } catch (\Throwable $e) {
            return ApiResponse::error($response, 'Erreur de stockage : ' . $e->getMessage(), 500);
        }

        $attachment = MedicalAttachment::create(
            tenantId:   $tenantId,
            recordId:   $recordId,
            filename:   $filename,
            mimeType:   $mimeType,
            sizeBytes:  $size,
            storageKey: $storageKey,
            uploadedBy: $userId,
        );

        $this->attachRepo->save($attachment);

        return ApiResponse::success($response, $this->toPublicArray($attachment), 201);
    }

    /** DELETE /medical-records/{id}/attachments/{attachmentId} */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId    = (string) $request->getAttribute('tenantId');
        $attachmentId = (string) $args['attachmentId'];

        $attachment = $this->attachRepo->findById($attachmentId);
        if ($attachment === null || $attachment->getTenantId() !== $tenantId) {
            return ApiResponse::error($response, 'Pièce jointe introuvable.', 404);
        }

        try {
            $this->storage->delete($attachment->getStorageKey());
        } catch (\Throwable) {
            // Storage delete failure is non-fatal — still remove the DB record
        }

        $this->attachRepo->delete($attachmentId);

        return ApiResponse::success($response, ['deleted' => true]);
    }

    /** GET /medical-records/{id}/attachments/{attachmentId}/download */
    public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId     = (string) $request->getAttribute('tenantId');
        $attachmentId = (string) $args['attachmentId'];

        $attachment = $this->attachRepo->findById($attachmentId);
        if ($attachment === null || $attachment->getTenantId() !== $tenantId) {
            return ApiResponse::error($response, 'Pièce jointe introuvable.', 404);
        }

        // Return a redirect to the public/signed URL
        $url = $this->storage->getPublicUrl($attachment->getStorageKey());

        return $response
            ->withStatus(302)
            ->withHeader('Location', $url);
    }

    private function toPublicArray(MedicalAttachment $a): array
    {
        $data = $a->toArray();
        unset($data['storage_key']); // never expose internal storage key
        $data['download_url'] = "/medical-records/{$a->getRecordId()}/attachments/{$a->getId()}/download";
        return $data;
    }
}
