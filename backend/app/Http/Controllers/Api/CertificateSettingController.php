<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CertificateSettingResource;
use App\Models\CertificateSetting;
use Illuminate\Http\JsonResponse;

class CertificateSettingController extends Controller
{
    /**
     * GET /api/certificate-settings
     *
     * Public (no auth) — returns the global certificate branding for the canvas.
     * Falls back to canonical defaults when the singleton row does not exist yet,
     * so the certificate never renders blank.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => new CertificateSettingResource(CertificateSetting::current()),
        ]);
    }
}
