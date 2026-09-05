<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Repositories\ResearchRepository;
use FastWebsite\Repositories\InnovationRepository;
use FastWebsite\Repositories\DepartmentRepository;

final class ResearchPublicController extends Controller
{
    public function __construct(\FastWebsite\Core\View$view,private readonly ResearchRepository$research,private readonly InnovationRepository$innovations,private readonly DepartmentRepository$departments){parent::__construct($view);}
    public function index(Request$request):Response{
        $departmentSlug=mb_substr(trim((string)$request->query('department','')),0,220);
        if($departmentSlug!==''){
            $department=$this->departments->findPublishedBySlug($departmentSlug);
            if($department===null)throw new HttpException(404,'Department not found.');
            return$this->view('public/research/index',['pageTitle'=>$department['name'].' research labs','metaDescription'=>'Research labs in the '.$department['name'].'.','currentPath'=>$request->path(),'baseUrl'=>$request->baseUrl(),'search'=>'','typeFilter'=>'','types'=>$this->research->types(),'filtered'=>false,'departmentFilter'=>$department,'result'=>null,'groups'=>[],'units'=>$this->research->publishedByDepartment((int)$department['id'])]);
        }
        $search=mb_substr(trim((string)$request->query('q','')),0,100);$type=mb_substr(trim((string)$request->query('type','')),0,50);$codes=array_column($this->research->types(),'code');$type=in_array($type,$codes,true)?$type:'';$page=ctype_digit((string)$request->query('page','1'))?max(1,(int)$request->query('page','1')):1;$filtered=$search!==''||$type!=='';return$this->view('public/research/index',['pageTitle'=>'Research','metaDescription'=>'Explore FAST research units and centres.','currentPath'=>$request->path(),'baseUrl'=>$request->baseUrl(),'search'=>$search,'typeFilter'=>$type,'types'=>$this->research->types(),'filtered'=>$filtered,'departmentFilter'=>null,'result'=>$filtered?$this->research->paginatePublished($search,$type,$page):null,'groups'=>$filtered?[]:$this->research->publishedGroupedByDepartment(),'units'=>[]]);
    }
    public function show(Request$request):Response{$slug=(string)$request->route('slug','');if(preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)!==1)throw new HttpException(404,'Research unit not found.');$unit=$this->research->findPublishedBySlug($slug);if($unit===null)throw new HttpException(404,'Research unit not found.');return$this->view('public/research/show',['pageTitle'=>$unit['name'],'metaDescription'=>mb_substr((string)($unit['overview']??''),0,300),'currentPath'=>$request->path(),'baseUrl'=>$request->baseUrl(),'unit'=>$unit,'members'=>$this->research->members((int)$unit['id'],true),'projects'=>$this->research->projects((int)$unit['id'],true),'facilities'=>$this->innovations->facilitiesByUnit((int)$unit['id']),'equipment'=>$this->innovations->equipmentByUnit((int)$unit['id'])]);}
}
