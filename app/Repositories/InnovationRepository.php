<?php

declare(strict_types=1);

namespace FastWebsite\Repositories;

use FastWebsite\Core\Database;
use FastWebsite\Services\DepartmentScopeService;
use PDO;

final class InnovationRepository
{
    public function __construct(private readonly Database $database, private readonly DepartmentScopeService $scope)
    {
    }

    /** @return list<array<string,mixed>> */
    public function departments(int $userId): array
    {
        $where = $this->scope->hasGlobalScope($userId) ? '1=1' : $this->ids('id', $this->scope->departmentIds($userId));
        return $this->connection()->query('SELECT id,name FROM departments WHERE deleted_at IS NULL AND '.$where.' ORDER BY name')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function projects(int $userId): array
    {
        $where = $this->scope->hasGlobalScope($userId) ? '1=1' : $this->ids('lead_department_id', $this->scope->departmentIds($userId));
        return $this->connection()->query('SELECT id,title,lead_department_id FROM projects WHERE deleted_at IS NULL AND '.$where.' ORDER BY title')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function researchUnits(int $userId, bool $publishedOnly = false): array
    {
        $where = $this->scope->hasGlobalScope($userId) ? '1=1' : $this->ids('department_id', $this->scope->departmentIds($userId));
        $published = $publishedOnly ? ' AND status="published" AND published_at IS NOT NULL AND published_at<=NOW()' : '';
        return $this->connection()->query('SELECT id,name,slug,department_id FROM research_units WHERE deleted_at IS NULL AND '.$where.$published.' ORDER BY name')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function locations(): array
    {
        return $this->connection()->query('SELECT id,campus,building,floor,room FROM locations ORDER BY campus,building,floor,room')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function activeImages(): array
    {
        return $this->connection()->query('SELECT id,original_name,file_path,alt_text FROM media WHERE media_type="image" AND status="active" AND deleted_at IS NULL ORDER BY original_name')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function innovations(?int $userId, bool $publishedOnly = false): array
    {
        $conditions = [$userId === null ? '1=1' : $this->innovationScope($userId, 'i')];
        if ($publishedOnly) $conditions[] = 'i.status="published" AND i.published_at IS NOT NULL AND i.published_at<=NOW()';
        $sql = 'SELECT i.*,d.name AS department_name,p.title AS project_title,m.file_path AS hero_path,m.alt_text AS hero_alt_text
                FROM innovations i LEFT JOIN departments d ON d.id=i.lead_department_id AND d.deleted_at IS NULL
                LEFT JOIN projects p ON p.id=i.project_id AND p.deleted_at IS NULL
                LEFT JOIN media m ON m.id=i.hero_media_id AND m.status="active" AND m.deleted_at IS NULL
                WHERE '.implode(' AND ', $conditions).' ORDER BY i.updated_at DESC,i.name';
        return $this->connection()->query($sql)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findInnovation(int $id, int $userId): ?array
    {
        $statement = $this->connection()->prepare('SELECT i.*,d.name AS department_name,p.title AS project_title,m.file_path AS hero_path,m.alt_text AS hero_alt_text FROM innovations i LEFT JOIN departments d ON d.id=i.lead_department_id LEFT JOIN projects p ON p.id=i.project_id LEFT JOIN media m ON m.id=i.hero_media_id AND m.status="active" AND m.deleted_at IS NULL WHERE i.id=:id AND '.$this->innovationScope($userId, 'i').' LIMIT 1');
        $statement->execute(['id'=>$id]); $row=$statement->fetch(); return is_array($row)?$row:null;
    }

    /** @return array<string,mixed>|null */
    public function findPublishedInnovation(string $slug): ?array
    {
        $statement=$this->connection()->prepare('SELECT i.*,d.name AS department_name,d.slug AS department_slug,p.title AS project_title,p.slug AS project_slug,m.file_path AS hero_path,m.alt_text AS hero_alt_text FROM innovations i LEFT JOIN departments d ON d.id=i.lead_department_id AND d.deleted_at IS NULL LEFT JOIN projects p ON p.id=i.project_id AND p.deleted_at IS NULL LEFT JOIN media m ON m.id=i.hero_media_id AND m.status="active" AND m.deleted_at IS NULL WHERE i.slug=:slug AND i.status="published" AND i.published_at IS NOT NULL AND i.published_at<=NOW() LIMIT 1');
        $statement->execute(['slug'=>$slug]);$row=$statement->fetch();return is_array($row)?$row:null;
    }

    public function innovationSlugExists(string $slug, ?int $exceptId=null): bool
    {
        $sql='SELECT 1 FROM innovations WHERE slug=:slug';$params=['slug'=>$slug];if($exceptId!==null){$sql.=' AND id<>:id';$params['id']=$exceptId;}$statement=$this->connection()->prepare($sql.' LIMIT 1');$statement->execute($params);return$statement->fetchColumn()!==false;
    }

    /** @param array<string,mixed> $data */
    public function saveInnovation(?int $id, array $data): int
    {
        if($id===null){$fields=array_keys($data);$statement=$this->connection()->prepare(sprintf('INSERT INTO innovations(%s,status)VALUES(%s,"draft")',implode(',',$fields),implode(',',array_map(static fn(string$f):string=>':'.$f,$fields))));$statement->execute($data);return(int)$this->connection()->lastInsertId();}
        $sets=array_map(static fn(string$f):string=>$f.'=:'.$f,array_keys($data));$statement=$this->connection()->prepare('UPDATE innovations SET '.implode(',',$sets).' WHERE id=:id');$statement->execute([...$data,'id'=>$id]);return$id;
    }

    public function innovationStatus(int$id,string$status):void{$published=$status==='published'?',published_at=NOW()':($status==='draft'?',published_at=NULL':'');$statement=$this->connection()->prepare('UPDATE innovations SET status=:status'.$published.' WHERE id=:id');$statement->execute(['status'=>$status,'id'=>$id]);}

    /** @return list<array<string,mixed>> */
    public function facilities(?int $userId, bool $publicOnly=false): array
    {
        $where=$userId===null?'1=1':$this->unitScope($userId,'ru');if($publicOnly)$where.=' AND f.status="active" AND ru.status="published" AND ru.published_at IS NOT NULL AND ru.published_at<=NOW()';
        return$this->connection()->query('SELECT f.*,ru.name AS research_unit_name,ru.slug AS research_unit_slug,d.name AS department_name,l.campus,l.building,l.floor,l.room FROM facilities f INNER JOIN research_units ru ON ru.id=f.research_unit_id AND ru.deleted_at IS NULL LEFT JOIN departments d ON d.id=ru.department_id LEFT JOIN locations l ON l.id=f.location_id WHERE '.$where.' ORDER BY ru.name,f.name')->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findFacility(int$id,int$userId):?array{$statement=$this->connection()->prepare('SELECT f.*,ru.name AS research_unit_name,ru.department_id,l.campus,l.building,l.floor,l.room FROM facilities f INNER JOIN research_units ru ON ru.id=f.research_unit_id AND ru.deleted_at IS NULL LEFT JOIN locations l ON l.id=f.location_id WHERE f.id=:id AND '.$this->unitScope($userId,'ru').' LIMIT 1');$statement->execute(['id'=>$id]);$row=$statement->fetch();return is_array($row)?$row:null;}

    /** @param array<string,mixed> $data */
    public function saveFacility(?int$id,array$data):int{if($id===null){$fields=array_keys($data);$statement=$this->connection()->prepare(sprintf('INSERT INTO facilities(%s)VALUES(%s)',implode(',',$fields),implode(',',array_map(static fn(string$f):string=>':'.$f,$fields))));$statement->execute($data);return(int)$this->connection()->lastInsertId();}$sets=array_map(static fn(string$f):string=>$f.'=:'.$f,array_keys($data));$statement=$this->connection()->prepare('UPDATE facilities SET '.implode(',',$sets).' WHERE id=:id');$statement->execute([...$data,'id'=>$id]);return$id;}

    /** @return list<array<string,mixed>> */
    public function equipment(?int$userId,bool$publicOnly=false):array{$where=$userId===null?'1=1':$this->unitScope($userId,'ru');if($publicOnly)$where.=' AND e.status="active" AND ru.status="published" AND ru.published_at IS NOT NULL AND ru.published_at<=NOW()';return$this->connection()->query('SELECT e.*,ru.name AS research_unit_name,ru.slug AS research_unit_slug,f.name AS facility_name,d.name AS department_name FROM equipment e INNER JOIN research_units ru ON ru.id=e.research_unit_id AND ru.deleted_at IS NULL LEFT JOIN facilities f ON f.id=e.facility_id LEFT JOIN departments d ON d.id=ru.department_id WHERE '.$where.' ORDER BY ru.name,e.name')->fetchAll();}

    /** @return array<string,mixed>|null */
    public function findEquipment(int$id,int$userId):?array{$statement=$this->connection()->prepare('SELECT e.*,ru.name AS research_unit_name,ru.department_id,f.name AS facility_name FROM equipment e INNER JOIN research_units ru ON ru.id=e.research_unit_id AND ru.deleted_at IS NULL LEFT JOIN facilities f ON f.id=e.facility_id WHERE e.id=:id AND '.$this->unitScope($userId,'ru').' LIMIT 1');$statement->execute(['id'=>$id]);$row=$statement->fetch();return is_array($row)?$row:null;}

    /** @param array<string,mixed> $data */
    public function saveEquipment(?int$id,array$data):int{if($id===null){$fields=array_keys($data);$statement=$this->connection()->prepare(sprintf('INSERT INTO equipment(%s)VALUES(%s)',implode(',',$fields),implode(',',array_map(static fn(string$f):string=>':'.$f,$fields))));$statement->execute($data);return(int)$this->connection()->lastInsertId();}$sets=array_map(static fn(string$f):string=>$f.'=:'.$f,array_keys($data));$statement=$this->connection()->prepare('UPDATE equipment SET '.implode(',',$sets).' WHERE id=:id');$statement->execute([...$data,'id'=>$id]);return$id;}

    public function relationExists(string$table,int$id):bool{if(!in_array($table,['departments','projects','research_units','locations','media','facilities'],true))return false;$extra=match($table){'departments','projects','research_units'=>' AND deleted_at IS NULL','media'=>' AND media_type="image" AND status="active" AND deleted_at IS NULL',default=>''};$statement=$this->connection()->prepare("SELECT 1 FROM $table WHERE id=:id$extra LIMIT 1");$statement->execute(['id'=>$id]);return$statement->fetchColumn()!==false;}
    public function facilityBelongsToUnit(int$facilityId,int$unitId):bool{$s=$this->connection()->prepare('SELECT 1 FROM facilities WHERE id=:facility AND research_unit_id=:unit LIMIT 1');$s->execute(['facility'=>$facilityId,'unit'=>$unitId]);return$s->fetchColumn()!==false;}
    public function canUseDepartment(int$userId,int$id):bool{return$this->scope->hasGlobalScope($userId)||in_array($id,$this->scope->departmentIds($userId),true);}
    public function canUseUnit(int$userId,int$id):bool{if($this->scope->hasGlobalScope($userId))return true;$ids=$this->scope->departmentIds($userId);if($ids===[])return false;$statement=$this->connection()->prepare('SELECT 1 FROM research_units WHERE id=:id AND department_id IN('.implode(',',array_map('intval',$ids)).') AND deleted_at IS NULL LIMIT 1');$statement->execute(['id'=>$id]);return$statement->fetchColumn()!==false;}
    public function connection():PDO{return$this->database->connection();}
    private function innovationScope(int$userId,string$alias):string{if($this->scope->hasGlobalScope($userId))return'1=1';$ids=$this->scope->departmentIds($userId);if($ids===[])return'1=0';$list=implode(',',array_map('intval',$ids));return'('.$alias.'.lead_department_id IN('.$list.') OR EXISTS(SELECT 1 FROM projects scoped_p WHERE scoped_p.id='.$alias.'.project_id AND scoped_p.lead_department_id IN('.$list.')))';}
    private function unitScope(int$userId,string$alias):string{if($this->scope->hasGlobalScope($userId))return'1=1';return$this->ids($alias.'.department_id',$this->scope->departmentIds($userId));}
    /** @param list<int> $ids */private function ids(string$field,array$ids):string{return$ids===[]?'1=0':$field.' IN('.implode(',',array_map('intval',$ids)).')';}
}
