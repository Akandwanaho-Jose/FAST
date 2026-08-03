<?php

declare(strict_types=1);

namespace FastWebsite\Validation;

use FastWebsite\Repositories\ResearchRepository;

final class ResearchUnitValidator
{
    public function __construct(private readonly ResearchRepository $research){}

    /** @param array<string,string|null> $input @return array{data:array<string,mixed>,errors:list<string>} */
    public function validate(array $input,?int $existingId=null): array
    {
        $errors=[];$name=trim((string)($input['name']??''));$slug=strtolower(trim((string)($input['slug']??'')));if($slug==='')$slug=$this->slugify($name);
        $faculty=$this->id($input['faculty_id']??null);$department=$this->id($input['department_id']??null);$type=$this->id($input['unit_type_id']??null);$location=$this->id($input['location_id']??null);$media=$this->id($input['hero_media_id']??null);
        if($name===''||mb_strlen($name)>255)$errors[]='Research unit name is required and must be 255 characters or fewer.';
        if($slug===''||mb_strlen($slug)>280||preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)!==1)$errors[]='Slug must contain lowercase letters, numbers, and single hyphens only.';elseif($this->research->slugExists($slug,$existingId))$errors[]='That research unit slug is already in use.';
        foreach([['faculties',$faculty,'Select a valid faculty.'],['research_unit_types',$type,'Select a valid unit type.']] as[$table,$id,$message])if($id===null||!$this->research->relationExists($table,$id))$errors[]=$message;
        foreach([['departments',$department,'Select a valid department.'],['locations',$location,'Select a valid location.'],['media',$media,'Select an active image.']] as[$table,$id,$message])if(trim((string)($input[match($table){'departments'=>'department_id','locations'=>'location_id',default=>'hero_media_id'}]??''))!==''&&($id===null||!$this->research->relationExists($table,$id)))$errors[]=$message;
        if($department!==null&&$faculty!==null&&!$this->research->departmentBelongsToFaculty($department,$faculty))$errors[]='The selected department does not belong to the selected faculty.';
        $email=$this->nullable($input['email']??null);if($email!==null&&filter_var($email,FILTER_VALIDATE_EMAIL)===false)$errors[]='Enter a valid contact email.';
        $website=$this->nullable($input['website_url']??null);if($website!==null&&filter_var($website,FILTER_VALIDATE_URL)===false)$errors[]='Enter a valid website URL.';
        $order=filter_var($input['display_order']??'0',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>65535]]);if($order===false){$errors[]='Display order must be from 0 to 65535.';$order=0;}
        $text=[];foreach(['overview','research_focus','capabilities','student_opportunities','industry_services'] as$field){$text[$field]=$this->nullable($input[$field]??null);if($text[$field]!==null&&mb_strlen($text[$field])>100000)$errors[]=ucfirst(str_replace('_',' ',$field)).' is too long.';}
        return['data'=>['faculty_id'=>$faculty,'department_id'=>$department,'unit_type_id'=>$type,'name'=>$name,'acronym'=>$this->limited($input['acronym']??null,40,'Acronym',$errors),'slug'=>$slug,...$text,'location_id'=>$location,'hero_media_id'=>$media,'email'=>$email,'phone'=>$this->limited($input['phone']??null,50,'Phone',$errors),'website_url'=>$website,'display_order'=>(int)$order],'errors'=>$errors];
    }
    /** @param list<string> $errors */ private function limited(?string$value,int$max,string$label,array&$errors):?string{$value=$this->nullable($value);if($value!==null&&mb_strlen($value)>$max)$errors[]="$label must be $max characters or fewer.";return$value;}
    private function nullable(?string$value):?string{$v=trim((string)$value);return$v===''?null:$v;}
    private function id(?string$value):?int{if($value===null||trim($value)==='')return null;$v=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return$v===false?null:(int)$v;}
    private function slugify(string$value):string{$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);return trim(strtolower((string)preg_replace('/[^a-zA-Z0-9]+/','-',$ascii===false?$value:$ascii)),'-');}
}
