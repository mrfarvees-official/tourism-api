<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\Resource;
use App\Models\Tenant;
use Illuminate\Http\Request;

class OrganizationProfileController extends Controller 
{
    public function index(Request $request) 
    {
        $tenantKey = $request->input('tenantKey');
    
        $user = $request->user();
        $resource = Resource::where('resource', 'organization_profile')->first();
        $action = Action::where('action', 'view')->first();
        $can = $user->canAccess($resource, $action, null, ['tenant_key' => $tenantKey ?? '']);

        if (!$can) {
            return response()->json([
                'ok' => false,
                'status' => 401,
                'error' => 'Unauthorized access'
            ]);
        }

        $tenant = Tenant::query()
            ->where('key', $tenantKey)
            ->with(['assets', 'brand', 'domain', 'theme', 'owner'])
            ->get();

        return response()->json([
            'ok' => true,
            'status' => 200,
            'organization' => $tenant
        ]);
    }
}