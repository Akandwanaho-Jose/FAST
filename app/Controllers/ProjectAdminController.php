<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\Csrf;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Core\Session;
use FastWebsite\Repositories\ProjectRepository;
use FastWebsite\Repositories\ResearchMetadataRepository;
use FastWebsite\Services\AdminPageContext;
use FastWebsite\Services\AuthService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\MediaUploadService;
use FastWebsite\Services\ProjectService;
use FastWebsite\Validation\ProjectValidator;
use Throwable;

final class ProjectAdminController extends Controller
{
    private const STATUSES = ['draft', 'under_review', 'approved', 'published', 'archived'];
    private const PROJECT_STATUSES = ['planned', 'ongoing', 'completed', 'suspended', 'cancelled'];
    private const FIELDS = ['lead_department_id', 'lead_research_unit_id', 'title', 'short_title', 'slug', 'summary', 'objectives', 'methodology', 'expected_outputs', 'outcomes', 'impact', 'project_status', 'start_date', 'end_date', 'budget_amount', 'currency_code', 'funding_reference', 'project_url', 'hero_media_id'];

    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly AuthService $auth,
        private readonly AuthorizationService $authorization,
        private readonly AdminPageContext $context,
        private readonly ProjectRepository $projects,
        private readonly ResearchMetadataRepository $metadata,
        private readonly ProjectValidator $validator,
        private readonly ProjectService $service,
        private readonly MediaUploadService $uploads,
        private readonly Csrf $csrf,
        private readonly Session $session
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $user = $this->user();
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = (string) $request->query('status', '');
        $status = in_array($status, self::STATUSES, true) ? $status : '';
        $projectStatus = (string) $request->query('project_status', '');
        $projectStatus = in_array($projectStatus, self::PROJECT_STATUSES, true) ? $projectStatus : '';
        $page = ctype_digit((string) $request->query('page', '1')) ? max(1, (int) $request->query('page', '1')) : 1;
        return $this->adminView($request, $user, 'admin/projects/index', [
            'pageTitle'=>'Research projects',
            'result'=>$this->projects->paginateAdmin((int) $user['id'], $search, $status, $projectStatus, $page),
            'search'=>$search, 'statusFilter'=>$status, 'projectStatusFilter'=>$projectStatus,
            'statuses'=>self::STATUSES, 'projectStatuses'=>self::PROJECT_STATUSES,
            'canCreate'=>$this->service->canCreate((int) $user['id']),
        ]);
    }

    public function show(Request $request): Response
    {
        $user = $this->user(); $id = $this->id($request);
        $project = $this->projects->findAdmin($id, (int) $user['id']);
        if ($project === null) throw new HttpException(404, 'Project not found.');
        $canPublish = $this->service->canDirectPublish((int) $user['id'], $id);
        return $this->adminView($request, $user, 'admin/projects/show-metadata', [
            'pageTitle'=>$project['title'], 'project'=>$project,
            'units'=>$this->projects->linkedUnits($id), 'members'=>$this->projects->members($id),
            'staffOptions'=>$this->projects->availableStaff((int) $user['id']),
            'history'=>$this->projects->approvalHistory($id),
            'actions'=>$this->service->actions((int) $user['id'], $id, (string) $project['publication_status']),
            'canEdit'=>($project['publication_status'] === 'draft' && $this->authorization->can((int) $user['id'], 'research.edit')) || ($project['publication_status'] === 'published' && $canPublish),
            'canManageMembers'=>$this->authorization->can((int) $user['id'], 'research.edit'),
            'canManageMetadata'=>$this->authorization->can((int) $user['id'], 'research.edit'),
            'themes'=>$this->metadata->projectThemes($id),
            'sdgLinks'=>$this->metadata->projectSdgs($id),
            'partnerLinks'=>$this->metadata->projectPartners($id),
            'themeOptions'=>$this->metadata->themes((int) $user['id'], true),
            'sdgs'=>$this->metadata->sdgs(),
            'partnerOptions'=>$this->metadata->partners(true),
            'canQuickPublish'=>$project['publication_status'] === 'draft' && trim((string) ($project['summary'] ?? '')) !== '' && $canPublish,
            'needsSummary'=>$project['publication_status'] === 'draft' && trim((string) ($project['summary'] ?? '')) === '' && $canPublish,
        ]);
    }

    public function create(Request $request): Response { $user=$this->user(); $this->assert((int)$user['id'],'research.create'); return $this->form($request,$user); }
    public function store(Request $request): Response { $user=$this->user(); $this->assert((int)$user['id'],'research.create'); return $this->save($request,$user); }

    public function edit(Request $request): Response
    {
        $user=$this->user(); $this->assert((int)$user['id'],'research.edit'); $id=$this->id($request);
        $project=$this->projects->findAdmin($id,(int)$user['id']);
        if($project===null)throw new HttpException(404,'Project not found.');
        if($project['publication_status']!=='draft'&&!($project['publication_status']==='published'&&$this->service->canDirectPublish((int)$user['id'],$id)))throw new HttpException(409,'This project must be draft or published with publishing access before editing.');
        $values=$project; $values['lead_research_unit_id']=$this->projects->linkedUnits($id)[0]['id']??'';
        return $this->form($request,$user,$values,[],$id,'',(string)$project['publication_status']);
    }

    public function update(Request $request): Response { $user=$this->user(); $this->assert((int)$user['id'],'research.edit'); return $this->save($request,$user,$this->id($request)); }

    public function workflow(Request $request): Response
    {
        $user=$this->user(); $id=$this->id($request); $this->csrf($request);
        $action=mb_substr(trim((string)$request->input('action','')),0,40);
        $comment=mb_substr(trim((string)$request->input('comment','')),0,2000);
        if($action==='publish_now'){
            $this->service->publishDraft($id,(int)$user['id'],$comment!==''?$comment:'Published directly.',$request->ipAddress(),$request->userAgent());
            $this->session->put('_flash_success','Project published successfully.');
        }else{
            $target=$this->service->transition($id,$action,(int)$user['id'],$comment,$request->ipAddress(),$request->userAgent());
            $this->session->put('_flash_success','Project publication status changed to '.str_replace('_',' ',$target).'.');
        }
        return Response::redirect($request->baseUrl().'admin/research/projects/'.$id);
    }

    public function addMember(Request $request): Response
    {
        $this->csrf($request); $user=$this->user(); $id=$this->id($request);
        $staff=trim((string)$request->input('staff_id','')); $staffId=ctype_digit($staff)&&(int)$staff>0?(int)$staff:null;
        $this->service->addMember($id,$staffId,(string)$request->input('external_member_name',''),(string)$request->input('external_affiliation',''),(string)$request->input('external_email',''),(string)$request->input('project_role','researcher'),(string)$request->input('role_title',''),(string)$request->input('start_date',''),(string)$request->input('end_date',''),(int)$request->input('display_order','0'),(int)$user['id'],$request->ipAddress(),$request->userAgent());
        $this->session->put('_flash_success','Project member added.');
        return Response::redirect($request->baseUrl().'admin/research/projects/'.$id.'#team');
    }

    public function removeMember(Request $request): Response
    {
        $this->csrf($request); $user=$this->user(); $id=$this->id($request); $member=(string)$request->route('memberId','');
        if(!ctype_digit($member)||(int)$member<1)throw new HttpException(404,'Project member not found.');
        $this->service->removeMember($id,(int)$member,(int)$user['id'],$request->ipAddress(),$request->userAgent());
        $this->session->put('_flash_success','Project member removed.');
        return Response::redirect($request->baseUrl().'admin/research/projects/'.$id.'#team');
    }

    /** @param array<string,mixed> $user */
    private function save(Request $request,array $user,?int $id=null): Response
    {
        $this->csrf($request); $status='draft';
        if($id!==null){$existing=$this->projects->findAdmin($id,(int)$user['id']);if($existing===null)throw new HttpException(404,'Project not found.');$status=(string)$existing['publication_status'];}
        $values=[];foreach(self::FIELDS as $field)$values[$field]=$request->input($field);
        $validation=$this->validator->validate($values,$id);
        $upload=$this->uploads->validate($request->file('hero_image'),(string)$request->input('hero_alt_text',''));
        $errors=[...$validation['errors'],...$upload['errors']];
        $note=mb_substr(trim((string)$request->input('revision_note','')),0,2000);
        if($errors!==[])return $this->form($request,$user,$values,$errors,$id,$note,$status);
        $operation=function(array $data)use($id,$user,$request,$note,$status,$validation):int{
            $unit=$validation['lead_unit_id'];
            if($id===null)return $request->input('submit_action')==='publish'?$this->service->createAndPublish($data,$unit,(int)$user['id'],$request->ipAddress(),$request->userAgent()):$this->service->create($data,$unit,(int)$user['id'],$request->ipAddress(),$request->userAgent());
            if($status==='published')$this->service->updatePublished($id,$data,$unit,(int)$user['id'],$note,$request->ipAddress(),$request->userAgent());
            elseif($request->input('submit_action')==='publish')$this->service->updateAndPublish($id,$data,$unit,(int)$user['id'],$note,$request->ipAddress(),$request->userAgent());
            else$this->service->update($id,$data,$unit,(int)$user['id'],$note,$request->ipAddress(),$request->userAgent());
            return $id;
        };
        $saved=$this->withImage($validation['data'],$upload['upload'],(int)$user['id'],$operation);
        $message=$id===null?($request->input('submit_action')==='publish'?'Project created and published.':'Project created as a draft.'):($status==='published'?'Published project updated.':($request->input('submit_action')==='publish'?'Project saved and published.':'Project updated.'));
        $this->session->put('_flash_success',$message);
        return Response::redirect($request->baseUrl().'admin/research/projects/'.$saved);
    }

    /** @param array<string,mixed> $user @param array<string,mixed> $values @param list<string> $errors */
    private function form(Request $request,array $user,array $values=[],array $errors=[],?int $id=null,string $note='',string $status='draft'): Response
    {
        if($values===[]){$departments=$this->projects->departments((int)$user['id']);$values=['lead_department_id'=>(string)($departments[0]['id']??''),'project_status'=>'planned','currency_code'=>'UGX'];}
        return $this->adminView($request,$user,'admin/projects/form',[
            'pageTitle'=>$id===null?'Create project':'Edit project','projectId'=>$id,'values'=>$values,'errors'=>$errors,
            'departments'=>$this->projects->departments((int)$user['id']),'researchUnits'=>$this->projects->researchUnits((int)$user['id']),
            'images'=>$this->projects->activeImages(),'revisionNote'=>$note,'currentStatus'=>$status,
            'canPublishDirectly'=>$id!==null?$this->service->canDirectPublish((int)$user['id'],$id):$this->authorization->can((int)$user['id'],'research.publish')&&$this->authorization->can((int)$user['id'],'content.approve'),
        ]);
    }

    /** @param array<string,mixed> $user @param array<string,mixed> $data */
    private function adminView(Request $request,array $user,string $template,array $data): Response{return $this->view($template,array_merge($this->context->data($request,$user),$data),200,'layouts/admin')->withHeader('Cache-Control','no-store');}
    private function user(): array{return $this->auth->user()??throw new HttpException(403,'Authentication required.');}
    private function id(Request $request): int{$value=(string)$request->route('id','');if(!ctype_digit($value)||(int)$value<1)throw new HttpException(404,'Project not found.');return(int)$value;}
    private function assert(int $userId,string $permission): void{if(!$this->authorization->can($userId,$permission))throw new HttpException(403,'This project action is not allowed.');}
    private function csrf(Request $request): void{if(!$this->csrf->validate($request->input('_token')))throw new HttpException(403,'The secure form session expired.');}

    /** @param array<string,mixed> $data @param array<string,mixed>|null $upload */
    private function withImage(array $data,?array $upload,int $userId,callable $operation): mixed
    {
        if($upload===null)return $operation($data);
        $connection=$this->projects->connection();$owns=!$connection->inTransaction();$path=null;if($owns)$connection->beginTransaction();
        try{$stored=$this->uploads->store($upload,$userId,'projects');$path=$stored['absolute_path'];$data['hero_media_id']=$stored['id'];$result=$operation($data);if($owns)$connection->commit();return$result;}
        catch(Throwable $exception){if($owns&&$connection->inTransaction())$connection->rollBack();$this->uploads->removeStoredFile($path);throw$exception;}
    }
}
