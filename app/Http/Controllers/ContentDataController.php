<?php

namespace App\Http\Controllers;

use App\Models\ContentData;
use App\Models\ContentSchema;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\Request;
  
class ContentDataController extends Controller
{
    public function index(Request $request) 
    {
        $user = $request->user();
        
        $request->validate([
            'tenantKey' => ['required', 'string'],
            'schema_id' => ['required', 'exists:content_schema,id']    
        ]);
        
        $tenant = Tenant::query()->where('key', $request->tenantKey)->first();
        if (!$tenant) {
            return response()->json([ 
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant'
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
                'error' => 'Unknown tenant user'
            ], 404);
        }

        $contentSchema = ContentSchema::where('id', $request->schema_id)->first();
        if ($contentSchema->tenant_id !== $tenant->id) {
            return response()->json([
                'ok' => false,
                'status' => 401,
                'error' => 'Unauthorized tenant'
            ], 401);
        }

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => []
        ], 200);
    }

    public function store(Request $request) 
    {

    }

    public function show(ContentData $data) 
    {

    }

    public function update(ContentData $data, Request $request)
    {

    }

    public function destroy(ContentData $data, Request $request)
    {

    }
}
