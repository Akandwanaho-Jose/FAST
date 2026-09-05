<?php

declare(strict_types=1);

/**
 * FAST Research Agenda 2026-2030 content build.
 *
 * Source data (read directly at runtime, not re-typed here): the scratchpad
 * plain-text dumps of 5 department "Pilot Projects" Excel sheets, the
 * faculty-wide master planning Excel sheet, and 5 distinct department
 * "Research Playbook" Word documents (a 6th, "CVE copy", is a strictly
 * older draft and is ignored). Lab-level metadata (name, vision, status,
 * team lead, other members) below was hand-reconciled by reading all
 * source files in full; project titles/descriptions and the ~800
 * milestone rows are parsed programmatically from the source dumps so
 * that no long-form text is retyped by hand (and therefore cannot drift
 * from the source).
 *
 * Idempotent: every insert is guarded by a natural-key existence check
 * (slug for research_units/projects/research_themes, staff slug for new
 * staff, (project_id, sequence_number) for milestones), so re-running
 * this script is safe and creates zero new rows on a second run.
 */

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/** @var \FastWebsite\Core\Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$pdo = $database->connection();

$SCRATCH = 'C:/Users/joetech/AppData/Local/Temp/claude/C--xampp-htdocs-FAST/c460ef50-ff13-4e05-b8ba-4b7879e2ef0a/scratchpad/';

$report = [
    'staff_created' => [],
    'staff_matched_existing' => [],
    'research_units_published' => 0,
    'research_units_draft' => 0,
    'projects_created' => 0,
    'projects_skipped_existing' => 0,
    'milestones_created' => 0,
    'themes_created' => 0,
    'project_themes_created' => 0,
    'project_sdgs_created' => 0,
    'unmatched_dept_projects' => [],
];

// =============================================================================
// Helpers
// =============================================================================

function slugify(string $value): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii ?: $value));
    return trim($slug, '-');
}

function uniqueSlug(PDO $pdo, string $table, string $base, ?int $maxLen = null): string
{
    $base = $maxLen ? substr($base, 0, $maxLen) : $base;
    $slug = $base;
    $i = 2;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE slug = :slug");
    while (true) {
        $stmt->execute(['slug' => $slug]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $suffix = '-' . $i;
        $slug = ($maxLen ? substr($base, 0, $maxLen - strlen($suffix)) : $base) . $suffix;
        $i++;
    }
}

/** Split a §§-delimited row (from the xlsx dump) into trimmed columns. */
function splitCells(string $line): array
{
    return array_map('trim', explode('§§', $line));
}

function parseMemberList(string $value): array
{
    if ($value === '') {
        return [];
    }
    $parts = preg_split('/\s*,\s*/', $value);
    return array_values(array_filter(array_map('trim', $parts), fn($v) => $v !== ''));
}

/**
 * Parses a department "Pilot Projects" xlsx dump (dept_XXX.txt) into an
 * ordered list of labs: each ['name'=>, 'lead'=>, 'members'=>[], 'projects'=>
 * [['title'=>, 'description'=>, 'lead'=>, 'members'=>[]], ...]]].
 * Handles per-project lead overrides (a row with an empty lab-name column
 * but a filled team-lead column applies only to that one project).
 */
function parseDeptFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $lines = preg_split('/\r\n|\r|\n/', file_get_contents($path));
    $labs = [];
    $currentIdx = -1;
    $currentLead = null;
    $currentMembers = [];
    foreach (array_slice($lines, 4) as $line) {
        if (trim($line) === '') {
            continue;
        }
        $c = splitCells($line);
        $labName = $c[1] ?? '';
        $lead = $c[2] ?? '';
        $members = $c[3] ?? '';
        $projectName = $c[4] ?? '';
        $description = $c[5] ?? '';
        $description = trim(str_replace(' | ', "\n\n", $description));
        if ($description === '') {
            $description = null;
        }

        if ($labName !== '') {
            $labs[] = ['name' => $labName, 'lead' => $lead ?: null, 'members' => parseMemberList($members), 'projects' => []];
            $currentIdx = count($labs) - 1;
            $currentLead = $lead ?: null;
            $currentMembers = parseMemberList($members);
            if ($projectName !== '') {
                $labs[$currentIdx]['projects'][] = [
                    'title' => $projectName, 'description' => $description,
                    'lead' => $currentLead, 'members' => $currentMembers,
                ];
            }
            continue;
        }

        if ($currentIdx < 0) {
            continue;
        }

        if ($lead !== '') {
            // Per-project lead override; does not change the lab default.
            if ($projectName !== '') {
                $labs[$currentIdx]['projects'][] = [
                    'title' => $projectName, 'description' => $description,
                    'lead' => $lead, 'members' => parseMemberList($members),
                ];
            }
            continue;
        }

        if ($projectName !== '') {
            $labs[$currentIdx]['projects'][] = [
                'title' => $projectName, 'description' => $description,
                'lead' => $currentLead, 'members' => $currentMembers,
            ];
        }
    }
    return $labs;
}

/**
 * Parses a department Research Playbook docx dump (pb_XXX.txt) into a flat,
 * ordered list of pilot projects: [['lab'=>headingText,'title'=>,
 * 'milestones'=>[['seq','title','expected_output','timeline','lead','level_set'
 * (array of bachelors/masters/phd/staff),'students']]]].
 *
 * $knownHeadings is the authoritative whitelist of exact lab-heading strings
 * for this department (verified by direct inspection, since a playbook's own
 * "THE RESEARCH LABS IN..." summary list has been observed to omit a lab
 * that nonetheless has a full section further down the document).
 */
function parsePlaybook(string $path, array $knownHeadings): array
{
    if (!is_file($path)) {
        return [];
    }
    $lines = preg_split('/\r\n|\r|\n/', file_get_contents($path));
    $headingSet = array_flip($knownHeadings);
    $projects = [];
    $currentLab = null;
    $pendingTitle = null;
    $i = 0;
    $n = count($lines);
    while ($i < $n) {
        $trimmed = trim($lines[$i]);
        if ($trimmed === '') {
            $i++;
            continue;
        }
        if (isset($headingSet[$trimmed])) {
            $currentLab = $trimmed;
            $pendingTitle = null;
            $i++;
            continue;
        }
        if ($trimmed === '=== TABLE START ===') {
            $title = $pendingTitle ?? '';
            $title = preg_replace('/^(Flagship\s+)?Pilot Project:\s*/i', '', $title);
            $title = preg_replace('/^PROJECT\s*\d+\s*:\s*/i', '', $title);
            $title = trim($title, " .\t");
            $i++;
            // Header row
            $headerCells = $i < $n ? array_map('trim', explode('|', $lines[$i])) : [];
            $colMap = [];
            foreach ($headerCells as $idx => $h) {
                $hl = strtolower($h);
                if (str_contains($hl, 'mini project') || str_contains($hl, 'activity')) {
                    $colMap['title'] = $idx;
                } elseif (str_contains($hl, 'output') || str_contains($hl, 'prototype')) {
                    $colMap['output'] = $idx;
                } elseif (str_contains($hl, 'timeline')) {
                    $colMap['timeline'] = $idx;
                } elseif (str_contains($hl, 'member') || str_contains($hl, 'lead')) {
                    $colMap['lead'] = $idx;
                } elseif (str_contains($hl, 'level')) {
                    $colMap['level'] = $idx;
                } elseif (str_contains($hl, 'student')) {
                    $colMap['students'] = $idx;
                }
            }
            $numCols = count($headerCells);
            $i++;
            $milestones = [];
            while ($i < $n && trim($lines[$i]) !== '=== TABLE END ===') {
                $row = trim($lines[$i]);
                if ($row === '') {
                    $i++;
                    continue;
                }
                $cells = array_map('trim', explode('|', $row, max($numCols, 1)));
                $get = function (string $key) use ($cells, $colMap) {
                    return isset($colMap[$key], $cells[$colMap[$key]]) ? $cells[$colMap[$key]] : '';
                };
                $seq = (int) trim($cells[0] ?? '0');
                $leadRaw = $get('lead');
                $studentsRaw = $get('students');
                $levelRaw = strtolower($get('level'));
                $levelSet = [];
                if (str_contains($levelRaw, 'bachelor')) $levelSet[] = 'bachelors';
                if (str_contains($levelRaw, 'master')) $levelSet[] = 'masters';
                if (str_contains($levelRaw, 'phd')) $levelSet[] = 'phd';
                if (str_contains($levelRaw, 'staff')) $levelSet[] = 'staff';
                $milestones[] = [
                    'seq' => $seq > 0 ? $seq : (count($milestones) + 1),
                    'title' => $get('title'),
                    'output' => $get('output') ?: null,
                    'timeline' => $get('timeline') ?: null,
                    'lead' => (strcasecmp(trim($leadRaw), 'TBD') === 0 || $leadRaw === '') ? null : $leadRaw,
                    'level_set' => $levelSet,
                    'students' => (strcasecmp(trim($studentsRaw), 'TBD') === 0 || $studentsRaw === '') ? null : $studentsRaw,
                ];
                $i++;
            }
            if ($title !== '') {
                $projects[] = ['lab' => $currentLab, 'title' => $title, 'milestones' => $milestones];
            }
            $pendingTitle = null;
            $i++;
            continue;
        }
        // Regular text line: candidate title for the next table.
        $pendingTitle = $trimmed;
        $i++;
    }
    return $projects;
}

/** Words, crudely stemmed to a 6-char prefix so suffix variation
 * ("epidemiology" vs "epidemiological", "detect" vs "detection") doesn't
 * defeat matching between a short dept-file title and its long, more
 * formally-worded playbook counterpart. */
function normalizeWords(string $text): array
{
    $ascii = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $ascii = strtolower($ascii);
    $ascii = preg_replace('/[^a-z0-9 ]+/', ' ', $ascii);
    $words = array_filter(explode(' ', $ascii), fn($w) => strlen($w) > 2);
    $stems = array_map(fn($w) => strlen($w) > 6 ? substr($w, 0, 6) : $w, $words);
    return array_unique($stems);
}

/**
 * Overlap coefficient (intersection / size of the SMALLER word set) rather
 * than Jaccard: dept-file titles are frequently a short 2-4 word summary
 * ("Aflatoxin Detection Sensor") of a much longer, more formal playbook
 * title ("Development of a Low-Cost Portable Biosensor for Rapid Aflatoxin
 * Detection in Food and Agricultural Products"). Jaccard's large union
 * from the long title would score such an obviously-correct pair too low
 * to match; overlap coefficient asks instead "does (almost) all of the
 * short title's vocabulary appear in the long one?", which is robust to
 * that length asymmetry.
 */
function titleSimilarity(string $a, string $b): float
{
    $wa = normalizeWords($a);
    $wb = normalizeWords($b);
    if (!$wa || !$wb) {
        return 0.0;
    }
    $intersect = count(array_intersect($wa, $wb));
    $smaller = min(count($wa), count($wb));
    return $smaller > 0 ? $intersect / $smaller : 0.0;
}

/**
 * For a department, reconciles the dept-file's per-lab project list against
 * the playbook's flat, milestone-backed project list using whole-department
 * fuzzy title matching (greedy, best-match-wins, each dept project usable
 * once). This is more robust than positional or per-lab matching because
 * playbook lab headings are occasionally mis-placed relative to the actual
 * project boundary in the source Word document (observed in the MIE
 * playbook), and because not every dept-file project has playbook coverage
 * (observed in EEE) while some playbook projects have no dept-file
 * description at all (observed in PEEM/MIE/BME).
 *
 * Returns a flat list: [['pb_lab'=>, 'title'=>, 'description'=>,
 * 'lead'=>, 'members'=>[], 'milestones'=>[]]].
 */
function reconcileProjects(array $deptLabs, array $pbProjects): array
{
    $pool = [];
    foreach ($deptLabs as $lab) {
        foreach ($lab['projects'] as $p) {
            $pool[] = $p + ['_used' => false];
        }
    }
    $out = [];
    foreach ($pbProjects as $pbp) {
        $bestIdx = -1;
        $bestScore = 0.0;
        foreach ($pool as $idx => $dp) {
            if ($dp['_used']) {
                continue;
            }
            $score = titleSimilarity($pbp['title'], $dp['title']);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIdx = $idx;
            }
        }
        $description = null;
        $lead = null;
        $members = [];
        if ($bestIdx >= 0 && $bestScore >= 0.45) {
            $pool[$bestIdx]['_used'] = true;
            $description = $pool[$bestIdx]['description'];
            $lead = $pool[$bestIdx]['lead'];
            $members = $pool[$bestIdx]['members'];
        }
        $out[] = [
            'pb_lab' => $pbp['lab'], 'title' => $pbp['title'], 'description' => $description,
            'lead' => $lead, 'members' => $members, 'milestones' => $pbp['milestones'],
        ];
    }
    $unmatched = array_values(array_filter($pool, fn($dp) => !$dp['_used']));
    return [$out, $unmatched];
}

$leadTokens = []; // filled after staff creation, maps UPPERCASE token => staff id

function matchLeadStaffId(?string $text, array $leadTokens): ?int
{
    if (!$text) {
        return null;
    }
    $u = strtoupper($text);
    foreach ($leadTokens as $token => $id) {
        // Whole-word match only: a plain substring check would let "EZE"
        // (for VHU Eze / staff id 60) false-positive-match "EZEKIEL Nnadi",
        // since "Ezekiel" starts with "Eze".
        if (preg_match('/\b' . preg_quote($token, '/') . '\b/', $u) === 1) {
            return $id;
        }
    }
    return null;
}

// =============================================================================
// Lookups
// =============================================================================

$facultyId = (int) $pdo->query("SELECT id FROM faculties WHERE slug = 'fast' LIMIT 1")->fetchColumn();

$deptIds = [];
foreach ($pdo->query('SELECT id, slug FROM departments') as $row) {
    $deptIds[$row['slug']] = (int) $row['id'];
}
$DEPT = [
    'CVE' => $deptIds['civil-engineering'],
    'MIE' => $deptIds['mechanical-engineering'],
    'PEEM' => $deptIds['petroleum-engineering-and-environmental-management'],
    'BME' => $deptIds['biomedical-engineering'],
    'EEE' => $deptIds['electrical-and-electronics-engineering'],
];

$positionIds = [];
foreach ($pdo->query('SELECT id, code FROM positions') as $row) {
    $positionIds[$row['code']] = (int) $row['id'];
}

$unitTypeIds = [];
foreach ($pdo->query('SELECT id, code FROM research_unit_types') as $row) {
    $unitTypeIds[$row['code']] = (int) $row['id'];
}

$sdgIds = [];
foreach ($pdo->query('SELECT id, code FROM sdgs') as $row) {
    $sdgIds[$row['code']] = (int) $row['id'];
}

$existingEzeId = (int) $pdo->query("SELECT id FROM staff WHERE slug = 'eze-valentine-hyginus-edoka' LIMIT 1")->fetchColumn();

// =============================================================================
// PART 1: New staff (research-agenda data gaps).
//
// Titles/spellings below are taken from database/seeds-source memberships.txt
// (the faculty's official per-department staff-name rosters), which is more
// authoritative than the informal first-name-only mentions used throughout
// the pilot-project spreadsheets and playbooks. All 4 "check DB first" names
// flagged for this task (Ezekiel Nnadi, James Mbabazi, Stephen Opolot,
// Michael Marembo) were checked against the live `staff` table before this
// script was written and confirmed to NOT already exist as DOCX-only-gap
// records from earlier work this session -- so all are created fresh here.
// "VHU Eze" / "Assoc. Prof. Valentine Udoka" in these source documents is
// treated as the same person as the existing staff row id={$existingEzeId}
// (Dr. Eze Valentine Hyginus Edoka); that record is left untouched.
// "Jordan Agaba" appears repeatedly as a mini-project lead in the CVE
// playbook but never appears in any department's official staff roster in
// memberships.txt -- treated as a student/junior contributor per the task's
// guidance, recorded only as free-text milestone leads, no staff row.
// =============================================================================

$newStaff = [
    ['honorific' => 'Assoc. Prof.', 'first' => 'Ezekiel', 'middle' => null, 'last' => 'Nnadi', 'dept' => 'CVE',
        'position' => 'associate_professor', 'token' => 'NNADI',
        'bio' => 'Team lead of two of the six research laboratories in the Department of Civil and Building Services Engineering under the FAST Research Agenda 2026-2030: the Sustainable Materials, Green Infrastructure and Climate Resilience Research Laboratory and the Construction Management, Surveying, Risk and Cost Innovation Research Laboratory.'],
    ['honorific' => 'Dr.', 'first' => 'James', 'middle' => null, 'last' => 'Mbabazi', 'dept' => 'CVE',
        'position' => 'lecturer', 'token' => 'MBABAZI',
        'bio' => 'Contributes as a project lead and team member across several of the Department of Civil and Building Services Engineering\'s research laboratories -- including Sustainable Materials, Water Resources and Structural Engineering -- under the FAST Research Agenda 2026-2030.'],
    ['honorific' => 'Mr.', 'first' => 'Victor', 'middle' => null, 'last' => 'Mugabe', 'dept' => 'CVE',
        'position' => 'lecturer', 'token' => 'MUGABE',
        'bio' => 'Research team member across multiple Department of Civil and Building Services Engineering research laboratories, including Construction Management, Water Resources and Artificial Intelligence for Civil Engineering, under the FAST Research Agenda 2026-2030.'],
    ['honorific' => 'Mr.', 'first' => 'John Vianney', 'middle' => null, 'last' => 'Tushabe', 'dept' => 'BME',
        'position' => 'lecturer', 'token' => 'VIANNEY',
        'bio' => 'Team lead of the Bioinformatics, Data Science and Synthetic Biology Laboratory in the Department of Biomedical Sciences and Engineering, and a research team member in several other departmental laboratories, under the FAST Research Agenda 2026-2030.'],
    ['honorific' => 'Mr.', 'first' => 'Stephen', 'middle' => null, 'last' => 'Opolot', 'dept' => 'BME',
        'position' => 'lecturer', 'token' => 'OPOLOT',
        'bio' => 'Team lead of the Biomedical Systems and Education Laboratory in the Department of Biomedical Sciences and Engineering under the FAST Research Agenda 2026-2030.'],
    ['honorific' => null, 'first' => 'Joshua', 'middle' => null, 'last' => 'Biryomumeisho', 'dept' => 'BME',
        'position' => 'lecturer', 'token' => 'BIRYOMUMEISHO',
        'bio' => 'Research team member of the Medical Imaging, Virtual Reality and Artificial Intelligence Laboratory in the Department of Biomedical Sciences and Engineering under the FAST Research Agenda 2026-2030.'],
    ['honorific' => 'Dr.', 'first' => 'James', 'middle' => null, 'last' => 'Kaconco', 'dept' => 'MIE',
        'position' => 'lecturer', 'token' => 'KACONCO',
        'bio' => 'Research team member of the Advanced Manufacturing and Industry 4.0 Laboratory and the Industrial Engineering and Operations Optimization Laboratory in the Department of Mechanical and Industrial Engineering under the FAST Research Agenda 2026-2030.'],
    ['honorific' => 'Assoc. Prof.', 'first' => 'Stephen', 'middle' => 'Ndubuisi', 'last' => 'Nnamchi', 'dept' => 'MIE',
        'position' => 'associate_professor', 'token' => 'NNAMCHI',
        'bio' => 'Team lead of the Materials Engineering and Structural Integrity Laboratory and the Sustainable Energy Systems and Thermal Engineering Laboratory in the Department of Mechanical and Industrial Engineering under the FAST Research Agenda 2026-2030.'],
    ['honorific' => 'Mr.', 'first' => 'Titus', 'middle' => null, 'last' => 'Wanazusi', 'dept' => 'MIE',
        'position' => 'lecturer', 'token' => 'WANAZUSI',
        'bio' => 'Member of the Department of Mechanical and Industrial Engineering\'s official research-active staff roster for the FAST Research Agenda 2026-2030.'],
    ['honorific' => 'Dr.', 'first' => 'Michael', 'middle' => null, 'last' => 'Marembo', 'dept' => 'PEEM',
        'position' => 'lecturer', 'token' => 'MAREMBO',
        'bio' => 'Team lead of the Centre for Sustainable GeoEnergy Systems (CSGS) and the Reservoir Simulation and Computational Energy Systems Laboratory in the Department of Energy, Mineral and Petroleum Engineering under the FAST Research Agenda 2026-2030.'],
    ['honorific' => 'Mr.', 'first' => 'Ian', 'middle' => null, 'last' => 'Nyesiga', 'dept' => 'PEEM',
        'position' => 'lecturer', 'token' => 'NYESIGA',
        'bio' => 'Research team member of the Enhanced Oil Recovery and Flow Assurance Laboratory and the Produced Water Treatment and Petroleum Quality Innovation Laboratory in the Department of Energy, Mineral and Petroleum Engineering under the FAST Research Agenda 2026-2030.'],
    ['honorific' => null, 'first' => 'Athur', 'middle' => null, 'last' => 'Mugumya', 'dept' => 'EEE',
        'position' => 'lecturer', 'token' => 'MUGUMYA',
        'bio' => 'Research team member of the Power Systems Laboratory and the Renewable Energy, Bioenergy and Sustainable Mobility Laboratory in the Department of Electrical and Electronics Engineering under the FAST Research Agenda 2026-2030.'],
];

$staffIdByToken = ['EZE' => $existingEzeId, 'VHU' => $existingEzeId, 'UDOKA' => $existingEzeId];
$report['staff_matched_existing'][] = ['name' => 'VHU Eze / Assoc. Prof. Valentine Udoka', 'matched_id' => $existingEzeId, 'existing_name' => 'Dr. Eze Valentine Hyginus Edoka', 'note' => 'Name-order/spelling conflict preserved, not merged, per task instruction.'];

$insertStaff = $pdo->prepare(
    'INSERT INTO staff (faculty_id, honorific_title, first_name, middle_name, last_name, slug, staff_category, short_biography, status, published_at, display_order)
     VALUES (:faculty_id, :honorific, :first, :middle, :last, :slug, "academic", :bio, "published", NOW(), 0)'
);
$insertStaffDept = $pdo->prepare(
    'INSERT INTO staff_departments (staff_id, department_id, is_primary, is_current) VALUES (:staff_id, :dept_id, 1, 1)'
);
$insertStaffPosition = $pdo->prepare(
    'INSERT INTO staff_positions (staff_id, position_id, department_id, is_current, display_order) VALUES (:staff_id, :position_id, :dept_id, 1, 0)'
);

foreach ($newStaff as $s) {
    $slugBase = slugify(trim(($s['first'] ?? '') . ' ' . ($s['middle'] ?? '') . ' ' . $s['last']));
    $existing = $pdo->prepare('SELECT id FROM staff WHERE slug = :slug LIMIT 1');
    $existing->execute(['slug' => $slugBase]);
    $id = $existing->fetchColumn();
    if ($id !== false) {
        $staffIdByToken[$s['token']] = (int) $id;
        $report['staff_matched_existing'][] = ['name' => $s['first'] . ' ' . $s['last'], 'matched_id' => (int) $id];
        continue;
    }
    $slug = uniqueSlug($pdo, 'staff', $slugBase, 240);
    $insertStaff->execute([
        'faculty_id' => $facultyId, 'honorific' => $s['honorific'], 'first' => $s['first'],
        'middle' => $s['middle'], 'last' => $s['last'], 'slug' => $slug,
        'bio' => $s['bio'],
    ]);
    $id = (int) $pdo->lastInsertId();
    $insertStaffDept->execute(['staff_id' => $id, 'dept_id' => $DEPT[$s['dept']]]);
    $insertStaffPosition->execute(['staff_id' => $id, 'position_id' => $positionIds[$s['position']], 'dept_id' => $DEPT[$s['dept']]]);
    $staffIdByToken[$s['token']] = $id;
    $report['staff_created'][] = ['name' => trim($s['first'] . ' ' . ($s['middle'] ?? '') . ' ' . $s['last']), 'id' => $id, 'slug' => $slug];
}
$leadTokens = $staffIdByToken;

// =============================================================================
// PART 2: Research unit (lab) definitions.
//
// `dept_lab_name` is the exact lab-name string used in the department's
// pilot-projects xlsx dump (dept_XXX.txt), used to pull team lead/other
// members/project descriptions programmatically. `pb_heading` is the exact
// heading string used in that department's Research Playbook docx dump
// (pb_XXX.txt), used to pull milestone-backed projects programmatically.
// Either may be null (a master-file-only draft proposal has neither; BME's
// Biomaterials lab and PEEM's Reservoir Simulation lab have a dept/pb
// heading but, per direct inspection of the source files, no milestone
// table of their own -- see the report for details).
//
// `overview` is the lab's vision/objective statement, taken verbatim from
// the master faculty-wide planning spreadsheet (RAFAST1.0) where a lab
// there could be confidently matched to this one; left null where no
// confident match exists rather than guessing.
// =============================================================================

$labs = [];

// --- CVE: 6 published, 0 draft (all 6 master entries matched 1:1) ---------
$labs[] = ['dept' => 'CVE', 'name' => 'Sustainable Materials, Green Infrastructure and Climate Resilience Research Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Sustainable Materials, Green Infrastructure and Climate Resilience Research Laboratory', 'pb_heading' => 'Sustainable Materials, Green Infrastructure and Climate Resilience Research Laboratory',
    'overview' => 'To develop innovative, affordable, and environmentally sustainable construction materials and infrastructure technologies that reduce carbon emissions, improve resource efficiency, and promote resilient infrastructure for sustainable development.'];
$labs[] = ['dept' => 'CVE', 'name' => 'Construction Management, Surveying, Risk and Cost Innovation Research Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Construction Management, Surveying, Risk and Cost Innovation Research Laboratory', 'pb_heading' => 'Construction Management, Surveying, Risk and Cost Innovation Research Laboratory',
    'overview' => 'To advance innovative project management, risk assessment, and cost optimization strategies that enhance the successful delivery of infrastructure projects while improving productivity, quality, and sustainability.'];
$labs[] = ['dept' => 'CVE', 'name' => 'Water Resources, Public Health and Environmental Engineering Research Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Water Resources, Public Health and Environmental Engineering Research Laboratory', 'pb_heading' => 'Water Resources, Public Health and Environmental Engineering Research Laboratory',
    'overview' => 'To advance research, design and innovation in sustainable water-resources management, flood risk reduction, and resilient infrastructure development for communities, cities, industries, and ecosystems.'];
$labs[] = ['dept' => 'CVE', 'name' => 'Transport Planning and Engineering Research Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Transport Planning and Engineering Research Laboratory', 'pb_heading' => 'Transport Planning and Engineering Research Laboratory',
    'overview' => 'To develop intelligent, safe, and sustainable transportation systems that improve mobility, road safety, infrastructure performance and stability, and transport efficiency through innovative engineering solutions.'];
$labs[] = ['dept' => 'CVE', 'name' => 'Structural Engineering Research Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Structural Engineering Research Laboratory', 'pb_heading' => 'Structural Engineering Research Laboratory',
    'overview' => 'Focusses on full-scale structural components, concrete behaviour and load capacities.'];
$labs[] = ['dept' => 'CVE', 'name' => 'Artificial Intelligence and Digital Technologies for Civil Engineering Research Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Artificial Intelligence and Digital Technologies for Civil Engineering Research Laboratory', 'pb_heading' => 'Artificial Intelligence and Digital Technologies for Civil Engineering Research Laboratory',
    'overview' => 'Focuses on solving civil engineering problems using artificial intelligence.'];

// --- BME: 7 published (per approved decision), 0 draft --------------------
$labs[] = ['dept' => 'BME', 'name' => 'Medical Devices, Microfluidics and Diagnostics Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Medical Devices, Microfluidics and Diagnostics Laboratory', 'pb_heading' => 'Medical Devices, Microfluidics and Diagnostics Laboratory',
    'overview' => 'To design, develop, and evaluate affordable medical devices, biosensors, diagnostic technologies, and point-of-care systems that address healthcare challenges in low-resource settings.'];
$labs[] = ['dept' => 'BME', 'name' => 'Rehabilitation and Regenerative Engineering Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Rehabilitation and Regenerative Engineering Laboratory', 'pb_heading' => 'Rehabilitation and Regenerative Engineering Laboratory',
    'overview' => 'To develop affordable rehabilitation technologies, assistive devices, and patient-centered engineering solutions that improve mobility, independence, and quality of life for people living with disabilities.'];
$labs[] = ['dept' => 'BME', 'name' => 'Bioinformatics, Data Science and Synthetic Biology Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Bioinformatics, Data Science and Synthetic Biology Laboratory', 'pb_heading' => 'Bioinformatics, Data Science and Synthetic Biology Laboratory',
    'overview' => 'To apply bioinformatics, computational biology, artificial intelligence, and data science to precision medicine, infectious diseases, genomics, and systems biology research.'];
$labs[] = ['dept' => 'BME', 'name' => 'Medical Imaging, Virtual Reality and Artificial Intelligence Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Medical Imaging, Virtual Reality and Artificial Intelligence Lab', 'pb_heading' => 'Medical Imaging, Virtual Reality and Artificial Intelligence Laboratory',
    'overview' => 'To advance medical imaging, artificial intelligence, virtual reality, and computational healthcare technologies for disease diagnosis, medical education, and clinical decision support.'];
$labs[] = ['dept' => 'BME', 'name' => 'Biomedical Systems and Education Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Biomedical Systems and Education Laboratory', 'pb_heading' => 'Biomedical Systems and Education Laboratory',
    'overview' => 'To strengthen healthcare systems, biomedical engineering education, digital health, and medical technology management through innovative engineering solutions and evidence-based research.'];
$labs[] = ['dept' => 'BME', 'name' => 'Biomaterials, Bioprinting and Tissue Engineering Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => null, 'pb_heading' => null,
    'overview' => 'To develop advanced biomaterials, tissue engineering technologies, regenerative medicine solutions, and biomedical manufacturing systems for healthcare innovation.',
    'lead' => 'John Vianney Tushabe', 'members' => ['Vicent Kasambula']];
$labs[] = ['dept' => 'BME', 'name' => 'Climate & Planetary Health Engineering', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Climate & Planetary Health Engineering', 'pb_heading' => 'Climate & Planetary Health Engineering',
    'overview' => null];

// --- EEE: 6 published, 8 draft ---------------------------------------------
$labs[] = ['dept' => 'EEE', 'name' => 'Power Systems Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Power Systems', 'pb_heading' => 'Power Systems',
    'overview' => null];
$labs[] = ['dept' => 'EEE', 'name' => 'Industrial Automation, Robotics and Intelligent Embedded Systems Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Industrial Automation, Robotics and Intelligent Embedded Systems Laboratory', 'pb_heading' => 'Industrial Automation, Robotics and Intelligent Embedded Systems Laboratory',
    'overview' => 'Position the EEE Department and FAST as a center of excellence for research, practical skills transfer, and collaboration in industrial automation, robotics, and smart technologies.'];
$labs[] = ['dept' => 'EEE', 'name' => 'Renewable Energy, Bioenergy and Sustainable Mobility Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Renewable Energy, Bioenergy and Sustainable Mobility Laboratory', 'pb_heading' => 'Renewable Energy, Bioenergy and Sustainable Mobility Laboratory',
    'overview' => 'To provide advanced facilities for practical postgraduate research in solar photovoltaic systems, intelligent MPPT, batteries and energy storage, electric-vehicle charging, biofuels, bioethanol, biogas, biomass, and other locally appropriate renewable-energy technologies.'];
$labs[] = ['dept' => 'EEE', 'name' => 'Advanced Materials, Semiconductor and Electronics Technologies Laboratory', 'type' => 'research_group', 'status' => 'published',
    'dept_lab_name' => 'Advanced Materials, Semiconductor and Electronics Technologies Laboratory (AM_SET)', 'pb_heading' => 'Advanced Materials, Semiconductor and Electronics Technologies Laboratory (AM_SET)',
    'overview' => 'To become a leading multidisciplinary research group for the identification, characterisation, purification, modelling, processing and value addition of locally available materials for semiconductor, electronic, renewable-energy and advanced manufacturing applications in Uganda and beyond.'];
$labs[] = ['dept' => 'EEE', 'name' => 'Communication, Cybersecurity and Space Systems Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Communication, Cybersecurity and Space Systems Laboratory', 'pb_heading' => 'Communication, Cybersecurity and Space Systems Laboratory',
    'overview' => 'To develop secure and reliable communication systems that protect information and support digital services for industry and communities.'];
$labs[] = ['dept' => 'EEE', 'name' => 'Engineering Education Research Group', 'type' => 'research_group', 'status' => 'published',
    'dept_lab_name' => 'Engineering Education Research Group', 'pb_heading' => 'Engineering Education Research Group',
    'overview' => null];
$labs[] = ['dept' => 'EEE', 'name' => 'Engineering Teaching, Learning and Assessment', 'type' => 'research_group', 'status' => 'draft', 'overview' => 'To advance evidence-based teaching, learning, and assessment practices that enhance engineering education, student success, and graduate preparedness for industry and society.'];
$labs[] = ['dept' => 'EEE', 'name' => 'Responsible AI and Digital Technologies', 'type' => 'research_group', 'status' => 'draft', 'overview' => 'To promote the responsible development and use of Artificial Intelligence and digital technologies that improve education, industry, public services, and community development.'];
$labs[] = ['dept' => 'EEE', 'name' => 'Power Systems and Renewable Energy Lab', 'type' => 'research_laboratory', 'status' => 'draft', 'overview' => "To develop innovative, reliable, and sustainable power and renewable energy technologies that improve energy access, smart grid management, and industrial productivity while supporting Uganda's transition to clean and resilient energy systems."];
$labs[] = ['dept' => 'EEE', 'name' => 'Smart Power Systems, Renewable Energy and Energy Access Laboratory', 'type' => 'research_laboratory', 'status' => 'draft', 'overview' => 'To develop reliable, sustainable and context-responsive power and renewable energy solutions that improve energy access, system performance and industrial productivity.'];
$labs[] = ['dept' => 'EEE', 'name' => 'Intelligent Electronics, Embedded Systems and Digital Infrastructure Laboratory', 'type' => 'research_laboratory', 'status' => 'draft', 'overview' => 'To develop locally relevant electronic, embedded and digital solutions for industry, public services and community needs.'];
$labs[] = ['dept' => 'EEE', 'name' => 'Electrical Power Systems and Machines Laboratory', 'type' => 'research_laboratory', 'status' => 'draft', 'overview' => 'To provide advanced practical and research facilities for the analysis, operation, protection, monitoring, control, and improvement of electrical power systems, machines, transformers, and high-voltage equipment.'];
$labs[] = ['dept' => 'EEE', 'name' => 'Electronics, Embedded Systems and Intelligent Technologies Laboratory', 'type' => 'research_laboratory', 'status' => 'draft', 'overview' => 'To provide an advanced platform for designing, building, testing, and validating intelligent electronic and embedded systems that address practical challenges in healthcare, agriculture, industry, infrastructure, and communities.'];
$labs[] = ['dept' => 'EEE', 'name' => 'Health and Well-Being Research Group', 'type' => 'research_group', 'status' => 'draft', 'overview' => 'Low-power technology sensing in healthcare and intuitive diagnostic visualisation for real-time monitoring.'];

// --- MIE: 6 published, 5 draft ---------------------------------------------
$labs[] = ['dept' => 'MIE', 'name' => 'Advanced Manufacturing and Industry 4.0 Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Advanced Manufacturing and Industry 4.0 Laboratory', 'pb_heading' => 'Advanced Manufacturing and Industry 4.0 Laboratory',
    'overview' => 'To advance intelligent manufacturing systems through automation, digital technologies, artificial intelligence, and smart factory solutions.'];
$labs[] = ['dept' => 'MIE', 'name' => 'Robotics, Automation and Mechatronics Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Robotics, Automation and Mechatronics Laboratory', 'pb_heading' => 'Robotics, Automation and Mechatronics Laboratory',
    'overview' => 'To develop intelligent robotic and automated systems that improve productivity, safety, and efficiency across industrial, healthcare, and agricultural sectors.'];
$labs[] = ['dept' => 'MIE', 'name' => 'Automotive Engineering and Intelligent Mobility Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Automotive Engineering and Intelligent Mobility Laboratory', 'pb_heading' => null,
    'overview' => 'To develop innovative automotive technologies that improve vehicle performance, sustainable mobility, transportation safety, and local automotive manufacturing.'];
$labs[] = ['dept' => 'MIE', 'name' => 'Industrial Engineering and Operations Optimization Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Industrial Engineering and Operations Optimization Laboratory', 'pb_heading' => 'Industrial Engineering and Operations Optimization Laboratory',
    'overview' => 'To improve industrial productivity through systems optimization, operations research, data analytics, and intelligent decision-support systems.'];
$labs[] = ['dept' => 'MIE', 'name' => 'Materials Engineering and Structural Integrity Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Materials Engineering and Structural Integrity Laboratory', 'pb_heading' => 'Materials Engineering and Structural Integrity Laboratory',
    'overview' => 'To develop advanced engineering materials and evaluate their performance for sustainable manufacturing and infrastructure applications.'];
$labs[] = ['dept' => 'MIE', 'name' => 'Sustainable Energy Systems and Thermal Engineering Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Sustainable Energy Systems and Thermal Engineering Laboratory', 'pb_heading' => 'Sustainable Energy Systems and Thermal Engineering Laboratory',
    'overview' => 'To develop efficient thermal and energy systems that promote clean energy, industrial productivity, and sustainable engineering solutions.'];
$labs[] = ['dept' => 'MIE', 'name' => 'Energy Research Group', 'type' => 'research_group', 'status' => 'draft', 'overview' => 'To advance sustainable, affordable, and intelligent energy solutions that improve livelihoods, strengthen healthcare systems, accelerate industrial development, and support climate-resilient communities through multidisciplinary research, innovation, and policy engagement.'];
$labs[] = ['dept' => 'MIE', 'name' => 'Materials Research Group', 'type' => 'research_group', 'status' => 'draft', 'overview' => 'To develop, characterize, test, and commercialize advanced, sustainable, and high-performance materials that address national and regional challenges in infrastructure, healthcare, defence, transportation, manufacturing, energy, water, and environmental sustainability.'];
$labs[] = ['dept' => 'MIE', 'name' => 'Advanced Manufacturing and Digital Fabrication Laboratory', 'type' => 'research_laboratory', 'status' => 'draft', 'overview' => 'To develop modern manufacturing technologies that promote local production, rapid prototyping, precision engineering, and industrial innovation.'];
$labs[] = ['dept' => 'MIE', 'name' => 'Biomedical Mechanical Systems and Rehabilitation Engineering Laboratory', 'type' => 'research_laboratory', 'status' => 'draft', 'overview' => 'To design and manufacture affordable mechanical systems, rehabilitation technologies, and medical equipment for improved healthcare delivery.'];
$labs[] = ['dept' => 'MIE', 'name' => 'Sustainable and Green Industrial Systems Laboratory', 'type' => 'research_laboratory', 'status' => 'draft', 'overview' => 'To develop environmentally sustainable manufacturing systems that improve resource efficiency, waste utilization, and circular economy practices.'];

// --- PEEM: 6 published, 7 draft ---------------------------------------------
$labs[] = ['dept' => 'PEEM', 'name' => 'Enhanced Oil Recovery and Flow Assurance Laboratory', 'type' => 'research_group', 'status' => 'published',
    'dept_lab_name' => 'Enhanced Oil Recovery and Flow Assurance Laboratory', 'pb_heading' => 'Enhanced Oil Recovery and Flow Assurance Laboratory',
    'overview' => "To develop and validate locally relevant enhanced oil recovery and flow-assurance technologies that maximise oil recovery, reduce production challenges and improve the economic and environmental sustainability of Uganda's petroleum resources."];
$labs[] = ['dept' => 'PEEM', 'name' => 'Produced Water Treatment and Petroleum Quality Innovation Laboratory', 'type' => 'research_group', 'status' => 'published',
    'dept_lab_name' => 'Produced Water Treatment and Petroleum Quality Innovation Laboratory', 'pb_heading' => 'Produced Water Treatment and Petroleum Quality Innovation Laboratory',
    'overview' => 'To develop sustainable technologies for produced-water treatment, reuse, locally manufactured specialty chemicals and petroleum-product quality assurance, while supporting environmental protection and regulatory compliance.'];
$labs[] = ['dept' => 'PEEM', 'name' => 'Sustainable Bioenergy and Circular Materials Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Sustainable Bioenergy and Circular Materials Laboratory', 'pb_heading' => 'Sustainable Bioenergy and Circular Materials Laboratory',
    'overview' => null];
$labs[] = ['dept' => 'PEEM', 'name' => 'Centre for Sustainable GeoEnergy Systems (CSGS)', 'type' => 'research_centre', 'status' => 'published',
    'dept_lab_name' => 'Sustainable GeoEnergy Systems Laboratory', 'pb_heading' => 'Sustainable GeoEnergy Systems Laboratory',
    'overview' => 'To become a leading Centre of Excellence in sustainable geoenergy by advancing CCUS, CCS, Natural Hydrogen, Geothermal Energy, and Artificial Intelligence to enhance energy security, accelerate the low-carbon transition, and support sustainable development.'];
$labs[] = ['dept' => 'PEEM', 'name' => 'Sustainable Drilling Fluids and Wellbore Chemistry Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Sustainable Drilling Fluids and Wellbore Chemistry Laboratory', 'pb_heading' => 'Sustainable Drilling Fluids and Wellbore Chemistry Laboratory',
    'overview' => 'To develop environmentally sustainable drilling fluid technologies and wellbore chemical solutions that enhance drilling efficiency, minimize formation damage, improve well productivity, and support environmentally responsible petroleum development in Uganda through innovative research, advanced materials characterization, and locally adapted engineering solutions.'];
$labs[] = ['dept' => 'PEEM', 'name' => 'Reservoir Simulation and Computational Energy Systems Laboratory', 'type' => 'research_laboratory', 'status' => 'published',
    'dept_lab_name' => 'Reservoir Characterisation and Computational Laboratory', 'pb_heading' => null,
    'overview' => null];
$labs[] = ['dept' => 'PEEM', 'name' => 'Sustainable Petroleum and Energy Systems Laboratory', 'type' => 'research_laboratory', 'status' => 'draft', 'overview' => 'To develop innovative petroleum and energy technologies that improve energy security, operational efficiency, environmental sustainability, and the responsible utilization of energy resources.'];
$labs[] = ['dept' => 'PEEM', 'name' => 'Renewable Energy and Smart Energy Technologies Laboratory', 'type' => 'research_laboratory', 'status' => 'draft', 'overview' => 'To develop affordable and intelligent renewable energy technologies that increase access to clean, reliable, and sustainable energy for communities and industries.'];
$labs[] = ['dept' => 'PEEM', 'name' => 'Mining Engineering and Mineral Processing Laboratory', 'type' => 'research_laboratory', 'status' => 'draft', 'overview' => 'To develop sustainable mining technologies and mineral processing solutions that improve resource recovery, safety, environmental protection, and value addition.'];
$labs[] = ['dept' => 'PEEM', 'name' => 'Environmental Sustainability and Climate Technologies Laboratory', 'type' => 'research_laboratory', 'status' => 'draft', 'overview' => 'To develop engineering solutions that promote efficient renewable resource utilization while promoting environmental protection, climate resilience, pollution control, and sustainable natural resource management.'];
$labs[] = ['dept' => 'PEEM', 'name' => 'Smart Energy, AI and Industrial Digitalization Laboratory', 'type' => 'research_laboratory', 'status' => 'draft', 'overview' => 'To integrate artificial intelligence, IoT, automation, and digital technologies into the energy, petroleum, and mining sectors to improve operational efficiency, safety, and decision-making.'];
$labs[] = ['dept' => 'PEEM', 'name' => 'Wind Energy and Sustainable Power Systems Laboratory', 'type' => 'research_laboratory', 'status' => 'draft', 'overview' => 'To develop innovative wind energy technologies and hybrid renewable power systems for sustainable electricity generation in rural and industrial settings.'];
$labs[] = ['dept' => 'PEEM', 'name' => 'Oil, Gas and Process Engineering Laboratory', 'type' => 'research_laboratory', 'status' => 'draft', 'overview' => 'To develop innovative process engineering technologies that improve oil and gas production, transportation, storage, refining, and safety.'];

// =============================================================================
// PART 3: Parse source files and reconcile projects per department.
// =============================================================================

$deptHeadings = [
    'CVE' => [
        'Sustainable Materials, Green Infrastructure and Climate Resilience Research Laboratory',
        'Construction Management, Surveying, Risk and Cost Innovation Research Laboratory',
        'Water Resources, Public Health and Environmental Engineering Research Laboratory',
        'Transport Planning and Engineering Research Laboratory',
        'Structural Engineering Research Laboratory',
        'Artificial Intelligence and Digital Technologies for Civil Engineering Research Laboratory',
    ],
    'BME' => [
        'Medical Devices, Microfluidics and Diagnostics Laboratory',
        'Bioinformatics, Data Science and Synthetic Biology Laboratory',
        'Rehabilitation and Regenerative Engineering Laboratory',
        'Medical Imaging, Virtual Reality and Artificial Intelligence Laboratory',
        'Biomedical Systems and Education Laboratory',
        'Climate & Planetary Health Engineering',
    ],
    'EEE' => [
        'Industrial Automation, Robotics and Intelligent Embedded Systems Laboratory',
        'Renewable Energy, Bioenergy and Sustainable Mobility Laboratory',
        'Advanced Materials, Semiconductor and Electronics Technologies Laboratory (AM_SET)',
        'Communication, Cybersecurity and Space Systems Laboratory',
        'Engineering Education Research Group',
        'Power Systems',
    ],
    'MIE' => [
        'Advanced Manufacturing and Industry 4.0 Laboratory',
        'Robotics, Automation and Mechatronics Laboratory',
        'Industrial Engineering and Operations Optimization Laboratory',
        'Materials Engineering and Structural Integrity Laboratory',
        'Sustainable Energy Systems and Thermal Engineering Laboratory',
    ],
    'PEEM' => [
        'Enhanced Oil Recovery and Flow Assurance Laboratory',
        'Produced Water Treatment and Petroleum Quality Innovation Laboratory',
        'Sustainable Bioenergy and Circular Materials Laboratory',
        'Sustainable GeoEnergy Systems Laboratory',
        'Sustainable Drilling Fluids and Wellbore Chemistry Laboratory',
    ],
];
$deptFiles = ['CVE' => 'dept_CVE.txt', 'BME' => 'dept_BME.txt', 'EEE' => 'dept_EEE.txt', 'MIE' => 'dept_MIE.txt', 'PEEM' => 'dept_PEEM.txt'];
$pbFiles = ['CVE' => 'pb_CVE.txt', 'BME' => 'pb_BME.txt', 'EEE' => 'pb_EEE.txt', 'MIE' => 'pb_MIE.txt', 'PEEM' => 'pb_PEEM.txt'];

$reconciled = []; // dept => flat list of matched projects
$unmatchedAll = [];
foreach (['CVE', 'BME', 'EEE', 'MIE', 'PEEM'] as $dept) {
    $deptLabs = parseDeptFile($SCRATCH . $deptFiles[$dept]);
    $pbProjects = parsePlaybook($SCRATCH . $pbFiles[$dept], $deptHeadings[$dept]);
    [$matched, $unmatched] = reconcileProjects($deptLabs, $pbProjects);
    $reconciled[$dept] = $matched;
    foreach ($unmatched as $u) {
        $unmatchedAll[] = $dept . ': ' . $u['title'];
    }
}
$report['unmatched_dept_projects'] = $unmatchedAll;

// =============================================================================
// PART 4: Insert research_units + research_unit_members.
// =============================================================================

$insertUnit = $pdo->prepare(
    'INSERT INTO research_units (faculty_id, department_id, unit_type_id, name, slug, overview, status, published_at, display_order)
     VALUES (:faculty_id, :dept_id, :type_id, :name, :slug, :overview, :status, :published_at, :display_order)'
);
$insertUnitMember = $pdo->prepare(
    'INSERT INTO research_unit_members (research_unit_id, staff_id, membership_role, display_order) VALUES (:unit_id, :staff_id, :role, :order)'
);
$checkUnitMember = $pdo->prepare('SELECT COUNT(*) FROM research_unit_members WHERE research_unit_id = :unit_id AND staff_id = :staff_id');

$labUnitId = []; // index in $labs => research_units.id
$labDefaultLead = []; // index in $labs => lab-level default team-lead name (dept file), for project-level fallback
$labDefaultMembers = [];
$order = 0;
foreach ($labs as $i => $lab) {
    $order++;
    $slugBase = slugify($lab['name']);
    $existing = $pdo->prepare('SELECT id FROM research_units WHERE slug = :slug LIMIT 1');
    $existing->execute(['slug' => $slugBase]);
    $unitId = $existing->fetchColumn();
    if ($unitId !== false) {
        $labUnitId[$i] = (int) $unitId;
    } else {
        $slug = uniqueSlug($pdo, 'research_units', $slugBase, 280);
        $isPublished = $lab['status'] === 'published';
        $insertUnit->execute([
            'faculty_id' => $facultyId,
            'dept_id' => $DEPT[$lab['dept']],
            'type_id' => $unitTypeIds[$lab['type']],
            'name' => $lab['name'],
            'slug' => $slug,
            'overview' => $lab['overview'],
            'status' => $lab['status'],
            'published_at' => $isPublished ? date('Y-m-d H:i:s') : null,
            'display_order' => $order,
        ]);
        $unitId = (int) $pdo->lastInsertId();
        $labUnitId[$i] = $unitId;
        if ($isPublished) {
            $report['research_units_published']++;
        } else {
            $report['research_units_draft']++;
        }
    }

    // Team lead + other members (only for labs where a lead/members list is known).
    $leadName = $lab['lead'] ?? null;
    $memberNames = $lab['members'] ?? [];
    if (($lab['dept_lab_name'] ?? null) !== null && $leadName === null) {
        // Pull lab-level lead/members straight from the dept file (first lab row).
        $deptLabs = parseDeptFile($SCRATCH . $deptFiles[$lab['dept']]);
        foreach ($deptLabs as $dl) {
            if ($dl['name'] === $lab['dept_lab_name']) {
                $leadName = $dl['lead'];
                $memberNames = $dl['members'];
                break;
            }
        }
    }
    $labDefaultLead[$i] = $leadName;
    $labDefaultMembers[$i] = $memberNames;

    $roster = [];
    if ($leadName) {
        $roster[$leadName] = 'lead';
    }
    foreach ($memberNames as $m) {
        if (!isset($roster[$m])) {
            $roster[$m] = 'researcher';
        }
    }
    $mo = 0;
    foreach ($roster as $name => $role) {
        $sid = matchLeadStaffId($name, $leadTokens);
        if ($sid === null) {
            continue; // research_unit_members requires a staff_id; unidentified names are skipped here (captured at project level instead).
        }
        $checkUnitMember->execute(['unit_id' => $unitId, 'staff_id' => $sid]);
        if ((int) $checkUnitMember->fetchColumn() > 0) {
            continue;
        }
        $insertUnitMember->execute(['unit_id' => $unitId, 'staff_id' => $sid, 'role' => $role, 'order' => $mo++]);
    }
}

// =============================================================================
// PART 5: Insert projects, project_research_units, project_members, milestones.
// =============================================================================

$insertProject = $pdo->prepare(
    'INSERT INTO projects (lead_department_id, title, slug, summary, project_status, start_date, end_date, publication_status, published_at)
     VALUES (:dept_id, :title, :slug, :summary, "ongoing", "2026-01-01", "2030-12-31", "published", :published_at)'
);
$insertProjectUnit = $pdo->prepare('INSERT INTO project_research_units (project_id, research_unit_id, is_lead_unit) VALUES (:project_id, :unit_id, 1)');
$insertProjectMember = $pdo->prepare(
    'INSERT INTO project_members (project_id, staff_id, external_member_name, project_role, display_order) VALUES (:project_id, :staff_id, :ext_name, :role, :order)'
);
$insertMilestone = $pdo->prepare(
    'INSERT INTO project_milestones (project_id, sequence_number, title, expected_output, timeline_text, target_level, lead_name_text, lead_staff_id, student_names_text, status)
     VALUES (:project_id, :seq, :title, :output, :timeline, :level, :lead_text, :lead_staff_id, :students, "planned")'
);
$checkMilestone = $pdo->prepare('SELECT COUNT(*) FROM project_milestones WHERE project_id = :project_id AND sequence_number = :seq');

$allProjectIds = [];
foreach ($labs as $i => $lab) {
    if ($lab['status'] !== 'published' || empty($lab['pb_heading'])) {
        continue;
    }
    $unitId = $labUnitId[$i];
    $deptCode = $lab['dept'];
    $projects = array_filter($reconciled[$deptCode], fn($p) => $p['pb_lab'] === $lab['pb_heading']);
    foreach ($projects as $p) {
        $slugBase = slugify($p['title']);
        $existing = $pdo->prepare('SELECT id FROM projects WHERE slug = :slug LIMIT 1');
        $existing->execute(['slug' => $slugBase]);
        $projectId = $existing->fetchColumn();
        if ($projectId !== false) {
            $projectId = (int) $projectId;
            $report['projects_skipped_existing']++;
        } else {
            $slug = uniqueSlug($pdo, 'projects', $slugBase, 320);
            $insertProject->execute([
                'dept_id' => $DEPT[$deptCode],
                'title' => mb_substr($p['title'], 0, 300),
                'slug' => $slug,
                'summary' => $p['description'],
                'published_at' => date('Y-m-d H:i:s'),
            ]);
            $projectId = (int) $pdo->lastInsertId();
            $report['projects_created']++;

            $checkPU = $pdo->prepare('SELECT COUNT(*) FROM project_research_units WHERE project_id = :pid AND research_unit_id = :uid');
            $checkPU->execute(['pid' => $projectId, 'uid' => $unitId]);
            if ((int) $checkPU->fetchColumn() === 0) {
                $insertProjectUnit->execute(['project_id' => $projectId, 'unit_id' => $unitId]);
            }

            // Members: lead (from dept-file match, falling back to the lab's
            // default lead) as principal_investigator, then other members.
            $memberOrder = 0;
            $seen = [];
            $leadName = $p['lead'] ?? $labDefaultLead[$i] ?? ($lab['lead'] ?? null);
            $memberPool = $p['members'] ?: ($labDefaultMembers[$i] ?? []);
            $allNames = [];
            if ($leadName) {
                $allNames[$leadName] = 'principal_investigator';
            }
            foreach ($memberPool as $m) {
                if (!isset($allNames[$m])) {
                    $allNames[$m] = 'researcher';
                }
            }
            foreach ($allNames as $name => $role) {
                $key = strtolower($name);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $sid = matchLeadStaffId($name, $leadTokens);
                $insertProjectMember->execute([
                    'project_id' => $projectId,
                    'staff_id' => $sid,
                    'ext_name' => $sid ? null : mb_substr($name, 0, 200),
                    'role' => $role,
                    'order' => $memberOrder++,
                ]);
            }
        }
        $allProjectIds[] = $projectId;

        foreach ($p['milestones'] as $m) {
            $checkMilestone->execute(['project_id' => $projectId, 'seq' => $m['seq']]);
            if ((int) $checkMilestone->fetchColumn() > 0) {
                continue;
            }
            $leadStaffId = matchLeadStaffId($m['lead'], $leadTokens);
            $insertMilestone->execute([
                'project_id' => $projectId,
                'seq' => $m['seq'],
                'title' => mb_substr($m['title'], 0, 300),
                'output' => $m['output'],
                'timeline' => $m['timeline'] ? mb_substr($m['timeline'], 0, 100) : null,
                'level' => implode(',', $m['level_set']),
                'lead_text' => $m['lead'] ? mb_substr($m['lead'], 0, 200) : null,
                'lead_staff_id' => $leadStaffId,
                'students' => $m['students'] ? mb_substr($m['students'], 0, 500) : null,
            ]);
            $report['milestones_created']++;
        }
    }
}

// =============================================================================
// PART 6: Research themes + project_themes (keyword-based tagging).
// =============================================================================

$themes = [
    'Renewable Energy & Power Systems' => ['solar', 'wind turbine', 'biogas', 'biomass', 'bioenergy', 'biofuel', 'bioethanol', 'geothermal', 'renewable energy', 'renewable-energy', 'microgrid', 'mini-grid', 'mini grid', 'electric vehicle', 'e-motorcycle', 'ev charging', 'battery energy', 'energy storage', 'transformer', 'power distribution', 'power system', 'photovoltaic', 'mppt', 'wind energy', 'electric motor'],
    'AI & Digital Technologies' => ['artificial intelligence', ' ai ', 'ai-', 'ai_', 'machine learning', 'digital twin', 'computer vision', 'internet of things', ' iot', 'robot', 'automation', 'cybersecurity', 'virtual reality', 'deep learning', 'neural network', 'data science', 'bioinformatics', 'software platform', 'dashboard', 'mobile app', 'digitalization', 'digitalisation'],
    'Sustainable Materials & Circular Economy' => ['waste', 'recycl', 'composite material', 'geopolymer', 'biochar', 'circular economy', 'agro-waste', 'agro waste', 'sustainable material', '3d printing', '3d-printed', 'biodegradable', 'valorisation', 'valorization'],
    'Water & Environmental Engineering' => ['water treatment', 'water quality', 'flood', 'stormwater', 'storm water', 'hydrology', 'wastewater', 'drainage', 'catchment', 'environmental pollution', 'air quality', 'climate resilien', 'produced water', 'rainwater'],
    'Biomedical & Health Technologies' => ['medical', 'biomedical', 'health', 'diagnos', 'patient', 'clinical', 'disease', 'malaria', 'biosensor', 'prosthetic', 'orthotic', 'rehabilitation', 'imaging', 'mri', 'fetal', 'epidemi', 'aflatoxin'],
    'Petroleum & GeoEnergy' => ['petroleum', ' oil ', 'oil-', 'crude oil', ' gas ', 'reservoir', 'drilling', 'wellbore', 'hydrocarbon', 'geoenergy', 'geo-energy', 'mining', 'mineral', 'ccus', 'geothermal'],
];
$insertTheme = $pdo->prepare(
    'INSERT INTO research_themes (name, slug, description, status, published_at) VALUES (:name, :slug, :description, "published", NOW())'
);
$themeIds = [];
foreach ($themes as $name => $keywords) {
    $slug = slugify($name);
    $existing = $pdo->prepare('SELECT id FROM research_themes WHERE slug = :slug LIMIT 1');
    $existing->execute(['slug' => $slug]);
    $id = $existing->fetchColumn();
    if ($id === false) {
        $insertTheme->execute(['name' => $name, 'slug' => $slug, 'description' => 'Projects across FAST\'s five departments addressing ' . strtolower($name) . '.']);
        $id = (int) $pdo->lastInsertId();
        $report['themes_created']++;
    }
    $themeIds[$name] = (int) $id;
}

$projectTextStmt = $pdo->prepare('SELECT title, summary FROM projects WHERE id = :id');
$checkProjectTheme = $pdo->prepare('SELECT COUNT(*) FROM project_themes WHERE project_id = :pid AND research_theme_id = :tid');
$insertProjectTheme = $pdo->prepare('INSERT INTO project_themes (project_id, research_theme_id) VALUES (:pid, :tid)');
foreach (array_unique($allProjectIds) as $pid) {
    $projectTextStmt->execute(['id' => $pid]);
    $row = $projectTextStmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        continue;
    }
    $haystack = ' ' . strtolower($row['title'] . ' ' . ($row['summary'] ?? '')) . ' ';
    foreach ($themes as $name => $keywords) {
        $hit = false;
        foreach ($keywords as $kw) {
            if (str_contains($haystack, $kw)) {
                $hit = true;
                break;
            }
        }
        if (!$hit) {
            continue;
        }
        $tid = $themeIds[$name];
        $checkProjectTheme->execute(['pid' => $pid, 'tid' => $tid]);
        if ((int) $checkProjectTheme->fetchColumn() > 0) {
            continue;
        }
        $insertProjectTheme->execute(['pid' => $pid, 'tid' => $tid]);
        $report['project_themes_created']++;
    }
}

// =============================================================================
// PART 7: project_sdgs -- only the 2 EEE projects with explicit SDG
// citations in their source description (dept_EEE.txt).
// =============================================================================

// Matched by a distinctive substring of the playbook title (the projects
// table stores the playbook's own project title, which is worded slightly
// differently -- and in one case, less formally capitalised -- than the
// dept_EEE.txt description text that carries the SDG citation).
$sdgProjects = [
    '%Biogas and Biofertilizer Production System%' => ['2', '7', '12', '13'],
    '%Solar-Battery Energy Hub for Rural Health Facilities%' => ['3', '7', '9', '10', '13'],
];
$checkProjectSdg = $pdo->prepare('SELECT COUNT(*) FROM project_sdgs WHERE project_id = :pid AND sdg_id = :sid');
$insertProjectSdg = $pdo->prepare('INSERT INTO project_sdgs (project_id, sdg_id, is_primary) VALUES (:pid, :sid, 0)');
foreach ($sdgProjects as $titleLike => $codes) {
    $stmt = $pdo->prepare('SELECT id FROM projects WHERE title LIKE :t LIMIT 1');
    $stmt->execute(['t' => $titleLike]);
    $pid = $stmt->fetchColumn();
    if ($pid === false) {
        $report['unmatched_dept_projects'][] = 'SDG-tag project not found: ' . $titleLike;
        continue;
    }
    foreach ($codes as $code) {
        if (!isset($sdgIds[$code])) {
            continue;
        }
        $checkProjectSdg->execute(['pid' => $pid, 'sid' => $sdgIds[$code]]);
        if ((int) $checkProjectSdg->fetchColumn() > 0) {
            continue;
        }
        $insertProjectSdg->execute(['pid' => $pid, 'sid' => $sdgIds[$code]]);
        $report['project_sdgs_created']++;
    }
}

fwrite(STDOUT, "Research agenda content seeded.\n");
fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
