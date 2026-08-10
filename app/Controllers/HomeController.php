<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Repositories\HomepageRepository;
use FastWebsite\Repositories\SiteContentRepository;
use FastWebsite\Repositories\InnovationRepository;
use FastWebsite\Repositories\EngagementRepository;

final class HomeController extends Controller
{
    public function __construct(\FastWebsite\Core\View $view,private readonly HomepageRepository$home,private readonly SiteContentRepository$content,private readonly InnovationRepository$innovation,private readonly EngagementRepository$engagement){parent::__construct($view);}
    public function index(Request $request): Response
    {
        $historyPage = $this->content->publicPage('history-of-the-faculty');
        $historySections = is_array($historyPage)
            ? $this->content->sections((int) $historyPage['id'], true)
            : [];

        return $this->view(
            'public/home',
            [
                'pageTitle' => 'Faculty of Applied Sciences and Technology',
                'metaDescription' => 'Explore programmes, people, research, innovation, news, and events at FAST.',
                'currentPath' => $request->path(),
                'baseUrl' => $request->baseUrl(),
                'faculty'=>$this->home->faculty(),'slides'=>$this->home->slides(),'counts'=>$this->home->counts(),'programmeCategoryCounts'=>$this->home->programmeCategoryCounts(),'departments'=>$this->home->featuredDepartments(),'programmes'=>$this->home->featuredProgrammes(),'sections'=>$this->home->sections(),'quickLinks'=>$this->home->quickLinks(),'historyPage'=>$historyPage,'historySections'=>$historySections,'announcements'=>$this->content->announcements(true),'news'=>$this->content->news(null,true,3),'events'=>$this->content->events(null,true,3),'innovations'=>$this->innovation->innovations(null,true),'impacts'=>$this->engagement->impacts(null,true),
            ]
        );
    }
}
