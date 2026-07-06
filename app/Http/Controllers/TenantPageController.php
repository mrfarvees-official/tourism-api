<?php

namespace App\Http\Controllers;

use App\Models\ContentData;
use App\Models\ContentDataChild;
use App\Models\ContentDataChildField;
use App\Models\ContentSchema;
use App\Models\Tenant;
use App\Models\TenantComponentFeild;
use App\Models\TenantComponentRepeaterItem;
use App\Models\TenantPageComponent;
use App\Models\TenantPages;
use App\Models\TenantRepeaterField;
use App\Models\TenantUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantPageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        $pages = TenantPages::query()
            ->where('tenant_id', $tenant->id)
            ->latest('updated_at')
            ->get()
            ->map(fn (TenantPages $page) => $this->hydratePage($page, false));

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => $pages,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        $payload = $this->validatePagePayload($request);
        $schemaPayload = $payload['schema'] ?? [];
        $page = DB::transaction(function () use ($tenant, $payload, $schemaPayload) {
            $page = TenantPages::withTrashed()
                ->where('tenant_id', $tenant->id)
                ->where('slug', $payload['slug'])
                ->first();

            $pageData = [
                'title' => $payload['title'],
                'schema' => $schemaPayload + [
                    'components' => $payload['components'] ?? [],
                ],
                'seo' => $payload['seo'] ?? null,
                'status' => $payload['status'] ?? 'published',
                'meta_title' => $payload['meta_title'] ?? null,
                'meta_description' => $payload['meta_description'] ?? null,
                'og_asset_id' => $payload['og_asset_id'] ?? null,
                'published_at' => $payload['published_at'] ?? now(),
            ];

            if ($page) {
                $page->fill($pageData);
                if (method_exists($page, 'trashed') && $page->trashed()) {
                    $page->restore();
                }
                $page->save();
            } else {
                $page = TenantPages::create([
                    'tenant_id' => $tenant->id,
                    'slug' => $payload['slug'],
                    ...$pageData,
                ]);
            }

            if (!empty($payload['components'])) {
                $this->syncPageTree($tenant->id, $page, $payload['components'] ?? []);
            }

            if (!empty($payload['content_datas'])) {
                $this->syncContentData($tenant->id, $page, $payload['content_datas'] ?? []);
            }

            return $page->refresh();
        });

        return response()->json([
            'ok' => true,
            'status' => 200,
            'message' => 'Page saved',
            'data' => $this->hydratePage($page, true),
        ]);
    }

    public function show(Request $request, string $tenantKey, string $slug): JsonResponse
    {
        $tenant = $this->resolveTenant($request, $tenantKey);
        $page = $this->resolvePublishedLivePage($tenant->id, $slug);

        if (!$page) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Page not found',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => $this->hydratePage($page, true),
        ]);
    }

    public function showDefault(Request $request, string $tenantKey): JsonResponse
    {
        $tenant = $this->resolveTenant($request, $tenantKey);
        $page = $this->resolvePublishedLivePage($tenant->id, null);

        if (!$page) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Page not found',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => $this->hydratePage($page, true),
        ]);
    }

    public function edit(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        $page = TenantPages::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->first();

        if (!$page) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Page not found',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => $this->hydratePage($page, true),
        ]);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        $page = TenantPages::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->firstOrFail();

        $payload = $this->validatePagePayload($request);

        $page = DB::transaction(function () use ($tenant, $page, $payload) {
            $schemaPayload = $payload['schema'] ?? [];
            $page->fill([
                'slug' => $payload['slug'],
                'title' => $payload['title'],
                'schema' => $schemaPayload + [
                    'components' => $payload['components'] ?? [],
                ],
                'seo' => $payload['seo'] ?? null,
                'status' => $payload['status'] ?? $page->status,
                'meta_title' => $payload['meta_title'] ?? null,
                'meta_description' => $payload['meta_description'] ?? null,
                'og_asset_id' => $payload['og_asset_id'] ?? null,
                'published_at' => $payload['published_at'] ?? $page->published_at,
            ])->save();

            if (!empty($payload['components'])) {
                $this->syncPageTree($tenant->id, $page, $payload['components'] ?? []);
            }

            if (!empty($payload['content_datas'])) {
                $this->syncContentData($tenant->id, $page, $payload['content_datas'] ?? []);
            }

            return $page->refresh();
        });

        return response()->json([
            'ok' => true,
            'status' => 200,
            'message' => 'Page updated',
            'data' => $this->hydratePage($page, true),
        ]);
    }

    public function destroy(Request $request, ?string $slug = null): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        $slug = $slug ?: $request->input('slug');
        if (!$slug) {
            return response()->json([
                'ok' => false,
                'status' => 400,
                'error' => 'Page slug required',
            ], 400);
        }

        $page = TenantPages::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->firstOrFail();

        $page->delete($page->id);

        return response()->json([
            'ok' => true,
            'status' => 200,
            'message' => 'Page deleted',
        ]);
    }

    private function validatePagePayload(Request $request): array
    {
        return $request->validate([
            'tenantKey' => ['required', 'string'],
            'slug' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:200'],
            'schema' => ['nullable', 'array'],
            'seo' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:draft,published'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'og_asset_id' => ['nullable', 'integer'],
            'published_at' => ['nullable', 'date'],
            'components' => ['nullable', 'array'],
            'components.*.component_type' => ['required', 'string', 'max:60'],
            'components.*.variant' => ['nullable', 'string', 'max:60'],
            'components.*.sort_order' => ['nullable', 'integer'],
            'components.*.is_enabled' => ['nullable', 'boolean'],
            'components.*.fields' => ['nullable', 'array'],
            'components.*.repeaters' => ['nullable', 'array'],
            'content_datas' => ['nullable', 'array'],
            'content_datas.*.content_schema_menu' => ['required', 'string', 'max:191'],
            'content_datas.*.data' => ['required', 'array'],
            'content_datas.*.children' => ['nullable', 'array'],
            'content_datas.*.children.*.source_key' => ['nullable', 'string', 'max:191'],
            'content_datas.*.children.*.row_key' => ['nullable', 'string', 'max:191'],
            'content_datas.*.children.*.sort_order' => ['nullable', 'integer'],
            'content_datas.*.children.*.payload' => ['nullable', 'array'],
            'content_datas.*.children.*.data' => ['nullable', 'array'],
            'content_datas.*.children.*.fields' => ['nullable', 'array'],
            'content_datas.*.children.*.fields.*.field_key' => ['required', 'string', 'max:80'],
            'content_datas.*.children.*.fields.*.source_column' => ['nullable', 'string', 'max:120'],
            'content_datas.*.children.*.fields.*.field_type' => ['nullable', 'string', 'max:20'],
            'content_datas.*.children.*.fields.*.value_string' => ['nullable', 'string', 'max:500'],
            'content_datas.*.children.*.fields.*.value_text' => ['nullable', 'string'],
            'content_datas.*.children.*.fields.*.value_int' => ['nullable', 'integer'],
            'content_datas.*.children.*.fields.*.value_bool' => ['nullable', 'boolean'],
            'content_datas.*.children.*.fields.*.value_decimal' => ['nullable', 'numeric'],
            'content_datas.*.children.*.fields.*.value_asset_id' => ['nullable', 'integer', 'exists:tenant_assets,id'],
        ]);
    }

    private function resolveTenant(Request $request, ?string $tenantKey = null): Tenant
    {
        $tenantKey = $tenantKey ?: $request->input('tenantKey');
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

    private function resolvePublishedLivePage(int $tenantId, ?string $slug): ?TenantPages
    {
        if (is_string($slug) && $slug !== '') {
            $page = TenantPages::query()
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->where('status', 'published')
                ->first();

            if ($page) {
                return $page;
            }

            $page = TenantPages::query()
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->first();

            if ($page) {
                return $page;
            }

            if ($slug !== 'home') {
                return null;
            }
        }

        $page = TenantPages::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->orderByRaw("CASE WHEN slug = 'home' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->first();

        if ($page) {
            return $page;
        }

        return TenantPages::query()
            ->where('tenant_id', $tenantId)
            ->orderByRaw("CASE WHEN slug = 'home' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->first();
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

    private function syncPageTree(int $tenantId, TenantPages $page, array $components): void
    {
        $componentIds = [];

        foreach ($components as $componentData) {
            $component = TenantPageComponent::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'page_id' => $page->id,
                    'component_type' => $componentData['component_type'],
                    'sort_order' => (int) ($componentData['sort_order'] ?? 0),
                ],
                [
                    'variant' => $componentData['variant'] ?? null,
                    'is_enabled' => (bool) ($componentData['is_enabled'] ?? true),
                ]
            );

            $componentIds[] = $component->id;
            $this->syncComponentFields($tenantId, $component, $componentData['fields'] ?? []);
            $this->syncRepeaterItems($tenantId, $component, $componentData['repeaters'] ?? []);
        }

        TenantPageComponent::query()
            ->where('tenant_id', $tenantId)
            ->where('page_id', $page->id)
            ->whereNotIn('id', $componentIds ?: [0])
            ->delete();
    }

    private function syncComponentFields(int $tenantId, TenantPageComponent $component, array $fields): void
    {
        $fieldKeys = [];

        foreach ($fields as $field) {
            $key = $field['field_key'] ?? null;
            if (!$key) {
                continue;
            }

            $fieldKeys[] = $key;

            TenantComponentFeild::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'component_id' => $component->id,
                    'field_key' => $key,
                ],
                [
                    'field_type' => $field['field_type'] ?? 'string',
                    'value_string' => $field['value_string'] ?? null,
                    'value_text' => $field['value_text'] ?? null,
                    'value_int' => $field['value_int'] ?? null,
                    'value_bool' => $field['value_bool'] ?? null,
                    'value_decimal' => $field['value_decimal'] ?? null,
                    'value_asset_id' => $field['value_asset_id'] ?? null,
                ]
            );
        }

        TenantComponentFeild::query()
            ->where('tenant_id', $tenantId)
            ->where('component_id', $component->id)
            ->whereNotIn('field_key', $fieldKeys ?: ['__none__'])
            ->delete();
    }

    private function syncRepeaterItems(int $tenantId, TenantPageComponent $component, array $repeaters): void
    {
        $repeaterIds = [];

        foreach ($repeaters as $itemData) {
            $repeaterKey = $itemData['repeater_key'] ?? null;
            if (!$repeaterKey) {
                continue;
            }

            $repeaterItem = TenantComponentRepeaterItem::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'component_id' => $component->id,
                    'repeater_key' => $repeaterKey,
                    'sort_order' => (int) ($itemData['sort_order'] ?? 0),
                ],
                []
            );

            $repeaterIds[] = $repeaterItem->id;
            $this->syncRepeaterFields($tenantId, $repeaterItem, $itemData['fields'] ?? []);
        }

        TenantComponentRepeaterItem::query()
            ->where('tenant_id', $tenantId)
            ->where('component_id', $component->id)
            ->whereNotIn('id', $repeaterIds ?: [0])
            ->delete();
    }

    private function syncRepeaterFields(int $tenantId, TenantComponentRepeaterItem $repeaterItem, array $fields): void
    {
        $fieldKeys = [];

        foreach ($fields as $field) {
            $key = $field['field_key'] ?? null;
            if (!$key) {
                continue;
            }

            $fieldKeys[] = $key;

            TenantRepeaterField::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'repeater_item_id' => $repeaterItem->id,
                    'field_key' => $key,
                ],
                [
                    'field_type' => $field['field_type'] ?? 'string',
                    'value_string' => $field['value_string'] ?? null,
                    'value_text' => $field['value_text'] ?? null,
                    'value_int' => $field['value_int'] ?? null,
                    'value_bool' => $field['value_bool'] ?? null,
                    'value_decimal' => $field['value_decimal'] ?? null,
                    'value_asset_id' => $field['value_asset_id'] ?? null,
                ]
            );
        }

        TenantRepeaterField::query()
            ->where('tenant_id', $tenantId)
            ->where('repeater_item_id', $repeaterItem->id)
            ->whereNotIn('field_key', $fieldKeys ?: ['__none__'])
            ->delete();
    }

    private function syncContentData(int $tenantId, TenantPages $page, array $contentDatas): void
    {
        if (empty($contentDatas)) {
            Log::info('tenant page save: no content_datas to sync', [
                'tenant_id' => $tenantId,
                'page_id' => $page->id,
                'page_slug' => $page->slug,
            ]);
            return;
        }

        $schemas = ContentSchema::query()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('menu');

        Log::info('tenant page save: syncing content_datas', [
            'tenant_id' => $tenantId,
            'page_id' => $page->id,
            'page_slug' => $page->slug,
            'snapshot_count' => count($contentDatas),
            'menus' => array_values(array_filter(array_map(
                fn ($snapshot) => is_array($snapshot) ? ($snapshot['content_schema_menu'] ?? null) : null,
                $contentDatas
            ))),
            'available_schema_menus' => $schemas->keys()->values()->all(),
        ]);

        foreach ($contentDatas as $snapshot) {
            if (!is_array($snapshot)) {
                Log::warning('tenant page save: skipped non-array content snapshot', [
                    'tenant_id' => $tenantId,
                    'page_id' => $page->id,
                ]);
                continue;
            }

            $menu = $snapshot['content_schema_menu'] ?? null;
            if (!is_string($menu) || $menu === '') {
                Log::warning('tenant page save: skipped content snapshot with missing schema menu', [
                    'tenant_id' => $tenantId,
                    'page_id' => $page->id,
                    'menu' => $menu,
                    'available_schema_menus' => $schemas->keys()->values()->all(),
                ]);
                continue;
            }

            $schema = $schemas[$menu] ?? $this->ensureContentSchemaForSnapshot($tenantId, $menu, $snapshot);
            if (!$schema) {
                Log::warning('tenant page save: skipped content snapshot because schema could not be resolved', [
                    'tenant_id' => $tenantId,
                    'page_id' => $page->id,
                    'menu' => $menu,
                    'available_schema_menus' => $schemas->keys()->values()->all(),
                ]);
                continue;
            }

            $data = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];
            $data['page_slug'] = $page->slug;
            $data['component_id'] = isset($data['component_id']) ? (string) $data['component_id'] : null;

            $existing = ContentData::query()
                ->where('content_schema_id', $schema->id)
                ->get()
                ->first(function (ContentData $contentData) use ($page, $data): bool {
                    return data_get($contentData->data, 'page_slug') === $page->slug
                        && (string) data_get($contentData->data, 'component_id') === (string) ($data['component_id'] ?? null);
                });

            if ($existing) {
                $existing->children()->get()->each->forceDelete();
                $existing->forceDelete();
            }

            $contentData = ContentData::create([
                'content_schema_id' => $schema->id,
                'data' => $data,
            ]);

            $children = $snapshot['children'] ?? [];
            Log::info('tenant page save: created content data snapshot', [
                'tenant_id' => $tenantId,
                'page_id' => $page->id,
                'content_schema_id' => $schema->id,
                'content_data_id' => $contentData->id,
                'menu' => $menu,
                'child_count' => is_array($children) ? count($children) : 0,
            ]);
            $this->syncContentChildren($contentData, $children);
        }
    }

    private function ensureContentSchemaForSnapshot(int $tenantId, string $menu, array $snapshot): ?ContentSchema
    {
        $schema = ContentSchema::query()
            ->where('tenant_id', $tenantId)
            ->where('menu', $menu)
            ->first();

        if ($schema) {
            return $schema;
        }

        $data = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];
        $children = is_array($snapshot['children'] ?? null) ? $snapshot['children'] : [];

        $columnNames = array_values(array_filter(array_map(
            fn ($key) => is_string($key) && $key !== '' ? $key : null,
            array_keys($data)
        )));

        $childFieldNames = [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }

            foreach (($child['fields'] ?? []) as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $fieldKey = $field['field_key'] ?? null;
                if (is_string($fieldKey) && $fieldKey !== '') {
                    $childFieldNames[] = $fieldKey;
                }
            }
        }

        $schema = ContentSchema::create([
            'tenant_id' => $tenantId,
            'name' => ucfirst($menu) . ' Auto Schema',
            'menu' => $menu,
            'schema' => json_encode([
                'columns' => array_map(fn (string $name) => ['name' => $name], $columnNames),
                'child_fields' => array_values(array_unique($childFieldNames)),
            ]),
            'version' => 'v1',
            'status' => 'enabled',
        ]);

        Log::info('tenant page save: auto-created missing content schema', [
            'tenant_id' => $tenantId,
            'menu' => $menu,
            'content_schema_id' => $schema->id,
            'column_count' => count($columnNames),
            'child_field_count' => count(array_unique($childFieldNames)),
        ]);

        return $schema;
    }

    private function syncContentChildren(ContentData $contentData, array $children): void
    {
        Log::info('tenant page save: syncing content children', [
            'content_data_id' => $contentData->id,
            'child_count' => count($children),
        ]);

        ContentDataChild::query()
            ->where('content_data_id', $contentData->id)
            ->get()
            ->each->forceDelete();

        foreach (array_values($children) as $index => $childData) {
            if (!is_array($childData)) {
                continue;
            }

            $child = ContentDataChild::create([
                'content_data_id' => $contentData->id,
                'source_key' => $childData['source_key'] ?? null,
                'row_key' => $childData['row_key'] ?? null,
                'sort_order' => (int) ($childData['sort_order'] ?? $index),
                'payload' => $childData['payload'] ?? $childData['data'] ?? null,
            ]);

            $fields = $childData['fields'] ?? $this->deriveContentChildFields($childData);
            Log::info('tenant page save: created child row', [
                'content_data_id' => $contentData->id,
                'child_id' => $child->id,
                'source_key' => $child->source_key,
                'row_key' => $child->row_key,
                'sort_order' => $child->sort_order,
                'derived_field_count' => is_array($fields) ? count($fields) : 0,
                'has_payload' => is_array($child->payload),
            ]);

            $this->syncContentChildFields($child, is_array($fields) ? $fields : []);
        }
    }

    private function deriveContentChildFields(array $childData): array
    {
        $payload = $childData['payload'] ?? $childData['data'] ?? null;
        if (!is_array($payload)) {
            return [];
        }

        $fields = [];
        foreach ($payload as $fieldKey => $value) {
            $fields[] = $this->normalizeContentChildField((string) $fieldKey, $value, (string) $fieldKey);
        }

        return $fields;
    }

    private function syncContentChildFields(ContentDataChild $child, array $fields): void
    {
        Log::info('tenant page save: syncing child fields', [
            'content_data_child_id' => $child->id,
            'field_count' => count($fields),
        ]);

        ContentDataChildField::query()
            ->where('content_data_child_id', $child->id)
            ->get()
            ->each->forceDelete();

        $seenKeys = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                Log::warning('tenant page save: skipped non-array child field', [
                    'content_data_child_id' => $child->id,
                ]);
                continue;
            }

            $key = $field['field_key'] ?? null;
            if (!is_string($key) || $key === '') {
                Log::warning('tenant page save: skipped child field with empty key', [
                    'content_data_child_id' => $child->id,
                    'field' => $field,
                ]);
                continue;
            }

            if (isset($seenKeys[$key])) {
                $suffix = $seenKeys[$key] + 1;
                while (isset($seenKeys[$key . '_' . $suffix])) {
                    $suffix += 1;
                }
                $key = $key . '_' . $suffix;
            }
            $seenKeys[$field['field_key']] = ($seenKeys[$field['field_key']] ?? 0) + 1;
            $seenKeys[$key] = 1;

            ContentDataChildField::create([
                'content_data_child_id' => $child->id,
                'field_key' => $key,
                'source_column' => $field['source_column'] ?? $key,
                'field_type' => $field['field_type'] ?? 'string',
                'value_string' => $field['value_string'] ?? null,
                'value_text' => $field['value_text'] ?? null,
                'value_int' => $field['value_int'] ?? null,
                'value_bool' => $field['value_bool'] ?? null,
                'value_decimal' => $field['value_decimal'] ?? null,
                'value_asset_id' => $field['value_asset_id'] ?? null,
            ]);
        }
    }

    private function normalizeContentChildField(string $fieldKey, mixed $value, ?string $sourceColumn = null): array
    {
        if ($value === null) {
            return [
                'field_key' => $fieldKey,
                'source_column' => $sourceColumn ?? $fieldKey,
                'field_type' => 'string',
                'value_string' => null,
            ];
        }

        if (is_bool($value)) {
            return [
                'field_key' => $fieldKey,
                'source_column' => $sourceColumn ?? $fieldKey,
                'field_type' => 'bool',
                'value_bool' => $value,
            ];
        }

        if (is_int($value)) {
            return [
                'field_key' => $fieldKey,
                'source_column' => $sourceColumn ?? $fieldKey,
                'field_type' => 'int',
                'value_int' => $value,
            ];
        }

        if (is_float($value)) {
            return [
                'field_key' => $fieldKey,
                'source_column' => $sourceColumn ?? $fieldKey,
                'field_type' => 'decimal',
                'value_decimal' => $value,
            ];
        }

        if (is_string($value)) {
            return [
                'field_key' => $fieldKey,
                'source_column' => $sourceColumn ?? $fieldKey,
                'field_type' => 'string',
                'value_string' => $value,
            ];
        }

        return [
            'field_key' => $fieldKey,
            'source_column' => $sourceColumn ?? $fieldKey,
            'field_type' => 'text',
            'value_text' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function hydratePage(TenantPages $page, bool $withChildren): array
    {
        $page->loadMissing('tenant');

        $data = [
            'id' => $page->id,
            'tenant_id' => $page->tenant_id,
            'slug' => $page->slug,
            'title' => $page->title,
            'schema' => $this->normalizePageSchema($page->schema),
            'seo' => $page->seo,
            'status' => $page->status,
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'og_asset_id' => $page->og_asset_id,
            'published_at' => $page->published_at,
            'created_at' => $page->created_at,
            'updated_at' => $page->updated_at,
        ];

        if (!$withChildren) {
            return $data;
        }

        $components = TenantPageComponent::query()
            ->where('tenant_id', $page->tenant_id)
            ->where('page_id', $page->id)
            ->orderBy('sort_order')
            ->get()
            ->map(function (TenantPageComponent $component) {
                return [
                    'id' => $component->id,
                    'tenant_id' => $component->tenant_id,
                    'page_id' => $component->page_id,
                    'component_type' => $component->component_type,
                    'variant' => $component->variant,
                    'sort_order' => $component->sort_order,
                    'is_enabled' => $component->is_enabled,
                    'fields' => TenantComponentFeild::query()
                        ->where('tenant_id', $component->tenant_id)
                        ->where('component_id', $component->id)
                        ->get()
                        ->map(fn (TenantComponentFeild $field) => [
                            'id' => $field->id,
                            'field_key' => $field->field_key,
                            'field_type' => $field->field_type,
                            'value_string' => $field->value_string,
                            'value_text' => $field->value_text,
                            'value_int' => $field->value_int,
                            'value_bool' => $field->value_bool,
                            'value_decimal' => $field->value_decimal,
                            'value_asset_id' => $field->value_asset_id,
                        ])
                        ->values(),
                    'repeaters' => TenantComponentRepeaterItem::query()
                        ->where('tenant_id', $component->tenant_id)
                        ->where('component_id', $component->id)
                        ->orderBy('sort_order')
                        ->get()
                        ->map(function (TenantComponentRepeaterItem $item) {
                            return [
                                'id' => $item->id,
                                'repeater_key' => $item->repeater_key,
                                'sort_order' => $item->sort_order,
                                'fields' => TenantRepeaterField::query()
                                    ->where('tenant_id', $item->tenant_id)
                                    ->where('repeater_item_id', $item->id)
                                    ->get()
                                    ->map(fn ($field) => [
                                        'id' => $field->id,
                                        'field_key' => $field->field_key,
                                        'field_type' => $field->field_type,
                                        'value_string' => $field->value_string,
                                        'value_text' => $field->value_text,
                                        'value_int' => $field->value_int,
                                        'value_bool' => $field->value_bool,
                                        'value_decimal' => $field->value_decimal,
                                        'value_asset_id' => $field->value_asset_id,
                                    ])
                                    ->values(),
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();

        $data['components'] = $components;

        return $data;
    }

    private function normalizePageSchema(?array $schema): ?array
    {
        if (!$schema) {
            return $schema;
        }

        $builderSchema = $schema['schema'] ?? null;
        if (is_array($builderSchema)) {
            return $builderSchema + [
                'components' => $schema['components'] ?? [],
            ];
        }

        return $schema;
    }
}

