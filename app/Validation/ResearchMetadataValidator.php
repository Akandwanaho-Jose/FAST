<?php

declare(strict_types=1);

namespace FastWebsite\Validation;

use FastWebsite\Repositories\ResearchMetadataRepository;

final class ResearchMetadataValidator
{
    public const PARTNER_TYPES = [
        'university', 'government', 'industry', 'ngo', 'funder',
        'professional_body', 'community', 'international_agency', 'other',
    ];

    public const PARTNER_ROLES = [
        'lead', 'funder', 'implementer', 'technical_partner',
        'academic_partner', 'industry_partner', 'community_partner', 'other',
    ];

    public function __construct(private readonly ResearchMetadataRepository $metadata)
    {
    }

    /**
     * @param array<string,mixed> $input
     * @return array{data:array<string,mixed>,department_id:int,errors:list<string>}
     */
    public function theme(array $input, int $userId, ?int $exceptId = null): array
    {
        $errors = [];
        $name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 200);
        $slug = $this->slug((string) ($input['slug'] ?? ''), $name);
        $description = $this->nullable($input['description'] ?? null, 20000);
        $icon = $this->nullable($input['icon'] ?? null, 100);
        $orderValue = trim((string) ($input['display_order'] ?? '0'));
        $order = ctype_digit($orderValue) ? (int) $orderValue : -1;
        $departmentValue = trim((string) ($input['department_id'] ?? ''));
        $departmentId = ctype_digit($departmentValue) ? (int) $departmentValue : 0;
        $parentValue = trim((string) ($input['parent_theme_id'] ?? ''));
        $parentId = ctype_digit($parentValue) && (int) $parentValue > 0 ? (int) $parentValue : null;

        if (mb_strlen($name) < 2) {
            $errors[] = 'Theme name must contain at least two characters.';
        }
        if ($slug === '') {
            $errors[] = 'Theme URL could not be generated.';
        } elseif ($this->metadata->themeSlugExists($slug, $exceptId)) {
            $errors[] = 'That theme URL is already in use.';
        }
        if ($departmentId < 1 || !$this->metadata->departmentExists($departmentId)) {
            $errors[] = 'Choose a valid lead department.';
        } elseif (!$this->metadata->canUseDepartment($userId, $departmentId)) {
            $errors[] = 'The selected department is outside your editing scope.';
        }
        if ($order < 0 || $order > 65535) {
            $errors[] = 'Display order must be between 0 and 65535.';
        }
        if ($parentId !== null) {
            if ($parentId === $exceptId) {
                $errors[] = 'A theme cannot be its own parent.';
            } elseif ($this->metadata->findTheme($parentId, $userId) === null) {
                $errors[] = 'Choose a valid parent theme.';
            }
        }

        return [
            'data'=>[
                'parent_theme_id'=>$parentId,
                'name'=>$name,
                'slug'=>$slug,
                'description'=>$description,
                'icon'=>$icon,
                'display_order'=>max(0, $order),
            ],
            'department_id'=>$departmentId,
            'errors'=>$errors,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{data:array<string,mixed>,errors:list<string>}
     */
    public function partner(array $input, ?int $exceptId = null): array
    {
        $errors = [];
        $name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 255);
        $slug = $this->slug((string) ($input['slug'] ?? ''), $name);
        $type = trim((string) ($input['partner_type'] ?? 'other'));
        $country = $this->nullable($input['country'] ?? null, 100);
        $website = $this->nullable($input['website_url'] ?? null, 500);
        $description = $this->nullable($input['description'] ?? null, 20000);
        $logoValue = trim((string) ($input['logo_media_id'] ?? ''));
        $logoId = ctype_digit($logoValue) && (int) $logoValue > 0 ? (int) $logoValue : null;

        if (mb_strlen($name) < 2) {
            $errors[] = 'Partner name must contain at least two characters.';
        }
        if ($slug === '') {
            $errors[] = 'Partner URL could not be generated.';
        } elseif ($this->metadata->partnerSlugExists($slug, $exceptId)) {
            $errors[] = 'That partner URL is already in use.';
        }
        if (!in_array($type, self::PARTNER_TYPES, true)) {
            $errors[] = 'Choose a valid partner type.';
        }
        if ($website !== null && filter_var($website, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Partner website must be a complete URL.';
        }
        if ($logoValue !== '' && ($logoId === null || !$this->metadata->activeImageExists($logoId))) {
            $errors[] = 'Choose a valid active logo image.';
        }

        return [
            'data'=>[
                'name'=>$name,
                'slug'=>$slug,
                'partner_type'=>$type,
                'country'=>$country,
                'website_url'=>$website,
                'logo_media_id'=>$logoId,
                'description'=>$description,
            ],
            'errors'=>$errors,
        ];
    }

    private function slug(string $value, string $fallback): string
    {
        $value = trim($value) !== '' ? trim($value) : $fallback;
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = is_string($ascii) ? $ascii : $value;
        $value = strtolower($value);
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $value), '-');
    }

    private function nullable(mixed $value, int $length): ?string
    {
        $value = mb_substr(trim((string) $value), 0, $length);
        return $value === '' ? null : $value;
    }
}
