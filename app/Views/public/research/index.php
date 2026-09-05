<?php declare(strict_types=1); use FastWebsite\Core\View;
$mediaUrl=static function(mixed$path)use($baseUrl):?string{if(!is_string($path)||trim($path)===''||preg_match('#^(?:https?:)?//#i',$path)===1)return null;return$baseUrl.ltrim(preg_replace('#^public/#','',str_replace('\\','/',$path))??$path,'/');};
$departmentFilter=$departmentFilter??null;$units=$units??[];
?>
<?php if($departmentFilter!==null):?>
<section class="page-title-hero"><div class="shell"><a class="back-link" href="<?=View::escape($baseUrl)?>departments/<?=View::escape($departmentFilter['slug'])?>">← <?=View::escape($departmentFilter['name'])?></a><p class="eyebrow">Discovery and innovation</p><h1>Research labs — <?=View::escape($departmentFilter['name'])?></h1><p class="lead">Explore the research labs and groups active in this department.</p></div></section>
<section class="section"><div class="shell">
<?php if($units===[]):?><div class="public-empty"><h2>No research labs found</h2><p>Published research labs will appear here.</p></div><?php else:?><div class="department-card-grid"><?php foreach($units as$unit):?><?php $image=$mediaUrl($unit['hero_path']);?><article class="department-card"><?php if($image):?><img class="department-card-image" src="<?=View::escape($image)?>" alt="<?=View::escape($unit['hero_alt_text']?:$unit['name'])?>"><?php endif;?><div><p class="eyebrow"><?=View::escape($unit['type_name'])?></p><h2><a href="<?=View::escape($baseUrl)?>research/<?=View::escape($unit['slug'])?>"><?=View::escape($unit['name'])?></a></h2><p><?=View::escape(mb_strimwidth((string)($unit['overview']?:$unit['research_focus']),0,190,'…'))?></p><?php if($unit['acronym']):?><p class="card-meta"><?=View::escape((string)$unit['acronym'])?></p><?php endif;?><a class="text-link" href="<?=View::escape($baseUrl)?>research/<?=View::escape($unit['slug'])?>">Explore research →</a></div></article><?php endforeach;?></div><?php endif;?>
<p><a class="text-link" href="<?=View::escape($baseUrl)?>research">View all research labs, every department →</a></p>
</div></section>
<?php elseif(!$filtered): ?>
<?php
$totalLabs = array_sum(array_map(static fn(array $g): int => count($g['units']), $groups));
$featuredImage = null; $featuredAlt = '';
foreach ($groups as $group) { foreach ($group['units'] as $unit) { $candidate = $mediaUrl($unit['hero_path']); if ($candidate !== null) { $featuredImage = $candidate; $featuredAlt = trim((string) ($unit['hero_alt_text'] ?? '')); break 2; } } }
$featuredImage ??= $baseUrl . 'assets/images/fast-building.png';
?>
<header class="department-directory-hero">
    <div class="shell department-directory-hero-grid">
        <div class="department-directory-intro" data-department-reveal>
            <p class="eyebrow"><?=View::escape(View::setting($siteContent??[],'research.eyebrow','Discovery and innovation'))?></p>
            <h1><?=View::escape(View::setting($siteContent??[],'research.title','Research at FAST'))?></h1>
            <p class="lead"><?=View::escape(View::setting($siteContent??[],'research.introduction','Explore our research centres, laboratories, groups, expertise, and opportunities.'))?></p>
            <a class="department-hero-link" href="#research-directory">Browse by department <span aria-hidden="true">↓</span></a>
        </div>
        <figure class="department-directory-visual" data-department-reveal>
            <img src="<?=View::escape($featuredImage)?>" alt="<?=View::escape($featuredAlt)?>">
            <figcaption><strong><?=$totalLabs?></strong><span>active research lab<?=$totalLabs===1?'':'s'?></span></figcaption>
        </figure>
    </div>
</header>

<section class="department-directory-section" id="research-directory">
    <div class="shell">
        <div class="department-directory-heading" data-department-reveal>
            <div><p class="eyebrow">Find your discipline</p><h2>Research, organised by department</h2></div>
            <p>Every lab belongs to one of FAST's five departments — start there to explore in depth.</p>
        </div>

        <?php if($groups===[]):?>
            <div class="public-empty" data-department-reveal><h2><?=View::escape(View::setting($siteContent??[],'research.empty','Research pages are being prepared'))?></h2><p>Published research labs will appear here.</p></div>
        <?php else: ?>
            <div class="department-directory-grid">
                <?php foreach($groups as$index=>$group):?>
                    <?php
                    $dept = $group['department'];
                    $labCount = count($group['units']);
                    $deptImage = null;
                    foreach ($group['units'] as $unit) { $deptImage = $mediaUrl($unit['hero_path']); if ($deptImage !== null) break; }
                    $deptLabel = preg_replace('/^Department of /', '', (string) $dept['name']) ?? $dept['name'];
                    $deptHref = $dept['slug'] !== '' ? View::escape($baseUrl) . 'research?department=' . View::escape($dept['slug']) : '#';
                    ?>
                    <article class="department-directory-card<?=$deptImage===null?' has-placeholder':''?>" data-department-reveal style="--department-delay: <?=min(3,$index%4)*100?>ms">
                        <a class="department-card-visual" href="<?=$deptHref?>" tabindex="-1" aria-hidden="true">
                            <?php if($deptImage!==null):?><img src="<?=View::escape($deptImage)?>" alt="" loading="lazy"><?php else:?><span>FAST</span><?php endif;?>
                        </a>
                        <div class="department-directory-copy">
                            <p class="eyebrow"><?=$labCount?> research lab<?=$labCount===1?'':'s'?></p>
                            <h3><a href="<?=$deptHref?>"><?=View::escape($deptLabel)?></a></h3>
                            <p>Explore the labs, projects, and researchers active in this department.</p>
                            <a class="department-directory-link" href="<?=$deptHref?>">View research labs <span aria-hidden="true">→</span></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="public-search research-directory-search" method="get" action="<?=View::escape($baseUrl)?>research" data-department-reveal>
            <label for="research-directory-search"><span>Or search across every lab</span><input id="research-directory-search" name="q" type="search" maxlength="100" value="<?=View::escape($search)?>" placeholder="Name, acronym, or focus"></label>
            <select name="type" aria-label="Research unit type"><option value="">All unit types</option><?php foreach($types as$type):?><option value="<?=View::escape($type['code'])?>"<?=$typeFilter===$type['code']?' selected':''?>><?=View::escape($type['name'])?></option><?php endforeach;?></select>
            <button class="button button-secondary" type="submit">Search</button>
        </form>
    </div>
</section>
<?php else: ?>
<section class="page-title-hero"><div class="shell"><p class="eyebrow"><?=View::escape(View::setting($siteContent??[],'research.eyebrow','Discovery and innovation'))?></p><h1>Search results</h1><p class="lead">Results across every FAST research lab.</p></div></section>
<section class="section"><div class="shell">
<?php if($result['items']===[]):?><div class="public-empty"><h2>No research units found</h2><p>Try a different search term.</p><a class="text-link" href="<?=View::escape($baseUrl)?>research">← Back to research</a></div><?php else:?><div class="department-card-grid"><?php foreach($result['items']as$unit):?><?php $image=$mediaUrl($unit['hero_path']);?><article class="department-card"><?php if($image):?><img class="department-card-image" src="<?=View::escape($image)?>" alt="<?=View::escape($unit['hero_alt_text']?:$unit['name'])?>"><?php endif;?><div><p class="eyebrow"><?=View::escape($unit['type_name'])?></p><h2><a href="<?=View::escape($baseUrl)?>research/<?=View::escape($unit['slug'])?>"><?=View::escape($unit['name'])?></a></h2><p><?=View::escape(mb_strimwidth((string)($unit['overview']?:$unit['research_focus']),0,190,'…'))?></p><p class="card-meta"><?=View::escape(implode(' · ',array_filter([$unit['acronym']??null,$unit['department_name']??null])))?></p><a class="text-link" href="<?=View::escape($baseUrl)?>research/<?=View::escape($unit['slug'])?>">Explore research →</a></div></article><?php endforeach;?></div><?php endif;?>
</div></section>
<?php endif; ?>
