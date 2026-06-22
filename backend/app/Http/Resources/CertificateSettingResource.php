<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'business_name'    => $this->business_name,
            'title'            => $this->title,
            'award_line'       => $this->award_line,
            'achievement_line' => $this->achievement_line,
            'signer_name'      => $this->signer_name,
            'signer_role'      => $this->signer_role,
            'design_variant'   => (int) $this->design_variant,
            // Absolute, same-origin URL (or null) so the admin preview path is
            // identical to the print path — avoids cross-origin print failures.
            'logo_url'         => $this->resolveImageUrl($this->logo_path),
        ];
    }
}
