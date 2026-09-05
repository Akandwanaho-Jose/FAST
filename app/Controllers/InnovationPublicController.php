<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;use FastWebsite\Core\HttpException;use FastWebsite\Core\Request;use FastWebsite\Core\Response;use FastWebsite\Repositories\InnovationRepository;use FastWebsite\Repositories\DepartmentRepository;

final class InnovationPublicController extends Controller
{
    public function __construct(\FastWebsite\Core\View$view,private readonly InnovationRepository$repository,private readonly DepartmentRepository$departments){parent::__construct($view);}
    public function innovations(Request$r):Response{
        $departmentSlug=mb_substr(trim((string)$r->query('department','')),0,220);
        if($departmentSlug!==''){
            $department=$this->departments->findPublishedBySlug($departmentSlug);
            if($department===null)throw new HttpException(404,'Department not found.');
            return$this->view('public/innovations/index',['pageTitle'=>$department['name'].' innovations','metaDescription'=>'Innovations from the '.$department['name'].'.','currentPath'=>$r->path(),'baseUrl'=>$r->baseUrl(),'departmentFilter'=>$department,'items'=>$this->repository->publishedByDepartment((int)$department['id'])]);
        }
        return$this->view('public/innovations/index',['pageTitle'=>'Innovations','metaDescription'=>'Explore FAST innovations and technology outputs.','currentPath'=>$r->path(),'baseUrl'=>$r->baseUrl(),'departmentFilter'=>null,'items'=>$this->repository->innovations(null,true)]);
    }public function innovation(Request$r):Response{$slug=(string)$r->route('slug','');$item=$this->repository->findPublishedInnovation($slug);if($item===null)throw new HttpException(404,'Innovation not found.');return$this->view('public/innovations/show',['pageTitle'=>$item['name'],'metaDescription'=>mb_substr((string)($item['description']??''),0,300),'currentPath'=>$r->path(),'baseUrl'=>$r->baseUrl(),'item'=>$item]);}public function facilities(Request$r):Response{return$this->view('public/facilities/index',['pageTitle'=>'Facilities and equipment','metaDescription'=>'Explore FAST research facilities and equipment.','currentPath'=>$r->path(),'baseUrl'=>$r->baseUrl(),'facilities'=>$this->repository->facilities(null,true),'equipment'=>$this->repository->equipment(null,true)]);}
}
