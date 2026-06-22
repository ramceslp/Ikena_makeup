<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCertificateSettingRequest;
use App\Http\Resources\CertificateSettingResource;
use App\Models\CertificateSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CertificateSettingController extends Controller
{
    /**
     * GET /api/admin/certificate-settings
     * Initial load for the admin form (same payload as the public endpoint).
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => new CertificateSettingResource(CertificateSetting::current()),
        ]);
    }

    /**
     * POST /api/admin/certificate-settings  (multipart — PHP can't parse multipart PUT/PATCH)
     * Upsert the singleton (id=1); handle logo upload/replace like the post cover.
     */
    public function update(UpdateCertificateSettingRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Logo is handled separately (file, not a column value).
        unset($data['logo']);

        $setting = CertificateSetting::updateOrCreate(['id' => 1], $data);

        if ($request->hasFile('logo')) {
            // Replace: remove the previous file first (no orphans).
            if ($setting->logo_path) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $setting->logo_path = $request->file('logo')->store('certificate', 'public');
            $setting->save();
        }

        return response()->json([
            'data' => new CertificateSettingResource($setting),
        ]);
    }
}
