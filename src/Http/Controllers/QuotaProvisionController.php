<?php

namespace Keloola\Quota\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Keloola\Quota\Http\Requests\ProvisionQuotaRequest;
use Keloola\Quota\Services\QuotaProvisioner;

class QuotaProvisionController extends Controller
{
    public function __construct(protected QuotaProvisioner $provisioner)
    {
    }

    /**
     * Called by the SSO service on app install AND whenever a plan's
     * quota definition changes. Idempotent.
     */
    public function store(ProvisionQuotaRequest $request): JsonResponse
    {
        $result = $this->provisioner->provision($request->validated());

        return response()->json([
            'status'  => 'ok',
            'message' => 'Quota provisioned.',
            'synced'  => $result,
        ]);
    }

    /**
     * Called when an org uninstalls the app or a plan is removed upstream.
     */
    public function destroy(string $appPlanId): JsonResponse
    {
        $deleted = $this->provisioner->deprovisionPlan($appPlanId);

        return response()->json([
            'status'  => 'ok',
            'deleted' => $deleted,
        ]);
    }
}
