<?php

declare(strict_types=1);

namespace FastWebsite\Validation;

use FastWebsite\Repositories\StaffRepository;

final class StaffValidator
{
    private const CATEGORIES = [
        'academic', 'administrative', 'technical', 'support',
        'research', 'visiting', 'emeritus', 'other',
    ];
    private const LINK_TYPES = [
        'orcid', 'google_scholar', 'researchgate', 'linkedin',
        'institutional_repository', 'personal_website', 'other',
    ];

    public function __construct(private readonly StaffRepository $staff)
    {
    }

    /** @param array<string,mixed> $input
     * @return array{data:array<string,mixed>,assignment:array<string,mixed>,relations:array<string,mixed>,errors:list<string>}
     */
    public function validate(array $input, ?int $existingId = null): array
    {
        $errors = [];
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        $slug = $slug !== '' ? $slug : $this->slugify($firstName . ' ' . $lastName);
        $facultyId = $this->id($input['faculty_id'] ?? null);
        $departmentId = $this->id($input['department_id'] ?? null);
        $positionId = $this->id($input['position_id'] ?? null);
        $locationId = $this->id($input['office_location_id'] ?? null);
        $mediaId = $this->id($input['profile_media_id'] ?? null);
        $category = (string) ($input['staff_category'] ?? '');

        foreach (['First name' => $firstName, 'Last name' => $lastName] as $label => $value) {
            if ($value === '') {
                $errors[] = $label . ' is required.';
            } elseif (mb_strlen($value) > 100) {
                $errors[] = $label . ' must not exceed 100 characters.';
            }
        }

        if ($facultyId === null || !$this->staff->relationExists('faculties', $facultyId)) {
            $errors[] = 'Select a valid faculty.';
        }
        if ($departmentId !== null && !$this->staff->relationExists('departments', $departmentId)) {
            $errors[] = 'Select a valid department.';
        } elseif (trim((string) ($input['department_id'] ?? '')) !== ''
            && $departmentId === null
        ) {
            $errors[] = 'Select a valid department.';
        }
        if ($positionId !== null && !$this->staff->relationExists('positions', $positionId)) {
            $errors[] = 'Select a valid position.';
        } elseif (trim((string) ($input['position_id'] ?? '')) !== ''
            && $positionId === null
        ) {
            $errors[] = 'Select a valid position.';
        }
        $position = $positionId !== null ? $this->staff->position($positionId) : null;
        if (is_array($position)
            && $position['position_type'] === 'department_leadership'
            && $departmentId === null
        ) {
            $errors[] = 'Department leadership positions require a primary department.';
        }
        if ($facultyId !== null && $departmentId !== null
            && !$this->staff->departmentBelongsToFaculty($departmentId, $facultyId)
        ) {
            $errors[] = 'The selected department does not belong to the staff faculty.';
        }
        if ($locationId !== null && !$this->staff->relationExists('locations', $locationId)) {
            $errors[] = 'Select a valid office location.';
        } elseif (trim((string) ($input['office_location_id'] ?? '')) !== ''
            && $locationId === null
        ) {
            $errors[] = 'Select a valid office location.';
        }
        if ($mediaId !== null && !$this->staff->relationExists('media', $mediaId)) {
            $errors[] = 'Select a valid profile image.';
        } elseif (trim((string) ($input['profile_media_id'] ?? '')) !== ''
            && $mediaId === null
        ) {
            $errors[] = 'Select a valid profile image.';
        }
        if (!in_array($category, self::CATEGORIES, true)) {
            $errors[] = 'Select a valid staff category.';
        }
        if ($slug === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1
            || mb_strlen($slug) > 240
        ) {
            $errors[] = 'Profile URL must use lowercase letters, numbers, and hyphens.';
        } elseif ($this->staff->slugExists($slug, $existingId)) {
            $errors[] = 'That staff profile URL is already in use.';
        }

        $email = $this->nullable($input['institutional_email'] ?? null);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Enter a valid institutional email.';
        } elseif ($email !== null && $this->staff->emailExists($email, $existingId)) {
            $errors[] = 'That institutional email is already in use.';
        }
        $alternativeEmail = $this->nullable($input['alternative_email'] ?? null);
        if ($alternativeEmail !== null
            && filter_var($alternativeEmail, FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors[] = 'Enter a valid alternative email.';
        }
        $number = $this->nullable($input['staff_number'] ?? null);
        if ($number !== null && $this->staff->staffNumberExists($number, $existingId)) {
            $errors[] = 'That staff number is already in use.';
        }
        $order = filter_var(
            $input['display_order'] ?? '0',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 65535]]
        );
        if ($order === false) {
            $errors[] = 'Display order must be from 0 to 65535.';
            $order = 0;
        }
        $relations = $this->relations($input, $errors);

        return [
            'data' => [
                'faculty_id' => $facultyId,
                'staff_number' => $number,
                'honorific_title' => $this->limited($input, 'honorific_title', 30, $errors),
                'first_name' => $firstName,
                'middle_name' => $this->limited($input, 'middle_name', 100, $errors),
                'last_name' => $lastName,
                'post_nominals' => $this->limited($input, 'post_nominals', 120, $errors),
                'slug' => $slug,
                'staff_category' => $category,
                'short_biography' => $this->limited($input, 'short_biography', 65535, $errors),
                'biography' => $this->nullable($input['biography'] ?? null),
                'research_summary' => $this->nullable($input['research_summary'] ?? null),
                'teaching_summary' => $this->nullable($input['teaching_summary'] ?? null),
                'supervision_interests' => $this->nullable($input['supervision_interests'] ?? null),
                'institutional_email' => $email,
                'alternative_email' => $alternativeEmail,
                'public_phone' => $this->limited($input, 'public_phone', 50, $errors),
                'profile_media_id' => $mediaId,
                'office_location_id' => $locationId,
                'office_room' => $this->limited($input, 'office_room', 120, $errors),
                'consultation_hours' => $this->limited($input, 'consultation_hours', 255, $errors),
                'supervision_available' => ($input['supervision_available'] ?? '') === '1' ? 1 : 0,
                'display_order' => (int) $order,
            ],
            'assignment' => [
                'department_id' => $departmentId,
                'position_id' => $positionId,
                'title_override' => $this->limited($input, 'title_override', 150, $errors),
            ],
            'relations' => $relations,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $input @param list<string> $errors
     * @return array{qualifications:list<array<string,mixed>>,links:list<array<string,mixed>>,expertise_ids:list<int>}
     */
    private function relations(array $input, array &$errors): array
    {
        $qualifications = [];
        foreach (is_array($input['qualifications'] ?? null) ? $input['qualifications'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $qualification = trim((string) ($row['qualification'] ?? ''));
            $field = trim((string) ($row['field_of_study'] ?? ''));
            $institution = trim((string) ($row['institution'] ?? ''));
            $country = trim((string) ($row['country'] ?? ''));
            $yearValue = trim((string) ($row['completion_year'] ?? ''));
            if ($qualification === '' && $field === '' && $institution === ''
                && $country === '' && $yearValue === ''
            ) {
                continue;
            }
            if ($qualification === '') {
                $errors[] = 'Each qualification entry needs a qualification or award.';
                continue;
            }
            if (mb_strlen($qualification) > 200 || mb_strlen($field) > 200
                || mb_strlen($institution) > 255 || mb_strlen($country) > 100
            ) {
                $errors[] = 'A qualification entry contains text that is too long.';
                continue;
            }
            $year = null;
            if ($yearValue !== '') {
                $validYear = filter_var($yearValue, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1900, 'max_range' => (int) date('Y') + 10],
                ]);
                if ($validYear === false) {
                    $errors[] = 'Qualification years must be valid four-digit years.';
                    continue;
                }
                $year = (int) $validYear;
            }
            $qualifications[] = [
                'qualification' => $qualification,
                'field_of_study' => $field !== '' ? $field : null,
                'institution' => $institution !== '' ? $institution : null,
                'country' => $country !== '' ? $country : null,
                'completion_year' => $year,
            ];
        }

        $links = [];
        foreach (is_array($input['links'] ?? null) ? $input['links'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = trim((string) ($row['link_type'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            if ($label === '' && $url === '') {
                continue;
            }
            if (!in_array($type, self::LINK_TYPES, true)) {
                $errors[] = 'Select a valid professional link type.';
                continue;
            }
            if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false
                || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            ) {
                $errors[] = 'Professional links must use a complete http or https address.';
                continue;
            }
            if (mb_strlen($url) > 500 || mb_strlen($label) > 120) {
                $errors[] = 'A professional link is too long.';
                continue;
            }
            $links[] = [
                'link_type' => $type,
                'label' => $label !== '' ? $label : null,
                'url' => $url,
            ];
        }

        $expertiseIds = [];
        foreach (is_array($input['expertise_ids'] ?? null) ? $input['expertise_ids'] : [] as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || !$this->staff->relationExists('expertise_areas', (int) $id)) {
                $errors[] = 'Select valid areas of expertise.';
                continue;
            }
            $expertiseIds[] = (int) $id;
        }

        return [
            'qualifications' => $qualifications,
            'links' => $links,
            'expertise_ids' => array_values(array_unique($expertiseIds)),
        ];
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /** @param array<string,mixed> $input @param list<string> $errors */
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
