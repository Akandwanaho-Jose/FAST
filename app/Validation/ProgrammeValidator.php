<?php

declare(strict_types=1);

namespace FastWebsite\Validation;

use FastWebsite\Repositories\ProgrammeRepository;

final class ProgrammeValidator
{
    private const STUDY_MODES = ['full_time', 'part_time', 'both', 'other'];
    private const DELIVERY_MODES = ['face_to_face', 'online', 'blended', 'other'];

    public function __construct(private readonly ProgrammeRepository $programmes)
    {
    }

    /** @param array<string,string|null> $input @return array{data:array<string,mixed>,department_id:?int,errors:list<string>} */
    public function validate(array $input, ?int $existingId = null): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        $slug = $slug !== '' ? $slug : $this->slugify($name);
        $levelId = $this->id($input['programme_level_id'] ?? null);
        $departmentId = $this->id($input['department_id'] ?? null);
        $mediaId = $this->id($input['hero_media_id'] ?? null);
        $code = $this->nullable($input['programme_code'] ?? null);

        if ($name === '') {
            $errors[] = 'Programme name is required.';
        } elseif (mb_strlen($name) > 255) {
            $errors[] = 'Programme name must not exceed 255 characters.';
        }
        if ($levelId === null || !$this->programmes->relationExists('programme_levels', $levelId)) {
            $errors[] = 'Select a valid programme level.';
        }
        if ($departmentId === null || !$this->programmes->relationExists('departments', $departmentId)) {
            $errors[] = 'Select a valid lead department.';
        }
        if ($mediaId !== null && !$this->programmes->relationExists('media', $mediaId)) {
            $errors[] = 'Select a valid hero image.';
        }
        if ($slug === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1 || mb_strlen($slug) > 280) {
            $errors[] = 'Programme URL must use lowercase letters, numbers, and hyphens.';
        } elseif ($this->programmes->slugExists($slug, $existingId)) {
            $errors[] = 'That programme URL is already in use.';
        }
        if ($code !== null && mb_strlen($code) > 50) {
            $errors[] = 'Programme code must not exceed 50 characters.';
        } elseif ($code !== null && $this->programmes->codeExists($code, $existingId)) {
            $errors[] = 'That programme code is already in use.';
        }
        $studyMode = (string) ($input['study_mode'] ?? 'full_time');
        if (!in_array($studyMode, self::STUDY_MODES, true)) {
            $errors[] = 'Select a valid study mode.';
        }
        $deliveryMode = (string) ($input['delivery_mode'] ?? 'face_to_face');
        if (!in_array($deliveryMode, self::DELIVERY_MODES, true)) {
            $errors[] = 'Select a valid delivery mode.';
        }
        $durationYears = $this->nullable($input['duration_years'] ?? null);
        if ($durationYears !== null && (!is_numeric($durationYears) || (float) $durationYears <= 0 || (float) $durationYears > 99.9)) {
            $errors[] = 'Duration in years must be between 0.1 and 99.9.';
            $durationYears = null;
        }
        $applicationUrl = $this->nullable($input['application_url'] ?? null);
        if ($applicationUrl !== null && filter_var($applicationUrl, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Enter a valid application URL including https://.';
        }
        $order = filter_var($input['display_order'] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 65535]]);
        if ($order === false) {
            $errors[] = 'Display order must be from 0 to 65535.';
            $order = 0;
        }

        return [
            'data' => [
                'programme_level_id' => $levelId,
                'programme_code' => $code,
                'name' => $name,
                'award_title' => $this->limited($input, 'award_title', 255, $errors),
                'slug' => $slug,
                'overview' => $this->nullable($input['overview'] ?? null),
                'why_study' => $this->nullable($input['why_study'] ?? null),
                'objectives' => $this->nullable($input['objectives'] ?? null),
                'learning_outcomes' => $this->nullable($input['learning_outcomes'] ?? null),
                'entry_requirements' => $this->nullable($input['entry_requirements'] ?? null),
                'career_opportunities' => $this->nullable($input['career_opportunities'] ?? null),
                'practical_training' => $this->nullable($input['practical_training'] ?? null),
                'duration_years' => $durationYears === null ? null : (float) $durationYears,
                'duration_text' => $this->limited($input, 'duration_text', 100, $errors),
                'study_mode' => $studyMode,
                'delivery_mode' => $deliveryMode,
                'accreditation' => $this->nullable($input['accreditation'] ?? null),
                'application_url' => $applicationUrl,
                'hero_media_id' => $mediaId,
                'display_order' => (int) $order,
            ],
            'department_id' => $departmentId,
            'errors' => $errors,
        ];
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /** @param array<string,string|null> $input @param list<string> $errors */
    private function limited(array $input, string $field, int $max, array &$errors): ?string
    {
        $value = $this->nullable($input[$field] ?? null);
        if ($value !== null && mb_strlen($value) > $max) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is too long.';
        }
        return $value;
    }

    private function id(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : (int) $id;
    }

    private function slugify(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii ?: $value));
        return trim($slug, '-');
    }
}
