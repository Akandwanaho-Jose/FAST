<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Core\HttpException;
use FastWebsite\Repositories\InnovationRepository;
use Throwable;

final class InnovationService
{
    public function __construct(private readonly InnovationRepository$repository,private readonly AuthorizationService$authorization,private readonly AuditService$audit){}
    /** @param array<string,mixed>$data */public function saveInnovation(?int$id,array$data,int$userId,bool$publish,string$ip,string$agent):int{$this->assert($userId,$id===null?'research.create':'research.edit');if(!$this->repository->canUseDepartment($userId,(int)$data['lead_department_id']))throw new HttpException(403,'The lead department is outside your scope.');if($publish)$this->publisher($userId);return$this->transaction(function()use($id,$data,$userId,$publish,$ip,$agent){$creating=$id===null;$id=$this->repository->saveInnovation($id,$data);if($publish)$this->repository->innovationStatus($id,'published');$this->audit->record($userId,$creating?'innovation.created':'innovation.updated','innovation',$id,$ip,$agent,['published'=>$publish]);return$id;});}
    public function innovationStatus(int$id,string$action,int$userId,string$ip,string$agent):string{$record=$this->repository->findInnovation($id,$userId);if($record===null)throw new HttpException(404,'Innovation not found.');$target=match($action){'publish'=>'published','archive'=>'archived','restore'=>'draft',default=>throw new HttpException(422,'Choose a valid innovation action.')};if($target==='published')$this->publisher($userId);else$this->assert($userId,'research.edit');if($target==='published'&&trim((string)($record['description']??''))==='')throw new HttpException(409,'Add an innovation description before publishing.');$this->repository->innovationStatus($id,$target);$this->audit->record($userId,'innovation.'.$target,'innovation',$id,$ip,$agent);return$target;}
    /** @param array<string,mixed>$data */public function saveFacility(?int$id,array$data,int$userId,string$ip,string$agent):int{$creating=$id===null;$this->assert($userId,$creating?'research.create':'research.edit');if(!$this->repository->canUseUnit($userId,(int)$data['research_unit_id']))throw new HttpException(403,'The research unit is outside your scope.');$id=$this->repository->saveFacility($id,$data);$this->audit->record($userId,$creating?'facility.created':'facility.updated','facility',$id,$ip,$agent);return$id;}
    /** @param array<string,mixed>$data */public function saveEquipment(?int$id,array$data,int$userId,string$ip,string$agent):int{$creating=$id===null;$this->assert($userId,$creating?'research.create':'research.edit');if(!$this->repository->canUseUnit($userId,(int)$data['research_unit_id']))throw new HttpException(403,'The research unit is outside your scope.');$id=$this->repository->saveEquipment($id,$data);$this->audit->record($userId,$creating?'equipment.created':'equipment.updated','equipment',$id,$ip,$agent);return$id;}
    private function publisher(int$userId):void{$this->assert($userId,'research.publish');$this->assert($userId,'content.approve');}
    private function assert(int$userId,string$permission):void{if(!$this->authorization->can($userId,$permission))throw new HttpException(403,'This innovation action is not allowed.');}
    private function transaction(callable$operation):mixed{$connection=$this->repository->connection();$owns=!$connection->inTransaction();if($owns)$connection->beginTransaction();try{$result=$operation();if($owns)$connection->commit();return$result;}catch(Throwable$e){if($owns&&$connection->inTransaction())$connection->rollBack();throw$e;}}
}
