<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Application\Photo\DeletePhotoHandler;
use ZenCoParent\Application\Photo\ListPhotosHandler;
use ZenCoParent\Application\Photo\UploadPhotoCommand;
use ZenCoParent\Application\Photo\UploadPhotoHandler;

final class PhotoController
{
    public function __construct(
        private ListPhotosHandler  $listHandler,
        private UploadPhotoHandler $uploadHandler,
        private DeletePhotoHandler $deleteHandler,
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $params   = $request->getQueryParams();

        $photos = $this->listHandler->handle($tenantId, $params['child_id'] ?? null);

        return ApiResponse::success($response, array_map(fn($p) => $p->toArray(), $photos));
    }

    public function upload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $userId   = (string) $request->getAttribute('userId');

        $files = $request->getUploadedFiles();
        $file  = $files['file'] ?? null;

        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            return ApiResponse::error($response, 'No valid file uploaded', 400);
        }

        $mimeType = $file->getClientMediaType() ?? 'application/octet-stream';
        if (!str_starts_with($mimeType, 'image/')) {
            return ApiResponse::error($response, 'Only image files are allowed', 415);
        }

        $sizeBytes = $file->getSize() ?? 0;
        if ($sizeBytes > 10 * 1024 * 1024) {
            return ApiResponse::error($response, 'File exceeds 10 MB limit', 413);
        }

        $body    = (array) ($request->getParsedBody() ?? []);
        $content = (string) $file->getStream();

        $command = new UploadPhotoCommand(
            tenantId:   $tenantId,
            childId:    isset($body['child_id']) && $body['child_id'] !== '' ? (string) $body['child_id'] : null,
            filename:   $file->getClientFilename() ?? 'upload',
            mimeType:   $mimeType,
            sizeBytes:  $sizeBytes,
            content:    $content,
            caption:    isset($body['caption']) && $body['caption'] !== '' ? (string) $body['caption'] : null,
            uploadedBy: $userId,
        );

        $dto = $this->uploadHandler->handle($command);

        return ApiResponse::success($response, $dto->toArray(), 201);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenantId');
        $this->deleteHandler->handle((string) $args['id'], $tenantId);

        return ApiResponse::success($response, null, 204);
    }
}
