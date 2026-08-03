<?php

declare(strict_types=1);

namespace FastWebsite\Validation;

final class CurriculumValidator
{
    /** @param array<string,mixed> $input @return array{errors:list<string>,version:array<string,mixed>,course:array<string,mixed>,placement:array<string,mixed>} */
    public function validate(array $input): array
    {
        $errors = [];
        $code = strtoupper(preg_replace('/\s+/', '', trim((string) ($input['course_code'] ?? ''))) ?? '');
        $title = trim((string) ($input['title'] ?? ''));
        $credits = trim((string) ($input['credit_units'] ?? ''));
        $year = filter_var($input['study_year'] ?? null, FILTER_VALIDATE_INT);
        $semester = filter_var($input['semester'] ?? null, FILTER_VALIDATE_INT);
        $order = filter_var($input['display_order'] ?? 0, FILTER_VALIDATE_INT);
        $requirement = (string) ($input['requirement_type'] ?? 'core');
        $effectiveYear = filter_var($input['effective_year'] ?? null, FILTER_VALIDATE_INT);
        if (preg_match('/^[A-Z]{2,8}[0-9]{3,5}$/', $code) !== 1) {
            $errors[] = 'Enter a valid course code, for example EEE1101.';
        }
        if ($title === '' || mb_strlen($title) > 255) {
            $errors[] = 'Course title is required and must be 255 characters or fewer.';
        }
        if ($credits === '' || !is_numeric($credits) || (float) $credits <= 0 || (float) $credits > 30) {
            $errors[] = 'Credit units must be a number between 0 and 30.';
        }
        if ($year === false || $year < 1 || $year > 10) {
            $errors[] = 'Study year must be between 1 and 10.';
        }
        if ($semester === false || $semester < 1 || $semester > 3) {
            $errors[] = 'Semester must be 1, 2, or 3 (recess term).';
        }
        if (!in_array($requirement, ['core', 'elective', 'optional', 'audited'], true)) {
            $errors[] = 'Choose a valid requirement type.';
        }
        if ($order === false || $order < 0 || $order > 1000) {
            $errors[] = 'Display order must be between 0 and 1000.';
        }
        if ($effectiveYear === false || $effectiveYear < 2000 || $effectiveYear > 2200) {
            $errors[] = 'Enter a valid effective year.';
        }
        $versionName = trim((string) ($input['version_name'] ?? ''));
        if ($versionName === '' || mb_strlen($versionName) > 120) {
            $errors[] = 'Curriculum version name is required.';
        }
        $approvalDate = trim((string) ($input['approval_date'] ?? ''));
        if ($approvalDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $approvalDate) !== 1) {
            $errors[] = 'Approval date must use YYYY-MM-DD.';
        }
        $courseType = $requirement === 'elective' ? 'elective' : ($requirement === 'audited' ? 'audited' : 'core');
        return [
            'errors' => $errors,
            'version' => [
                'version_name' => $versionName,
                'effective_year' => $effectiveYear === false ? 0 : $effectiveYear,
                'expiry_year' => null,
                'approval_reference' => ($reference = trim((string) ($input['approval_reference'] ?? ''))) !== '' ? mb_substr($reference, 0, 150) : null,
                'approval_date' => $approvalDate !== '' ? $approvalDate : null,
            ],
            'course' => [
                'course_code' => $code,
                'title' => $title,
                'description' => ($description = trim((string) ($input['description'] ?? ''))) !== '' ? $description : null,
                'default_credit_units' => $credits !== '' && is_numeric($credits) ? number_format((float) $credits, 2, '.', '') : null,
                'course_type' => $courseType,
            ],
            'placement' => [
                'study_year' => $year === false ? 0 : $year,
                'semester' => $semester === false ? 0 : $semester,
                'requirement_type' => $requirement,
                'credit_units_override' => $credits !== '' && is_numeric($credits) ? number_format((float) $credits, 2, '.', '') : null,
                'display_order' => $order === false ? 0 : $order,
            ],
        ];
    }
}
