<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\ErrorCode;
use App\Services\Contracts\CommentServiceInterface;
use App\Services\Contracts\ErrorCodeServiceInterface;
use App\Services\PanelUserService;

class ErrorCodeCommentController extends AbstractCommentController
{
    public function __construct(
        PanelUserService $panelUserService,
        CommentServiceInterface $commentService,
        private readonly ErrorCodeServiceInterface $errorCodeService,
    ) {
        parent::__construct($panelUserService, $commentService);
    }

    protected function commentableType(): string
    {
        return ErrorCode::class;
    }

    protected function assertParentExists(int $parentId): void
    {
        $this->errorCodeService->findOrFail($parentId);
    }
}
