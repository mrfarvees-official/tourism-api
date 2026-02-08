<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyBootstrapResource;
use App\Models\Tenant;
use App\Models\TenantTheme;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CompanyBootstrapController extends Controller implements HasMiddleware
{
    public static function middleware() 
    {
        return [
            new Middleware('auth:sanctum')
        ];
    }

    public function index(Request $request) 
    {
        $tenantKey = $request->get('tenantKey');

        $tenant = Tenant::query()
            ->where('key', $tenantKey)
            ->with(['theme'])
            ->first();
        
        if (!$tenant) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Tenant information not found.'
            ], 404);
        }

        return CompanyBootstrapResource::make($tenant);
    }

    public function getTheme(Request $request)
    {
        $tenantKey = $request->get("tenantKey");
        $tenant = Tenant::where('key', $tenantKey)->first();
        $tokens = TenantTheme::where('tenant_id', $tenant->id)->value('tokens');

        return response()->json([
            'ok' => true,
            'status' => 200,
            'tokens' => $tokens
        ], 200);
    }

    public function updateTheme(Request $request) 
    {
        $validated = $request->validate([
            'tokens' => 'required|json',
            'tenantKey' => 'required|string'
        ]);

        $tenant = Tenant::where('key', $validated['tenantKey'])->first();
        TenantTheme::where('tenant_id', $tenant->id)->update([
            'tokens' => $validated['tokens']
        ]);

        return response()->json([
            'ok' => true,
            'status' => 200,
            'message' => 'Theme updated.'
        ], 200);
    }
}
