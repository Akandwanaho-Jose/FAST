<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/**
 * Reconciliation seed against the "FACULTY OF APPLIED SCIENCES AND TECHNOLOGY
 * (FAST) WEBSITE INFORMATION GUIDE — AUGUST 2026", the authoritative document
 * supplied by the user to replace paraphrased/drifted content and fill gaps.
 *
 * This script is idempotent: it UPDATEs existing rows in place wherever a
 * corresponding row already exists (matched by stable slug, or by the sole
 * non-gallery content section on a page, or by a settings_json marker for
 * newly-introduced structured sections), and only INSERTs where content is
 * genuinely new (the Resources page, the clubs/professional-bodies card
 * lists). No staff/staff_departments/staff_positions rows are touched.
 *
 * Photos are explicitly out of scope (the source document's <Photo>
 * placeholders are skipped throughout), as are any other placeholders the
 * source itself left blank (e.g. "President: Student Student", "Join Here -
 * Google form" on every club) — no fabricated names/URLs are introduced for
 * those.
 */

/** @var Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$pdo = $database->connection();

$esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$paragraphs = static fn (array $items) => implode('', array_map(
    static fn (string $item): string => '<p>' . htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
    $items
));
$list = static fn (array $items) => '<ul>' . implode('', array_map(
    static fn (string $item): string => '<li>' . htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>',
    $items
)) . '</ul>';
/** @param array<int, array{name: string, description: string}> $items */
$cards = static fn (array $items) => implode('', array_map(
    static fn (array $item): string => '<h4>' . htmlspecialchars($item['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</h4><p>' . htmlspecialchars($item['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
    $items
));

$pdo->beginTransaction();
try {
    // ------------------------------------------------------------------
    // 1. Department overviews + "Research areas" lists.
    //    biomedical-engineering and electrical-and-electronics-engineering
    //    already match the source document (paragraphs + <h3>Research
    //    areas</h3><ul>...) and are left untouched. civil-engineering,
    //    mechanical-engineering and petroleum-engineering-and-environmental-management
    //    currently hold an unrelated HOD-essay-style overview from an older
    //    handbook seed; they are fully replaced here with the source
    //    document's actual wording, in the same overview+research-list shape
    //    already established by the other two departments (the department
    //    detail view only renders the `overview` field — there is no
    //    dedicated "research areas" column/section, confirmed against
    //    app/Views/public/departments/show.php).
    // ------------------------------------------------------------------
    $departments = [
        'civil-engineering' => [
            'name' => 'Department of Civil and Building Services Engineering',
            'overview' => [
                'The Department of Civil and Building Services Engineering is dedicated to producing highly competent civil engineers capable of designing, constructing, and managing sustainable infrastructure that supports national and regional development. The Department combines engineering theory with practical training to address challenges related to transportation, water resources, environmental management, structural engineering, and urban development.',
                'Students acquire competencies in structural analysis, geotechnical engineering, construction management, water resources engineering, transportation engineering, public health engineering, surveying, and environmental sustainability. Through extensive laboratory work, field training, and industry placements, graduates develop the practical skills required to design safe, resilient, and environmentally responsible infrastructure.',
                'Research within the Department focuses on sustainable construction materials, climate-resilient infrastructure, smart cities, water and sanitation systems, disaster risk reduction, and infrastructure resilience.',
            ],
            'research' => ['Structural Engineering', 'Water Resources Engineering', 'Transportation Engineering', 'Geotechnical Engineering', 'Construction Management', 'Environmental Engineering', 'Public Health Engineering', 'Sustainable Infrastructure', 'Sustainable materials', 'Smart Cities', 'Remote sensing and GIS for surveying', 'Artificial Intelligence for infrastructure solutions'],
        ],
        'mechanical-engineering' => [
            'name' => 'Department of Mechanical and Industrial Engineering',
            'overview' => [
                'The Department of Mechanical and Industrial Engineering prepares engineers with the technical expertise and practical skills required to design, manufacture, optimize, and maintain engineering systems that support industrial growth and sustainable development. The Department combines strong engineering science with practical training to produce graduates capable of solving complex engineering challenges across manufacturing, energy, transportation, agriculture, healthcare, and emerging industries.',
                'The Department emphasizes competence-based learning through modern engineering laboratories, workshops, industrial training, project-based learning, and research. Students develop expertise in machine design, manufacturing systems, thermodynamics, fluid mechanics, materials engineering, production engineering, industrial automation, and sustainable engineering technologies.',
                'Through research and innovation, the Department supports the development of locally appropriate engineering solutions, prototype development, advanced manufacturing technologies, additive manufacturing (3D printing), renewable energy systems, and industrial productivity improvement.',
            ],
            'research' => ['Advanced Manufacturing', 'Industrial Automation', 'Materials Engineering', 'Renewable Energy Systems', 'Product Design', 'Additive Manufacturing', 'Robotics and Mechatronics', 'Sustainable Engineering', 'Industrial Systems Optimization'],
        ],
        'petroleum-engineering-and-environmental-management' => [
            'name' => 'Department of Energy, Mineral and Petroleum Engineering',
            'overview' => [
                "The Department of Energy, Mineral and Petroleum Engineering prepares engineers with the knowledge and technical expertise required to support the sustainable exploration, development, processing, and management of energy and mineral resources. The Department contributes to Uganda's industrialization agenda by producing graduates capable of addressing national priorities in petroleum, mining, renewable energy, environmental sustainability, and emerging nuclear technologies.",
                'The Department integrates engineering, geosciences, environmental management, and technological innovation to prepare graduates for careers in petroleum engineering, mineral processing, energy systems, geoscience, and environmental management. Students gain practical experience through laboratory training, fieldwork, industry placements, and applied research.',
                "With Uganda's expanding energy and mineral sectors, the Department is strengthening its research capacity in petroleum engineering, renewable energy technologies, mineral processing, geochemistry, geographic information systems (GIS), environmental monitoring, and emerging nuclear science applications. It also plays a strategic role in supporting national initiatives such as the proposed National Nuclear Fuel Resources Analytical Laboratory (NNFRAL) at MUST.",
            ],
            'research' => ['Petroleum Engineering', 'Renewable Energy Systems', 'Mineral Processing', 'Geochemistry', 'Geographic Information Systems (GIS)', 'Environmental Engineering', 'Energy Policy and Sustainability', 'Nuclear Science and Technology', 'Resource Exploration and Management'],
        ],
    ];
    $departmentUpdate = $pdo->prepare('UPDATE departments SET name = :name, overview = :overview WHERE slug = :slug AND deleted_at IS NULL');
    foreach ($departments as $slug => $data) {
        $overview = $paragraphs($data['overview']) . '<h3>Research areas</h3>' . $list($data['research']);
        $departmentUpdate->execute(['name' => $data['name'], 'overview' => $overview, 'slug' => $slug]);
    }

    // ------------------------------------------------------------------
    // 2. Programme overviews. The five undergraduate (Bachelor's) overviews
    //    are currently an older "handbook" paraphrase unrelated to the
    //    source document (most likely re-applied by a later run of
    //    bin/seed-undergraduate-handbook.php / undergraduate-handbook-2025-2026.php
    //    after deans-office-content-2026.php had already set the correct
    //    text) -- restored here to the source document's actual wording.
    //    career_opportunities for these five, plus MSc/PhD Biomedical
    //    Engineering, were spot-checked against the source and already
    //    match verbatim; left untouched. MSc Mechanical Engineering
    //    (proposed/draft) has no career_opportunities in the source either;
    //    left NULL, not invented.
    // ------------------------------------------------------------------
    $programmeOverviews = [
        'bachelor-of-biomedical-engineering' => 'The Bachelor of Biomedical Engineering programme prepares engineers to design, develop, manage, and maintain medical technologies that improve healthcare delivery. Students receive training in medical instrumentation, clinical engineering, artificial intelligence, medical imaging, digital health, rehabilitation engineering, biomaterials, biomechanics, and healthcare technology management.',
        'bachelor-of-mechanical-and-industrial-engineering' => 'This programme develops engineers with competencies in machine design, manufacturing systems, industrial automation, production engineering, thermodynamics, fluid mechanics, and sustainable engineering. Graduates are equipped to improve industrial productivity and develop innovative engineering solutions.',
        'bachelor-of-electrical-and-electronics-engineering' => 'The programme provides comprehensive training in electrical power systems, electronics, telecommunications, embedded systems, computer engineering, automation, and renewable energy technologies. Students develop practical skills through laboratory work, engineering design, and industry placements.',
        'bachelor-of-science-in-civil-and-building-services-engineering' => 'The Bachelor of Civil Engineering programme prepares graduates to design, construct, and manage sustainable infrastructure including buildings, roads, bridges, water supply systems, transportation networks, and environmental engineering projects.',
        'bachelor-of-petroleum-engineering-and-environmental-management' => 'This interdisciplinary programme develops professionals capable of addressing challenges in petroleum engineering, energy production, environmental protection, resource management, and sustainable development.',
    ];
    $programmeOverviewUpdate = $pdo->prepare('UPDATE programmes SET overview = :overview WHERE slug = :slug AND deleted_at IS NULL');
    foreach ($programmeOverviews as $slug => $overview) {
        $programmeOverviewUpdate->execute(['overview' => $paragraphs([$overview]), 'slug' => $slug]);
    }

    // Electrical bachelor's career_opportunities: the source gives this
    // programme's careers as flowing prose (not a list, unlike the other
    // four programmes). A list derived from the prose's own areas -- no
    // job titles invented -- keeps the site's established list pattern.
    $electricalCareers = [
        'Power Generation, Transmission and Distribution',
        'Renewable Energy and Rural Electrification',
        'Electrical Installation and Maintenance',
        'Telecommunications and Electronics',
        'Embedded Systems and Industrial Automation',
        'Instrumentation and Manufacturing',
        'Building Services',
        'Biomedical Equipment in Hospitals',
        'Transport Systems',
        'Technical Sales, Consultancy and Project Management',
        'Government Agencies and Research',
        'Teaching and Entrepreneurship (e.g. Solar Energy, Electrical Contracting)',
    ];
    $pdo->prepare('UPDATE programmes SET career_opportunities = :careers WHERE slug = :slug AND deleted_at IS NULL')
        ->execute(['careers' => $list($electricalCareers), 'slug' => 'bachelor-of-electrical-and-electronics-engineering']);

    // ------------------------------------------------------------------
    // 3. hero_slides: verified against the source's 3 slide titles/captions.
    //    They already match; re-asserted here defensively/idempotently so
    //    this script does not depend on deans-office-content-2026.php
    //    having run most recently.
    // ------------------------------------------------------------------
    $slides = [
        0 => ["Building Tomorrow's Engineers, Innovators and Technology Leaders", 'Welcome to FAST'],
        1 => ['Where Innovation Meets Real-World Impact', 'Innovation & Research'],
        2 => ['State-of-the-Art Engineering Laboratories', 'Laboratories'],
    ];
    $slideUpdate = $pdo->prepare('UPDATE hero_slides SET title = :title, caption = :caption WHERE display_order = :sort');
    foreach ($slides as $sort => [$title, $caption]) {
        $slideUpdate->execute(['title' => $title, 'caption' => $caption, 'sort' => $sort]);
    }

    // ------------------------------------------------------------------
    // 4. Helper: find or create the page id for a slug (all pages touched
    //    below are expected to already exist except 'resources').
    // ------------------------------------------------------------------
    $pageId = static function (string $slug) use ($pdo): ?int {
        $s = $pdo->prepare('SELECT id FROM pages WHERE slug = :slug AND deleted_at IS NULL LIMIT 1');
        $s->execute(['slug' => $slug]);
        $id = $s->fetchColumn();
        return $id === false ? null : (int) $id;
    };

    // Find the single "main" non-gallery content section for a page, or a
    // specific one by a case-insensitive heading needle (used for the two
    // vision-and-mission cards). Falls back to inserting a new section if
    // none is found, matched for future idempotency by a settings_json
    // marker.
    $upsertMainSection = static function (
        int $page,
        string $key,
        string $sectionType,
        string $heading,
        string $subheading,
        string $body,
        int $sort,
        ?string $headingNeedle = null
    ) use ($pdo): void {
        $existingId = null;
        if ($headingNeedle !== null) {
            $find = $pdo->prepare('SELECT id FROM page_sections WHERE page_id = :page AND LOWER(heading) LIKE :needle LIMIT 1');
            $find->execute(['page' => $page, 'needle' => '%' . mb_strtolower($headingNeedle) . '%']);
            $id = $find->fetchColumn();
            $existingId = $id === false ? null : (int) $id;
        } else {
            $find = $pdo->prepare("SELECT id FROM page_sections WHERE page_id = :page AND section_type <> 'gallery' ORDER BY display_order ASC LIMIT 1");
            $find->execute(['page' => $page]);
            $id = $find->fetchColumn();
            $existingId = $id === false ? null : (int) $id;
        }

        if ($existingId !== null) {
            $update = $pdo->prepare('UPDATE page_sections SET section_type = :type, heading = :heading, subheading = :subheading, body = :body, display_order = :sort, is_visible = 1 WHERE id = :id');
            $update->execute(['type' => $sectionType, 'heading' => $heading, 'subheading' => $subheading, 'body' => $body, 'sort' => $sort, 'id' => $existingId]);
            return;
        }

        $marker = json_encode(['source' => 'fast-info-guide-2026', 'key' => $key], JSON_UNESCAPED_SLASHES);
        $insert = $pdo->prepare('INSERT INTO page_sections (page_id, section_type, heading, subheading, body, settings_json, display_order, is_visible) VALUES (:page, :type, :heading, :subheading, :body, :marker, :sort, 1)');
        $insert->execute(['page' => $page, 'type' => $sectionType, 'heading' => $heading, 'subheading' => $subheading, 'body' => $body, 'marker' => $marker, 'sort' => $sort]);
    };

    // Insert-or-update a *new*, structured section (clubs / professional
    // bodies card lists / resource links) matched by a settings_json marker
    // so re-running this script updates it in place rather than duplicating.
    $upsertMarkedSection = static function (
        int $page,
        string $key,
        string $sectionType,
        ?string $heading,
        ?string $subheading,
        ?string $body,
        ?string $buttonLabel,
        ?string $buttonUrl,
        ?string $settingsExtra,
        int $sort
    ) use ($pdo): void {
        $marker = json_encode(
            array_filter(['source' => 'fast-info-guide-2026', 'key' => $key, 'items' => $settingsExtra !== null ? json_decode($settingsExtra, true) : null]),
            JSON_UNESCAPED_SLASHES
        );
        $find = $pdo->prepare('SELECT id FROM page_sections WHERE page_id = :page AND JSON_UNQUOTE(JSON_EXTRACT(settings_json, "$.key")) = :key LIMIT 1');
        $find->execute(['page' => $page, 'key' => $key]);
        $id = $find->fetchColumn();
        $params = [
            'page' => $page, 'type' => $sectionType, 'heading' => $heading, 'subheading' => $subheading,
            'body' => $body, 'button_label' => $buttonLabel, 'button_url' => $buttonUrl, 'marker' => $marker, 'sort' => $sort,
        ];
        if ($id === false) {
            $insert = $pdo->prepare('INSERT INTO page_sections (page_id, section_type, heading, subheading, body, button_label, button_url, settings_json, display_order, is_visible) VALUES (:page, :type, :heading, :subheading, :body, :button_label, :button_url, :marker, :sort, 1)');
            $insert->execute($params);
            return;
        }
        $updateParams = $params;
        unset($updateParams['page']);
        $updateParams['id'] = (int) $id;
        $update = $pdo->prepare('UPDATE page_sections SET section_type = :type, heading = :heading, subheading = :subheading, body = :body, button_label = :button_label, button_url = :button_url, settings_json = :marker, display_order = :sort, is_visible = 1 WHERE id = :id');
        $update->execute($updateParams);
    };

    // ------------------------------------------------------------------
    // 5. Faculty Overview: insert the paragraph listing the 5 departments,
    //    which the current section is genuinely missing (everything else
    //    already matches the source verbatim).
    // ------------------------------------------------------------------
    if (($id = $pageId('faculty-overview')) !== null) {
        $upsertMainSection($id, 'overview', 'image_text', 'About the Faculty of Applied Sciences and Technology', 'About FAST', $paragraphs([
            "The Faculty of Applied Sciences and Technology (FAST) at Mbarara University of Science and Technology (MUST) is the University's premier engineering and technology faculty, dedicated to producing highly competent engineers, innovators, researchers, and technology leaders capable of addressing real-world challenges through science, engineering, and innovation. Established to support Uganda's industrialization agenda and national development priorities, FAST provides high-quality education, cutting-edge research, practical training, community engagement within a competence-based learning environment.",
            'Located at the MUST Kihumuro Campus, the Faculty serves as a hub for engineering education, applied research, technology development, and innovation. Our programmes integrate strong scientific foundations with hands-on laboratory training, industry engagement, entrepreneurship, and multidisciplinary collaboration to prepare graduates who are not only job seekers but also job creators capable of developing locally relevant and globally competitive solutions.',
            'FAST currently comprises five academic departments: Department of Biomedical Sciences and Engineering; Department of Mechanical and Industrial Engineering; Department of Electrical and Electronics Engineering; Department of Civil and Building Services Engineering; Department of Energy, Mineral and Petroleum Engineering.',
            'The Faculty offers accredited undergraduate programmes in Biomedical Engineering, Electrical and Electronics Engineering, Mechanical and Industrial Engineering, Civil Engineering, and Petroleum Engineering and Environmental Management, while progressively expanding its portfolio of postgraduate programmes to meet emerging national and regional needs in engineering, healthcare technologies, energy, and applied sciences.',
            'FAST is distinguished by its modern engineering laboratories established through the Higher Education, Science and Technology (HEST) Project, supported by the Government of Uganda through the Ministry of Education and Sports and the African Development Bank. These state-of-the-art facilities provide students and researchers with opportunities for practical learning, experimentation, prototype development, product innovation, and multidisciplinary research across engineering disciplines.',
            "Research and innovation are central to the Faculty's mission. FAST actively promotes interdisciplinary research that addresses challenges in healthcare, manufacturing, energy, infrastructure, digital technologies, environmental sustainability, and industrial development. Through strong collaborations with government, industry, development partners, and international universities, the Faculty translates research into technologies, products, services, and policies that improve lives and drive socio-economic transformation.",
            'As the engineering arm of MUST, FAST embraces Competence-Based Engineering Education (CBEE), ensuring that graduates acquire the knowledge, practical skills, professional ethics, leadership abilities, and entrepreneurial mindset required by modern industry. Students benefit from project-based learning, industrial training, community engagement, innovation challenges, and exposure to real-world engineering problems throughout their academic journey.',
            "Today, the Faculty of Applied Sciences and Technology stands as one of Uganda's emerging centres of excellence in engineering education and applied research, committed to building people, advancing knowledge, and transforming communities through science, technology, engineering, and innovation.",
        ]), 10);
    }

    // ------------------------------------------------------------------
    // 6. Dean's Message: restore the missing sentence in paragraph 4.
    // ------------------------------------------------------------------
    if (($id = $pageId('deans-message')) !== null) {
        $upsertMainSection($id, 'dean-message', 'rich_text', 'Welcome to FAST', 'From the Dean', $paragraphs([
            'It is my great pleasure to welcome you to the Faculty of Applied Sciences and Technology (FAST) at Mbarara University of Science and Technology (MUST); a dynamic and forward-looking community dedicated to excellence in engineering education, applied research, innovation, and technology-driven solutions.',
            "At FAST, we believe that engineering is more than a profession; it is a powerful catalyst for transforming lives, strengthening communities, and advancing sustainable development. Our mission is to develop highly competent, ethical, and innovative graduates who possess the knowledge, practical skills, and leadership qualities needed to address today's complex challenges and shape tomorrow's technologies.",
            'The Faculty offers high-quality undergraduate and postgraduate programmes across Biomedical Engineering, Mechanical and Industrial Engineering, Electrical and Electronics Engineering, Civil Engineering, and Energy, Mineral and Petroleum Engineering. Through our competence-based educational approach, state-of-the-art laboratories, industry partnerships, and multidisciplinary research environment, we provide students with opportunities to learn by doing, innovate with purpose, and translate ideas into impactful solutions.',
            'Research and innovation are at the heart of everything we do. Our staff and students are actively engaged in developing technologies that address pressing challenges in healthcare, infrastructure, manufacturing, energy, digital transformation, artificial intelligence, environmental sustainability, and industrial development. We are committed to creating an ecosystem where scientific discovery, entrepreneurship, and collaboration flourish, enabling our graduates and researchers to contribute meaningfully to Uganda, Africa, and the global community. We are strengthening postgraduate education, expanding research laboratories, establishing vibrant research groups, deepening industry engagement, and building strategic national and international partnerships. Our goal is to position FAST as a leading centre of excellence in engineering, applied sciences, and technological innovation.',
            'Whether you are a prospective student, current student, researcher, member of staff, industry partner, alumnus, or visitor, I warmly invite you to become part of our journey. Together, we will continue to build knowledge, inspire innovation, develop transformative technologies, and produce engineering solutions that improve lives and contribute to sustainable socio-economic development.',
            'Thank you for visiting our Faculty. We look forward to learning, innovating, and transforming the future together.',
        ]), 10);
    }

    // Vision and Mission: verified verbatim against the source already; no
    // changes, left untouched (not re-written to avoid pointless UPDATEs).

    // ------------------------------------------------------------------
    // 7. Feature pages: replace the paraphrased body text with the source
    //    document's actual paragraphs, and relabel each bullet list with
    //    the source's own heading text (previously a generic "Key areas").
    // ------------------------------------------------------------------
    if (($id = $pageId('industrial-training')) !== null) {
        $upsertMainSection($id, 'main', 'rich_text', 'Bridging Classroom Learning with Professional Practice', 'Industrial Training',
            $paragraphs([
                "Industrial Training is a core component of the Faculty's Competence-Based Engineering Education (CBEE) model. The programme provides students with structured experiential learning opportunities that enable them to apply classroom knowledge to real engineering and technological challenges within industry, healthcare institutions, government agencies, and the private sector.",
                'Students undertake supervised placements in manufacturing industries, construction companies, hospitals, energy companies, ICT firms, research institutions, government agencies, and engineering consultancies. During these placements, they develop practical technical competencies, professional ethics, teamwork, communication, project management, and problem-solving skills while gaining exposure to modern engineering technologies and workplace practices.',
                'The Faculty works closely with industry partners to ensure that industrial training aligns with programme learning outcomes and labour market needs. Through continuous supervision and mentorship, students graduate with the practical experience and confidence required for successful professional careers.',
            ]) . '<h3>Key Features</h3>' . $list(['Structured industrial attachments', 'Industry supervision and mentorship', 'Competency-based assessment', 'Professional skills development', 'Exposure to modern engineering technologies', 'Career networking and employability enhancement']),
            10);
    }

    if (($id = $pageId('community-outreach')) !== null) {
        $upsertMainSection($id, 'main', 'rich_text', 'Engineering Solutions for Community Transformation', 'Community Outreach',
            $paragraphs([
                'Community engagement is central to the mission of the Faculty of Applied Sciences and Technology. Through community outreach programmes, students and staff apply engineering knowledge, scientific research, and technological innovation to address real societal challenges while improving livelihoods and promoting sustainable development.',
                'Our outreach activities include healthcare technology support, engineering design projects, renewable energy initiatives, water and sanitation interventions, infrastructure improvement, STEM education, assistive technology development, digital health innovations, environmental conservation, and engineering awareness programmes.',
                'Students actively participate in community-based learning where they identify community needs, co-design practical solutions with local stakeholders, and implement sustainable engineering interventions. This approach strengthens professional competence while fostering social responsibility, innovation, and ethical leadership.',
            ]) . '<h3>Our Outreach Focus</h3>' . $list(['Healthcare technology support', 'STEM education and mentorship', 'Water and sanitation initiatives', 'Assistive technologies', 'Environmental sustainability', 'Community innovation projects', 'Engineering awareness programmes']),
            10);
    }

    if (($id = $pageId('partnerships')) !== null) {
        $upsertMainSection($id, 'main', 'rich_text', 'Collaborating for Innovation, Research and Impact', 'Partnerships',
            $paragraphs([
                'The Faculty believes that meaningful partnerships are essential for advancing engineering education, research, innovation, and community development. We actively collaborate with government ministries, regulatory agencies, industry, healthcare institutions, professional bodies, development partners, research organizations, and universities across Uganda and internationally.',
                'These partnerships provide opportunities for collaborative research, staff and student exchange, industrial training, technology transfer, curriculum development, innovation, professional development, and joint resource mobilization.',
                'The Faculty continues to expand strategic collaborations that strengthen engineering education and contribute to national development priorities in healthcare, infrastructure, manufacturing, energy, digital transformation, environmental sustainability, and emerging technologies.',
            ]) . '<h3>Our Partners Include</h3>' . $list(['Government Ministries and Agencies', 'Engineering Development and Innovation Centre (EDIC)', 'Hospitals and Healthcare Institutions', 'Manufacturing and Engineering Industries', 'Professional Engineering Bodies', 'National and International Universities', 'Research Institutions', 'Development Partners', 'Innovation Hubs and Technology Companies']),
            10);
    }

    if (($id = $pageId('engineering-education')) !== null) {
        $upsertMainSection($id, 'main', 'rich_text', 'Excellence in Competence-Based Engineering Education', 'Engineering Education',
            $paragraphs([
                'The Faculty of Applied Sciences and Technology is committed to delivering high-quality engineering education that prepares graduates for the rapidly evolving world of science, technology, and innovation. Our programmes are delivered using a Competence-Based Engineering Education (CBEE) approach that emphasizes practical learning, problem-solving, innovation, entrepreneurship, and professional competence.',
                'Students learn through interactive lectures, laboratory practicals, design projects, simulations, industrial training, community engagement, and multidisciplinary research. This learner-centred approach ensures that graduates possess not only strong technical knowledge but also critical thinking, leadership, communication, teamwork, and lifelong learning skills.',
                'To further strengthen engineering education, the Faculty has established the Engineering Education Centre (EEC), which coordinates curriculum development, industrial training, engineering pedagogy, accreditation, professional development, industry engagement, and continuous quality improvement. The Centre promotes educational innovation and ensures that our programmes remain responsive to emerging technologies, industry needs, and national development priorities.',
            ]) . '<h3>Our Educational Approach</h3>' . $list(['Competence-Based Engineering Education (CBEE)', 'Project-Based Learning', 'Problem-Based Learning', 'Modern Engineering Laboratories', 'Industry and Hospital Engagement', 'Innovation and Entrepreneurship', 'Digital Learning Technologies', 'Professional Skills Development', 'Continuous Curriculum Improvement', 'International Collaboration']),
            10);
    }

    // Student Life: main section keeps only the general intro paragraphs
    // from the source (the 5 clubs move into their own structured cards
    // section below, matching the source's own "Clubs and Associations"
    // list-of-name+description structure rather than a flat bullet list).
    $studentLifePageId = $pageId('student-life');
    if ($studentLifePageId !== null) {
        $upsertMainSection($studentLifePageId, 'main', 'rich_text', 'Experience Engineering Beyond the Classroom', 'Student Life',
            $paragraphs([
                'At the Faculty of Applied Sciences and Technology (FAST), student life extends far beyond lectures and laboratories. We are committed to providing a vibrant, inclusive, and engaging learning environment where students develop academically, professionally, socially, and personally.',
                'Our students are encouraged to participate in innovation challenges, engineering competitions, leadership programmes, community outreach, industrial visits, professional societies, sports, entrepreneurship, research groups, and student-led organizations. These activities complement classroom learning by nurturing teamwork, creativity, communication, leadership, and problem-solving skills that are essential for successful engineering careers.',
                'Through active participation in clubs, professional associations, and extracurricular activities, students build lifelong friendships, expand professional networks, and develop the confidence to become future engineers, innovators, entrepreneurs, and technology leaders.',
            ]),
            10);
    }

    // Professional Bodies: main section keeps only the general intro
    // paragraphs; the 10 named organizations move into a structured cards
    // section below (each with its own source-given description, which a
    // flat bullet list cannot represent).
    $professionalBodiesPageId = $pageId('professional-bodies');
    if ($professionalBodiesPageId !== null) {
        $upsertMainSection($professionalBodiesPageId, 'main', 'rich_text', 'Building Future Professional Engineers', 'Professional Bodies',
            $paragraphs([
                'The Faculty encourages students to actively participate in professional engineering organizations that provide opportunities for networking, mentorship, continuous professional development, leadership, and career advancement. Membership in professional bodies exposes students to industry best practices, ethical standards, emerging technologies, and professional certification pathways.',
                'Students are encouraged to join and actively participate in the following organizations:',
            ]),
            10);
    }

    if (($id = $pageId('student-mentorship-programme')) !== null) {
        $upsertMainSection($id, 'main', 'rich_text', 'Supporting Students to Learn, Lead, and Succeed', 'Student Mentorship Programme',
            $paragraphs([
                'The FAST Student Mentorship Programme is designed to support students throughout their academic journey by connecting them with experienced academic staff, industry professionals, alumni, and senior students. The programme provides guidance on academic success, career planning, research, innovation, entrepreneurship, leadership, and personal development.',
                'Students benefit from regular mentorship meetings, career talks, research guidance, industrial exposure, innovation challenges, and professional networking opportunities that prepare them for successful careers in engineering, technology, and applied sciences.',
            ]) . '<h3>Programme Objectives</h3>' . $list(['Support academic success and student retention', 'Guide students in career planning and professional development', 'Promote research, innovation, and entrepreneurship', 'Foster leadership, teamwork, and communication skills', 'Enhance student well-being and personal growth', 'Connect students with industry, alumni, and professional mentors']),
            10);
    }

    if (($id = $pageId('faculty-mentorship-programme')) !== null) {
        $upsertMainSection($id, 'main', 'rich_text', 'Developing Academic Excellence and Leadership', 'Faculty Mentorship Programme',
            $paragraphs([
                'The FAST Faculty Mentorship Programme supports the professional growth of academic, technical, and administrative staff by fostering a culture of collaboration, continuous learning, and shared leadership. The programme pairs early-career and developing staff with experienced mentors who provide guidance on teaching excellence, research, grant writing, postgraduate supervision, academic promotion, leadership, innovation, and professional development.',
                "The programme aims to build a vibrant academic community where staff are empowered to achieve their full potential while contributing to the Faculty's vision of excellence in education, research, innovation, and community engagement.",
            ]) . '<h3>Programme Objectives</h3>' . $list(['Support career progression and academic promotion', 'Strengthen research capacity and publication output', 'Enhance teaching and supervision skills', 'Promote grant writing and resource mobilization', 'Foster leadership and institutional service', 'Encourage interdisciplinary collaboration and mentorship', 'Support innovation, entrepreneurship, and industry engagement']),
            10);
    }

    if (($id = $pageId('meet-our-mentors')) !== null) {
        $upsertMainSection($id, 'main', 'rich_text', 'Guidance for every stage of the journey', 'Meet Our Mentors',
            $paragraphs([
                'Our mentors are experienced academics, researchers, industry professionals, and leaders who are committed to supporting the next generation of engineers, scientists, and innovators. They bring diverse expertise across engineering, applied sciences, healthcare technologies, research, innovation, and leadership, providing invaluable guidance and inspiration to both students and staff.',
                'Through one-on-one mentorship, group mentoring, seminars, career coaching, and professional development activities, our mentors help mentees navigate academic challenges, build successful careers, develop research programmes, and become leaders in their respective fields.',
            ])
            . '<h3>Our mentors provide guidance in</h3>' . $list(['Teaching and Learning Excellence', 'Research and Scientific Writing', 'Grant Proposal Development', 'Postgraduate Supervision', 'Innovation and Entrepreneurship', 'Engineering Design and Practice', 'Leadership and Academic Administration', 'Industry Engagement and Consultancy', 'Career Planning and Professional Development', 'Work–Life Balance and Personal Growth'])
            . $paragraphs([
                'We are proud of our growing community of mentors whose dedication continues to strengthen the Faculty and inspire excellence across every stage of the academic and professional journey.',
                'Interested in becoming a mentor or mentee? We welcome students, staff, alumni, and industry professionals to join our mentorship community and contribute to building the next generation of engineering leaders.',
            ]),
            10);
    }

    // ------------------------------------------------------------------
    // 8. Structured card lists: 5 student clubs, 10 professional bodies.
    //    settings_json is NOT read by any current public view (confirmed:
    //    only app/Validation/SiteContentValidator.php references it, for
    //    admin-form validation) -- so the repeating name+description items
    //    are rendered into `body` as <h4>name</h4><p>description</p> pairs
    //    to guarantee they actually appear on the live pages. A structured
    //    "items" array is additionally stored in settings_json for forward
    //    compatibility with any future admin-CMS card editor.
    //    The source document leaves "President: Student Student" and
    //    "Join Here - Google form" blank on every club; those placeholders
    //    are skipped entirely, not fabricated.
    // ------------------------------------------------------------------
    if ($studentLifePageId !== null) {
        $clubs = [
            ['name' => 'Biomedical Engineering Students Association', 'description' => 'Promotes academic excellence, innovation, research, and professional development in Biomedical Engineering through seminars, workshops, innovation projects, hospital visits, and community engagement.'],
            ['name' => 'Mechanical and Industrial Engineering Students Association', 'description' => 'Provides a platform for students to engage in engineering design competitions, industrial visits, manufacturing projects, entrepreneurship activities, and professional networking.'],
            ['name' => 'Electrical and Electronics Engineering Students Association', 'description' => 'Promotes learning in electronics, power systems, embedded systems, robotics, artificial intelligence, programming, and engineering innovation through technical workshops and competitions.'],
            ['name' => 'Civil Engineering Students Association', 'description' => 'Engages students in sustainable infrastructure development, construction projects, surveying activities, technical seminars, and professional networking with the construction industry.'],
            ['name' => 'Energy, Mineral and Petroleum Engineering Students Association', 'description' => 'Provides opportunities for students to explore emerging technologies in energy, petroleum, mining, environmental management, and sustainability through industry engagement and technical activities.'],
        ];
        // NOTE: the student-life page renders through page.php's dedicated
        // isStudentLifeLanding branch, which loops content sections and
        // prints only `body` -- it never prints a section's heading/
        // subheading (confirmed in app/Views/public/content/page.php). The
        // "Clubs and Associations" label is therefore baked into `body`
        // itself (as an <h3>) so it is actually visible on the live page,
        // even though the `heading` column also carries it for admin use.
        $upsertMarkedSection($studentLifePageId, 'clubs', 'cards', 'Clubs and Associations', 'Student Life', '<h3>Clubs and Associations</h3>' . $cards($clubs), null, null, json_encode($clubs, JSON_UNESCAPED_SLASHES), 20);
    }

    if ($professionalBodiesPageId !== null) {
        $bodies = [
            ['name' => 'Uganda Institution of Professional Engineers (UIPE)', 'description' => 'The national professional body for engineers in Uganda that promotes engineering excellence, ethical practice, professional development, and registration of engineers.'],
            ['name' => 'Engineers Registration Board (ERB Uganda)', 'description' => 'The statutory body responsible for regulating the engineering profession and registration of professional engineers in Uganda.'],
            ['name' => 'Institution of Engineering and Technology (IET)', 'description' => 'An internationally recognized professional engineering institution that supports education, professional certification, technical publications, networking, and lifelong learning.'],
            ['name' => 'Institute of Electrical and Electronics Engineers (IEEE)', 'description' => "The world's largest professional organization dedicated to advancing technology in electrical engineering, electronics, computer engineering, robotics, artificial intelligence, telecommunications, and related fields."],
            ['name' => 'American Society of Mechanical Engineers (ASME)', 'description' => 'Provides opportunities for professional development, engineering standards, technical resources, student competitions, and international networking for mechanical engineers.'],
            ['name' => 'American Society of Civil Engineers (ASCE)', 'description' => 'Supports students pursuing careers in civil engineering through professional development, technical resources, conferences, leadership programmes, and global collaboration.'],
            ['name' => 'Biomedical Engineering Society (BMES)', 'description' => 'Promotes education, research, innovation, and professional development in Biomedical Engineering through conferences, publications, networking, and student engagement.'],
            ['name' => 'International Federation for Medical and Biological Engineering (IFMBE)', 'description' => 'A global organization advancing biomedical engineering research, education, innovation, and healthcare technology development through international collaboration.'],
            ['name' => 'Society of Petroleum Engineers (SPE)', 'description' => 'Provides professional development, technical knowledge, leadership opportunities, and global networking for students interested in petroleum engineering and the energy sector.'],
            ['name' => 'Association of Energy Engineers (AEE)', 'description' => 'Supports education and professional development in energy engineering, renewable energy, sustainability, and energy management.'],
        ];
        $upsertMarkedSection($professionalBodiesPageId, 'professional-bodies-list', 'cards', 'Professional Engineering Organizations', 'Professional Bodies', $cards($bodies), null, null, json_encode($bodies, JSON_UNESCAPED_SLASHES), 20);
    }

    // ------------------------------------------------------------------
    // 9. New "Resources" page. The site already has a hardcoded top-nav
    //    "Resources" dropdown (app/Views/layouts/public.php) linking
    //    directly to /documents plus the identity.library_url /
    //    identity.elearning_url site settings -- that dropdown is NOT
    //    driven by the `pages` table and is left untouched here. This page
    //    is reachable at /resources via the generic catch-all page route
    //    (routes/web.php: GET /{slug} -> SiteContentPublicController::page).
    //    MUST Library / MUST e-Learning link URLs reuse the already-confirmed
    //    real URLs from site_settings (identity.library_url / identity.elearning_url).
    //    Scholarships and Grants have no URL anywhere in the source document
    //    or this codebase -- left with no button_url rather than invented;
    //    flagged in the report for a human to supply.
    // ------------------------------------------------------------------
    $pageUpsert = $pdo->prepare(
        'INSERT INTO pages (parent_page_id, title, slug, page_template, meta_description, display_order, show_in_navigation, status, published_at)
         VALUES (NULL, :title, :slug, "standard", :description, :sort, :nav, "published", NOW())
         ON DUPLICATE KEY UPDATE title = VALUES(title), meta_description = VALUES(meta_description), display_order = VALUES(display_order), status = "published", published_at = COALESCE(published_at, NOW()), deleted_at = NULL'
    );
    $pageUpsert->execute(['title' => 'Resources', 'slug' => 'resources', 'description' => 'MUST Library, eLearning, scholarships and grants for FAST students and staff.', 'sort' => 200, 'nav' => 0]);
    $resourcesPageId = $pageId('resources');

    if ($resourcesPageId !== null) {
        $library = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'identity.library_url' LIMIT 1")->fetchColumn();
        $elearning = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'identity.elearning_url' LIMIT 1")->fetchColumn();

        $resourceLinks = [
            ['key' => 'library', 'heading' => 'MUST Library', 'body' => '<p>Search electronic and library resources.</p>', 'button' => 'Visit MUST Library', 'url' => $library !== false && $library !== '' ? (string) $library : null],
            ['key' => 'elearning', 'heading' => 'MUST e-Learning', 'body' => '<p>Continue to the MUST virtual learning environment.</p>', 'button' => 'Visit MUST e-Learning', 'url' => $elearning !== false && $elearning !== '' ? (string) $elearning : null],
            ['key' => 'scholarships', 'heading' => 'Scholarships', 'body' => null, 'button' => null, 'url' => null],
            ['key' => 'grants', 'heading' => 'Grants', 'body' => null, 'button' => null, 'url' => null],
        ];
        foreach ($resourceLinks as $index => $link) {
            $upsertMarkedSection(
                $resourcesPageId,
                $link['key'],
                'call_to_action',
                $link['heading'],
                null,
                $link['body'],
                $link['url'] !== null ? $link['button'] : null,
                $link['url'],
                null,
                ($index + 1) * 10
            );
        }
    }

    $pdo->commit();
    fwrite(STDOUT, "Website Information Guide (August 2026) reconciliation complete.\n");
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}
