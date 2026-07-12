<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantInboxMessage;
use App\Models\TenantUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TenantInboxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        $status = trim((string) $request->input('status', ''));

        $messages = TenantInboxMessage::query()
            ->where('tenant_id', $tenant->id)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => $messages->map(fn (TenantInboxMessage $message) => $this->transformMessage($message))->values(),
        ]);
    }

    public function show(Request $request, TenantInboxMessage $inboxMessage): JsonResponse
    {
        $this->assertTenantAccess($request, $inboxMessage);

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => $this->transformMessage($inboxMessage),
        ]);
    }

    public function update(Request $request, TenantInboxMessage $inboxMessage): JsonResponse
    {
        $payload = $request->validate([
            'tenantKey' => ['required', 'string'],
            'status' => ['sometimes', 'string', 'in:new,read,replied,archived'],
            'read_at' => ['sometimes', 'nullable', 'date'],
            'replied_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $this->assertTenantAccess($request, $inboxMessage);

        if (array_key_exists('status', $payload)) {
            $inboxMessage->status = $payload['status'];

            if ($payload['status'] === 'new') {
                $inboxMessage->read_at = null;
                $inboxMessage->replied_at = null;
            } elseif ($payload['status'] === 'read' && !$inboxMessage->read_at) {
                $inboxMessage->read_at = Carbon::now();
            } elseif ($payload['status'] === 'replied' && !$inboxMessage->replied_at) {
                $inboxMessage->replied_at = Carbon::now();
                if (!$inboxMessage->read_at) {
                    $inboxMessage->read_at = Carbon::now();
                }
            }
        }

        if (array_key_exists('read_at', $payload)) {
            $inboxMessage->read_at = $payload['read_at'];
        }

        if (array_key_exists('replied_at', $payload)) {
            $inboxMessage->replied_at = $payload['replied_at'];
        }

        $inboxMessage->save();

        return response()->json([
            'ok' => true,
            'status' => 200,
            'message' => 'Inbox message updated.',
            'data' => $this->transformMessage($inboxMessage),
        ]);
    }

    public function destroy(Request $request, TenantInboxMessage $inboxMessage): JsonResponse
    {
        $this->assertTenantAccess($request, $inboxMessage);

        $inboxMessage->delete();

        return response()->json([
            'ok' => true,
            'status' => 200,
            'message' => 'Inbox message deleted.',
        ]);
    }

    private function transformMessage(TenantInboxMessage $message): array
    {
        return [
            'id' => $message->id,
            'tenant_id' => $message->tenant_id,
            'name' => $message->name,
            'email' => $message->email,
            'phone' => $message->phone,
            'subject' => $message->subject,
            'message' => $message->message,
            'page_slug' => $message->page_slug,
            'pageSlug' => $message->page_slug,
            'source' => $message->source,
            'status' => $message->status,
            'read_at' => $message->read_at,
            'replied_at' => $message->replied_at,
            'meta' => $message->meta,
            'updated_at' => $message->updated_at,
            'created_at' => $message->created_at,
            'title' => $message->subject ?: ($message->name ? sprintf('Contact inquiry from %s', $message->name) : 'Contact inquiry'),
            'preview' => $this->buildPreview($message->message),
        ];
    }

    private function buildPreview(?string $message): string
    {
        $text = trim((string) $message);

        if ($text === '') {
            return 'No preview available';
        }

        return mb_substr($text, 0, 140);
    }

    private function resolveTenant(Request $request): Tenant
    {
        $tenantKey = $request->input('tenantKey');
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

    private function assertTenantAccess(Request $request, TenantInboxMessage $message): void
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $message->tenant_id !== (int) $tenant->id) {
            throw new HttpResponseException(response()->json([
                'ok' => false,
                'status' => 401,
                'error' => 'Unauthorized tenant',
            ], 401));
        }
    }
}
