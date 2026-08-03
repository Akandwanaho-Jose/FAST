<?php

declare(strict_types=1);
?>
<section class="page-heading">
    <div class="shell narrow">
        <p class="eyebrow">Read-only verification</p>
        <h1>System health</h1>
        <p class="lead">
            This page reports safe operational status without displaying
            credentials, server details, or internal errors.
        </p>
    </div>
</section>

<section class="section section-compact">
    <div class="shell narrow">
        <div class="health-summary <?= $healthy ? 'is-healthy' : 'needs-attention' ?>">
            <span class="status-dot" aria-hidden="true"></span>
            <div>
                <p class="status-label">Overall status</p>
                <h2><?= $healthy ? 'Operational' : 'Action required' ?></h2>
            </div>
        </div>
        <dl class="health-list">
            <div>
                <dt>Database connection</dt>
                <dd><?= $health['connected'] ? 'Connected' : 'Unavailable' ?></dd>
            </div>
            <div>
                <dt>Expected database structure</dt>
                <dd><?= $health['structureMatches'] ? 'Verified' : 'Needs review' ?></dd>
            </div>
            <div>
                <dt>Application account privileges</dt>
                <dd><?= $health['leastPrivilege'] ? 'Restricted' : 'Too broad' ?></dd>
            </div>
        </dl>
        <p class="health-note">
            Detailed diagnostics are available only through the local command-line verifier.
        </p>
    </div>
</section>

