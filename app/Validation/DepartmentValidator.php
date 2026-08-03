<?php

declare(strict_types=1);

namespace FastWebsite\Validation;

use FastWebsite\Repositories\DepartmentRepository;

final class DepartmentValidator
{
    public function __construct(
        private readonly DepartmentRepository $departments
    ) {
    }

    /**
     * @param array<string, string|null> $input
     * @return array{
     *   data: array<string, mixed>,
     *   errors: list<string>
     * }
     */
    public function validate(array $input, ?int $existingId = null): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        $slug = $slug !== '' ? $slug : $this->slugify($name);
        $facultyId = $this->positiveInteger($input['faculty_id'] ?? null);
        $locationValue = trim((string) ($input['location_id'] ?? ''));
        $mediaValue = trim((string) ($input['hero_media_id'] ?? ''));
        $locationId = $this->positiveInteger($locationValue);
        $heroMediaId = $this->positiveInteger($mediaValue);
        $displayOrder = filter_var(
            $input['display_order'] ?? '0',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 65535]]
        );
        $email = $this->nullable($input['email'] ?? null);
        $errors = [];

        if ($name === '') {
            $errors[] = 'Department name is required.';
        } elseif (mb_strlen($name) > 200) {
            $errors[] = 'Department name must not exceed 200 characters.';
        }

        if ($facultyId === null || !$this->departments->facultyExists($facultyId)) {
            $errors[] = 'Select a valid faculty.';
        }

        if ($slug === ''
            || mb_strlen($slug) > 220
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1
        ) {
            $errors[] = 'Slug must contain lowercase letters, numbers, and single hyphens only.';
        } elseif ($this->departments->slugExists($slug, $existingId)) {
            $errors[] = 'That department slug is already in use.';
        }

        if ($facultyId !== null
            && $name !== ''
            && $this->departments->facultyNameExists(
                $facultyId,
                $name,
                $existingId
            )
        ) {
            $errors[] = 'A department with that name already exists in the faculty.';
        }

        $shortName = $this->nullable($input['short_name'] ?? null);

        if ($shortName !== null && mb_strlen($shortName) > 30) {
            $errors[] = 'Short name must not exceed 30 characters.';
        }

        if ($email !== null
            && (mb_strlen($email) > 190
                || filter_var($email, FILTER_VALIDATE_EMAIL) === false)
        ) {
            $errors[] = 'Enter a valid contact email address.';
        }

        $phone = $this->nullable($input['phone'] ?? null);

        if ($phone !== null && mb_strlen($phone) > 50) {
            $errors[] = 'Phone number must not exceed 50 characters.';
        }

        if (($locationValue !== '' && $locationId === null)
            || ($locationId !== null
                && !$this->departments->locationExists($locationId))
        ) {
            $errors[] = 'Select a valid location.';
        }

        if (($mediaValue !== '' && $heroMediaId === null)
            || ($heroMediaId !== null
                && !$this->departments->activeImageExists($heroMediaId))
        ) {
            $errors[] = 'Select an active image from the media library.';
        }

        if ($displayOrder === false) {
            $errors[] = 'Display order must be a whole number from 0 to 65535.';
            $displayOrder = 0;
        }

        $longFields = [
            'overview' => 100000,
            'history' => 100000,
            'vision' => 20000,
            'mission' => 20000,
            'strategic_direction' => 100000,
            'hod_message' => 100000,
        ];
        $text = [];

        foreach ($longFields as $field => $maximum) {
            $value = $this->nullable($input[$field] ?? null);

            if ($value !== null && mb_strlen($value) > $maximum) {
                $errors[] = sprintf(
                    '%s is too long.',
                    ucfirst(str_replace('_', ' ', $field))
                );
            }

            $text[$field] = $value;
        }

        return [
            'data' => [
                'faculty_id' => $facultyId,
                'name' => $name,
                'short_name' => $shortName,
                'slug' => $slug,
                ...$text,
                'email' => $email,
                'phone' => $phone,
                'location_id' => $locationId,
                'hero_media_id' => $heroMediaId,
                'display_order' => (int) $displayOrder,
            ],
            'errors' => $errors,
        ];
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function positiveInteger(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $validated = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        return $validated === false ? null : (int) $validated;
    }

    private function slugify(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $ascii = $ascii === false ? $value : $ascii;
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii));

        return trim($slug, '-');
    }
}
