<?php

declare(strict_types=1);

namespace FastWebsite\Validation;

use FastWebsite\Repositories\SiteContentRepository;

final class SiteContentValidator
{
    public function __construct(private readonly SiteContentRepository $repository) {}

    /** @param array<string,mixed> $input @return array{data:array<string,mixed>,department_id:?int,errors:list<string>} */
    public function news(array $input, int $userId, ?int $id = null): array
    {
        $errors = [];
        $title = $this->text($input['title'] ?? '', 300);
        $slug = $this->slug((string) ($input['slug'] ?? ''), $title);
        $category = $this->id($input['category_id'] ?? null);
        $department = $this->id($input['department_id'] ?? null);
        $media = $this->id($input['featured_media_id'] ?? null);
        $date = $this->date($input['article_date'] ?? null);
        if ($title === '') $errors[] = 'News title is required.';
        if ($slug === '' || $this->repository->slugExists('news', $slug, $id)) $errors[] = $slug === '' ? 'A valid public URL is required.' : 'That public URL is already in use.';
        if ($category === null || !$this->repository->relation('news_categories', $category)) $errors[] = 'Choose a news category.';
        if (!$this->repository->canDepartment($userId, $department)) $errors[] = 'Choose a department within your scope.';
        if ($department !== null && !$this->repository->relation('departments', $department)) $errors[] = 'Choose a valid department.';
        if ($media !== null && !$this->repository->relation('media', $media)) $errors[] = 'Choose an active image.';
        if ($date === null) $errors[] = 'Article date is required.';
        $body = $this->text($input['body'] ?? '', 150000);
        if ($body === '') $errors[] = 'News body is required.';
        return ['data' => ['category_id'=>$category,'title'=>$title,'slug'=>$slug,'summary'=>$this->nullable($input['summary']??null,5000),'body'=>$body,'featured_media_id'=>$media,'author_user_id'=>$userId,'article_date'=>$date,'is_featured'=>$this->flag($input['is_featured']??null),'allow_sharing'=>$this->flag($input['allow_sharing']??null),'meta_title'=>$this->nullable($input['meta_title']??null,255),'meta_description'=>$this->nullable($input['meta_description']??null,320)], 'department_id'=>$department, 'errors'=>$errors];
    }

    /** @param array<string,mixed> $input @return array{data:array<string,mixed>,department_id:?int,errors:list<string>} */
    public function event(array $input, int $userId, ?int $id = null): array
    {
        $errors=[];$title=$this->text($input['title']??'',300);$slug=$this->slug((string)($input['slug']??''),$title);$category=$this->id($input['category_id']??null);$department=$this->id($input['department_id']??null);$location=$this->id($input['location_id']??null);$staff=$this->id($input['contact_staff_id']??null);$media=$this->id($input['featured_media_id']??null);$starts=$this->dateTime($input['starts_at']??null);$ends=$this->dateTime($input['ends_at']??null);$deadline=$this->dateTime($input['registration_deadline']??null);$eventStatus=(string)($input['event_status']??'scheduled');
        if($title==='')$errors[]='Event title is required.';if($slug===''||$this->repository->slugExists('events',$slug,$id))$errors[]=$slug===''?'A valid public URL is required.':'That public URL is already in use.';if($category===null||!$this->repository->relation('event_categories',$category))$errors[]='Choose an event category.';if(!$this->repository->canDepartment($userId,$department))$errors[]='Choose a department within your scope.';if($department!==null&&!$this->repository->relation('departments',$department))$errors[]='Choose a valid department.';if($starts===null)$errors[]='Start date and time are required.';if($starts!==null&&$ends!==null&&$ends<$starts)$errors[]='End date must be after the start date.';if(!in_array($eventStatus,['scheduled','postponed','cancelled','completed'],true))$errors[]='Choose a valid event status.';
        foreach([['locations',$location,'location'],['staff',$staff,'contact person'],['media',$media,'image']] as [$table,$value,$label])if($value!==null&&!$this->repository->relation((string)$table,(int)$value))$errors[]='Choose a valid '.$label.'.';$email=$this->nullable($input['contact_email']??null,190);if($email!==null&&!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Enter a valid contact email.';$url=$this->nullable($input['registration_url']??null,500);if($url!==null&&!filter_var($url,FILTER_VALIDATE_URL))$errors[]='Enter a complete registration URL.';
        return ['data'=>['category_id'=>$category,'title'=>$title,'slug'=>$slug,'summary'=>$this->nullable($input['summary']??null,5000),'description'=>$this->nullable($input['description']??null,150000),'starts_at'=>$starts,'ends_at'=>$ends,'timezone_name'=>'Africa/Kampala','venue_name'=>$this->nullable($input['venue_name']??null,255),'location_id'=>$location,'contact_staff_id'=>$staff,'contact_name'=>$this->nullable($input['contact_name']??null,200),'contact_email'=>$email,'registration_url'=>$url,'registration_deadline'=>$deadline,'featured_media_id'=>$media,'event_status'=>$eventStatus],'department_id'=>$department,'errors'=>$errors];
    }

    /** @param array<string,mixed> $input @return array{data:array<string,mixed>,errors:list<string>} */
    public function page(array $input, ?int $id = null): array
    {
        $errors=[];$title=$this->text($input['title']??'',255);$slug=$this->slug((string)($input['slug']??''),$title);$parent=$this->id($input['parent_page_id']??null);if($title==='')$errors[]='Page title is required.';if($slug===''||$this->repository->slugExists('pages',$slug,$id))$errors[]=$slug===''?'A valid page URL is required.':'That page URL is already in use.';if($parent!==null&&($parent===$id||!$this->repository->relation('pages',$parent)))$errors[]='Choose a valid parent page.';return ['data'=>['parent_page_id'=>$parent,'title'=>$title,'slug'=>$slug,'page_template'=>$this->text($input['page_template']??'standard',100)?:'standard','meta_title'=>$this->nullable($input['meta_title']??null,255),'meta_description'=>$this->nullable($input['meta_description']??null,320),'display_order'=>max(0,min(65535,(int)($input['display_order']??0))),'show_in_navigation'=>$this->flag($input['show_in_navigation']??null)],'errors'=>$errors];
    }

    /** @param array<string,mixed> $input @return array{data:array<string,mixed>,errors:list<string>} */
    public function section(array $input): array
    {
        $types=['rich_text','image_text','cards','statistics','call_to_action','quote','gallery','video','custom'];$type=(string)($input['section_type']??'rich_text');$media=$this->id($input['media_id']??null);$errors=[];if(!in_array($type,$types,true))$errors[]='Choose a valid section type.';if($media!==null&&!$this->repository->relation('media',$media))$errors[]='Choose an active section image.';$url=$this->nullable($input['button_url']??null,500);if($url!==null&&!str_starts_with($url,'/')&&!filter_var($url,FILTER_VALIDATE_URL))$errors[]='Enter a complete button URL or a path beginning with /.';return ['data'=>['section_type'=>$type,'heading'=>$this->nullable($input['heading']??null,255),'subheading'=>$this->nullable($input['subheading']??null,255),'body'=>$this->nullable($input['body']??null,150000),'media_id'=>$media,'button_label'=>$this->nullable($input['button_label']??null,100),'button_url'=>$url,'settings_json'=>null,'display_order'=>max(0,min(65535,(int)($input['display_order']??0))),'is_visible'=>$this->flag($input['is_visible']??null)],'errors'=>$errors];
    }

    /** @param array<string,mixed> $input @return array{data:array<string,mixed>,errors:list<string>} */
    public function announcement(array $input): array
    {
        $errors=[];$title=$this->text($input['title']??'',255);$type=(string)($input['announcement_type']??'general');$starts=$this->dateTime($input['starts_at']??null);$ends=$this->dateTime($input['ends_at']??null);if($title==='')$errors[]='Announcement title is required.';if(!in_array($type,['general','opportunity','admissions','scholarship','notice','urgent'],true))$errors[]='Choose a valid announcement type.';if($starts!==null&&$ends!==null&&$ends<$starts)$errors[]='Announcement end must be after its start.';$url=$this->nullable($input['link_url']??null,500);if($url!==null&&!str_starts_with($url,'/')&&!filter_var($url,FILTER_VALIDATE_URL))$errors[]='Enter a complete link URL or a path beginning with /.';return ['data'=>['title'=>$title,'summary'=>$this->nullable($input['summary']??null,10000),'announcement_type'=>$type,'link_url'=>$url,'starts_at'=>$starts,'ends_at'=>$ends,'is_pinned'=>$this->flag($input['is_pinned']??null)],'errors'=>$errors];
    }
    private function id(mixed $v):?int{$v=trim((string)$v);return ctype_digit($v)&&(int)$v>0?(int)$v:null;}private function text(mixed$v,int$m):string{return mb_substr(trim((string)$v),0,$m);}private function nullable(mixed$v,int$m):?string{$v=$this->text($v,$m);return$v===''?null:$v;}private function flag(mixed$v):int{return(string)$v==='1'?1:0;}private function date(mixed$v):?string{$v=trim((string)$v);$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);return$d&&$d->format('Y-m-d')===$v?$v:null;}private function dateTime(mixed$v):?string{$v=trim((string)$v);if($v==='')return null;$d=\DateTimeImmutable::createFromFormat('Y-m-d\TH:i',$v);return$d?$d->format('Y-m-d H:i:s'):null;}private function slug(string$v,string$f):string{$v=trim($v)!==''?$v:$f;$a=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$v);return trim(strtolower((string)preg_replace('/[^a-zA-Z0-9]+/','-',is_string($a)?$a:$v)),'-');}
}
