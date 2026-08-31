<?php

declare(strict_types=1);

namespace Opscale\NovaAPI\Http\Controllers;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Orion\Http\Controllers\Controller;
use Orion\Http\Requests\Request as OrionRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class APIController extends Controller
{
    final public function __construct(Request $request)
    {
        $this->model = $this->resolveModel($request);
        $this->request = $this->resolveRequest($request);
        $this->policy = $this->resolvePolicy($request);

        // This constructror call must be at the end to ensure
        // properties are set before parent constructor logic
        parent::__construct();
    }

    /**
     * @phpstan-ignore solid.lsp.parentCall
     */
    final public function resolveUser(): ?Authenticatable
    {
        return Auth::guard('sanctum')->user();
    }

    /**
     * @return class-string<\Laravel\Nova\Resource<Model>>
     */
    final public function resolveResource(Request $request): string
    {
        $segments = $request->segments();
        if (count($segments) < 2) {
            (new JsonResponse(
                ['error' => 'Resource not found'],
                Response::HTTP_NOT_FOUND))->send();
            exit;
        }

        // For routes like /api/users or /api/users/123, we want the second segment (users)
        $uriKey = $segments[1];
        /** @var array<int, class-string<\Laravel\Nova\Resource<Model>>> $configuredResources */
        $configuredResources = Config::get('nova-api.resources', []);
        $resources = new Collection($configuredResources);

        /** @var class-string<\Laravel\Nova\Resource<Model>>|null $resourceClass */
        $resourceClass = $resources
            ->first(function (mixed $resource) use ($uriKey): bool {
                /** @var class-string<\Laravel\Nova\Resource<Model>> $resource */
                return $resource::uriKey() === $uriKey;
            });

        if ($resourceClass === null) {
            (new JsonResponse(
                ['error' => 'Resource not found'],
                Response::HTTP_NOT_FOUND))->send();
            exit;
        }

        return $resourceClass;
    }

    final public function resolveRequest(Request $request): string
    {
        $resource = $this->resolveResource($request);
        $binding = $resource::uriKey().'-request';

        return App::getAlias($binding);
    }

    final public function resolvePolicy(Request $request): string
    {
        $resource = $this->resolveResource($request);
        $binding = $resource::uriKey().'-policy';

        return App::getAlias($binding);
    }

    final public function index(OrionRequest $request): ResourceCollection|JsonResponse
    {
        try {
            return parent::index($request);
        } catch (AuthorizationException $e) {
            return $this->authorizationErrorResponse($e);
        } catch (Exception $e) {
            return $this->exceptionResponse($e);
        }
    }

    final public function store(OrionRequest $request): JsonResource|JsonResponse
    {
        try {
            return parent::store($request);
        } catch (AuthorizationException $e) {
            return $this->authorizationErrorResponse($e);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * @param  array<int, mixed>  $args
     */
    final public function show(OrionRequest $request, mixed ...$args): JsonResource|JsonResponse
    {
        try {
            return parent::show($request, ...$args);
        } catch (AuthorizationException $e) {
            return $this->authorizationErrorResponse($e);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Resource not found', Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * @param  array<int, mixed>  $args
     */
    final public function update(OrionRequest $request, mixed ...$args): JsonResource|JsonResponse
    {
        try {
            return parent::update($request, ...$args);
        } catch (AuthorizationException $e) {
            return $this->authorizationErrorResponse($e);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Resource not found', Response::HTTP_NOT_FOUND);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * @param  array<int, mixed>  $args
     */
    final public function destroy(OrionRequest $request, mixed ...$args): JsonResource|JsonResponse
    {
        try {
            return parent::destroy($request, ...$args);
        } catch (AuthorizationException $e) {
            return $this->authorizationErrorResponse($e);
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Resource not found', Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            return $this->exceptionResponse($e);
        }
    }

    final protected function resolveModel(Request $request): string
    {
        $resourceClass = $this->resolveResource($request);

        return $resourceClass::$model;
    }

    private function errorResponse(string $message, int $statusCode): JsonResponse
    {
        return new JsonResponse(
            ['error' => $message],
            $statusCode
        );
    }

    private function authorizationErrorResponse(AuthorizationException $authorizationException): JsonResponse
    {
        $statusCode = $authorizationException->status() ?? Response::HTTP_FORBIDDEN;

        return $this->errorResponse($authorizationException->getMessage(), $statusCode);
    }

    private function validationErrorResponse(ValidationException $validationException): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => 'Validation failed',
                'errors' => $validationException->errors(),
            ],
            $validationException->status
        );
    }

    private function exceptionResponse(Exception $exception): JsonResponse
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $this->errorResponse($exception->getMessage(), $exception->getStatusCode());
        }

        return $this->errorResponse($exception->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
