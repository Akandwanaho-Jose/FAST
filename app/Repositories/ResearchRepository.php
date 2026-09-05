<?php

declare(strict_types=1);

namespace FastWebsite\Repositories;

use FastWebsite\Core\Database;
use FastWebsite\Services\DepartmentScopeService;
use PDO;

final class ResearchRepository
{
    public function __construct(private readonly Database $database, private readonly DepartmentScopeService $scope)
    {
    }

    /** @return list<array<string,mixed>> */
    public function types(): array
    {
        return $this->connection()->query('SELECT id, name, code, description FROM research_unit_types ORDER BY display_order, name')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function faculties(): array
    {
        return $this->connection()->query('SELECT id, name FROM faculties WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function departments(int $userId): array
    {
        $where = $this->scope->hasGlobalScope($userId) ? '1=1' : $this->idCondition('id', $this->scope->departmentIds($userId));
        return $this->connection()->query('SELECT id, faculty_id, name FROM departments WHERE deleted_at IS NULL AND ' . $where . ' ORDER BY name')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function locations(): array
    {
        return $this->connection()->query('SELECT id, campus, building, floor, room FROM locations ORDER BY campus, building, floor, room')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function activeImages(): array
    {
        return $this->connection()->query('SELECT id, original_name, file_path, alt_text FROM media WHERE media_type="image" AND status="active" AND deleted_at IS NULL ORDER BY original_name')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function availableStaff(int $userId): array
    {
        $departmentIds = $this->scope->departmentIds($userId);
        $condition = $this->scope->hasGlobalScope($userId)
            ? '1=1'
            : ($departmentIds === [] ? '1=0' : 'EXISTS (SELECT 1 FROM staff_departments scoped_sd WHERE scoped_sd.staff_id=s.id AND scoped_sd.department_id IN (' . implode(',', array_map('intval', $departmentIds)) . ') AND scoped_sd.is_current=1)');
        $statement = $this->connection()->query(
            'SELECT s.id, s.honorific_title, s.first_name, s.middle_name, s.last_name
             FROM staff s WHERE s.deleted_at IS NULL AND s.status="published" AND ' . $condition . '
             ORDER BY s.first_name, s.last_name'
        );
        return $statement->fetchAll();
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public function paginateAdmin(int $userId, string $search, string $status, int $page, int $perPage = 12): array
    {
        $conditions = ['ru.deleted_at IS NULL', $this->scopeCondition($userId, 'ru')];
        $parameters = [];
        if ($search !== '') {
            $conditions[] = '(ru.name LIKE :name OR ru.acronym LIKE :acronym OR ru.research_focus LIKE :focus)';
            $parameters = ['name'=>'%'.$search.'%', 'acronym'=>'%'.$search.'%', 'focus'=>'%'.$search.'%'];
        }
        if ($status !== '') {
            $conditions[] = 'ru.status=:status';
            $parameters['status'] = $status;
        }
        $where = implode(' AND ', $conditions);
        $count = $this->connection()->prepare('SELECT COUNT(*) FROM research_units ru WHERE ' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $statement = $this->connection()->prepare(sprintf(
            'SELECT ru.id, ru.name, ru.acronym, ru.slug, ru.status, ru.updated_at,
                    rut.name AS type_name, d.name AS department_name
             FROM research_units ru INNER JOIN research_unit_types rut ON rut.id=ru.unit_type_id
             LEFT JOIN departments d ON d.id=ru.department_id
             WHERE %s ORDER BY ru.display_order, ru.name LIMIT %d OFFSET %d',
            $where, $perPage, ($page-1)*$perPage
        ));
        $statement->execute($parameters);
        return ['items'=>$statement->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }

    /** @return array<string,mixed>|null */
    public function findAdmin(int $id, int $userId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT ru.*, rut.name AS type_name, f.name AS faculty_name, d.name AS department_name,
                    l.campus,l.building,l.floor,l.room,l.directions,
                    m.file_path AS hero_path,m.alt_text AS hero_alt_text
             FROM research_units ru INNER JOIN research_unit_types rut ON rut.id=ru.unit_type_id
             INNER JOIN faculties f ON f.id=ru.faculty_id LEFT JOIN departments d ON d.id=ru.department_id
             LEFT JOIN locations l ON l.id=ru.location_id LEFT JOIN media m ON m.id=ru.hero_media_id AND m.status="active" AND m.deleted_at IS NULL
             WHERE ru.id=:id AND ru.deleted_at IS NULL AND ' . $this->scopeCondition($userId, 'ru') . ' LIMIT 1'
        );
        $statement->execute(['id'=>$id]);
        $row=$statement->fetch(); return is_array($row)?$row:null;
    }

    /** @return array<string,mixed>|null */
    public function findForUpdate(int $id): ?array
    {
        $statement=$this->connection()->prepare('SELECT * FROM research_units WHERE id=:id AND deleted_at IS NULL FOR UPDATE');
        $statement->execute(['id'=>$id]); $row=$statement->fetch(); return is_array($row)?$row:null;
    }

    /** @return array<string,mixed>|null */
    public function findPublishedBySlug(string $slug): ?array
    {
        $statement=$this->connection()->prepare(
            'SELECT ru.*,rut.name AS type_name,f.name AS faculty_name,d.name AS department_name,d.slug AS department_slug,
                    l.campus,l.building,l.floor,l.room,l.directions,m.file_path AS hero_path,m.alt_text AS hero_alt_text
             FROM research_units ru INNER JOIN research_unit_types rut ON rut.id=ru.unit_type_id
             INNER JOIN faculties f ON f.id=ru.faculty_id LEFT JOIN departments d ON d.id=ru.department_id
             LEFT JOIN locations l ON l.id=ru.location_id LEFT JOIN media m ON m.id=ru.hero_media_id AND m.status="active" AND m.deleted_at IS NULL
             WHERE ru.slug=:slug AND ru.status="published" AND ru.published_at IS NOT NULL AND ru.published_at<=NOW() AND ru.deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['slug'=>$slug]); $row=$statement->fetch(); return is_array($row)?$row:null;
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public function paginatePublished(string $search, string $type, int $page, int $perPage=12): array
    {
        $conditions=['ru.status="published"','ru.published_at IS NOT NULL','ru.published_at<=NOW()','ru.deleted_at IS NULL']; $parameters=[];
        if($search!==''){ $conditions[]='(ru.name LIKE :name OR ru.acronym LIKE :acronym OR ru.research_focus LIKE :focus)'; $parameters=['name'=>'%'.$search.'%','acronym'=>'%'.$search.'%','focus'=>'%'.$search.'%']; }
        if($type!==''){ $conditions[]='rut.code=:type'; $parameters['type']=$type; }
        $where=implode(' AND ',$conditions);
        $count=$this->connection()->prepare('SELECT COUNT(*) FROM research_units ru INNER JOIN research_unit_types rut ON rut.id=ru.unit_type_id WHERE '.$where); $count->execute($parameters);
        $total=(int)$count->fetchColumn(); $pages=max(1,(int)ceil($total/$perPage)); $page=min(max(1,$page),$pages);
        $statement=$this->connection()->prepare(sprintf(
            'SELECT ru.id,ru.name,ru.acronym,ru.slug,ru.overview,ru.research_focus,rut.name AS type_name,d.name AS department_name,m.file_path AS hero_path,m.alt_text AS hero_alt_text
             FROM research_units ru INNER JOIN research_unit_types rut ON rut.id=ru.unit_type_id LEFT JOIN departments d ON d.id=ru.department_id
             LEFT JOIN media m ON m.id=ru.hero_media_id AND m.status="active" AND m.deleted_at IS NULL
             WHERE %s ORDER BY ru.display_order,ru.name LIMIT %d OFFSET %d',$where,$perPage,($page-1)*$perPage));
        $statement->execute($parameters); return ['items'=>$statement->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }

    /**
     * Published research units grouped by their department, in the site's
     * canonical department order (departments.display_order, then name).
     * Used for the ungrouped/no-filter view of the public research index.
     *
     * @return list<array{department:array<string,mixed>,units:list<array<string,mixed>>}>
     */
    public function publishedGroupedByDepartment(): array
    {
        $statement = $this->connection()->query(
            'SELECT ru.id,ru.name,ru.acronym,ru.slug,ru.overview,ru.research_focus,rut.name AS type_name,
                    d.id AS department_id,d.name AS department_name,d.slug AS department_slug,
                    d.display_order AS department_display_order,
                    m.file_path AS hero_path,m.alt_text AS hero_alt_text
             FROM research_units ru INNER JOIN research_unit_types rut ON rut.id=ru.unit_type_id
             LEFT JOIN departments d ON d.id=ru.department_id AND d.deleted_at IS NULL
             LEFT JOIN media m ON m.id=ru.hero_media_id AND m.status="active" AND m.deleted_at IS NULL
             WHERE ru.status="published" AND ru.published_at IS NOT NULL AND ru.published_at<=NOW() AND ru.deleted_at IS NULL
             ORDER BY d.display_order IS NULL, d.display_order, d.name, ru.display_order, ru.name'
        );
        $groups = [];
        foreach ($statement->fetchAll() as $row) {
            $key = $row['department_id'] !== null ? (int) $row['department_id'] : 0;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'department' => $row['department_id'] !== null
                        ? ['id' => (int) $row['department_id'], 'name' => $row['department_name'], 'slug' => $row['department_slug']]
                        : ['id' => 0, 'name' => 'Other research labs', 'slug' => ''],
                    'units' => [],
                ];
            }
            $groups[$key]['units'][] = $row;
        }
        return array_values($groups);
    }

    /** @return list<array<string,mixed>> */
    public function publishedByDepartment(int $departmentId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT ru.id,ru.name,ru.acronym,ru.slug,ru.overview,ru.research_focus,rut.name AS type_name,
                    m.file_path AS hero_path,m.alt_text AS hero_alt_text
             FROM research_units ru INNER JOIN research_unit_types rut ON rut.id=ru.unit_type_id
             LEFT JOIN media m ON m.id=ru.hero_media_id AND m.status="active" AND m.deleted_at IS NULL
             WHERE ru.department_id=:department_id AND ru.status="published" AND ru.published_at IS NOT NULL
               AND ru.published_at<=NOW() AND ru.deleted_at IS NULL
             ORDER BY ru.display_order, ru.name'
        );
        $statement->execute(['department_id' => $departmentId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function projects(int $unitId, bool $publishedOnly=false): array
    {
        $published=$publishedOnly?' AND p.publication_status="published" AND p.published_at IS NOT NULL AND p.published_at<=NOW()':'';
        $statement=$this->connection()->prepare(
            'SELECT p.id,p.title,p.short_title,p.slug,p.summary,p.project_status,pru.is_lead_unit,
                    m.file_path AS hero_path,m.alt_text AS hero_alt_text
             FROM project_research_units pru INNER JOIN projects p ON p.id=pru.project_id AND p.deleted_at IS NULL'.$published.'
             LEFT JOIN media m ON m.id=p.hero_media_id AND m.status="active" AND m.deleted_at IS NULL
             WHERE pru.research_unit_id=:id
             ORDER BY FIELD(p.project_status,"ongoing","planned","completed","suspended","cancelled"),p.title'
        );
        $statement->execute(['id'=>$unitId]); return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function members(int $unitId, bool $publishedOnly=false): array
    {
        $published=$publishedOnly?' AND s.status="published" AND s.published_at IS NOT NULL AND s.published_at<=NOW()':'';
        $statement=$this->connection()->prepare(
            'SELECT rum.*,s.honorific_title,s.first_name,s.middle_name,s.last_name,s.slug AS staff_slug,
                    s.short_biography,m.file_path AS profile_path,m.alt_text AS profile_alt_text
             FROM research_unit_members rum INNER JOIN staff s ON s.id=rum.staff_id AND s.deleted_at IS NULL'.$published.'
             LEFT JOIN media m ON m.id=s.profile_media_id AND m.status="active" AND m.deleted_at IS NULL
             WHERE rum.research_unit_id=:id AND rum.is_current=1 AND (rum.start_date IS NULL OR rum.start_date<=CURDATE()) AND (rum.end_date IS NULL OR rum.end_date>=CURDATE())
             ORDER BY FIELD(rum.membership_role,"lead","deputy_lead","researcher","technician","graduate_researcher","student_researcher","affiliate","external_collaborator","other"),rum.display_order,s.first_name,s.last_name'
        );
        $statement->execute(['id'=>$unitId]); return $statement->fetchAll();
    }

    public function relationExists(string $table,int $id): bool
    {
        if(!in_array($table,['faculties','departments','research_unit_types','locations','media','staff'],true)) return false;
        $extra=match($table){'faculties','departments','staff'=>' AND deleted_at IS NULL','media'=>' AND media_type="image" AND status="active" AND deleted_at IS NULL',default=>''};
        $statement=$this->connection()->prepare("SELECT 1 FROM $table WHERE id=:id$extra LIMIT 1"); $statement->execute(['id'=>$id]); return $statement->fetchColumn()!==false;
    }

    public function departmentBelongsToFaculty(int $departmentId, int $facultyId): bool
    {
        $statement=$this->connection()->prepare('SELECT 1 FROM departments WHERE id=:department AND faculty_id=:faculty AND deleted_at IS NULL LIMIT 1');
        $statement->execute(['department'=>$departmentId,'faculty'=>$facultyId]);
        return $statement->fetchColumn()!==false;
    }

    public function slugExists(string $slug,?int $exceptId=null): bool
    {
        $sql='SELECT 1 FROM research_units WHERE slug=:slug AND deleted_at IS NULL'; $params=['slug'=>$slug];
        if($exceptId!==null){$sql.=' AND id<>:id';$params['id']=$exceptId;} $s=$this->connection()->prepare($sql.' LIMIT 1');$s->execute($params);return $s->fetchColumn()!==false;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $fields=array_keys($data);$s=$this->connection()->prepare(sprintf('INSERT INTO research_units (%s,status) VALUES (%s,"draft")',implode(',',$fields),implode(',',array_map(fn($f)=>':'.$f,$fields))));$s->execute($data);return(int)$this->connection()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id,array $data): void
    {
        $sets=array_map(fn($f)=>$f.'=:'.$f,array_keys($data));$s=$this->connection()->prepare('UPDATE research_units SET '.implode(',',$sets).' WHERE id=:id AND deleted_at IS NULL');$s->execute([...$data,'id'=>$id]);
    }

    public function changeStatus(int $id,string $status): void
    {
        $published=$status==='published'?',published_at=NOW()':($status==='draft'?',published_at=NULL':'');$s=$this->connection()->prepare("UPDATE research_units SET status=:status$published WHERE id=:id");$s->execute(['status'=>$status,'id'=>$id]);
    }

    public function addMember(int $unitId,int $staffId,string $role,?string $title,?string $startDate,int $order): int
    {
        $this->connection()->prepare('UPDATE research_unit_members SET is_current=0,end_date=COALESCE(end_date,CURDATE()) WHERE research_unit_id=:unit AND staff_id=:staff AND is_current=1')->execute(['unit'=>$unitId,'staff'=>$staffId]);
        if($role==='lead'){$this->connection()->prepare('UPDATE research_unit_members SET is_current=0,end_date=COALESCE(end_date,CURDATE()) WHERE research_unit_id=:id AND membership_role="lead" AND is_current=1')->execute(['id'=>$unitId]);}
        $s=$this->connection()->prepare('INSERT INTO research_unit_members (research_unit_id,staff_id,membership_role,role_title,start_date,is_current,display_order) VALUES (:unit,:staff,:role,:title,:start,1,:ordering)');
        $s->execute(['unit'=>$unitId,'staff'=>$staffId,'role'=>$role,'title'=>$title,'start'=>$startDate,'ordering'=>$order]);return(int)$this->connection()->lastInsertId();
    }

    public function removeMember(int $unitId,int $memberId): bool
    {
        $s=$this->connection()->prepare('UPDATE research_unit_members SET is_current=0,end_date=COALESCE(end_date,CURDATE()) WHERE id=:id AND research_unit_id=:unit');$s->execute(['id'=>$memberId,'unit'=>$unitId]);return$s->rowCount()===1;
    }

    public function recordRevision(int $id,?array $previous,array $revised,int $userId,string $note): void
    {
        $n=$this->connection()->prepare('SELECT COALESCE(MAX(revision_number),0)+1 FROM content_revisions WHERE entity_type="research_unit" AND entity_id=:id');$n->execute(['id'=>$id]);
        $s=$this->connection()->prepare('INSERT INTO content_revisions (entity_type,entity_id,revision_number,previous_data,revised_data,revised_by,revision_note) VALUES ("research_unit",:id,:number,:previous,:revised,:user,:note)');
        $s->execute(['id'=>$id,'number'=>(int)$n->fetchColumn(),'previous'=>$previous===null?null:json_encode($previous,JSON_THROW_ON_ERROR),'revised'=>json_encode($revised,JSON_THROW_ON_ERROR),'user'=>$userId,'note'=>$note]);
    }

    public function recordApproval(int $id,string $action,int $userId,string $comment): void
    { $s=$this->connection()->prepare('INSERT INTO content_approvals (entity_type,entity_id,action,acted_by,comment) VALUES ("research_unit",:id,:action,:user,:comment)');$s->execute(['id'=>$id,'action'=>$action,'user'=>$userId,'comment'=>$comment!==''?$comment:null]); }

    /** @return list<array<string,mixed>> */
    public function approvalHistory(int $id): array
    { $s=$this->connection()->prepare('SELECT ca.*,u.name AS actor_name FROM content_approvals ca LEFT JOIN users u ON u.id=ca.acted_by WHERE ca.entity_type="research_unit" AND ca.entity_id=:id ORDER BY ca.created_at DESC,ca.id DESC');$s->execute(['id'=>$id]);return$s->fetchAll(); }

    public function canAccess(int $userId,int $unitId): bool
    { if($this->scope->hasGlobalScope($userId))return true;$ids=$this->scope->departmentIds($userId);if($ids===[])return false;$s=$this->connection()->prepare('SELECT 1 FROM research_units WHERE id=:id AND department_id IN ('.implode(',',array_map('intval',$ids)).') LIMIT 1');$s->execute(['id'=>$unitId]);return$s->fetchColumn()!==false; }

    public function canUseDepartment(int $userId,?int $departmentId): bool
    { return $this->scope->hasGlobalScope($userId)||($departmentId!==null&&in_array($departmentId,$this->scope->departmentIds($userId),true)); }

    public function connection(): PDO{return $this->database->connection();}

    private function scopeCondition(int $userId,string $alias): string
    { if($this->scope->hasGlobalScope($userId))return'1=1';$ids=$this->scope->departmentIds($userId);return$ids===[]?'1=0':$alias.'.department_id IN ('.implode(',',array_map('intval',$ids)).')'; }
    /** @param list<int> $ids */
    private function idCondition(string $field,array $ids): string{return$ids===[]?'1=0':$field.' IN ('.implode(',',array_map('intval',$ids)).')';}
}
