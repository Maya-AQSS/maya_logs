<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Dtos\CommentDto;
use App\Http\Controllers\Api\Concerns\ResolvesCommentActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\PanelUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * CRUD de comentarios anidados bajo un padre comentable (`/{padre}/{id}/comments`),
 * parametrizado por modelo padre. Cada hijo aporta: FQCN del padre
 * ({@see self::commentableType()}), verificación de existencia vía su service
 * ({@see self::assertParentExists()}) y, si su wire format histórico difiere,
 * un override de {@see self::storeResponse()}.
 */
abstract class AbstractCommentController extends Controller
{
    use ResolvesCommentActor;

    public function __construct(
        protected readonly PanelUserService $panelUserService,
        protected readonly CommentServiceInterface $commentService,
    ) {}

    /**
     * FQCN del modelo padre comentable (morph type de `comments`).
     *
     * @return class-string
     */
    abstract protected function commentableType(): string;

    /**
     * Verifica que el padre existe (vía service; lanza ModelNotFoundException → 404).
     */
    abstract protected function assertParentExists(int $parentId): void;

    public function index(int $parentId): AnonymousResourceCollection
    {
        $this->assertParentExists($parentId);

        return CommentResource::collection(
            $this->commentService->listForCommentable($this->commentableType(), $parentId),
        );
    }

    public function store(StoreCommentRequest $request, int $parentId): JsonResponse
    {
        $this->assertParentExists($parentId);

        $user = $this->resolveActor($request);

        $dto = $this->commentService->createForCommentable(
            $this->commentableType(),
            $parentId,
            $user->id,
            $request->validated('content'),
        );

        return $this->storeResponse($dto, $request);
    }

    /**
     * Shape de la respuesta 201. Default: envelope `{"data": {...}}`.
     */
    protected function storeResponse(CommentDto $dto, StoreCommentRequest $request): JsonResponse
    {
        return response()->json([
            'data' => (new CommentResource($dto))->resolve($request),
        ], 201);
    }
}
