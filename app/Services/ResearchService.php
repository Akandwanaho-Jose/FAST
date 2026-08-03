<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Core\HttpException;
use FastWebsite\Repositories\ResearchRepository;
use Throwable;

final class ResearchService
{
    private const TRANSITIONS=['submitted'=>['draft','under_review'],'changes_requested'=>['under_review','draft'],'approved'=>['under_review','approved'],'published'=>['approved','published'],'archived'=>['published','archived'],'restored'=>['archived','draft']];
    private const MEMBER_ROLES=['lead','deputy_lead','researcher','technician','graduate_researcher','student_researcher','affiliate','external_collaborator','other'];
    public function __construct(private readonly ResearchRepository $research,private readonly AuthorizationService $authorization,private readonly AuditService $audit){}

    /** @param array<string,mixed> $data */
    public function create(array$data,int$userId,string$ip,string$agent):int
    {
        $this->requireDepartment($userId,'research.create',$data['department_id']);
        return$this->transaction(function()use($data,$userId,$ip,$agent){$id=$this->research->create($data);$this->research->recordRevision($id,null,$data,$userId,'Initial research unit record');$this->audit->record($userId,'research_unit.created','research_unit',$id,$ip,$agent,['status'=>'draft']);return$id;});
    }
    /** @param array<string,mixed> $data */
    public function createAndPublish(array$data,int$userId,string$ip,string$agent):int
    { return$this->transaction(function()use($data,$userId,$ip,$agent){if(!$this->canDirectPublish($userId,null,$data['department_id']))throw new HttpException(403,'You do not have all permissions required to publish this research unit.');$id=$this->create($data,$userId,$ip,$agent);$this->publishDraft($id,$userId,'Created and published directly.',$ip,$agent);return$id;}); }
    /** @param array<string,mixed> $data */
    public function update(int$id,array$data,int$userId,string$note,string$ip,string$agent):void
    { $this->requireUnit($userId,'research.edit',$id);$this->requireDepartment($userId,'research.edit',$data['department_id']);$this->transaction(function()use($id,$data,$userId,$note,$ip,$agent){$previous=$this->research->findForUpdate($id);if($previous===null)throw new HttpException(404,'Research unit not found.');if($previous['status']!=='draft')throw new HttpException(409,'Only draft research units can be edited.');$this->saveRevision($id,$data,$previous,$userId,$note,$ip,$agent,'research_unit.updated');}); }
    /** @param array<string,mixed> $data */
    public function updatePublished(int$id,array$data,int$userId,string$note,string$ip,string$agent):void
    { if(!$this->canDirectPublish($userId,$id,$data['department_id']))throw new HttpException(403,'Editing a published research unit requires publishing access.');$this->transaction(function()use($id,$data,$userId,$note,$ip,$agent){$previous=$this->research->findForUpdate($id);if($previous===null)throw new HttpException(404,'Research unit not found.');if($previous['status']!=='published')throw new HttpException(409,'The research unit is no longer published. Reload before editing.');$this->requireOverview($data);$this->saveRevision($id,$data,$previous,$userId,$note!==''?$note:'Published research unit updated',$ip,$agent,'research_unit.published_updated');}); }
    /** @param array<string,mixed> $data */
    public function updateAndPublish(int$id,array$data,int$userId,string$note,string$ip,string$agent):void
    { $this->transaction(function()use($id,$data,$userId,$note,$ip,$agent){$this->update($id,$data,$userId,$note,$ip,$agent);$this->publishDraft($id,$userId,'Direct publication.',$ip,$agent);}); }
    public function publishDraft(int$id,int$userId,string$comment,string$ip,string$agent):void
    { $this->transaction(function()use($id,$userId,$comment,$ip,$agent){if(!$this->canDirectPublish($userId,$id))throw new HttpException(403,'You do not have all permissions required to publish this research unit.');foreach(['submitted','approved','published']as$action)$this->transition($id,$action,$userId,$comment,$ip,$agent);}); }
    public function transition(int$id,string$action,int$userId,string$comment,string$ip,string$agent):string
    { return$this->transaction(function()use($id,$action,$userId,$comment,$ip,$agent){$unit=$this->research->findForUpdate($id);if($unit===null)throw new HttpException(404,'Research unit not found.');if(!isset(self::TRANSITIONS[$action]))throw new HttpException(403,'That research workflow action is unavailable.');if($action==='changes_requested'&&trim($comment)==='')throw new HttpException(409,'A comment is required when requesting changes.');[$from,$target]=self::TRANSITIONS[$action];if($unit['status']!==$from)throw new HttpException(409,'The research unit status has changed.');$permission=match($action){'submitted'=>'research.edit','approved','changes_requested'=>'content.approve',default=>'research.publish'};$this->requireUnit($userId,$permission,$id);if(in_array($target,['under_review','published'],true))$this->requireOverview($unit);$this->research->changeStatus($id,$target);$this->research->recordApproval($id,$action,$userId,$comment);$this->audit->record($userId,'research_unit.'.$action,'research_unit',$id,$ip,$agent,['from_status'=>$from,'to_status'=>$target]);return$target;}); }

    public function addMember(int$unitId,int$staffId,string$role,string$title,string$startDate,int$order,int$userId,string$ip,string$agent):void
    { $this->requireUnit($userId,'research.edit',$unitId);if(!in_array($role,self::MEMBER_ROLES,true))throw new HttpException(409,'Choose a valid membership role.');if(!$this->research->relationExists('staff',$staffId))throw new HttpException(409,'Choose a valid staff member.');if($startDate!==''&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$startDate)!==1)throw new HttpException(409,'Start date must use YYYY-MM-DD.');$this->transaction(function()use($unitId,$staffId,$role,$title,$startDate,$order,$userId,$ip,$agent){$id=$this->research->addMember($unitId,$staffId,$role,$title!==''?mb_substr($title,0,150):null,$startDate!==''?$startDate:null,max(0,min(65535,$order)));$this->audit->record($userId,'research_unit.member_added','research_unit',$unitId,$ip,$agent,['member_id'=>$id,'staff_id'=>$staffId,'role'=>$role]);}); }
    public function removeMember(int$unitId,int$memberId,int$userId,string$ip,string$agent):void
    { $this->requireUnit($userId,'research.edit',$unitId);$this->transaction(function()use($unitId,$memberId,$userId,$ip,$agent){if(!$this->research->removeMember($unitId,$memberId))throw new HttpException(404,'Research unit member not found.');$this->audit->record($userId,'research_unit.member_removed','research_unit',$unitId,$ip,$agent,['member_id'=>$memberId]);}); }

    /** @return list<array{action:string,label:string}> */
    public function actions(int$userId,int$id,string$status):array
    { if(!$this->research->canAccess($userId,$id))return[];$definitions=['submitted'=>['draft','research.edit','Submit for review'],'changes_requested'=>['under_review','content.approve','Request changes'],'approved'=>['under_review','content.approve','Approve'],'published'=>['approved','research.publish','Publish'],'archived'=>['published','research.publish','Archive'],'restored'=>['archived','research.publish','Restore as draft']];$result=[];foreach($definitions as$action=>[$from,$permission,$label])if($status===$from&&$this->authorization->can($userId,$permission))$result[]=['action'=>$action,'label'=>$label];return$result; }
    public function canDirectPublish(int$userId,?int$id=null,?int$departmentId=null):bool
    { $scope=$id!==null?$this->research->canAccess($userId,$id):$this->research->canUseDepartment($userId,$departmentId);return$scope&&$this->authorization->can($userId,'research.edit')&&$this->authorization->can($userId,'research.publish')&&$this->authorization->can($userId,'content.approve'); }
    public function canCreate(int$userId):bool{return$this->authorization->can($userId,'research.create')&&$this->research->departments($userId)!==[];}
    /** @param array<string,mixed> $data @param array<string,mixed> $previous */ private function saveRevision(int$id,array$data,array$previous,int$userId,string$note,string$ip,string$agent,string$action):void{$this->research->update($id,$data);$this->research->recordRevision($id,$previous,[...$data,'status'=>$previous['status']],$userId,$note!==''?$note:'Research unit updated');$this->audit->record($userId,$action,'research_unit',$id,$ip,$agent,['status'=>$previous['status']]);}
    /** @param array<string,mixed> $data */ private function requireOverview(array$data):void{if(trim((string)($data['overview']??''))==='')throw new HttpException(409,'Add an overview before publishing this research unit.');}
    private function requireUnit(int$userId,string$permission,int$id):void{if(!$this->authorization->can($userId,$permission)||!$this->research->canAccess($userId,$id))throw new HttpException(403,'This research action is outside your access.');}
    private function requireDepartment(int$userId,string$permission,?int$departmentId):void{if(!$this->authorization->can($userId,$permission)||!$this->research->canUseDepartment($userId,$departmentId))throw new HttpException(403,'This department is outside your research access.');}
    private function transaction(callable$operation):mixed{$connection=$this->research->connection();$owns=!$connection->inTransaction();if($owns)$connection->beginTransaction();try{$result=$operation();if($owns)$connection->commit();return$result;}catch(Throwable$exception){if($owns&&$connection->inTransaction())$connection->rollBack();throw$exception;}}
}
