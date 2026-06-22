<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Global singleton (id=1) holding the configurable certificate branding.
 * Read LIVE by the certificate canvas; never affects the verifiable facts
 * (student, course, code, issued_at) or the verification flow.
 */
class CertificateSetting extends Model
{
    protected $fillable = [
        'logo_path',
        'business_name',
        'title',
        'award_line',
        'achievement_line',
        'signer_name',
        'signer_role',
        'design_variant',
    ];

    protected function casts(): array
    {
        return [
            'design_variant' => 'integer',
        ];
    }

    /**
     * Canonical defaults — single source of truth, equal to the original
     * hardcoded certificate copy. Reused by current() and the seeder.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'logo_path'        => null,
            'business_name'    => 'Ikena Makeup Academy',
            'title'            => 'Certificado de Profesionalización',
            'award_line'       => 'Se otorga el presente certificado a',
            'achievement_line' => 'por completar satisfactoriamente el curso',
            'signer_name'      => 'Ikena Makeup Academy',
            'signer_role'      => null,
            'design_variant'   => 1,
        ];
    }

    /**
     * Singleton accessor. Returns the persisted row, or a NON-persisted
     * defaults-filled instance when the row does not exist yet — so the canvas
     * never blanks and the read path never writes.
     */
    public static function current(): self
    {
        return static::firstOrNew(['id' => 1], static::defaults());
    }

    /**
     * Resolve a stored path to an absolute URL.
     * - null        → null
     * - http(s) URL → returned unchanged
     * - stored path → resolved via the public disk.
     * Mirrors Service/Product resolveImageUrl().
     */
    public function resolveImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
