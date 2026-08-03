<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;use FastWebsite\Core\HttpException;use FastWebsite\Core\Request;use FastWebsite\Core\Response;use FastWebsite\Repositories\EngagementRepository;

final class EngagementPublicController extends Controller
{
    public function __construct(\FastWebsite\Core\View$view,private readonly EngagementRepository$r){parent::__construct($view);}public function index(Request$q):Response{return$this->view('public/engagement/index',['pageTitle'=>'Partnerships and impact','metaDescription'=>'Explore FAST partnerships and community impact.','currentPath'=>$q->path(),'baseUrl'=>$q->baseUrl(),'partnerships'=>$this->r->partnerships(true),'impacts'=>$this->r->impacts(null,true)]);}public function impact(Request$q):Response{$item=$this->r->findPublicImpact((string)$q->route('slug',''));if($item===null)throw new HttpException(404,'Impact story not found.');return$this->view('public/engagement/show',['pageTitle'=>$item['title'],'metaDescription'=>mb_substr((string)($item['summary']??''),0,300),'currentPath'=>$q->path(),'baseUrl'=>$q->baseUrl(),'item'=>$item]);}
}
