<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Core\HttpException;use FastWebsite\Repositories\EngagementRepository;

final class EngagementService
{
    public function __construct(private readonly EngagementRepository$r,private readonly AuthorizationService$a,private readonly AuditService$audit){}
    /** @param array<string,mixed>$data */public function savePartnership(?int$id,array$data,int$u,bool$publish,string$ip,string$agent):int{if(!$this->r->global($u))throw new HttpException(403,'Partnership administration requires faculty-wide access.');$this->need($u,$id===null?'research.create':'research.edit');if($publish)$this->publisher($u);$creating=$id===null;$id=$this->r->savePartnership($id,$data);if($publish)$this->r->partnershipStatus($id,'published');$this->audit->record($u,$creating?'partnership.created':'partnership.updated','partnership',$id,$ip,$agent);return$id;}
    public function partnershipStatus(int$id,string$action,int$u,string$ip,string$agent):string{if(!$this->r->global($u)||$this->r->findPartnership($id)===null)throw new HttpException(404,'Partnership not found.');$target=$this->target($action);$target==='published'?$this->publisher($u):$this->need($u,'research.edit');$this->r->partnershipStatus($id,$target);$this->audit->record($u,'partnership.'.$target,'partnership',$id,$ip,$agent);return$target;}
    /** @param array<string,mixed>$data */public function saveImpact(?int$id,array$data,int$u,bool$publish,string$ip,string$agent):int{$this->need($u,$id===null?'research.create':'research.edit');if(!$this->r->canUseDepartment($u,$data['department_id']))throw new HttpException(403,'Impact story is outside your scope.');if($publish)$this->publisher($u);$creating=$id===null;$id=$this->r->saveImpact($id,$data);if($publish)$this->r->impactStatus($id,'published');$this->audit->record($u,$creating?'impact_story.created':'impact_story.updated','impact_story',$id,$ip,$agent);return$id;}
    public function impactStatus(int$id,string$action,int$u,string$ip,string$agent):string{if($this->r->findImpact($id,$u)===null)throw new HttpException(404,'Impact story not found.');$target=$this->target($action);$target==='published'?$this->publisher($u):$this->need($u,'research.edit');$this->r->impactStatus($id,$target);$this->audit->record($u,'impact_story.'.$target,'impact_story',$id,$ip,$agent);return$target;}
    private function target(string$a):string{return match($a){'publish'=>'published','archive'=>'archived','restore'=>'draft',default=>throw new HttpException(422,'Choose a valid workflow action.')};}private function publisher(int$u):void{$this->need($u,'research.publish');$this->need($u,'content.approve');}private function need(int$u,string$p):void{if(!$this->a->can($u,$p))throw new HttpException(403,'This engagement action is not allowed.');}
}
