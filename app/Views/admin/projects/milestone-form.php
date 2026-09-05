<?php declare(strict_types=1); use FastWebsite\Core\View;
$value=static fn(string$field):string=>(string)($milestone[$field]??'');
$selectedLevels=array_filter(explode(',',(string)($milestone['target_level']??'')));
?>
<section class="admin-page-heading"><div><p class="eyebrow">Roadmap milestone</p><h1>Edit milestone</h1><p>Part of <?=View::escape($project['title'])?>.</p></div><a class="button button-secondary" href="<?=View::escape($baseUrl)?>admin/research/projects/<?=(int)$project['id']?>#roadmap">Cancel</a></section>
<form class="content-form" method="post" action="<?=View::escape($baseUrl)?>admin/research/projects/<?=(int)$project['id']?>/milestones/<?=(int)$milestone['id']?>"><input type="hidden" name="_token" value="<?=View::escape($csrfToken)?>">
<section class="form-section"><div class="form-grid">
<div class="form-field span-2"><label for="milestone_title">Title *</label><input id="milestone_title" name="title" maxlength="300" required value="<?=View::escape($value('title'))?>"></div>
<div class="form-field"><label for="milestone_timeline">Timeline</label><input id="milestone_timeline" name="timeline_text" maxlength="100" value="<?=View::escape($value('timeline_text'))?>" placeholder="e.g. Year 2, Semester 1"></div>
<div class="form-field"><label for="milestone_sequence">Sequence</label><input id="milestone_sequence" name="sequence_number" type="number" min="0" max="65535" value="<?=View::escape($value('sequence_number'))?>"></div>
<div class="form-field"><label for="milestone_status">Status</label><select id="milestone_status" name="status"><?php foreach(['planned','in_progress','completed','dropped']as$status):?><option value="<?=$status?>"<?=$value('status')===$status?' selected':''?>><?=ucwords(str_replace('_',' ',$status))?></option><?php endforeach;?></select></div>
<div class="form-field"><label for="milestone_lead_staff">Internal lead</label><select id="milestone_lead_staff" name="lead_staff_id"><option value="">External / not set</option><?php foreach($staffOptions as$s):?><option value="<?=(int)$s['id']?>"<?=$value('lead_staff_id')===(string)$s['id']?' selected':''?>><?=View::escape(trim(implode(' ',array_filter([$s['honorific_title'],$s['first_name'],$s['middle_name'],$s['last_name']]))))?></option><?php endforeach;?></select></div>
<div class="form-field"><label for="milestone_lead_name">External lead name</label><input id="milestone_lead_name" name="lead_name_text" maxlength="200" value="<?=View::escape($value('lead_name_text'))?>"></div>
<fieldset class="form-field span-2 staff-expertise-picker"><legend>Target level</legend><div class="staff-expertise-options"><?php foreach(['bachelors'=>'Bachelors','masters'=>'Masters','phd'=>'PhD','staff'=>'Staff']as$level=>$label):?><label><input type="checkbox" name="target_level[]" value="<?=$level?>"<?=in_array($level,$selectedLevels,true)?' checked':''?>> <?=$label?></label><?php endforeach;?></div></fieldset>
<div class="form-field span-2"><label for="milestone_students">Student names</label><input id="milestone_students" name="student_names_text" maxlength="500" value="<?=View::escape($value('student_names_text'))?>" placeholder="Comma-separated"></div>
<div class="form-field span-2"><label for="milestone_output">Expected output</label><textarea id="milestone_output" name="expected_output" rows="5"><?=View::escape($value('expected_output'))?></textarea></div>
</div></section>
<div class="form-actions"><button class="button button-primary" type="submit">Save milestone</button><a class="button button-secondary" href="<?=View::escape($baseUrl)?>admin/research/projects/<?=(int)$project['id']?>#roadmap">Cancel</a></div>
</form>
