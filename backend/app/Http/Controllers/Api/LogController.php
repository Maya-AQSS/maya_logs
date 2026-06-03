<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ResolvesJwtUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListLogsRequest;
use App\Http\Resources\ArchiveLogResultResource;
use App\Http\Resources\LogResource;
use App\Http\Resources\ResolveLogResultResource;
use App\Services\Contracts\ArchivedLogServiceInterface;
use App\Services\Contracts\LogServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maya\Http\Concerns\RespondsWithEnvelope;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class LogController extends Controller
{
    use ResolvesJwtUser;
    use RespondsWithEnvelope;

    public function __construct(
        private LogServiceInterface $logService,
        private ArchivedLogServiceInterface $archivedLogService,
    ) {}

    public function index(ListLogsRequest $request): JsonResponse
    {
        $page = $this->logService->searchAndFilter($request->toFilterDto());

        return $this->paginated($page, LogResource::class, $request);
    }

    public function show(int $id): JsonResponse
    {
        $result = $this->logService->findForShow($id);

        return response()->json([
            'data' => new LogResource($result['dto']),
            'meta' => [
                'archived_log_id' => $result['archived_log_id'],
            ],
        ]);
    }

    public function archive(Request $request, int $id): JsonResponse
    {
        $matchedId = $this->logService->archivedLogIdFor($id);
        if ($matchedId !== null) {
            return response()->json([
                'data' => new ArchiveLogResultResource(['archived_log_id' => $matchedId, 'already_archived' => true]),
                'meta' => [],
            ]);
        }

        $jwtSubject = $this->resolveJwtSubject($request);

        if ($jwtSubject === null) {
            return response()->json([
                'error' => [
                    'code' => 'actor_missing',
                    'message' => __('logs.actor_missing'),
                ],
            ], 403);
        }

        try {
            $archivedDto = $this->archivedLogService->archiveFromLogId($id, $jwtSubject);

            return response()->json([
                'data' => new ArchiveLogResultResource(['archived_log_id' => $archivedDto->id, 'already_archived' => false]),
                'meta' => [],
            ], 201);
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'error' => [
                    'code' => 'user_not_found',
                    'message' => __('logs.not_authorized'),
                ],
            ], 403);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => [
                    'code' => 'archive_failed',
                    'message' => __('logs.archived_error'),
                ],
            ], 500);
        }
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $jwtSubject = $this->resolveJwtSubject($request);

        if ($jwtSubject === null) {
            return response()->json([
                'error' => [
                    'code' => 'actor_missing',
                    'message' => __('logs.actor_missing'),
                ],
            ], 403);
        }

        $this->logService->resolved($id, $jwtSubject);

        return response()->json([
            'data' => new ResolveLogResultResource(['id' => $id, 'resolved' => true]),
        ]);
    }

    public function stream(): StreamedResponse
    {
        return response()->stream(function (): void {
            $payload = $this->logService->streamPayload(10);

            echo "event: logs\n";
            echo 'data: '.json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )."\n\n";

            if (function_exists('ob_flush') && ob_get_level() > 0) {
                @ob_flush();
            }
            @flush();
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
