<?php

declare(strict_types=1);

namespace FastWebsite\Repositories;

use FastWebsite\Core\Database;use FastWebsite\Services\DepartmentScopeService;use PDO;

final class EngagementRepository
{
    public function __construct(private readonly Database$db,private readonly DepartmentScopeService$scope){}
    /** @return list<array<string,mixed>> */public function partners():array{return$this->pdo()->query('SELECT id,name FROM partners WHERE status="published" ORDER BY name')->fetchAll();}
    /** @return list<array<string,mixed>> */public function faculties():array{return$this->pdo()->query('SELECT id,name FROM faculties WHERE deleted_at IS NULL ORDER BY name')->fetchAll();}
    /** @return list<array<string,mixed>> */public function departments(int$u):array{$where=$this->scope->hasGlobalScope($u)?'1=1':$this->ids('id',$this->scope->departmentIds($u));return$this->pdo()->query('SELECT id,name FROM departments WHERE deleted_at IS NULL AND '.$where.' ORDER BY name')->fetchAll();}
    /** @return list<array<string,mixed>> */public function projects(int$u):array{$where=$this->scope->hasGlobalScope($u)?'1=1':$this->ids('lead_department_id',$this->scope->departmentIds($u));return$this->pdo()->query('SELECT id,title FROM projects WHERE deleted_at IS NULL AND '.$where.' ORDER BY title')->fetchAll();}
    /** @return list<array<string,mixed>> */public function images():array{return$this->pdo()->query('SELECT id,original_name FROM media WHERE media_type="image" AND status="active" AND deleted_at IS NULL ORDER BY original_name')->fetchAll();}
    /** @return list<array<string,mixed>> */public function documents():array{return$this->pdo()->query('SELECT id,title FROM documents WHERE deleted_at IS NULL AND status="published" ORDER BY title')->fetchAll();}
    /** @return list<array<string,mixed>> */public function partnerships(bool$public=false):array{$where=$public?'p.status="published" AND p.published_at IS NOT NULL AND p.published_at<=NOW()':'1=1';return$this->pdo()->query('SELECT p.*,o.name AS partner_name,o.partner_type,o.country,o.website_url,o.logo_media_id,f.name AS faculty_name,d.file_path AS document_path FROM partnerships p INNER JOIN partners o ON o.id=p.partner_id LEFT JOIN faculties f ON f.id=p.faculty_id LEFT JOIN documents d ON d.id=p.document_id AND d.status="published" AND d.is_public=1 AND d.deleted_at IS NULL WHERE '.$where.' ORDER BY p.start_date DESC,p.title')->fetchAll();}
    /** @return list<array<string,mixed>> */
    public function publicEngagementPages(): array
    {
        return $this->pdo()->query(
            'SELECT p.title, p.slug, p.meta_description,
                    m.file_path AS hero_path, m.alt_text AS hero_alt_text
             FROM pages p
             LEFT JOIN media m ON m.id = p.hero_media_id
                AND m.status = "active" AND m.deleted_at IS NULL
             WHERE p.slug IN ("industrial-training", "community-outreach", "partnerships", "engineering-education")
               AND p.status = "published" AND p.published_at IS NOT NULL
               AND p.published_at <= NOW() AND p.deleted_at IS NULL
             ORDER BY FIELD(p.slug, "industrial-training", "community-outreach", "partnerships", "engineering-education")'
        )->fetchAll();
    }
    /** @return list<array<string,mixed>> */
    public function engagementPages(): array
    {
        return $this->pdo()->query(
            'SELECT id, title, slug, meta_description, status, updated_at
             FROM pages
             WHERE slug IN ("industrial-training", "community-outreach", "partnerships", "engineering-education")
               AND deleted_at IS NULL
             ORDER BY FIELD(slug, "industrial-training", "community-outreach", "partnerships", "engineering-education")'
        )->fetchAll();
    }
    /** @return array<string,mixed>|null */public function findPartnership(int$id):?array{$s=$this->pdo()->prepare('SELECT * FROM partnerships WHERE id=:id LIMIT 1');$s->execute(['id'=>$id]);$r=$s->fetch();return is_array($r)?$r:null;}
    /** @param array<string,mixed>$data */public function savePartnership(?int$id,array$data):int{return$this->save('partnerships',$id,$data,true);}
    public function partnershipStatus(int$id,string$status):void{$published=$status==='published'?',published_at=NOW()':($status==='draft'?',published_at=NULL':'');$s=$this->pdo()->prepare('UPDATE partnerships SET status=:status'.$published.' WHERE id=:id');$s->execute(['status'=>$status,'id'=>$id]);}
    /** @return list<array<string,mixed>> */public function impacts(?int$u,bool$public=false):array{$where=$u===null?'1=1':$this->impactScope($u,'i');if($public)$where.=' AND i.status="published" AND i.published_at IS NOT NULL AND i.published_at<=NOW()';return$this->pdo()->query('SELECT i.*,d.name AS department_name,d.slug AS department_slug,p.title AS project_title,p.slug AS project_slug,m.file_path AS media_path,m.alt_text AS media_alt_text FROM impact_stories i LEFT JOIN departments d ON d.id=i.department_id AND d.deleted_at IS NULL LEFT JOIN projects p ON p.id=i.project_id AND p.deleted_at IS NULL LEFT JOIN media m ON m.id=i.featured_media_id AND m.status="active" AND m.deleted_at IS NULL WHERE '.$where.' ORDER BY i.impact_date DESC,i.updated_at DESC')->fetchAll();}
    /** @return array<string,mixed>|null */public function findImpact(int$id,int$u):?array{$s=$this->pdo()->prepare('SELECT * FROM impact_stories i WHERE i.id=:id AND '.$this->impactScope($u,'i').' LIMIT 1');$s->execute(['id'=>$id]);$r=$s->fetch();return is_array($r)?$r:null;}
    /** @return array<string,mixed>|null */public function findPublicImpact(string$slug):?array{$s=$this->pdo()->prepare('SELECT i.*,d.name AS department_name,d.slug AS department_slug,p.title AS project_title,p.slug AS project_slug,m.file_path AS media_path,m.alt_text AS media_alt_text FROM impact_stories i LEFT JOIN departments d ON d.id=i.department_id LEFT JOIN projects p ON p.id=i.project_id LEFT JOIN media m ON m.id=i.featured_media_id AND m.status="active" AND m.deleted_at IS NULL WHERE i.slug=:slug AND i.status="published" AND i.published_at IS NOT NULL AND i.published_at<=NOW() LIMIT 1');$s->execute(['slug'=>$slug]);$r=$s->fetch();return is_array($r)?$r:null;}
    public function impactSlugExists(string$slug,?int$id=null):bool{$sql='SELECT 1 FROM impact_stories WHERE slug=:slug';$params=['slug'=>$slug];if($id!==null){$sql.=' AND id<>:id';$params['id']=$id;}$s=$this->pdo()->prepare($sql.' LIMIT 1');$s->execute($params);return$s->fetchColumn()!==false;}
    /** @param array<string,mixed>$data */public function saveImpact(?int$id,array$data):int{return$this->save('impact_stories',$id,$data,true);}
    public function impactStatus(int$id,string$status):void{$published=$status==='published'?',published_at=NOW()':($status==='draft'?',published_at=NULL':'');$s=$this->pdo()->prepare('UPDATE impact_stories SET status=:status'.$published.' WHERE id=:id');$s->execute(['status'=>$status,'id'=>$id]);}
    public function canUseDepartment(int$u,?int$id):bool{return$id===null?$this->scope->hasGlobalScope($u):($this->scope->hasGlobalScope($u)||in_array($id,$this->scope->departmentIds($u),true));}public function global(int$u):bool{return$this->scope->hasGlobalScope($u);}
    public function relation(string$table,int$id):bool{if(!in_array($table,['partners','faculties','departments','projects','media','documents'],true))return false;$extra=match($table){'departments','projects','media','documents'=>' AND deleted_at IS NULL',default=>''};$s=$this->pdo()->prepare("SELECT 1 FROM $table WHERE id=:id$extra LIMIT 1");$s->execute(['id'=>$id]);return$s->fetchColumn()!==false;}
    public function pdo():PDO{return$this->db->connection();}
    /** @param array<string,mixed>$data */private function save(string$table,?int$id,array$data,bool$status):int{if($id===null){$fields=array_keys($data);$suffix=$status?',status':'';$values=$status?',"draft"':'';$s=$this->pdo()->prepare(sprintf('INSERT INTO %s(%s%s)VALUES(%s%s)',$table,implode(',',$fields),$suffix,implode(',',array_map(static fn(string$f):string=>':'.$f,$fields)),$values));$s->execute($data);return(int)$this->pdo()->lastInsertId();}$sets=array_map(static fn(string$f):string=>$f.'=:'.$f,array_keys($data));$s=$this->pdo()->prepare('UPDATE '.$table.' SET '.implode(',',$sets).' WHERE id=:id');$s->execute([...$data,'id'=>$id]);return$id;}
    private function impactScope(int$u,string$a):string{if($this->scope->hasGlobalScope($u))return'1=1';$ids=$this->scope->departmentIds($u);if($ids===[])return'1=0';$list=implode(',',array_map('intval',$ids));return'('.$a.'.department_id IN('.$list.') OR EXISTS(SELECT 1 FROM projects ep WHERE ep.id='.$a.'.project_id AND ep.lead_department_id IN('.$list.')))';}
    /** @param list<int>$ids */private function ids(string$f,array$ids):string{return$ids===[]?'1=0':$f.' IN('.implode(',',array_map('intval',$ids)).')';}
}
