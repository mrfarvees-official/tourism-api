<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantActivityLog;
use App\Models\TenantUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        $perPage = max(1, min((int) $request->integer('per_page', 10), 50));
        $page = max(1, (int) $request->integer('page', 1));

        $paginator = TenantActivityLog::query()
            ->where('tenant_id', $tenant->id)
            ->with(['user:id,name,email'])
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->getCollection()->map(function (TenantActivityLog $log) {
            $actorName = $log->user?->name ?? 'System';
            $action = $log->action ?: 'activity';

            return [
                'id' => $log->id,
                'tenant_id' => $log->tenant_id,
                'user_id' => $log->user_id,
                'actor_name' => $actorName,
                'actor_email' => $log->user?->email,
                'action' => $action,
                'label' => $log->label,
                'summary' => $log->summary,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'route' => $log->route,
                'method' => $log->method,
                'ip_address' => $log->ip_address,
                'meta' => $log->meta ?? [],
                'timestamp' => $log->created_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'key' => $tenant->key,
                    'name' => $tenant->name,
                    'status' => $tenant->status,
                ],
                'items' => $items,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
        ]);
    }

    private function resolveTenant(Request $request): Tenant
    {
        $tenantKey = (string) $request->input('tenantKey');
        $tenant = Tenant::query()->where('key', $tenantKey)->first();

        if (!$tenant) {
            throw new HttpResponseException(response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant',
            ], 404));
        }

        return $tenant;
    }

    private function assertTenantUser(Request $request, Tenant $tenant): void
    {
        $user = $request->user();

        $tenantUser = TenantUser::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$tenantUser) {
            throw new HttpResponseException(response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant user',
            ], 404));
        }
    }
}
