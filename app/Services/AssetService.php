<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Core\HttpException;use FastWebsite\Repositories\AssetRepository;

final class AssetService
{
    public function __construct(private readonly AssetRepository$r,private readonly AuthorizationService$a,private readonly AuditService$audit){}public function mediaStatus(int$id,string$action,int$u,string$ip,string$agent):string{$this->need($u,'media.manage');if($this->r->findMedia($id)===null)throw new HttpException(404,'Image not found.');$status=match($action){'archive'=>'archived','restore'=>'active',default=>throw new HttpException(422,'Choose a valid image action.')};$this->r->mediaStatus($id,$status);$this->audit->record($u,'media.'.$status,'media',$id,$ip,$agent);return$status;}public function documentStatus(int$id,string$action,int$u,string$ip,string$agent):string{$this->need($u,'documents.manage');if($this->r->findDocument($id)===null)throw new HttpException(404,'Document not found.');$status=match($action){'publish'=>'published','archive'=>'archived','restore'=>'draft',default=>throw new HttpException(422,'Choose a valid document action.')};$this->r->documentStatus($id,$status);$this->audit->record($u,'document.'.$status,'document',$id,$ip,$agent);return$status;}private function need(int$u,string$p):void{if(!$this->a->can($u,$p))throw new HttpException(403,'This asset action is not allowed.');}
}
