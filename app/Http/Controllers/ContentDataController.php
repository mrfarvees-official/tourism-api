<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContentDataResource;
use App\Http\Resources\ContentSchemaResource;
use App\Models\ContentData;
use App\Models\ContentDataChild;
use App\Models\ContentDataChildField;
use App\Models\ContentSchema;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContentDataController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'tenantKey' => ['required', 'string'],
            'content_schema_id' => ['nullable', 'integer', 'exists:content_schema,id'],
            'schema_id' => ['nullable', 'integer', 'exists:content_schema,id'],
        ]);

        $schemaId = $request->integer('content_schema_id') ?: $request->integer('schema_id');
        if (!$schemaId) {
            return response()->json([
                'ok' => false,
                'status' => 422,
                'error' => 'content_schema_id is required',
            ], 422);
        }

        $tenant = Tenant::query()->where('key', $request->tenantKey)->first();
        if (!$tenant) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant',
            ], 404);
        }

        $tenantUser = TenantUser::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$tenantUser) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant user',
            ], 404);
        }

        $contentSchema = ContentSchema::query()->find($schemaId);
        if (!$contentSchema || $contentSchema->tenant_id !== $tenant->id) {
            return response()->json([
                'ok' => false,
                'status' => 401,
                'error' => 'Unauthorized tenant',
            ], 401);
        }

        $data = ContentData::query()
            ->with(['contentSchema', 'children.fields'])
            ->where('content_schema_id', $contentSchema->id)
            ->latest()
            ->get();

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => [
                'schema' => ContentSchemaResource::make($contentSchema),
                'data' => ContentDataResource::collection($data),
            ],
        ], 200);
    }

    public function sources(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'tenantKey' => ['required', 'string'],
            'content_schema_id' => ['nullable', 'integer', 'exists:content_schema,id'],
            'schema_id' => ['nullable', 'integer', 'exists:content_schema,id'],
        ]);

        $schemaId = $request->integer('content_schema_id') ?: $request->integer('schema_id');

        $tenant = Tenant::query()->where('key', $request->tenantKey)->first();
        if (!$tenant) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant',
            ], 404);
        }

        $tenantUser = TenantUser::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$tenantUser) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant user',
            ], 404);
        }

        $contentSchema = null;
        if ($schemaId) {
            $contentSchema = ContentSchema::query()->find($schemaId);
            if (!$contentSchema || $contentSchema->tenant_id !== $tenant->id) {
                return response()->json([
                    'ok' => false,
                    'status' => 401,
                    'error' => 'Unauthorized tenant',
                ], 401);
            }
        }

        $childrenIds = DB::table('content_data_children as child')
            ->join('content_data as data', 'data.id', '=', 'child.content_data_id')
            ->join('content_schema as schema', 'schema.id', '=', 'data.content_schema_id')
            ->where('schema.tenant_id', $tenant->id)
            ->when($contentSchema, fn ($query) => $query->where('schema.id', $contentSchema->id))
            ->whereNotNull('child.source_key')
            ->where('child.source_key', '!=', '')
            ->orderByDesc('child.id')
            ->pluck('child.id')
            ->all();

        $childrenQuery = ContentDataChild::query()
            ->with(['fields', 'contentData.contentSchema'])
            ->whereIn('id', $childrenIds);

        $sources = [];
        foreach ($childrenQuery->get() as $child) {
            $sourceKey = trim((string) $child->source_key);
            if ($sourceKey === '') {
                continue;
            }

            if (!isset($sources[$sourceKey])) {
                $sources[$sourceKey] = [
                    'source_key' => $sourceKey,
                    'label' => $sourceKey,
                    'content_data_count' => 0,
                    'row_count' => 0,
                    'field_count' => 0,
                    'fields' => [],
                    'content_data_ids' => [],
                    'schema_ids' => [],
                    'schema_blueprint' => $child->contentData?->contentSchema?->schema,
                    'sample_row_key' => $child->row_key,
                ];
            }

            $sources[$sourceKey]['row_count']++;
            $sources[$sourceKey]['content_data_ids'][$child->content_data_id] = true;
            $schema = $child->contentData?->contentSchema;
            if ($schema) {
                $sources[$sourceKey]['schema_ids'][$schema->id] = true;
                if ($sources[$sourceKey]['schema_blueprint'] === null) {
                    $sources[$sourceKey]['schema_blueprint'] = $schema->schema;
                }
            }

            foreach ($child->fields as $field) {
                $fieldKey = $field->source_column ?: $field->field_key;
                if (!$fieldKey) {
                    continue;
                }

                if (!isset($sources[$sourceKey]['fields'][$fieldKey])) {
                    $sources[$sourceKey]['fields'][$fieldKey] = [
                        'field_key' => $field->field_key,
                        'source_column' => $field->source_column,
                        'field_type' => $field->field_type,
                        'visible' => true,
                        'required' => false,
                        'sample_value' => $field->value_bool
                            ?? $field->value_int
                            ?? $field->value_decimal
                            ?? $field->value_string
                            ?? $field->value_text
                            ?? $field->value_asset_id,
                    ];
                }
            }
        }

        $sources = array_values(array_map(function (array $source) {
            $source['content_data_count'] = count($source['content_data_ids']);
            $source['schema_count'] = count($source['schema_ids']);
            $source['field_count'] = count($source['fields']);
            $source['fields'] = array_values($source['fields']);
            unset($source['content_data_ids'], $source['schema_ids']);

            return $source;
        }, $sources));

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => $sources,
        ], 200);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'tenantKey' => ['required', 'string'],
            'content_schema_id' => ['nullable', 'integer', 'exists:content_schema,id'],
            'schema_id' => ['nullable', 'integer', 'exists:content_schema,id'],
            'data' => ['required', 'array'],
            'children' => ['nullable', 'array'],
            'children.*.source_key' => ['nullable', 'string', 'max:120'],
            'children.*.row_key' => ['nullable', 'string', 'max:120'],
            'children.*.sort_order' => ['nullable', 'integer'],
            'children.*.payload' => ['nullable', 'array'],
            'children.*.data' => ['nullable', 'array'],
            'children.*.fields' => ['nullable', 'array'],
            'children.*.fields.*.field_key' => ['required', 'string', 'max:80'],
            'children.*.fields.*.source_column' => ['nullable', 'string', 'max:120'],
            'children.*.fields.*.field_type' => ['nullable', 'string', 'max:20'],
            'children.*.fields.*.value_string' => ['nullable', 'string', 'max:500'],
            'children.*.fields.*.value_text' => ['nullable', 'string'],
            'children.*.fields.*.value_int' => ['nullable', 'integer'],
            'children.*.fields.*.value_bool' => ['nullable', 'boolean'],
            'children.*.fields.*.value_decimal' => ['nullable', 'numeric'],
            'children.*.fields.*.value_asset_id' => ['nullable', 'integer', 'exists:tenant_assets,id'],
        ]);

        $schemaId = $data['content_schema_id'] ?? $data['schema_id'] ?? null;
        if (!$schemaId) {
            return response()->json([
                'ok' => false,
                'status' => 422,
                'error' => 'content_schema_id is required',
            ], 422);
        }

        $tenant = Tenant::query()->where('key', $data['tenantKey'])->first();
        if (!$tenant) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant',
            ], 404);
        }

        $tenantUser = TenantUser::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$tenantUser) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant user',
            ], 404);
        }

        $contentSchema = ContentSchema::query()->find($schemaId);
        if (!$contentSchema || $contentSchema->tenant_id !== $tenant->id) {
            return response()->json([
                'ok' => false,
                'status' => 401,
                'error' => 'Unauthorized tenant',
            ], 401);
        }

        $contentData = DB::transaction(function () use ($data) {
            $contentData = ContentData::create([
                'content_schema_id' => $data['content_schema_id'] ?? $data['schema_id'],
                'data' => $data['data'],
            ]);

            $this->syncChildren($contentData, $data['children'] ?? []);

            return $contentData->load('contentSchema', 'children.fields');
        });

        return response()->json([
            'ok' => true,
            'status' => 201,
            'message' => 'Content data has created',
            'data' => ContentDataResource::make($contentData),
        ], 201);
    }

    public function show(Request $request, ContentData $data)
    {
        $this->assertTenantAccess($request, $data);

        $data->loadMissing('children.fields');
        $data->loadMissing('contentSchema');

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => ContentDataResource::make($data),
        ], 200);
    }

    public function update(Request $request, ContentData $data)
    {
        $payload = $request->validate([
            'tenantKey' => ['required', 'string'],
            'data' => ['sometimes', 'array'],
            'children' => ['sometimes', 'array'],
            'children.*.source_key' => ['nullable', 'string', 'max:120'],
            'children.*.row_key' => ['nullable', 'string', 'max:120'],
            'children.*.sort_order' => ['nullable', 'integer'],
            'children.*.payload' => ['nullable', 'array'],
            'children.*.data' => ['nullable', 'array'],
            'children.*.fields' => ['nullable', 'array'],
            'children.*.fields.*.field_key' => ['required', 'string', 'max:80'],
            'children.*.fields.*.source_column' => ['nullable', 'string', 'max:120'],
            'children.*.fields.*.field_type' => ['nullable', 'string', 'max:20'],
            'children.*.fields.*.value_string' => ['nullable', 'string', 'max:500'],
            'children.*.fields.*.value_text' => ['nullable', 'string'],
            'children.*.fields.*.value_int' => ['nullable', 'integer'],
            'children.*.fields.*.value_bool' => ['nullable', 'boolean'],
            'children.*.fields.*.value_decimal' => ['nullable', 'numeric'],
            'children.*.fields.*.value_asset_id' => ['nullable', 'integer', 'exists:tenant_assets,id'],
        ]);

        $this->assertTenantAccess($request, $data);

        $contentData = DB::transaction(function () use ($payload, $data) {
            if (array_key_exists('data', $payload)) {
                $data->fill([
                    'data' => $payload['data'],
                ]);
            }

            $data->save();

            if (array_key_exists('children', $payload)) {
                $this->syncChildren($data, $payload['children'] ?? []);
            }

            return $data->load('contentSchema', 'children.fields');
        });

        return response()->json([
            'ok' => true,
            'status' => 200,
            'message' => 'Content data updated',
            'data' => ContentDataResource::make($contentData),
        ], 200);
    }

    public function destroy(Request $request, ContentData $data)
    {
        $this->assertTenantAccess($request, $data);

        $data->children()->get()->each->forceDelete();
        $data->forceDelete();

        return response()->json([
            'ok' => true,
            'status' => 200,
            'message' => 'Content data deleted',
        ], 200);
    }

    private function syncChildren(ContentData $contentData, array $children): void
    {
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

            $fields = $childData['fields'] ?? $this->deriveChildFields($childData);
            $this->syncChildFields($child, is_array($fields) ? $fields : []);
        }
    }

    private function deriveChildFields(array $childData): array
    {
        $payload = $childData['payload'] ?? $childData['data'] ?? null;
        if (!is_array($payload)) {
            return [];
        }

        $fields = [];
        foreach ($payload as $fieldKey => $value) {
            $fields[] = $this->normalizeFieldPayload((string) $fieldKey, $value, (string) $fieldKey);
        }

        return $fields;
    }

    private function syncChildFields(ContentDataChild $child, array $fields): void
    {
        ContentDataChildField::query()
            ->where('content_data_child_id', $child->id)
            ->get()
            ->each->forceDelete();

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $key = $field['field_key'] ?? null;
            if (!$key) {
                continue;
            }

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

    private function normalizeFieldPayload(string $fieldKey, mixed $value, ?string $sourceColumn = null): array
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

    private function assertTenantAccess(Request $request, ContentData $data): void
    {
        $user = $request->user();

        $request->validate([
            'tenantKey' => ['required', 'string'],
        ]);

        $tenant = Tenant::query()->where('key', $request->tenantKey)->first();
        if (!$tenant) {
            throw new HttpResponseException(response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant',
            ], 404));
        }

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

        $contentSchema = ContentSchema::query()->find($data->content_schema_id);
        if (!$contentSchema || $contentSchema->tenant_id !== $tenant->id) {
            throw new HttpResponseException(response()->json([
                'ok' => false,
                'status' => 401,
                'error' => 'Unauthorized tenant',
            ], 401));
        }
    }
}


