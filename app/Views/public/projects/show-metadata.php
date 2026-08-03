<?php

declare(strict_types=1);

use FastWebsite\Core\View;

require __DIR__ . '/show.php';
?>
<?php if ($themes !== [] || $sdgs !== [] || $partners !== []): ?>
<section class="section research-metadata-section"><div class="shell">
    <div class="section-heading"><p class="eyebrow">Research connections</p><h2>Focus, impact, and collaboration</h2></div>
    <div class="public-metadata-grid">
        <?php if ($themes !== []): ?><article class="public-metadata-card"><h3>Research themes</h3><div class="metadata-chip-grid"><?php foreach ($themes as $theme): ?><span class="metadata-chip"><?=View::escape($theme['name'])?><?=$theme['is_primary']?' · Primary':''?></span><?php endforeach; ?></div></article><?php endif; ?>
        <?php if ($sdgs !== []): ?><article class="public-metadata-card"><h3>Sustainable Development Goals</h3><div class="metadata-chip-grid"><?php foreach ($sdgs as $sdg): ?><span class="metadata-chip sdg-chip" style="--chip-colour:<?=View::escape($sdg['colour_code'] ?: '#425466')?>"><strong><?=View::escape($sdg['code'])?></strong> <?=View::escape($sdg['name'])?><?=$sdg['is_primary']?' · Primary':''?></span><?php endforeach; ?></div></article><?php endif; ?>
        <?php if ($partners !== []): ?><article class="public-metadata-card span-2"><h3>Project partners</h3><div class="partner-card-grid"><?php foreach ($partners as $partner): ?><?php $partnerLogo=$media($partner['logo_path']); ?><div class="partner-card"><?php if ($partnerLogo): ?><img class="partner-logo" src="<?=View::escape($partnerLogo)?>" alt="<?=View::escape($partner['logo_alt_text'] ?: $partner['name'].' logo')?>"><?php endif; ?><div><p class="eyebrow"><?=View::escape(ucwords(str_replace('_', ' ', $partner['partner_role'])))?></p><h4><?php if ($partner['website_url']): ?><a href="<?=View::escape($partner['website_url'])?>"><?=View::escape($partner['name'])?></a><?php else: ?><?=View::escape($partner['name'])?><?php endif; ?></h4><?php if ($partner['country']): ?><p><?=View::escape($partner['country'])?></p><?php endif; ?><?php if ($partner['contribution']): ?><p><?=View::escape($partner['contribution'])?></p><?php endif; ?></div></div><?php endforeach; ?></div></article><?php endif; ?>
    </div>
</div></section>
<?php endif; ?>
