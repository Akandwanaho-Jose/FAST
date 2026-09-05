<?php

declare(strict_types=1);

/**
 * Additive enrichment of staff profiles from the "staff Bio.docx" source
 * (D:\FAST-CONTENT\Fast Website\staff profiles\staff Bio.docx).
 *
 * The CSV-driven handbook batch (bin/seed-handbook-batch.php) remains the
 * authoritative source for identity fields (name, slug, email, department,
 * position, office room). This script only ADDS biographical richness on
 * top of staff rows that batch already created, matched primarily by
 * institutional_email (case-insensitive) with a first/last-name fallback.
 *
 * It never touches office_location_id / office_room, and never creates new
 * staff rows -- people present only in the DOCX (no matching CSV/DB row)
 * are intentionally left untouched; see the run report for that list.
 *
 * Idempotent: re-running skips scalar fields that already carry a
 * non-empty/non-placeholder value, and skips relation inserts
 * (staff_qualifications / staff_expertise / staff_links) for a staff row
 * that already has any rows in that relation.
 */

use FastWebsite\Core\Database;

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/** @var Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$connection = $database->connection();

/**
 * @param list<array{qualification:string,institution:?string,field_of_study:?string,country:?string,completion_year:?int}> $qualifications
 * @param list<string> $expertise
 * @param list<array{type:string,url:string}> $links
 */
function enrichmentEntry(
    string $label,
    ?string $email,
    string $firstNameFallback,
    string $lastNameFallback,
    ?string $shortBiography = null,
    ?string $biography = null,
    array $qualifications = [],
    array $expertise = [],
    ?string $researchSummary = null,
    ?string $publicPhone = null,
    ?string $consultationHours = null,
    array $links = []
): array {
    return [
        'label' => $label,
        'email' => $email,
        'first_name_fallback' => $firstNameFallback,
        'last_name_fallback' => $lastNameFallback,
        'short_biography' => $shortBiography,
        'biography' => $biography,
        'qualifications' => $qualifications,
        'expertise' => $expertise,
        'research_summary' => $researchSummary,
        'public_phone' => $publicPhone,
        'consultation_hours' => $consultationHours,
        'links' => $links,
    ];
}

function qual(string $qualification, ?string $institution = null, ?string $fieldOfStudy = null, ?string $country = null, ?int $year = null): array
{
    return [
        'qualification' => $qualification,
        'institution' => $institution,
        'field_of_study' => $fieldOfStudy,
        'country' => $country,
        'completion_year' => $year,
    ];
}

$entries = [
    enrichmentEntry(
        'Ms. Addah Kyarisiima',
        'akyarisiima@must.ac.ug',
        'Addah',
        'Kyarisiima',
        shortBiography: "A researcher and engineering professional with expertise in innovative engineering, electronic communication, grant writing , research ethics, and project planning and management. She is meticulous, and believes in collaborative leadership, commitment to continuous learning and knowledge sharing. She is passionate about inclusion, people's welfare and the environment.",
        qualifications: [
            qual('PhD EEE - track', 'UoN'),
            qual('Msc Electrical and Electronics Engineering (EEE)', 'UoN'),
            qual('B. Computer Engineering', 'MUST & UMN - Twin Cities'),
        ],
        expertise: [], // source text lost its bullet separators during extraction; see report
        researchSummary: 'Energy efficiency, power quality and noise in renewable energy systems and powerlines. Gender, Disability and Social Inclusion in Innovative engineering'
    ),
    enrichmentEntry(
        'Mr. Mackenzie Tuhirirwe',
        'tmackenzie@must.ac.ug',
        'Mackenzie',
        'Tuhirirwe',
        biography: 'Mackenzie Tuhirirwe is an Assistant Lecturer in Electrical and Electronics Engineering and the FAST Research Projects Coordinator at Mbarara University of Science and Technology. A computer and materials engineer, technology educator and roboticist, he has more than 10 years of experience in robotics education, robotics design and development, embedded systems, product development and applied research. Since 2014, he has designed practical robotics programmes, educational robots and STEM content, trained educators and mentored more than 1,000 learners and student innovators. His current work connects robotics and electronics with advanced manufacturing, sustainable materials, digital traceability, medical-device innovation and semiconductor-wafer manufacturing pathways.',
        qualifications: [
            qual('PhD Candidate in Materials Engineering', 'Busitema University', year: null), // ongoing (2024-Present)
            qual('MSc in Materials Engineering', 'Busitema University', year: 2023),
            qual('BSc in Computer Engineering—Hardware', 'Mbarara University of Science and Technology', year: 2015),
            qual('Executive Certificate in Mastering Design Thinking', 'Massachusetts Institute of Technology', year: 2022),
        ]
    ),
    enrichmentEntry(
        'Eng. Dr. Johnes Obungoloch',
        'jobungoloch@must.ac.ug',
        'Johnes',
        'Obungoloch',
        shortBiography: "Eng. Dr. Johnes Obungoloch is a registered biomedical engineer and Senior Lecturer at Mbarara University of Science and Technology, Uganda. He has more than 15 years of experience in biomedical engineering, medical technology innovation, research leadership, and capacity building. His research focuses on developing affordable, accessible, and sustainable healthcare technologies for resource-limited settings, particularly low-field and point-of-care magnetic resonance imaging. His work encompasses MRI system development, medical image acquisition and reconstruction, artificial intelligence–based image enhancement, healthcare technology management, and medical-device quality and safety. He has contributed to establishing biomedical engineering training programmes and strengthening research capacity in Uganda and across Africa. Dr. Obungoloch has led multidisciplinary and international research collaborations and currently serves in professional leadership and global health advisory roles. He is also actively involved in supervising, training, and mentoring undergraduate and postgraduate students through research, innovation, and professional-development activities.",
        qualifications: [
            qual('Bsc. Electrical Engineering', 'Makerere University', country: 'Uganda'),
            qual('Msc. Biomedical Engineering', 'Keele University', country: 'United Kingdom'),
            qual('PhD Biomedical Engineering', 'Pennsylvania State University', country: 'USA'),
        ],
        expertise: [
            'Medical technology planning, acquisition and management',
            'Appropriate and sustainable medical technology development and innovations for low-resource settings',
            'Renewable Energy Applications in Healthcare',
            'Research supervision and mentorship',
            'Training and Capacity Development in healthcare technologies',
            'Low field MRI system development',
            'Translation and implementation of healthcare technologies in low resource settings',
        ],
        researchSummary: "Eng. Dr. Obungoloch's research interests focus on developing affordable, accessible, and sustainable healthcare technologies for low-resource settings. His work particularly covers low-field and point-of-care MRI, MRI system development, medical image reconstruction and enhancement, and the application of artificial intelligence to medical imaging and diagnosis. He is also interested in healthcare technology management, medical-device quality and safety, point-of-care diagnostic technologies, renewable-energy applications in healthcare, and translating engineering innovations into practical clinical solutions.",
        publicPhone: '256775646496',
        links: [
            ['type' => 'orcid', 'url' => 'https://orcid.org/0000-0003-4322-3557'],
            ['type' => 'google_scholar', 'url' => 'https://scholar.google.com/citations?user=0uUHd0AAAAAJ&hl=en'],
        ]
    ),
    enrichmentEntry(
        'Dr. Martha Mulerwa',
        'mmulerwa@must.ac.ug',
        'Martha',
        'Mulerwa',
        shortBiography: 'Dr. Martha holds a PhD in Biomedical Engineering where her research focus was in Medical Devices Manufacturing for LMIS. Martha is a lecturer of Biomedical Engineering at MUST and a consultant in health systems strengthening, health facilities infrastructure planning, implementation and research and development of medical devices. She has supported health systems strengthening, procurement and implementation of medical devices with partners including WHO, UNICEF and UNDP. She is pioneering translation of medical innovations from the laboratory to the market, she is co-leading a venture that is offering both original equipment manufacture and contractual manufacturing. She is a certified lead auditor with ISO 13485: Quality Management Systems for Medical Devices.',
        qualifications: [
            qual('BSc. Biomedical Engineering', 'Makerere University'),
            qual('Ph.D Biomedical Engineering', 'University of Salford, Manchester'),
        ],
        expertise: [
            'Health infrastructure strengthening',
            'Health facilities needs assessment, planning, specifications development, quality assurance and procurement support.',
            'Medical Devices classification, research and development, and manufacturing.',
            // Fourth bullet omitted: "Quality assurance-quality auditing. - Academic training-
            // Capacity development in Health Technology Management (HTM)" -- the source text
            // is missing a separator and appears to run 2-3 distinct concepts together; see report.
        ],
        researchSummary: "Martha's research focus spans the R&D and manufacturing of medical devices that are context for low and middle income settings. Her interests: she is also interested in understanding the medical devices regulatory pathway and how it impacts medical devices R&D, manufacturing and post market surveillance."
    ),
    enrichmentEntry(
        'Mr. Eugene Bizimana',
        'ebizimana@must.ac.ug',
        'Eugene',
        'Bizimana',
        shortBiography: 'Eugene Bizimana is a Ugandan biomedical engineer, educator, and researcher with experience in healthcare technology, medical equipment management, and academic teaching.',
        qualifications: [
            qual('BACHELOR OS BIOMEDICAL ENGINEERING'),
            qual('MASTERS OF SCIENCE IN Biomedical engineering'),
        ],
        expertise: [
            'Neonatal Intensive care Equipment',
            'Maternal and child health equipment, Community engagement and interhsip placement training, senior Volunteer',
        ],
        researchSummary: 'Neonatal Intensive care Equipment; Maternal and child health equipment, Community engagement'
    ),
    enrichmentEntry(
        'Ms. Josephine Najjobyo',
        'jnajjobyo@must.ac.ug',
        'Josephine',
        'Najjobyo',
        shortBiography: "Josephine is a researcher and academic supervisor in the Department of Civil and Building Services Engineering. Her work turns overlooked agricultural and industrial by products, ie coffee husks, sugarcane bagasse, fly ash and plastic waste into high perfomance construction materials, addressing infrastructure resilience in East Africa and beyond. She is a PhD candidate whose doctoral research develops coffee-husk derived nanomaterials to enhance asphalt perfomance. Her published work spans pavement engineering, geotechnical stabilization, water treatment and construction economics. Her collaborative work on construction project economics has examined the effects on cost estimation inacurracy and payment delays on project perfomance across Nigeria and Ugandan context. She has led a comprehesive review on nanomaterials in asphalt mixtures synthesizing two decades of global research through a novel SARA fractionation framework, and a landmark study on structural reform in Ugandan engineering education to close the critical skills mismatch for graduating engineers. Beyond her research, Najjobyo actively supervises undergraduate final year projects, shaping the next generation of civil engineers across the region. Her inter-disciplinary, solutions driven approach grounded in local materials and regional challenges, yet engaged with international standards and frameworks - positions her at the forefront of sustainable research in sub-saharan Africa.",
        qualifications: [
            qual('PhD Eng (On going)', 'Kabale University'),
            qual('MSc. Civil Engineering (Structures)', 'Universite Ferhat Abbass de Setif, Algeria'),
            qual('BSc. Civil Engineering', 'Universite Ferhat Abbass de Setif, Algeria'),
        ],
        expertise: [
            'Sustainable & waste derived construction materials',
            'Finite Element analysis',
            'structural analysis and design',
            'Pavement & Asphalt Engineering',
            'Geotechnical Engineering',
            'Construction project economics & risks',
            'water treatment',
            'engineering education policy & reform',
            'research supervision & systematic review methodology',
        ],
        researchSummary: 'Sustainable, waste-derived construction materials, computational and A.I driven engineering solutions, Infrastructure economics, risk and policy',
        publicPhone: '256750083602',
        consultationHours: 'Wednesday (9-11am), Thursday (2pm-5pm)',
        links: [
            ['type' => 'orcid', 'url' => 'https://orcid.org/0009-0001-0444-3787'],
        ]
    ),
    enrichmentEntry(
        'Mr. Hosea Mutanda',
        'hmutanda@must.ac.ug',
        'Hosea',
        'Mutanda',
        shortBiography: 'Hosea Mutanda is a Civil and Environmental Engineering academic and researcher specialising in Water resources and Environmental engineering.',
        qualifications: [
            qual('B. Engineering (Civil)', 'NDU'),
            qual('Msc in Water Management', 'ACEWM, AAU'),
        ],
        expertise: [
            'Water Resource Planning & Management',
            'Drinking water quality',
            'Hydrological modelling',
            'WASH Programs',
        ],
        researchSummary: 'Water Management, Hydrological Modelling, Water treatment, Wastewater treatment, Water pollution, Soil stabilization',
        publicPhone: '256777258667',
        links: [
            ['type' => 'orcid', 'url' => 'https://orcid.org/0000-0002-0288-5493'],
            ['type' => 'google_scholar', 'url' => 'https://scholar.google.com/citations?hl=en&user=DpKZk78AAAAJ'],
        ]
    ),
    enrichmentEntry(
        'Mr. Paul Tiboti',
        'ptiboti@must.ac.ug',
        'Paul',
        'Tiboti',
        shortBiography: 'Tiboti Paul is a civil/structural engineer, researcher, and PhD candidate with over 12 years of professional experience in infrastructure development, structural engineering, watershed management, construction supervision, and higher education. He has served in technical and leadership roles, including Project Engineer at ACORD Uganda, Assistant Estates Officer at Ndejje University, and Civil/Structural Engineering Task Force Committee Member with the Uganda Protestant Medical Bureau (UPMB). His expertise encompasses structural design and analysis, project management, quality assurance, catchment restoration, water resources management, and sustainable construction practices. In addition to professional practice, Tiboti is an experienced academic with a teaching career of over 12 years. He is also an active researcher with several peer-reviewed publications in structural and material science.',
        qualifications: [
            qual('PhD Eng (On going) Kabale University'), // no clean separator in source; kept whole
            qual('MSc Civil Engineering (Structures Option)', 'Makerere University'),
            qual('BSc Civil Engineering', 'Ndejje University'),
            qual('Dip. Water Engineering', 'Uganda Technical College, Elgon'),
        ],
        expertise: [
            'Infrastructure development',
            'structural Engineering Design',
            'watershed management',
            'construction supervision',
            'higher education',
        ],
        researchSummary: 'Structural Engineering; Geopolymer Cement; Alternative Cementitious Materials; Waste Valorization; Durability and Mechanical Performance of Materials; Soil Stabilization; Climate-Resilient Infrastructure; Environmental Sustainability',
        publicPhone: '256782308876',
        links: [
            ['type' => 'orcid', 'url' => 'https://orcid.org/0000-0001-6551-1344'],
        ]
    ),
    enrichmentEntry(
        'Eng. Dr. Denis Bbosa',
        'dbbosa@must.ac.ug',
        'Denis',
        'Bbosa',
        shortBiography: 'Eng. Dr. Denis Bbosa is a Registered Engineer (RE) with the Uganda Engineers Registration Board (ERB), Senior Lecturer in Mechanical and Industrial Engineering and Deputy Dean of the Faculty of Applied Sciences and Technology (FAST) at Mbarara University of Science and Technology (MUST), Uganda. He holds a PhD in Mechanical Engineering, a Master of Engineering in Energy Systems Engineering, and an MSc in Agricultural and Biosystems Engineering/Biorenewable Resources and Technology from Iowa State University, USA, as well as a First-Class BSc in Agricultural Engineering from Makerere University, Uganda. He brings over 15+ years of combined experience spanning academia, research and innovation, engineering consultancy, industry, entrepreneurship, and field implementation. His multidisciplinary research focuses on developing practical and scalable engineering solutions for sustainable energy, water, environment, agriculture, materials, and infrastructure challenges, particularly in resource-constrained and developing-country settings.',
        // No explicit "Qualifications:" or "Research interests:" label was given for this
        // entry (only embedded in the biography prose) -- left unparsed rather than guessed.
        expertise: [
            'Renewable & Hybrid Energy Systems',
            'Energy Systems Analysis & Optimization',
            'Techno-Economic & Life-Cycle Assessment',
            'Circular Economy & Waste Valorization',
            'Bioenergy, Biofuels & Biorefineries',
            'Sustainable Materials & Nanotechnology',
            'Agricultural & Post-Harvest Engineering',
            'Environmental & Water Engineering',
            'Engineering for Sustainable Development',
            'Research, Innovation & Project Leadership',
        ]
    ),
];

$report = [
    'matched' => [],
    'not_matched' => [],
    'fields_updated' => [],
    'fields_skipped_not_empty' => [],
    'qualifications_inserted' => [],
    'qualifications_skipped_existing' => [],
    'expertise_inserted' => [],
    'expertise_skipped_existing' => [],
    'links_inserted' => [],
    'links_skipped_existing' => [],
];

function slugifyExpertise(string $value): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii ?: $value));
    return trim($slug, '-');
}

/** @return array<string,mixed>|null */
function matchStaffRow(PDO $connection, ?string $email, string $firstName, string $lastName): ?array
{
    if ($email !== null) {
        $statement = $connection->prepare(
            'SELECT * FROM staff
             WHERE deleted_at IS NULL
               AND (LOWER(institutional_email) = LOWER(:email1) OR LOWER(alternative_email) = LOWER(:email2))
             LIMIT 2'
        );
        $statement->execute(['email1' => $email, 'email2' => $email]);
        $rows = $statement->fetchAll();
        if (count($rows) === 1) {
            return $rows[0];
        }
    }

    $statement = $connection->prepare(
        'SELECT * FROM staff
         WHERE deleted_at IS NULL
           AND LOWER(first_name) = LOWER(:first_name)
           AND LOWER(last_name) = LOWER(:last_name)
         LIMIT 2'
    );
    $statement->execute(['first_name' => $firstName, 'last_name' => $lastName]);
    $rows = $statement->fetchAll();
    if (count($rows) === 1) {
        return $rows[0];
    }

    return null;
}

$connection->beginTransaction();

try {
    foreach ($entries as $entry) {
        $staff = matchStaffRow($connection, $entry['email'], $entry['first_name_fallback'], $entry['last_name_fallback']);
        if ($staff === null) {
            $report['not_matched'][] = $entry['label'];
            continue;
        }

        $matchedBy = $entry['email'] !== null
            && strcasecmp((string) ($staff['institutional_email'] ?? ''), $entry['email']) === 0
            ? 'email' : 'name_fallback';
        $report['matched'][] = [
            'label' => $entry['label'],
            'staff_id' => (int) $staff['id'],
            'slug' => $staff['slug'],
            'matched_by' => $matchedBy,
        ];

        $staffId = (int) $staff['id'];
        $updates = [];
        $skipped = [];

        $currentShortBio = trim((string) ($staff['short_biography'] ?? ''));
        $isPlaceholderBio = $currentShortBio === '' || str_contains($currentShortBio, ' serves as ');
        if ($entry['short_biography'] !== null) {
            if ($isPlaceholderBio) {
                $updates['short_biography'] = $entry['short_biography'];
            } else {
                $skipped[] = 'short_biography (already has human-authored content)';
            }
        }

        if ($entry['biography'] !== null) {
            $currentBiography = trim((string) ($staff['biography'] ?? ''));
            if ($currentBiography === '') {
                $updates['biography'] = $entry['biography'];
            } else {
                $skipped[] = 'biography (already populated)';
            }
        }

        if ($entry['research_summary'] !== null) {
            $updates['research_summary'] = $entry['research_summary'];
        }

        if ($entry['public_phone'] !== null) {
            $currentPhone = trim((string) ($staff['public_phone'] ?? ''));
            if ($currentPhone === '') {
                $updates['public_phone'] = $entry['public_phone'];
            } else {
                $skipped[] = 'public_phone (already populated)';
            }
        }

        if ($entry['consultation_hours'] !== null) {
            $updates['consultation_hours'] = $entry['consultation_hours'];
        }

        if ($updates !== []) {
            $setClauses = [];
            $parameters = ['id' => $staffId];
            foreach ($updates as $column => $value) {
                $setClauses[] = "$column = :$column";
                $parameters[$column] = $value;
            }
            $connection->prepare(
                'UPDATE staff SET ' . implode(', ', $setClauses) . ' WHERE id = :id'
            )->execute($parameters);
            $report['fields_updated'][] = ['label' => $entry['label'], 'staff_id' => $staffId, 'fields' => array_keys($updates)];
        }
        if ($skipped !== []) {
            $report['fields_skipped_not_empty'][] = ['label' => $entry['label'], 'staff_id' => $staffId, 'fields' => $skipped];
        }

        // Qualifications -- only insert if this staff row has none yet (idempotent, non-clobbering).
        if ($entry['qualifications'] !== []) {
            $countStatement = $connection->prepare('SELECT COUNT(*) FROM staff_qualifications WHERE staff_id = :id');
            $countStatement->execute(['id' => $staffId]);
            $existingCount = (int) $countStatement->fetchColumn();
            if ($existingCount === 0) {
                $order = 1;
                $insert = $connection->prepare(
                    'INSERT INTO staff_qualifications
                        (staff_id, qualification, field_of_study, institution, country, completion_year, display_order)
                     VALUES (:staff_id, :qualification, :field_of_study, :institution, :country, :completion_year, :display_order)'
                );
                foreach ($entry['qualifications'] as $q) {
                    $insert->execute([
                        'staff_id' => $staffId,
                        'qualification' => $q['qualification'],
                        'field_of_study' => $q['field_of_study'],
                        'institution' => $q['institution'],
                        'country' => $q['country'],
                        'completion_year' => $q['completion_year'],
                        'display_order' => $order++,
                    ]);
                }
                $report['qualifications_inserted'][] = ['label' => $entry['label'], 'staff_id' => $staffId, 'count' => count($entry['qualifications'])];
            } else {
                $report['qualifications_skipped_existing'][] = ['label' => $entry['label'], 'staff_id' => $staffId, 'existing_count' => $existingCount];
            }
        }

        // Expertise -- only insert if this staff row has no expertise links yet.
        if ($entry['expertise'] !== []) {
            $countStatement = $connection->prepare('SELECT COUNT(*) FROM staff_expertise WHERE staff_id = :id');
            $countStatement->execute(['id' => $staffId]);
            $existingCount = (int) $countStatement->fetchColumn();
            if ($existingCount === 0) {
                $order = 1;
                foreach ($entry['expertise'] as $name) {
                    $name = trim($name);
                    if ($name === '') {
                        continue;
                    }
                    $findStatement = $connection->prepare(
                        'SELECT id FROM expertise_areas WHERE LOWER(name) = LOWER(:name) LIMIT 1'
                    );
                    $findStatement->execute(['name' => $name]);
                    $areaId = (int) $findStatement->fetchColumn();
                    if ($areaId < 1) {
                        $slug = slugifyExpertise($name);
                        $insertArea = $connection->prepare(
                            'INSERT INTO expertise_areas (name, slug, status) VALUES (:name, :slug, "active")'
                        );
                        $insertArea->execute(['name' => $name, 'slug' => $slug]);
                        $areaId = (int) $connection->lastInsertId();
                    }
                    $linkStatement = $connection->prepare(
                        'INSERT INTO staff_expertise (staff_id, expertise_area_id, is_primary, display_order)
                         VALUES (:staff_id, :expertise_area_id, :is_primary, :display_order)'
                    );
                    $linkStatement->execute([
                        'staff_id' => $staffId,
                        'expertise_area_id' => $areaId,
                        'is_primary' => $order === 1 ? 1 : 0,
                        'display_order' => $order++,
                    ]);
                }
                $report['expertise_inserted'][] = ['label' => $entry['label'], 'staff_id' => $staffId, 'count' => count($entry['expertise'])];
            } else {
                $report['expertise_skipped_existing'][] = ['label' => $entry['label'], 'staff_id' => $staffId, 'existing_count' => $existingCount];
            }
        }

        // Links -- only insert if this staff row has no links yet.
        if ($entry['links'] !== []) {
            $countStatement = $connection->prepare('SELECT COUNT(*) FROM staff_links WHERE staff_id = :id');
            $countStatement->execute(['id' => $staffId]);
            $existingCount = (int) $countStatement->fetchColumn();
            if ($existingCount === 0) {
                $order = 1;
                $insert = $connection->prepare(
                    'INSERT INTO staff_links (staff_id, link_type, label, url, display_order)
                     VALUES (:staff_id, :link_type, NULL, :url, :display_order)'
                );
                foreach ($entry['links'] as $link) {
                    $insert->execute([
                        'staff_id' => $staffId,
                        'link_type' => $link['type'],
                        'url' => $link['url'],
                        'display_order' => $order++,
                    ]);
                }
                $report['links_inserted'][] = ['label' => $entry['label'], 'staff_id' => $staffId, 'count' => count($entry['links'])];
            } else {
                $report['links_skipped_existing'][] = ['label' => $entry['label'], 'staff_id' => $staffId, 'existing_count' => $existingCount];
            }
        }
    }

    $connection->commit();
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    fwrite(STDERR, 'Staff bio enrichment failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
