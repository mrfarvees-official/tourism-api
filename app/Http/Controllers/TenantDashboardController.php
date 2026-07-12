<?php

namespace App\Http\Controllers;

use App\Models\ContentData;
use App\Models\ContentSchema;
use App\Models\Tenant;
use App\Models\TenantInboxMessage;
use App\Models\TenantAssets;
use App\Models\TenantInvites;
use App\Models\TenantPages;
use App\Models\TenantUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class TenantDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        $pages = TenantPages::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        $media = TenantAssets::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        $schemas = ContentSchema::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('updated_at')
            ->get();

        $contentData = ContentData::query()
            ->whereHas('contentSchema', fn ($query) => $query->where('tenant_id', $tenant->id))
            ->with(['contentSchema'])
            ->orderByDesc('updated_at')
            ->limit(24)
            ->get();

        $inboxMessages = TenantInboxMessage::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('updated_at')
            ->limit(24)
            ->get();

        $members = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->with('user:id,name,email')
            ->orderByDesc('last_seen_at')
            ->get();

        $invites = TenantInvites::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $summary = [
            'pages_total' => $pages->count(),
            'pages_published' => $pages->where('status', 'published')->count(),
            'pages_draft' => $pages->where('status', 'draft')->count(),
            'media_total' => $media->count(),
            'media_bytes' => (int) $media->sum(fn (TenantAssets $asset) => (int) ($asset->size ?? 0)),
            'schemas_total' => $schemas->count(),
            'schemas_enabled' => $schemas->where('status', 'enabled')->count(),
            'records_total' => $contentData->count(),
            'members_total' => $members->count(),
            'owners_total' => $members->where('role', 'tenant_owner')->count(),
            'invites_total' => $invites->count(),
            'updated_at' => $this->latestTimestamp(collect([$pages, $media, $schemas, $contentData, $members, $invites, $inboxMessages])),
        ];

        $recentPages = $pages->map(fn (TenantPages $page) => [
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'status' => $page->status,
            'published_at' => $page->published_at,
            'updated_at' => $page->updated_at,
        ])->values();

        $recentMedia = $media->map(fn (TenantAssets $asset) => [
            'id' => $asset->id,
            'label' => $asset->label,
            'kind' => $asset->kind,
            'mime' => $asset->mime,
            'size' => $asset->size,
            'secure_url' => $asset->secure_url,
            'public_id' => $asset->public_id,
            'updated_at' => $asset->updated_at,
        ])->values();

        $recentSchemas = $schemas->map(fn (ContentSchema $schema) => [
            'id' => $schema->id,
            'name' => $schema->name,
            'menu' => $schema->menu,
            'status' => $schema->status,
            'version' => $schema->version,
            'updated_at' => $schema->updated_at,
        ])->values();

        $recentMembers = $members->map(fn (TenantUser $member) => [
            'id' => $member->id,
            'user_id' => $member->user_id,
            'name' => $member->user?->name,
            'email' => $member->user?->email,
            'role' => $member->role,
            'status' => $member->status,
            'last_seen_at' => $member->last_seen_at,
            'joined_at' => $member->joined_at,
        ])->values();

        $recentRecords = $contentData->map(function (ContentData $record) {
            $menu = $record->contentSchema?->menu ?? 'content';
            $data = is_array($record->data) ? $record->data : [];

            return [
                'id' => $record->id,
                'menu' => $menu,
                'title' => Arr::get($data, 'title')
                    ?? Arr::get($data, 'name')
                    ?? Arr::get($data, 'label')
                    ?? Arr::get($data, 'headline')
                    ?? "Record #{$record->id}",
                'page_slug' => Arr::get($data, 'page_slug'),
                'name' => Arr::get($data, 'name'),
                'email' => Arr::get($data, 'email'),
                'phone' => Arr::get($data, 'phone'),
                'subject' => Arr::get($data, 'subject'),
                'message' => Arr::get($data, 'message'),
                'source' => Arr::get($data, 'source'),
                'status' => Arr::get($data, 'status'),
                'updated_at' => $record->updated_at,
                'created_at' => $record->created_at,
                'preview' => $this->buildPreview($data),
            ];
        })->values();

        $recentInbox = $inboxMessages->map(fn (TenantInboxMessage $message) => [
            'id' => $message->id,
            'menu' => 'inbox',
            'title' => $message->subject ?: ($message->name ? sprintf('Contact inquiry from %s', $message->name) : 'Contact inquiry'),
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
            'updated_at' => $message->updated_at,
            'created_at' => $message->created_at,
            'preview' => $this->buildPreview(['message' => $message->message, 'subject' => $message->subject, 'name' => $message->name]),
        ])->values();

        $legacyInbox = $this->filterRecords($recentRecords, ['inbox', 'lead', 'message', 'enquiry', 'inquiry', 'contact', 'quote', 'booking']);
        $mergedInbox = collect($recentInbox->all())
            ->merge($legacyInbox)
            ->unique(function (array $record) {
                return implode('|', [
                    strtolower((string) ($record['email'] ?? '')),
                    strtolower((string) ($record['subject'] ?? $record['title'] ?? '')),
                    strtolower((string) ($record['message'] ?? '')),
                    strtolower((string) ($record['page_slug'] ?? $record['pageSlug'] ?? '')),
                    strtolower((string) ($record['status'] ?? '')),
                ]);
            })
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'key' => $tenant->key,
                    'name' => $tenant->name,
                    'status' => $tenant->status,
                    'timezone' => $tenant->timezone,
                    'locale' => $tenant->locale,
                ],
                'summary' => $summary,
                'pages' => $recentPages,
                'media' => $recentMedia,
                'schemas' => $recentSchemas,
                'members' => $recentMembers,
                'invites' => $invites->values(),
                'records' => $recentRecords,
                'inbox' => $recentInbox,
                'categories' => [
                    'inbox' => $mergedInbox,
                    'tours' => $this->filterRecords($recentRecords, ['tour', 'package', 'destination', 'collection', 'trip', 'experience']),
                    'customers' => $recentMembers,
                ],
                'activity' => $this->buildActivity($recentPages, $recentMedia, $recentRecords, $recentMembers, $recentInbox),
            ],
        ]);
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

    private function latestTimestamp(Collection $groups): ?string
    {
        $timestamps = $groups->flatMap(function ($group) {
            return collect($group)->map(function ($item) {
                if (is_array($item)) {
                    return $item['updated_at'] ?? $item['created_at'] ?? $item['last_seen_at'] ?? $item['joined_at'] ?? null;
                }

                return $item->updated_at ?? $item->created_at ?? $item->last_seen_at ?? $item->joined_at ?? null;
            });
        })->filter();

        return $timestamps->sortDesc()->first();
    }

    private function buildPreview(array $data): string
    {
        foreach (['summary', 'description', 'message', 'title', 'name', 'label'] as $key) {
            $value = Arr::get($data, $key);
            if (is_string($value) && trim($value) !== '') {
                return trim(mb_substr($value, 0, 140));
            }
        }

        return 'No preview available';
    }

    private function filterRecords(Collection $records, array $keywords): array
    {
        return $records
            ->filter(function (array $record) use ($keywords) {
                $menu = strtolower((string) ($record['menu'] ?? ''));
                $title = strtolower((string) ($record['title'] ?? ''));
                $source = strtolower((string) ($record['source'] ?? ''));
                $message = strtolower((string) ($record['message'] ?? ''));
                $email = strtolower((string) ($record['email'] ?? ''));
                $name = strtolower((string) ($record['name'] ?? ''));
                foreach ($keywords as $keyword) {
                    if (
                        str_contains($menu, $keyword) ||
                        str_contains($title, $keyword) ||
                        str_contains($source, $keyword) ||
                        str_contains($message, $keyword) ||
                        str_contains($email, $keyword) ||
                        str_contains($name, $keyword)
                    ) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }

    private function buildActivity(Collection $pages, Collection $media, Collection $records, Collection $members, Collection $inboxMessages): array
    {
        $items = [];

        foreach ($pages as $page) {
            $items[] = [
                'type' => 'page',
                'label' => $page['title'] ?? $page->title,
                'meta' => $page['status'] ?? $page->status,
                'timestamp' => $page['updated_at'] ?? $page->updated_at,
            ];
        }

        foreach ($media as $asset) {
            $items[] = [
                'type' => 'media',
                'label' => $asset['label'] ?? $asset->label ?? "Asset #{$asset->id}",
                'meta' => $asset['mime'] ?? $asset->kind ?? 'media',
                'timestamp' => $asset['updated_at'] ?? $asset->updated_at,
            ];
        }

        foreach ($records as $record) {
            $items[] = [
                'type' => 'record',
                'label' => $record['title'],
                'meta' => $record['menu'],
                'timestamp' => $record['updated_at'],
            ];
        }

        foreach ($inboxMessages as $message) {
            $items[] = [
                'type' => 'inbox',
                'label' => $message['title'] ?? 'Inbox message',
                'meta' => $message['status'] ?? 'new',
                'timestamp' => $message['updated_at'],
            ];
        }

        foreach ($members as $member) {
            $items[] = [
                'type' => 'member',
                'label' => $member['name'] ?? $member->user?->name ?? 'Member',
                'meta' => $member['role'] ?? $member->role,
                'timestamp' => $member['last_seen_at'] ?? $member['joined_at'] ?? null,
            ];
        }

        return collect($items)
            ->filter(fn (array $item) => !empty($item['timestamp']))
            ->sortByDesc('timestamp')
            ->take(12)
            ->values()
            ->all();
    }
}
