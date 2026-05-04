<?php
declare(strict_types=1);

namespace ZenCoParent\Api\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Handlers\ErrorHandler;
use Slim\Interfaces\CallableResolverInterface;
use ZenCoParent\Api\Response\ApiResponse;
use ZenCoParent\Domain\Shared\Exception\NotFoundException;
use ZenCoParent\Domain\Shared\Exception\UnauthorizedException;
use ZenCoParent\Domain\Shared\Exception\ValidationException;
use ZenCoParent\Domain\User\Exception\UserAlreadyExistsException;

final class JsonErrorHandler extends ErrorHandler
{
    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
    ) {
        parent::__construct($callableResolver, $responseFactory);
    }

    protected function respond(): ResponseInterface
    {
        $exception = $this->exception;
        $response  = $this->responseFactory->createResponse();

        return match (true) {
            $exception instanceof ValidationException =>
                ApiResponse::error($response, 'Validation failed', 422, $exception->getErrors()),

            $exception instanceof UnauthorizedException =>
                ApiResponse::error($response, $exception->getMessage(), 401),

            $exception instanceof UserAlreadyExistsException =>
                ApiResponse::error($response, $exception->getMessage(), 409),

            $exception instanceof NotFoundException =>
                ApiResponse::error($response, $exception->getMessage(), 404),

            default => ApiResponse::error(
                $response,
                $this->displayErrorDetails ? $exception->getMessage() : 'Internal server error',
                500,
            ),
        };
    }
}
