<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/**
 * Authoritative FAST website copy supplied by the Dean's Office, August 2026.
 *
 * This importer is intentionally idempotent: stable slugs, codes and section
 * source keys are updated in place, so it can be run again without duplicates.
 * Existing photographs, curricula, contacts and unrelated editor-created
 * sections are preserved.
 */

/** @var Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$pdo = $database->connection();

$paragraphs = static fn (array $items): string => implode('', array_map(
    static fn (string $item): string => '<p>' . htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
    $items
));
$list = static fn (array $items): string => '<ul>' . implode('', array_map(
    static fn (string $item): string => '<li>' . htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>',
    $items
)) . '</ul>';

$facultyOverview = [
    "The Faculty of Applied Sciences and Technology (FAST) at Mbarara University of Science and Technology (MUST) is the University's premier engineering and technology faculty, dedicated to producing highly competent engineers, innovators, researchers, and technology leaders capable of addressing real-world challenges through science, engineering, and innovation. Established to support Uganda's industrialization agenda and national development priorities, FAST provides high-quality education, cutting-edge research, practical training, and community engagement within a competence-based learning environment.",
    'Located at the MUST Kihumuro Campus, the Faculty serves as a hub for engineering education, applied research, technology development, and innovation. Our programmes integrate strong scientific foundations with hands-on laboratory training, industry engagement, entrepreneurship, and multidisciplinary collaboration to prepare graduates who are not only job seekers but also job creators capable of developing locally relevant and globally competitive solutions.',
    'The Faculty offers accredited undergraduate programmes in Biomedical Engineering, Electrical and Electronics Engineering, Mechanical and Industrial Engineering, Civil Engineering, and Petroleum Engineering and Environmental Management, while progressively expanding its portfolio of postgraduate programmes to meet emerging national and regional needs in engineering, healthcare technologies, energy, and applied sciences.',
    'FAST is distinguished by its modern engineering laboratories established through the Higher Education, Science and Technology (HEST) Project, supported by the Government of Uganda through the Ministry of Education and Sports and the African Development Bank. These state-of-the-art facilities provide students and researchers with opportunities for practical learning, experimentation, prototype development, product innovation, and multidisciplinary research across engineering disciplines.',
    "Research and innovation are central to the Faculty's mission. FAST actively promotes interdisciplinary research that addresses challenges in healthcare, manufacturing, energy, infrastructure, digital technologies, environmental sustainability, and industrial development. Through strong collaborations with government, industry, development partners, and international universities, the Faculty translates research into technologies, products, services, and policies that improve lives and drive socio-economic transformation.",
    'As the engineering arm of MUST, FAST embraces Competence-Based Engineering Education (CBEE), ensuring that graduates acquire the knowledge, practical skills, professional ethics, leadership abilities, and entrepreneurial mindset required by modern industry. Students benefit from project-based learning, industrial training, community engagement, innovation challenges, and exposure to real-world engineering problems throughout their academic journey.',
    "Today, the Faculty of Applied Sciences and Technology stands as one of Uganda's emerging centres of excellence in engineering education and applied research, committed to building people, advancing knowledge, and transforming communities through science, technology, engineering, and innovation.",
];

$deanMessage = [
    'It is my great pleasure to welcome you to the Faculty of Applied Sciences and Technology (FAST) at Mbarara University of Science and Technology (MUST); a dynamic and forward-looking community dedicated to excellence in engineering education, applied research, innovation, and technology-driven solutions.',
    "At FAST, we believe that engineering is more than a profession; it is a powerful catalyst for transforming lives, strengthening communities, and advancing sustainable development. Our mission is to develop highly competent, ethical, and innovative graduates who possess the knowledge, practical skills, and leadership qualities needed to address today's complex challenges and shape tomorrow's technologies.",
    'The Faculty offers high-quality undergraduate and postgraduate programmes across Biomedical Engineering, Mechanical and Industrial Engineering, Electrical and Electronics Engineering, Civil Engineering, and Energy, Mineral and Petroleum Engineering. Through our competence-based educational approach, state-of-the-art laboratories, industry partnerships, and multidisciplinary research environment, we provide students with opportunities to learn by doing, innovate with purpose, and translate ideas into impactful solutions.',
    'Research and innovation are at the heart of everything we do. Our staff and students are actively engaged in developing technologies that address pressing challenges in healthcare, infrastructure, manufacturing, energy, digital transformation, artificial intelligence, environmental sustainability, and industrial development. We are strengthening postgraduate education, expanding research laboratories, establishing vibrant research groups, deepening industry engagement, and building strategic national and international partnerships. Our goal is to position FAST as a leading centre of excellence in engineering, applied sciences, and technological innovation.',
    'Whether you are a prospective student, current student, researcher, member of staff, industry partner, alumnus, or visitor, I warmly invite you to become part of our journey. Together, we will continue to build knowledge, inspire innovation, develop transformative technologies, and produce engineering solutions that improve lives and contribute to sustainable socio-economic development.',
    'Thank you for visiting our Faculty. We look forward to learning, innovating, and transforming the future together.',
];

$departments = [
    'biomedical-engineering' => [
        'name' => 'Department of Biomedical Sciences and Engineering', 'short' => 'BSE',
        'overview' => [
            'The Department of Biomedical Sciences and Engineering is one of the flagship departments within the Faculty of Applied Sciences and Technology and a pioneer in Biomedical Engineering education in Uganda. The Department is committed to advancing healthcare through engineering, innovation, research, and technology development. By integrating engineering principles with medicine and biological sciences, the Department develops professionals capable of designing, developing, managing, and improving healthcare technologies that enhance patient care and strengthen health systems.',
            "The Department offers undergraduate and postgraduate programmes designed to equip students with competencies in medical devices, clinical engineering, artificial intelligence, medical imaging, digital health, rehabilitation engineering, biomechanics, biomaterials, healthcare technology management, and biomedical innovation. It is proud to host Uganda's first Master's Programme in Biomedical Engineering and a PhD programme in Biomedical Engineering.",
            'Students benefit from modern teaching laboratories, simulation facilities, clinical attachments, multidisciplinary research opportunities, and strong collaborations with hospitals, government agencies, industry, and international universities.',
        ],
        'research' => ['Artificial Intelligence in Healthcare','Medical Devices and Instrumentation','Digital Health','Medical Imaging','Rehabilitation Engineering','Clinical Engineering','Healthcare Technology Management','Biomedical Signal Processing','Virtual Reality and Simulation','Medical Robotics'],
    ],
    'mechanical-engineering' => [
        'name' => 'Department of Mechanical and Industrial Engineering', 'short' => 'MIE',
        'overview' => [
            'The Department of Mechanical and Industrial Engineering prepares engineers with the technical expertise and practical skills required to design, manufacture, optimize, and maintain engineering systems that support industrial growth and sustainable development. The Department combines strong engineering science with practical training to produce graduates capable of solving complex engineering challenges across manufacturing, energy, transportation, agriculture, healthcare, and emerging industries.',
            'The Department emphasizes competence-based learning through modern engineering laboratories, workshops, industrial training, project-based learning, and research. Students develop expertise in machine design, manufacturing systems, thermodynamics, fluid mechanics, materials engineering, production engineering, industrial automation, and sustainable engineering technologies.',
            'Through research and innovation, the Department supports locally appropriate engineering solutions, prototype development, advanced manufacturing technologies, additive manufacturing, renewable energy systems, and industrial productivity improvement.',
        ],
        'research' => ['Advanced Manufacturing','Industrial Automation','Materials Engineering','Renewable Energy Systems','Product Design','Additive Manufacturing','Robotics and Mechatronics','Sustainable Engineering','Industrial Systems Optimization'],
    ],
    'electrical-and-electronics-engineering' => [
        'name' => 'Department of Electrical and Electronics Engineering', 'short' => 'EEE',
        'overview' => [
            'The Department of Electrical and Electronics Engineering provides high-quality education and research in electrical, electronic, communication, computer, and embedded systems engineering. The Department prepares graduates to contribute to the design, development, operation, and maintenance of modern electrical infrastructure and intelligent technologies that drive digital transformation and industrial development.',
            'Students receive extensive practical training in electrical machines, power systems, renewable energy, telecommunications, embedded systems, automation, control engineering, electronics, and computer engineering. The Department promotes innovation through laboratory-based learning, industry engagement, engineering design projects, and multidisciplinary research.',
            'Research activities focus on smart energy systems, embedded technologies, Internet of Things, robotics, automation, telecommunications, artificial intelligence applications, and intelligent control systems.',
        ],
        'research' => ['Electrical Power Systems','Renewable Energy','Embedded Systems','Internet of Things (IoT)','Telecommunications','Automation and Control','Robotics','Artificial Intelligence','Computer Engineering'],
    ],
    'civil-engineering' => [
        'name' => 'Department of Civil and Building Services Engineering', 'short' => 'CBSE',
        'overview' => [
            'The Department of Civil and Building Services Engineering is dedicated to producing highly competent civil engineers capable of designing, constructing, and managing sustainable infrastructure that supports national and regional development. The Department combines engineering theory with practical training to address challenges related to transportation, water resources, environmental management, structural engineering, and urban development.',
            'Students acquire competencies in structural analysis, geotechnical engineering, construction management, water resources engineering, transportation engineering, public health engineering, surveying, and environmental sustainability. Through extensive laboratory work, field training, and industry placements, graduates develop the practical skills required to design safe, resilient, and environmentally responsible infrastructure.',
            'Research focuses on sustainable construction materials, climate-resilient infrastructure, smart cities, water and sanitation systems, disaster risk reduction, and infrastructure resilience.',
        ],
        'research' => ['Structural Engineering','Water Resources Engineering','Transportation Engineering','Geotechnical Engineering','Construction Management','Environmental Engineering','Public Health Engineering','Sustainable Infrastructure','Smart Cities'],
    ],
    'petroleum-engineering-and-environmental-management' => [
        'name' => 'Department of Energy, Mineral and Petroleum Engineering', 'short' => 'EMPE',
        'overview' => [
            "The Department of Energy, Mineral and Petroleum Engineering prepares engineers with the knowledge and technical expertise required to support the sustainable exploration, development, processing, and management of energy and mineral resources. The Department contributes to Uganda's industrialization agenda by producing graduates capable of addressing national priorities in petroleum, mining, renewable energy, environmental sustainability, and emerging nuclear technologies.",
            'The Department integrates engineering, geosciences, environmental management, and technological innovation. Students gain practical experience through laboratory training, fieldwork, industry placements, and applied research.',
            "With Uganda's expanding energy and mineral sectors, the Department is strengthening research capacity in petroleum engineering, renewable energy technologies, mineral processing, geochemistry, geographic information systems, environmental monitoring, and emerging nuclear science applications.",
        ],
        'research' => ['Petroleum Engineering','Renewable Energy Systems','Mineral Processing','Geochemistry','Geographic Information Systems (GIS)','Environmental Engineering','Energy Policy and Sustainability','Nuclear Science and Technology','Resource Exploration and Management'],
    ],
];

$programmes = [
    ['bachelor-of-biomedical-engineering','Bachelor of Biomedical Engineering','UG','biomedical-engineering','The Bachelor of Biomedical Engineering programme prepares engineers to design, develop, manage, and maintain medical technologies that improve healthcare delivery. Students receive training in medical instrumentation, clinical engineering, artificial intelligence, medical imaging, digital health, rehabilitation engineering, biomaterials, biomechanics, and healthcare technology management.',['Clinical Engineer','Biomedical Engineer','Medical Device Engineer','Digital Health Specialist','Medical Equipment Manager','AI in Healthcare Specialist','Research Scientist','Entrepreneur'],'published'],
    ['bachelor-of-mechanical-and-industrial-engineering','Bachelor of Mechanical and Industrial Engineering','UG','mechanical-engineering','This programme develops engineers with competencies in machine design, manufacturing systems, industrial automation, production engineering, thermodynamics, fluid mechanics, and sustainable engineering. Graduates are equipped to improve industrial productivity and develop innovative engineering solutions.',['Mechanical Engineer','Manufacturing Engineer','Industrial Engineer','Design Engineer','Production Engineer','Maintenance Engineer','Energy Systems Engineer'],'published'],
    ['bachelor-of-electrical-and-electronics-engineering','Bachelor of Electrical and Electronics Engineering','UG','electrical-and-electronics-engineering','The programme provides comprehensive training in electrical power systems, electronics, telecommunications, embedded systems, computer engineering, automation, and renewable energy technologies. Students develop practical skills through laboratory work, engineering design, and industry placements.',['Electrical Engineer','Electronics Engineer','Telecommunications Engineer','Embedded Systems Engineer','Automation Engineer','Power Systems Engineer','Control Systems Engineer'],'published'],
    ['bachelor-of-science-in-civil-and-building-services-engineering','Bachelor of Civil Engineering','UG','civil-engineering','The Bachelor of Civil Engineering programme prepares graduates to design, construct, and manage sustainable infrastructure including buildings, roads, bridges, water supply systems, transportation networks, and environmental engineering projects.',['Civil Engineer','Structural Engineer','Water Resources Engineer','Geotechnical Engineer','Transportation Engineer','Construction Manager','Environmental Engineer'],'published'],
    ['bachelor-of-petroleum-engineering-and-environmental-management','Bachelor of Petroleum Engineering and Environmental Management','UG','petroleum-engineering-and-environmental-management','This interdisciplinary programme develops professionals capable of addressing challenges in petroleum engineering, energy production, environmental protection, resource management, and sustainable development.',['Petroleum Engineer','Environmental Engineer','Energy Consultant','Drilling Engineer','Reservoir Engineer','Environmental Management Specialist','Resource Exploration Engineer'],'published'],
    ['master-of-science-in-biomedical-engineering','Master of Science in Biomedical Engineering','MASTERS','biomedical-engineering','This programme provides advanced training in biomedical engineering, medical technologies, digital health, artificial intelligence, medical imaging, clinical engineering, and healthcare innovation. Students undertake independent research addressing contemporary healthcare challenges and develop technologies that improve patient care and health systems.',['Artificial Intelligence in Healthcare','Medical Imaging','Clinical Engineering','Medical Devices','Digital Health','Rehabilitation Engineering','Healthcare Technology Management'],'published'],
    ['doctor-of-philosophy-in-biomedical-engineering','Doctor of Philosophy (PhD) in Biomedical Engineering','PHD','biomedical-engineering','The PhD programme develops independent researchers and academic leaders capable of generating new knowledge and leading multidisciplinary research in biomedical engineering. Students undertake original research under expert supervision while contributing to innovations in healthcare technology and engineering.',['Biomedical Instrumentation','Artificial Intelligence','Medical Imaging','Clinical Engineering','Rehabilitation Engineering','Digital Health','Biomedical Signal Processing','Medical Robotics'],'published'],
    ['master-of-science-in-mechanical-engineering','Master of Science in Mechanical Engineering','MASTERS','mechanical-engineering','An advanced programme focusing on manufacturing systems, sustainable energy, advanced materials, industrial automation, robotics, and engineering design to support industrial development and technological innovation.',[],'draft'],
    ['master-of-science-in-nuclear-medicine-and-radiation-technology','Master of Science in Nuclear Medicine and Radiation Technology','MASTERS','biomedical-engineering','Proposed postgraduate programme. Details remain subject to institutional approval.',[],'draft'],
    ['postgraduate-diploma-in-healthcare-technology-management','Postgraduate Diploma in Healthcare Technology Management','PGD','biomedical-engineering','Proposed postgraduate programme. Details remain subject to institutional approval.',[],'draft'],
];

$pages = [
    'industrial-training' => ['Industrial Training','Bridging Classroom Learning with Professional Practice',[
        'Industrial Training is a core component of the Faculty’s Competence-Based Engineering Education model. It provides structured experiential learning opportunities that enable students to apply classroom knowledge to real engineering and technological challenges within industry, healthcare institutions, government agencies, and the private sector.',
        'Students undertake supervised placements in manufacturing industries, construction companies, hospitals, energy companies, ICT firms, research institutions, government agencies, and engineering consultancies. They develop practical technical competencies, professional ethics, teamwork, communication, project management, and problem-solving skills.',
        'The Faculty works closely with industry partners to align industrial training with programme learning outcomes and labour market needs.',
    ],['Structured industrial attachments','Industry supervision and mentorship','Competency-based assessment','Professional skills development','Exposure to modern engineering technologies','Career networking and employability enhancement']],
    'community-outreach' => ['Community Outreach','Engineering Solutions for Community Transformation',[
        'Community engagement is central to the mission of FAST. Through community outreach, students and staff apply engineering knowledge, scientific research, and technological innovation to address real societal challenges while improving livelihoods and promoting sustainable development.',
        'Students participate in community-based learning where they identify community needs, co-design practical solutions with local stakeholders, and implement sustainable engineering interventions.',
    ],['Healthcare technology support','STEM education and mentorship','Water and sanitation initiatives','Assistive technologies','Environmental sustainability','Community innovation projects','Engineering awareness programmes']],
    'partnerships' => ['Partnerships','Collaborating for Innovation, Research and Impact',[
        'FAST believes that meaningful partnerships are essential for advancing engineering education, research, innovation, and community development. We collaborate with government ministries, regulatory agencies, industry, healthcare institutions, professional bodies, development partners, research organizations, and universities in Uganda and internationally.',
        'These partnerships support collaborative research, staff and student exchange, industrial training, technology transfer, curriculum development, innovation, professional development, and joint resource mobilization.',
    ],['Government ministries and agencies','Engineering Development and Innovation Centre (EDIC)','Hospitals and healthcare institutions','Manufacturing and engineering industries','Professional engineering bodies','National and international universities','Research institutions','Development partners','Innovation hubs and technology companies']],
    'engineering-education' => ['Engineering Education','Excellence in Competence-Based Engineering Education',[
        'FAST delivers high-quality engineering education using a Competence-Based Engineering Education approach that emphasizes practical learning, problem-solving, innovation, entrepreneurship, and professional competence.',
        'Students learn through interactive lectures, laboratory practicals, design projects, simulations, industrial training, community engagement, and multidisciplinary research.',
        'The Engineering Education Centre coordinates curriculum development, industrial training, engineering pedagogy, accreditation, professional development, industry engagement, and continuous quality improvement.',
    ],['Competence-Based Engineering Education (CBEE)','Project-Based Learning','Problem-Based Learning','Modern Engineering Laboratories','Industry and Hospital Engagement','Innovation and Entrepreneurship','Digital Learning Technologies','Professional Skills Development','Continuous Curriculum Improvement','International Collaboration']],
    'student-life' => ['Student Life','Experience Engineering Beyond the Classroom',[
        'At FAST, student life extends far beyond lectures and laboratories. We provide a vibrant, inclusive, and engaging environment where students develop academically, professionally, socially, and personally.',
        'Students participate in innovation challenges, engineering competitions, leadership programmes, community outreach, industrial visits, professional societies, sports, entrepreneurship, research groups, and student-led organizations.',
    ],['Biomedical Engineering Students Association','Mechanical and Industrial Engineering Students Association','Electrical and Electronics Engineering Students Association','Civil Engineering Students Association','Energy, Mineral and Petroleum Engineering Students Association']],
    'professional-bodies' => ['Professional Bodies','Building Future Professional Engineers',[
        'The Faculty encourages students to participate in professional engineering organizations that offer networking, mentorship, continuous professional development, leadership, career advancement, exposure to ethical standards, and professional certification pathways.',
    ],['Uganda Institution of Professional Engineers (UIPE)','Engineers Registration Board (ERB Uganda)','Institution of Engineering and Technology (IET)','Institute of Electrical and Electronics Engineers (IEEE)','American Society of Mechanical Engineers (ASME)','American Society of Civil Engineers (ASCE)','Biomedical Engineering Society (BMES)','International Federation for Medical and Biological Engineering (IFMBE)','Society of Petroleum Engineers (SPE)','Association of Energy Engineers (AEE)']],
    'student-mentorship-programme' => ['Student Mentorship Programme','Supporting Students to Learn, Lead, and Succeed',[
        'The FAST Student Mentorship Programme connects students with experienced academic staff, industry professionals, alumni, and senior students for guidance on academic success, careers, research, innovation, entrepreneurship, leadership, and personal development.',
        'Students benefit from mentorship meetings, career talks, research guidance, industrial exposure, innovation challenges, and professional networking opportunities.',
    ],['Support academic success and student retention','Guide career planning and professional development','Promote research, innovation, and entrepreneurship','Foster leadership, teamwork, and communication skills','Enhance student well-being and personal growth','Connect students with industry, alumni, and professional mentors']],
    'faculty-mentorship-programme' => ['Faculty Mentorship Programme','Developing Academic Excellence and Leadership',[
        'The FAST Faculty Mentorship Programme supports the professional growth of academic, technical, and administrative staff through collaboration, continuous learning, and shared leadership. Experienced mentors guide staff in teaching excellence, research, grant writing, postgraduate supervision, academic promotion, leadership, innovation, and professional development.',
    ],['Support career progression and academic promotion','Strengthen research capacity and publication output','Enhance teaching and supervision skills','Promote grant writing and resource mobilization','Foster leadership and institutional service','Encourage interdisciplinary collaboration and mentorship','Support innovation, entrepreneurship, and industry engagement']],
    'meet-our-mentors' => ['Meet Our Mentors','Guidance for every stage of the journey',[
        'Our mentors are experienced academics, researchers, industry professionals, and leaders committed to supporting the next generation of engineers, scientists, and innovators.',
        'Through one-on-one mentorship, group mentoring, seminars, career coaching, and professional development activities, mentors help mentees navigate academic challenges, build successful careers, develop research programmes, and become leaders in their fields.',
    ],['Teaching and Learning Excellence','Research and Scientific Writing','Grant Proposal Development','Postgraduate Supervision','Innovation and Entrepreneurship','Engineering Design and Practice','Leadership and Academic Administration','Industry Engagement and Consultancy','Career Planning and Professional Development','Work–Life Balance and Personal Growth']],
];

$pdo->beginTransaction();
try {
    $facultyId = (int) $pdo->query('SELECT id FROM faculties WHERE deleted_at IS NULL ORDER BY id LIMIT 1')->fetchColumn();
    if ($facultyId < 1) {
        throw new RuntimeException('No active faculty record exists.');
    }

    // Homepage copy. Existing editor-selected images are retained and reused.
    $mediaIds = $pdo->query('SELECT id FROM media WHERE media_type="image" AND status="active" AND deleted_at IS NULL ORDER BY CASE WHEN file_path LIKE "%fast-building%" THEN 0 ELSE 1 END,id LIMIT 3')->fetchAll(PDO::FETCH_COLUMN);
    if ($mediaIds === []) {
        throw new RuntimeException('At least one active image is required for homepage slides.');
    }
    $slideData = [
        ['Building Tomorrow’s Engineers, Innovators and Technology Leaders','Welcome to FAST','/programmes'],
        ['Where Innovation Meets Real-World Impact','Innovation & Research','/research'],
        ['State-of-the-Art Engineering Laboratories','Laboratories','/facilities'],
    ];
    $slideSelect = $pdo->prepare('SELECT id FROM hero_slides WHERE title=:title LIMIT 1');
    $slideInsert = $pdo->prepare('INSERT INTO hero_slides(title,caption,media_id,button_label,button_url,text_alignment,overlay_strength,display_order,is_active) VALUES(:title,:caption,:media,NULL,:url,"left",55,:sort,1)');
    $slideUpdate = $pdo->prepare('UPDATE hero_slides SET caption=:caption,button_label=NULL,button_url=:url,display_order=:sort,is_active=1 WHERE id=:id');
    foreach ($slideData as $index => [$title,$caption,$url]) {
        $slideSelect->execute(['title'=>$title]); $id = $slideSelect->fetchColumn();
        if ($id === false) $slideInsert->execute(['title'=>$title,'caption'=>$caption,'media'=>(int)$mediaIds[$index % count($mediaIds)],'url'=>$url,'sort'=>$index]);
        else $slideUpdate->execute(['caption'=>$caption,'url'=>$url,'sort'=>$index,'id'=>(int)$id]);
    }
    $authoritativeSlideTitles = array_column($slideData, 0);
    $slidePlaceholders = implode(',', array_fill(0, count($authoritativeSlideTitles), '?'));
    $pushOtherSlides = $pdo->prepare('UPDATE hero_slides SET display_order=100+id WHERE title NOT IN (' . $slidePlaceholders . ')');
    $pushOtherSlides->execute($authoritativeSlideTitles);

    // Authoritative About pages and sections.
    $pageUpsert = $pdo->prepare('INSERT INTO pages(parent_page_id,title,slug,page_template,meta_description,display_order,show_in_navigation,status,published_at) VALUES(NULL,:title,:slug,"standard",:description,:sort,:nav,"published",NOW()) ON DUPLICATE KEY UPDATE title=VALUES(title),meta_description=VALUES(meta_description),display_order=VALUES(display_order),show_in_navigation=VALUES(show_in_navigation),status="published",published_at=COALESCE(published_at,NOW()),deleted_at=NULL');
    $pageId = static function (string $slug) use ($pdo): int { $s=$pdo->prepare('SELECT id FROM pages WHERE slug=:slug LIMIT 1');$s->execute(['slug'=>$slug]);return(int)$s->fetchColumn(); };
    $sectionUpsert = static function (int $page, string $key, string $type, string $heading, string $subheading, string $body, int $sort) use ($pdo): void {
        $marker = json_encode(['source'=>'deans-office-2026','key'=>$key], JSON_UNESCAPED_SLASHES);
        $find=$pdo->prepare('SELECT id FROM page_sections WHERE page_id=:page AND settings_json=:marker LIMIT 1');$find->execute(['page'=>$page,'marker'=>$marker]);$id=$find->fetchColumn();
        if($id===false){$s=$pdo->prepare('INSERT INTO page_sections(page_id,section_type,heading,subheading,body,settings_json,display_order,is_visible) VALUES(:page,:type,:heading,:subheading,:body,:marker,:sort,1)');$s->execute(compact('page','type','heading','subheading','body','marker','sort'));}
        else{$s=$pdo->prepare('UPDATE page_sections SET section_type=:type,heading=:heading,subheading=:subheading,body=:body,display_order=:sort,is_visible=1 WHERE id=:id');$s->execute(compact('type','heading','subheading','body','sort','id'));}
    };
    $galleryInsert = static function (int $page, string $key, int $media, int $sort) use ($pdo): void {
        $marker = json_encode(['source'=>'deans-office-2026','key'=>$key], JSON_UNESCAPED_SLASHES);
        $find=$pdo->prepare('SELECT id FROM page_sections WHERE page_id=:page AND settings_json=:marker LIMIT 1');$find->execute(['page'=>$page,'marker'=>$marker]);
        if($find->fetchColumn()===false){$s=$pdo->prepare('INSERT INTO page_sections(page_id,section_type,media_id,settings_json,display_order,is_visible) VALUES(:page,"gallery",:media,:marker,:sort,1)');$s->execute(compact('page','media','marker','sort'));}
    };

    $pageUpsert->execute(['title'=>'Faculty Overview','slug'=>'faculty-overview','description'=>'An introduction to the Faculty of Applied Sciences and Technology.','sort'=>10,'nav'=>1]);
    $sectionUpsert($pageId('faculty-overview'),'overview','image_text','About the Faculty of Applied Sciences and Technology','About FAST',$paragraphs($facultyOverview),10);
    $pageUpsert->execute(['title'=>"Dean's Message",'slug'=>'deans-message','description'=>'A welcome message from the Dean of FAST.','sort'=>30,'nav'=>1]);
    $sectionUpsert($pageId('deans-message'),'dean-message','rich_text','Welcome to FAST','From the Dean',$paragraphs($deanMessage),10);
    $pageUpsert->execute(['title'=>'Vision and Mission','slug'=>'vision-and-mission','description'=>'The vision and mission that guide FAST.','sort'=>40,'nav'=>1]);
    $sectionUpsert($pageId('vision-and-mission'),'vision','cards','Vision','Where we are going',$paragraphs(["To be a leading Faculty of academic and professional excellence in engineering, applied sciences, technology, research, innovation, and community transformation, contributing to the realization of Mbarara University of Science and Technology's vision of becoming a centre of academic and professional excellence in Science and Technology."]),10);
    $sectionUpsert($pageId('vision-and-mission'),'mission','cards','Mission','What we are here to do',$paragraphs(['In alignment with the mission of Mbarara University of Science and Technology, FAST is committed to providing quality and relevant education, research, innovation, and professional training in engineering, applied sciences, and technology, with particular emphasis on developing competent graduates, advancing scientific knowledge, fostering innovation, and applying science and technology to community and national development.']),20);
    $authoritativeAboutIds = [$pageId('faculty-overview'), $pageId('deans-message')];
    $removeReplacedAbout = $pdo->prepare('DELETE FROM page_sections WHERE page_id=:page AND (settings_json IS NULL OR settings_json NOT LIKE "%deans-office-2026%")');
    foreach ($authoritativeAboutIds as $authoritativeAboutId) $removeReplacedAbout->execute(['page'=>$authoritativeAboutId]);
    $removeOldDirection = $pdo->prepare('DELETE FROM page_sections WHERE page_id=:page AND LOWER(heading) IN ("vision","mission") AND (settings_json IS NULL OR settings_json NOT LIKE "%deans-office-2026%")');
    $removeOldDirection->execute(['page'=>$pageId('vision-and-mission')]);

    // Department overviews and research lists are editable through the existing overview field.
    $departmentIds = [];
    $departmentUpdate = $pdo->prepare('UPDATE departments SET name=:name,short_name=:short,overview=:overview WHERE slug=:slug AND deleted_at IS NULL');
    foreach($departments as $slug=>$data){
        $overview=$paragraphs($data['overview']).'<h3>Research areas</h3>'.$list($data['research']);
        $departmentUpdate->execute(['name'=>$data['name'],'short'=>$data['short'],'overview'=>$overview,'slug'=>$slug]);
        $s=$pdo->prepare('SELECT id FROM departments WHERE slug=:slug AND deleted_at IS NULL');$s->execute(['slug'=>$slug]);$departmentIds[$slug]=(int)$s->fetchColumn();
    }

    // Programmes: approved programmes are published; explicitly proposed ones are drafts.
    $levelIds=[];foreach($pdo->query('SELECT id,code FROM programme_levels WHERE is_active=1')->fetchAll(PDO::FETCH_ASSOC) as $level)$levelIds[$level['code']] = (int)$level['id'];
    $programmeFind=$pdo->prepare('SELECT id FROM programmes WHERE slug=:slug LIMIT 1');
    $programmeInsert=$pdo->prepare('INSERT INTO programmes(programme_level_id,name,award_title,slug,overview,career_opportunities,study_mode,delivery_mode,display_order,status,published_at) VALUES(:level,:name,:award,:slug,:overview,:careers,"full_time","face_to_face",:sort,:status,:published)');
    $programmeUpdate=$pdo->prepare('UPDATE programmes SET programme_level_id=:level,name=:name,award_title=:award,overview=:overview,career_opportunities=:careers,status=:status,published_at=:published,deleted_at=NULL WHERE id=:id');
    foreach($programmes as $sort=>[$slug,$name,$level,$department,$overview,$careers,$status]){
        $params=['level'=>$levelIds[$level],'name'=>$name,'award'=>$name,'slug'=>$slug,'overview'=>$paragraphs([$overview]),'careers'=>$careers === [] ? null : $list($careers),'sort'=>($sort+1)*10,'status'=>$status,'published'=>$status==='published'?date('Y-m-d H:i:s'):null];
        $programmeFind->execute(['slug'=>$slug]);$id=$programmeFind->fetchColumn();
        if($id===false){$programmeInsert->execute($params);$id=(int)$pdo->lastInsertId();}else{$programmeUpdate->execute(['level'=>$params['level'],'name'=>$name,'award'=>$name,'overview'=>$params['overview'],'careers'=>$params['careers'],'status'=>$status,'published'=>$params['published'],'id'=>(int)$id]);}
        $pdo->prepare('DELETE FROM programme_departments WHERE programme_id=:id')->execute(['id'=>(int)$id]);
        $pdo->prepare('INSERT INTO programme_departments(programme_id,department_id,is_lead_department) VALUES(:programme,:department,1)')->execute(['programme'=>(int)$id,'department'=>$departmentIds[$department]]);
    }

    // Stand-alone subject pages. They are all editable in Admin > Pages.
    $visualMedia = $pdo->query(
        'SELECT id FROM media WHERE media_type="image" AND status="active" AND deleted_at IS NULL
         AND file_path NOT LIKE "%/staff/%"
         ORDER BY CASE
            WHEN alt_text LIKE "%student%" THEN 0
            WHEN alt_text LIKE "%workshop%" OR alt_text LIKE "%research%" OR alt_text LIKE "%innovation%" THEN 1
            WHEN file_path LIKE "%fast-building%" THEN 2
            ELSE 3 END, id DESC LIMIT 12'
    )->fetchAll(PDO::FETCH_COLUMN);
    $sort=100;
    $visualIndex=0;
    foreach($pages as $slug=>[$title,$tagline,$copy,$bullets]){
        $pageUpsert->execute(['title'=>$title,'slug'=>$slug,'description'=>$tagline,'sort'=>$sort,'nav'=>0]);
        $featurePageId=$pageId($slug);
        $sectionUpsert($featurePageId,'main','rich_text',$tagline,$title,$paragraphs($copy).($bullets===[]?'':'<h3>Key areas</h3>'.$list($bullets)),10);
        if($visualMedia!==[]){
            $heroMedia=(int)$visualMedia[$visualIndex % count($visualMedia)];
            $pdo->prepare('UPDATE pages SET hero_media_id=COALESCE(hero_media_id,:media) WHERE id=:page')->execute(['media'=>$heroMedia,'page'=>$featurePageId]);
            $galleryInsert($featurePageId,'gallery-1',(int)$visualMedia[($visualIndex+1)%count($visualMedia)],80);
            $galleryInsert($featurePageId,'gallery-2',(int)$visualMedia[($visualIndex+2)%count($visualMedia)],90);
            $visualIndex+=3;
        }
        $sort+=10;
    }

    // News introduction and engagement landing copy use the dynamic settings editor.
    $setting=$pdo->prepare('INSERT INTO site_settings(setting_key,setting_value,value_type,is_public) VALUES(:key,:value,"text",1) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),is_public=1');
    $setting->execute(['key'=>'news.introduction','value'=>'Stay connected with FAST through academic updates, research breakthroughs, innovation initiatives, conferences, workshops, seminars, student activities, industry engagements, partnerships, and community outreach programmes.']);
    $setting->execute(['key'=>'engagement.introduction','value'=>'Explore how FAST connects classroom learning, professional practice, community transformation, strategic partnerships, and competence-based engineering education.']);
    $setting->execute(['key'=>'programmes.introduction','value'=>'Explore undergraduate and postgraduate programmes that combine rigorous scientific and engineering foundations with competence-based education, practical laboratory training, industry engagement, research, and innovation.']);

    // Correct the current Dean assignment while keeping the former assignment as history.
    $william=$pdo->query("SELECT id FROM staff WHERE slug IN ('wasswa-william','william-wasswa') OR (first_name='Wasswa' AND last_name='William') ORDER BY id LIMIT 1")->fetchColumn();
    $deanPosition=$pdo->query("SELECT id FROM positions WHERE code='dean' LIMIT 1")->fetchColumn();
    if($william!==false && $deanPosition!==false){
        $pdo->prepare('UPDATE staff SET first_name="William",last_name="Wasswa",post_nominals=COALESCE(NULLIF(post_nominals,""),"PhD"),slug="william-wasswa" WHERE id=:id')->execute(['id'=>(int)$william]);
        $pdo->prepare('UPDATE media SET alt_text="Portrait of Dr. William Wasswa" WHERE id=(SELECT profile_media_id FROM staff WHERE id=:id)')->execute(['id'=>(int)$william]);
        $pdo->prepare('UPDATE staff_positions SET is_current=0,end_date=COALESCE(end_date,CURDATE()) WHERE position_id=:position AND staff_id<>:staff AND is_current=1')->execute(['position'=>(int)$deanPosition,'staff'=>(int)$william]);
        $find=$pdo->prepare('SELECT id FROM staff_positions WHERE staff_id=:staff AND position_id=:position ORDER BY id DESC LIMIT 1');$find->execute(['staff'=>(int)$william,'position'=>(int)$deanPosition]);$assignment=$find->fetchColumn();
        if($assignment===false)$pdo->prepare('INSERT INTO staff_positions(staff_id,position_id,faculty_id,department_id,title_override,start_date,is_current,display_order) VALUES(:staff,:position,:faculty,NULL,"Dean",CURDATE(),1,1)')->execute(['staff'=>(int)$william,'position'=>(int)$deanPosition,'faculty'=>$facultyId]);
        else $pdo->prepare('UPDATE staff_positions SET faculty_id=:faculty,department_id=NULL,title_override="Dean",end_date=NULL,is_current=1,display_order=1 WHERE id=:id')->execute(['faculty'=>$facultyId,'id'=>(int)$assignment]);
    }

    $pdo->commit();
    fwrite(STDOUT, "Dean's Office content imported successfully.\n");
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}
