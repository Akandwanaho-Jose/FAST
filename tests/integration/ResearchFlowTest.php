<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Repositories\ResearchRepository;
use FastWebsite\Repositories\UserRepository;
use FastWebsite\Services\AuditService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\DepartmentScopeService;
use FastWebsite\Services\ResearchService;
use FastWebsite\Validation\ResearchUnitValidator;

return static function():void{
    /** @var Database $database */ $database=require dirname(__DIR__,2).'/bootstrap/database.php';$connection=$database->connection();$connection->beginTransaction();
    try{
        $suffix=bin2hex(random_bytes(5));$userId=(int)$connection->query('SELECT id FROM users WHERE is_active=1 AND deleted_at IS NULL ORDER BY id LIMIT 1')->fetchColumn();$department=$connection->query('SELECT id,faculty_id FROM departments WHERE deleted_at IS NULL ORDER BY id LIMIT 1')->fetch();$typeId=(int)$connection->query('SELECT id FROM research_unit_types ORDER BY display_order,id LIMIT 1')->fetchColumn();$staffIds=array_map('intval',$connection->query('SELECT id FROM staff WHERE status="published" AND deleted_at IS NULL ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN));
        if($userId<1||!is_array($department)||$typeId<1||count($staffIds)<2)throw new RuntimeException('Research flow prerequisites are missing.');
        $users=new UserRepository($database);$authorization=new AuthorizationService($users);$scope=new DepartmentScopeService($users,$authorization);$repository=new ResearchRepository($database,$scope);$service=new ResearchService($repository,$authorization,new AuditService($database));$validator=new ResearchUnitValidator($repository);
        $input=['faculty_id'=>(string)$department['faculty_id'],'department_id'=>(string)$department['id'],'unit_type_id'=>(string)$typeId,'name'=>'Transactional Research Unit '.$suffix,'acronym'=>'TRU','slug'=>'transactional-research-unit-'.$suffix,'overview'=>'A complete research unit created in a rolled-back integration test.','research_focus'=>'Applied testing and verification.','capabilities'=>'A test facility.','student_opportunities'=>'Test placements.','industry_services'=>'Test collaboration.','location_id'=>'','hero_media_id'=>'','email'=>'research-'.$suffix.'@example.invalid','phone'=>'','website_url'=>'https://example.invalid/research','display_order'=>'1'];
        $validated=$validator->validate($input);if($validated['errors']!==[])throw new RuntimeException('Valid research unit rejected: '.json_encode($validated['errors']));
        $id=$service->createAndPublish($validated['data'],$userId,'127.0.0.15','FAST research integration test');$public=$repository->findPublishedBySlug($input['slug']);if(!is_array($public)||$public['status']!=='published')throw new RuntimeException('Research unit was not published publicly.');
        $service->addMember($id,$staffIds[0],'lead','Research Unit Lead','2026-01-01',1,$userId,'127.0.0.15','FAST research integration test');$service->addMember($id,$staffIds[1],'lead','New Research Unit Lead','2026-02-01',1,$userId,'127.0.0.15','FAST research integration test');$members=$repository->members($id);if(count($members)!==1||(int)$members[0]['staff_id']!==$staffIds[1])throw new RuntimeException('Research unit lead replacement was not enforced.');
        $updated=$validated['data'];$updated['overview']='Updated while remaining published.';$service->updatePublished($id,$updated,$userId,'Live research correction','127.0.0.15','FAST research integration test');$public=$repository->findPublishedBySlug($input['slug']);if(!is_array($public)||$public['overview']!==$updated['overview'])throw new RuntimeException('Published research changes did not remain public.');
        if($service->transition($id,'archived',$userId,'Test archive','127.0.0.15','FAST research integration test')!=='archived'||$repository->findPublishedBySlug($input['slug'])!==null)throw new RuntimeException('Archived research unit remained public.');
        if($service->transition($id,'restored',$userId,'Test restore','127.0.0.15','FAST research integration test')!=='draft')throw new RuntimeException('Archived research unit did not restore to draft.');
    }finally{if($connection->inTransaction())$connection->rollBack();}
};
