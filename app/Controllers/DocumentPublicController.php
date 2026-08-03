<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;use FastWebsite\Core\Request;use FastWebsite\Core\Response;use FastWebsite\Repositories\AssetRepository;

final class DocumentPublicController extends Controller
{
    public function __construct(\FastWebsite\Core\View$view,private readonly AssetRepository$r){parent::__construct($view);}public function index(Request$q):Response{return$this->view('public/documents/index',['pageTitle'=>'Documents','metaDescription'=>'Public FAST documents and downloads.','currentPath'=>$q->path(),'baseUrl'=>$q->baseUrl(),'items'=>$this->r->documents(true)]);}
}
