<?php

declare(strict_types=1);

namespace FastWebsite\Repositories;

use FastWebsite\Core\Database;use FastWebsite\Services\DepartmentScopeService;use PDO;

final class SiteContentRepository
{
    public function __construct(private readonly Database$db,private readonly DepartmentScopeService$scope){}
    /** @return list<array<string,mixed>> */public function newsCategories():array{return$this->pdo()->query('SELECT * FROM news_categories WHERE is_active=1 ORDER BY display_order,name')->fetchAll();}
    /** @return list<array<string,mixed>> */public function eventCategories():array{return$this->pdo()->query('SELECT * FROM event_categories WHERE is_active=1 ORDER BY display_order,name')->fetchAll();}
    /** @return list<array<string,mixed>> */public function departments(int$u):array{$where=$this->scope->hasGlobalScope($u)?'1=1':$this->ids('id',$this->scope->departmentIds($u));return$this->pdo()->query('SELECT id,name FROM departments WHERE deleted_at IS NULL AND '.$where.' ORDER BY name')->fetchAll();}
    /** @return list<array<string,mixed>> */public function images():array{return$this->pdo()->query('SELECT id,original_name,file_path,alt_text FROM media WHERE media_type="image" AND status="active" AND deleted_at IS NULL ORDER BY original_name')->fetchAll();}
    /** @return list<array<string,mixed>> */public function staff():array{return$this->pdo()->query('SELECT id,honorific_title,first_name,middle_name,last_name FROM staff WHERE status="published" AND deleted_at IS NULL ORDER BY first_name,last_name')->fetchAll();}
    /** @return array<string,mixed>|null */
    public function publicDean(): ?array
    {
        $row = $this->pdo()->query(
            'SELECT s.id, s.slug, s.honorific_title, s.first_name, s.middle_name,
                    s.last_name, s.post_nominals, s.short_biography,
                    COALESCE(sp.title_override, p.name) AS position_title,
                    m.file_path AS profile_path, m.alt_text AS profile_alt_text
             FROM staff s
             INNER JOIN staff_positions sp ON sp.staff_id = s.id
                AND sp.is_current = 1
                AND (sp.start_date IS NULL OR sp.start_date <= CURDATE())
                AND (sp.end_date IS NULL OR sp.end_date >= CURDATE())
             INNER JOIN positions p ON p.id = sp.position_id AND p.code = "dean"
             LEFT JOIN media m ON m.id = s.profile_media_id
                AND m.status = "active" AND m.deleted_at IS NULL
             WHERE s.status = "published" AND s.published_at IS NOT NULL
                AND s.published_at <= NOW() AND s.deleted_at IS NULL
             ORDER BY sp.display_order, sp.id DESC LIMIT 1'
        )->fetch();

        return is_array($row) ? $row : null;
    }
    /** @return list<array<string,mixed>> */public function locations():array{return$this->pdo()->query('SELECT id,campus,building,floor,room FROM locations ORDER BY campus,building,room')->fetchAll();}
    /** @return list<array<string,mixed>> */public function news(?int$u,bool$public=false,int$limit=100):array{$where=$u===null?'1=1':$this->newsScope($u,'n');if($public)$where.=' AND n.status="published" AND n.published_at IS NOT NULL AND n.published_at<=NOW()';$limit=max(1,min(100,$limit));return$this->pdo()->query('SELECT n.*,c.name AS category_name,c.slug AS category_slug,m.file_path AS media_path,m.alt_text AS media_alt_text,u.name AS author_name FROM news n INNER JOIN news_categories c ON c.id=n.category_id LEFT JOIN media m ON m.id=n.featured_media_id AND m.status="active" AND m.deleted_at IS NULL LEFT JOIN users u ON u.id=n.author_user_id WHERE n.deleted_at IS NULL AND '.$where.' ORDER BY n.is_featured DESC,n.article_date DESC,n.id DESC LIMIT '.$limit)->fetchAll();}
    /** @return array<string,mixed>|null */public function findNews(int$id,int$u):?array{$s=$this->pdo()->prepare('SELECT n.*,nd.department_id FROM news n LEFT JOIN news_departments nd ON nd.news_id=n.id WHERE n.id=:id AND n.deleted_at IS NULL AND '.$this->newsScope($u,'n').' LIMIT 1');$s->execute(['id'=>$id]);$r=$s->fetch();return is_array($r)?$r:null;}
    /** @return array<string,mixed>|null */public function publicNews(string$slug):?array{$s=$this->pdo()->prepare('SELECT n.*,c.name AS category_name,m.file_path AS media_path,m.alt_text AS media_alt_text,u.name AS author_name,st.slug AS author_staff_slug,st.honorific_title AS author_honorific_title,st.first_name AS author_first_name,st.last_name AS author_last_name,sm.file_path AS author_photo_path FROM news n INNER JOIN news_categories c ON c.id=n.category_id LEFT JOIN media m ON m.id=n.featured_media_id AND m.status="active" AND m.deleted_at IS NULL LEFT JOIN users u ON u.id=n.author_user_id LEFT JOIN staff st ON st.user_id=n.author_user_id AND st.status="published" AND st.deleted_at IS NULL LEFT JOIN media sm ON sm.id=st.profile_media_id AND sm.status="active" AND sm.deleted_at IS NULL WHERE n.slug=:slug AND n.status="published" AND n.published_at IS NOT NULL AND n.published_at<=NOW() AND n.deleted_at IS NULL LIMIT 1');$s->execute(['slug'=>$slug]);$r=$s->fetch();return is_array($r)?$r:null;}
    /** @param array<string,mixed>$data */public function saveNews(?int$id,array$data,?int$department):int{$id=$this->save('news',$id,$data,true);$this->pdo()->prepare('DELETE FROM news_departments WHERE news_id=:id')->execute(['id'=>$id]);if($department!==null)$this->pdo()->prepare('INSERT INTO news_departments(news_id,department_id)VALUES(:news,:department)')->execute(['news'=>$id,'department'=>$department]);return$id;}
    /** @return list<array<string,mixed>> */public function newsGallery(int$newsId):array{$s=$this->pdo()->prepare('SELECT m.id,m.file_path,m.alt_text FROM news_media nm INNER JOIN media m ON m.id=nm.media_id AND m.status="active" AND m.deleted_at IS NULL WHERE nm.news_id=:news_id ORDER BY nm.display_order,nm.id');$s->execute(['news_id'=>$newsId]);return$s->fetchAll();}
    public function addNewsGalleryPhoto(int$newsId,int$mediaId):void{$o=$this->pdo()->prepare('SELECT COALESCE(MAX(display_order),-1)+1 FROM news_media WHERE news_id=:id');$o->execute(['id'=>$newsId]);$order=(int)$o->fetchColumn();$this->pdo()->prepare('INSERT INTO news_media(news_id,media_id,display_order)VALUES(:news,:media,:order)')->execute(['news'=>$newsId,'media'=>$mediaId,'order'=>$order]);}
    public function removeNewsGalleryPhoto(int$newsId,int$mediaId):bool{$s=$this->pdo()->prepare('DELETE FROM news_media WHERE news_id=:news AND media_id=:media');$s->execute(['news'=>$newsId,'media'=>$mediaId]);return$s->rowCount()===1;}
    public function newsStatus(int$id,string$status):void{$this->status('news',$id,$status);}
    /** @return list<array<string,mixed>> */public function events(?int$u,bool$public=false,int$limit=100):array{$where=$u===null?'1=1':$this->eventScope($u,'e');if($public)$where.=' AND e.status="published" AND e.published_at IS NOT NULL AND e.published_at<=NOW()';$limit=max(1,min(100,$limit));return$this->pdo()->query('SELECT e.*,c.name AS category_name,l.campus,l.building,l.room,m.file_path AS media_path,m.alt_text AS media_alt_text FROM events e INNER JOIN event_categories c ON c.id=e.category_id LEFT JOIN locations l ON l.id=e.location_id LEFT JOIN media m ON m.id=e.featured_media_id AND m.status="active" AND m.deleted_at IS NULL WHERE '.$where.' ORDER BY e.starts_at ASC LIMIT '.$limit)->fetchAll();}
    /** @return array<string,mixed>|null */public function findEvent(int$id,int$u):?array{$s=$this->pdo()->prepare('SELECT e.*,ed.department_id FROM events e LEFT JOIN event_departments ed ON ed.event_id=e.id AND ed.is_lead_organiser=1 WHERE e.id=:id AND '.$this->eventScope($u,'e').' LIMIT 1');$s->execute(['id'=>$id]);$r=$s->fetch();return is_array($r)?$r:null;}
    /** @return array<string,mixed>|null */public function publicEvent(string$slug):?array{$s=$this->pdo()->prepare('SELECT e.*,c.name AS category_name,l.campus,l.building,l.floor,l.room,l.directions,m.file_path AS media_path,m.alt_text AS media_alt_text,st.slug AS contact_staff_slug,st.honorific_title,st.first_name,st.middle_name,st.last_name FROM events e INNER JOIN event_categories c ON c.id=e.category_id LEFT JOIN locations l ON l.id=e.location_id LEFT JOIN media m ON m.id=e.featured_media_id AND m.status="active" AND m.deleted_at IS NULL LEFT JOIN staff st ON st.id=e.contact_staff_id AND st.status="published" AND st.deleted_at IS NULL WHERE e.slug=:slug AND e.status="published" AND e.published_at IS NOT NULL AND e.published_at<=NOW() LIMIT 1');$s->execute(['slug'=>$slug]);$r=$s->fetch();return is_array($r)?$r:null;}
    /** @param array<string,mixed>$data */public function saveEvent(?int$id,array$data,?int$department):int{$id=$this->save('events',$id,$data,true);$this->pdo()->prepare('DELETE FROM event_departments WHERE event_id=:id')->execute(['id'=>$id]);if($department!==null)$this->pdo()->prepare('INSERT INTO event_departments(event_id,department_id,is_lead_organiser)VALUES(:event,:department,1)')->execute(['event'=>$id,'department'=>$department]);return$id;}
    public function eventStatus(int$id,string$status):void{$this->status('events',$id,$status);}
    /** @return list<array<string,mixed>> */public function pages(bool$public=false):array{$where='deleted_at IS NULL'.($public?' AND status="published" AND published_at IS NOT NULL AND published_at<=NOW()':'');return$this->pdo()->query('SELECT * FROM pages WHERE '.$where.' ORDER BY display_order,title')->fetchAll();}
    /** @return array<string,mixed>|null */public function findPage(int$id):?array{$s=$this->pdo()->prepare('SELECT p.*,m.file_path AS hero_path,m.alt_text AS hero_alt_text FROM pages p LEFT JOIN media m ON m.id=p.hero_media_id AND m.status="active" AND m.deleted_at IS NULL WHERE p.id=:id AND p.deleted_at IS NULL LIMIT 1');$s->execute(['id'=>$id]);$r=$s->fetch();return is_array($r)?$r:null;}
    /** @return array<string,mixed>|null */public function publicPage(string$slug):?array{$s=$this->pdo()->prepare('SELECT p.*,m.file_path AS hero_path,m.alt_text AS hero_alt_text FROM pages p LEFT JOIN media m ON m.id=p.hero_media_id AND m.status="active" AND m.deleted_at IS NULL WHERE p.slug=:slug AND p.status="published" AND p.published_at IS NOT NULL AND p.published_at<=NOW() AND p.deleted_at IS NULL LIMIT 1');$s->execute(['slug'=>$slug]);$r=$s->fetch();return is_array($r)?$r:null;}
    /** @return list<array<string,mixed>> */
    public function publicStudentLifePages(): array
    {
        return $this->pdo()->query(
            'SELECT p.title, p.slug, p.meta_description,
                    m.file_path AS hero_path, m.alt_text AS hero_alt_text
             FROM pages p
             LEFT JOIN media m ON m.id = p.hero_media_id
                AND m.status = "active" AND m.deleted_at IS NULL
             WHERE p.slug IN ("professional-bodies", "student-mentorship-programme", "faculty-mentorship-programme", "meet-our-mentors")
               AND p.status = "published" AND p.published_at IS NOT NULL
               AND p.published_at <= NOW() AND p.deleted_at IS NULL
             ORDER BY FIELD(p.slug, "professional-bodies", "student-mentorship-programme", "faculty-mentorship-programme", "meet-our-mentors")'
        )->fetchAll();
    }
    /** @param array<string,mixed>$data */public function savePage(?int$id,array$data):int{return$this->save('pages',$id,$data,true);}public function pageStatus(int$id,string$status):void{$this->status('pages',$id,$status);}
    /**
     * Each section can optionally carry a couple of "additional photos"
     * (stored as {"extra_media_ids":[...]} in settings_json) that stack
     * beside its main image on the public page so the photo column can grow
     * to match a long paragraph next to it, instead of one image floating in
     * leftover white space - see app/Views/public/content/page.php.
     *
     * @return list<array<string,mixed>>
     */
    public function sections(int$page,bool$visible=false):array{
        $extra=$visible?' AND is_visible=1':'';
        $s=$this->pdo()->prepare('SELECT ps.*,m.file_path AS media_path,m.alt_text AS media_alt_text FROM page_sections ps LEFT JOIN media m ON m.id=ps.media_id AND m.status="active" AND m.deleted_at IS NULL WHERE ps.page_id=:id'.$extra.' ORDER BY ps.display_order,ps.id');
        $s->execute(['id'=>$page]);
        $rows=$s->fetchAll();

        $extraIds=[];
        foreach($rows as$row)foreach($this->extraMediaIds($row['settings_json']??null) as$id)$extraIds[$id]=$id;
        $extraMedia=[];
        if($extraIds!==[]){
            $placeholders=implode(',',array_fill(0,count($extraIds),'?'));
            $stmt=$this->pdo()->prepare('SELECT id,file_path,alt_text FROM media WHERE status="active" AND deleted_at IS NULL AND id IN ('.$placeholders.')');
            $stmt->execute(array_values($extraIds));
            foreach($stmt->fetchAll() as$media)$extraMedia[(int)$media['id']]=$media;
        }

        foreach($rows as&$row){
            $row['extra_photos']=[];
            foreach($this->extraMediaIds($row['settings_json']??null) as$id){
                if(!isset($extraMedia[$id]))continue;
                $row['extra_photos'][]=['id'=>$id,'media_path'=>$extraMedia[$id]['file_path'],'media_alt_text'=>$extraMedia[$id]['alt_text']];
            }
        }
        unset($row);

        return$rows;
    }

    /** @return list<int> */
    private function extraMediaIds(?string$settingsJson):array{
        if($settingsJson===null||$settingsJson==='')return[];
        $decoded=json_decode($settingsJson,true);
        if(!is_array($decoded)||!isset($decoded['extra_media_ids'])||!is_array($decoded['extra_media_ids']))return[];
        return array_values(array_filter(array_map('intval',$decoded['extra_media_ids']),static fn(int$id):bool=>$id>0));
    }
    /** @param array<string,mixed>$data */public function addSection(int$page,array$data):int{$data=['page_id'=>$page,...$data];return$this->save('page_sections',null,$data,false);}
    /** @return array<string,mixed>|null */public function findSection(int$page,int$id):?array{$s=$this->pdo()->prepare('SELECT * FROM page_sections WHERE id=:id AND page_id=:page LIMIT 1');$s->execute(['id'=>$id,'page'=>$page]);$r=$s->fetch();return is_array($r)?$r:null;}
    /** @param array<string,mixed>$data */public function updateSection(int$page,int$id,array$data):bool{$sets=array_map(static fn(string$field):string=>$field.'=:'.$field,array_keys($data));$s=$this->pdo()->prepare('UPDATE page_sections SET '.implode(',',$sets).' WHERE id=:id AND page_id=:page');$s->execute([...$data,'id'=>$id,'page'=>$page]);return$s->rowCount()<=1;}
    public function removeSection(int$page,int$id):bool{$s=$this->pdo()->prepare('DELETE FROM page_sections WHERE id=:id AND page_id=:page');$s->execute(['id'=>$id,'page'=>$page]);return$s->rowCount()===1;}
    public function slugExists(string$table,string$slug,?int$id=null):bool{if(!in_array($table,['news','events','pages'],true))return true;$sql='SELECT 1 FROM '.$table.' WHERE slug=:slug';$params=['slug'=>$slug];if($id!==null){$sql.=' AND id<>:id';$params['id']=$id;}$s=$this->pdo()->prepare($sql.' LIMIT 1');$s->execute($params);return$s->fetchColumn()!==false;}
    public function relation(string$table,int$id):bool{if(!in_array($table,['news_categories','event_categories','departments','media','staff','locations','pages'],true))return false;$extra=match($table){'departments','media','staff','pages'=>' AND deleted_at IS NULL',default=>''};$s=$this->pdo()->prepare("SELECT 1 FROM $table WHERE id=:id$extra LIMIT 1");$s->execute(['id'=>$id]);return$s->fetchColumn()!==false;}public function canDepartment(int$u,?int$d):bool{return$d===null?$this->scope->hasGlobalScope($u):($this->scope->hasGlobalScope($u)||in_array($d,$this->scope->departmentIds($u),true));}public function global(int$u):bool{return$this->scope->hasGlobalScope($u);}public function pdo():PDO{return$this->db->connection();}
    /** @param array<string,mixed>$data */private function save(string$table,?int$id,array$data,bool$status):int{if($id===null){$fields=array_keys($data);$suf=$status?',status':'';$val=$status?',"draft"':'';$s=$this->pdo()->prepare(sprintf('INSERT INTO %s(%s%s)VALUES(%s%s)',$table,implode(',',$fields),$suf,implode(',',array_map(static fn(string$f):string=>':'.$f,$fields)),$val));$s->execute($data);return(int)$this->pdo()->lastInsertId();}$sets=array_map(static fn(string$f):string=>$f.'=:'.$f,array_keys($data));$s=$this->pdo()->prepare('UPDATE '.$table.' SET '.implode(',',$sets).' WHERE id=:id');$s->execute([...$data,'id'=>$id]);return$id;}private function status(string$table,int$id,string$status):void{$published=$status==='published'?',published_at=NOW()':($status==='draft'?',published_at=NULL':'');$s=$this->pdo()->prepare('UPDATE '.$table.' SET status=:status'.$published.' WHERE id=:id');$s->execute(['status'=>$status,'id'=>$id]);}
    private function newsScope(int$u,string$a):string{if($this->scope->hasGlobalScope($u))return'1=1';$ids=$this->scope->departmentIds($u);return$ids===[]?'1=0':'EXISTS(SELECT 1 FROM news_departments ns WHERE ns.news_id='.$a.'.id AND ns.department_id IN('.implode(',',array_map('intval',$ids)).'))';}private function eventScope(int$u,string$a):string{if($this->scope->hasGlobalScope($u))return'1=1';$ids=$this->scope->departmentIds($u);return$ids===[]?'1=0':'EXISTS(SELECT 1 FROM event_departments es WHERE es.event_id='.$a.'.id AND es.department_id IN('.implode(',',array_map('intval',$ids)).'))';}/** @param list<int>$ids */private function ids(string$f,array$ids):string{return$ids===[]?'1=0':$f.' IN('.implode(',',array_map('intval',$ids)).')';}
}
