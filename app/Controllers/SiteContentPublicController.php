<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;use FastWebsite\Core\HttpException;use FastWebsite\Core\Request;use FastWebsite\Core\Response;use FastWebsite\Repositories\SiteContentRepository;

final class SiteContentPublicController extends Controller
{
    public function __construct(\FastWebsite\Core\View$view,private readonly SiteContentRepository$r){parent::__construct($view);}public function news(Request$q):Response{return$this->public($q,'public/content/news-index','News',['items'=>$this->r->news(null,true)]);}public function newsItem(Request$q):Response{$item=$this->r->publicNews((string)$q->route('slug',''));if($item===null)throw new HttpException(404,'News item not found.');return$this->public($q,'public/content/news-show',(string)$item['title'],['item'=>$item]);}public function events(Request$q):Response{return$this->public($q,'public/content/events-index','Events',['items'=>$this->r->events(null,true)]);}public function event(Request$q):Response{$item=$this->r->publicEvent((string)$q->route('slug',''));if($item===null)throw new HttpException(404,'Event not found.');return$this->public($q,'public/content/event-show',(string)$item['title'],['item'=>$item]);}public function page(Request$q):Response{$item=$this->r->publicPage((string)$q->route('slug',''));if($item===null)throw new HttpException(404,'Page not found.');return$this->public($q,'public/content/page',(string)$item['title'],['item'=>$item,'sections'=>$this->r->sections((int)$item['id'],true)]);}private function public(Request$q,string$template,string$title,array$data):Response{return$this->view($template,['pageTitle'=>$title,'metaDescription'=>$title,'currentPath'=>$q->path(),'baseUrl'=>$q->baseUrl(),...$data]);}
}
