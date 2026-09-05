<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$value = static fn (string $field): string => (string) ($values[$field] ?? '');
$editing = is_int($id);
$action = $editing ? $baseUrl . 'my-publications/' . $id : $baseUrl . 'my-publications';
$authorName = static function (array $author): string {
    if ($author['staff_id'] !== null) {
        return trim(implode(' ', array_filter([
            $author['honorific_title'], $author['first_name'],
            $author['middle_name'], $author['last_name'],
        ])));
    }
    return (string) $author['external_author_name'];
};
?>
<section class="admin-page-heading">
    <div>
        <p class="eyebrow">Staff self-service</p>
        <h1><?= $editing ? 'Edit publication' : 'Add publication' ?></h1>
        <p>Changes save immediately. Publication metadata is shared with the Research module once published.</p>
    </div>
    <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>my-publications">Back to my publications</a>
</section>
<?php if ($errors !== []): ?>
    <div class="form-alert" role="alert"><strong>Please correct the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= View::escape($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form class="content-form" method="post" action="<?= View::escape($action) ?>">
    <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
    <section class="form-section">
        <div class="form-section-heading"><h2>Publication identity</h2></div>
        <div class="form-grid">
            <div class="form-field span-2"><label for="title">Publication title *</label><textarea id="title" name="title" rows="3" required><?= View::escape($value('title')) ?></textarea></div>
            <div class="form-field"><label for="publication_type_id">Publication type *</label><select id="publication_type_id" name="publication_type_id" required><?php foreach ($types as $type): ?><option value="<?= (int) $type['id'] ?>" <?= $value('publication_type_id') === (string) $type['id'] ? 'selected' : '' ?>><?= View::escape($type['name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="access_type">Access</label><select id="access_type" name="access_type"><?php foreach (['open_access' => 'Open access', 'subscription' => 'Subscription', 'restricted' => 'Restricted', 'unknown' => 'Unknown'] as $key => $label): ?><option value="<?= $key ?>" <?= $value('access_type') === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
            <div class="form-field span-2"><label for="slug">Public URL</label><input id="slug" name="slug" maxlength="320" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="<?= View::escape($value('slug')) ?>"><p class="field-guidance">Leave blank to generate from the title.</p></div>
        </div>
    </section>
    <section class="form-section">
        <div class="form-section-heading"><h2>Bibliographic details</h2></div>
        <div class="form-grid">
            <div class="form-field"><label for="journal_name">Journal or source</label><input id="journal_name" name="journal_name" maxlength="255" value="<?= View::escape($value('journal_name')) ?>"></div>
            <div class="form-field"><label for="publisher">Publisher</label><input id="publisher" name="publisher" maxlength="255" value="<?= View::escape($value('publisher')) ?>"></div>
            <div class="form-field"><label for="publication_year">Publication year</label><input id="publication_year" name="publication_year" type="number" min="1800" max="<?= date('Y') + 2 ?>" value="<?= View::escape($value('publication_year')) ?>"></div>
            <div class="form-field"><label for="publication_date">Publication date</label><input id="publication_date" name="publication_date" type="date" value="<?= View::escape($value('publication_date')) ?>"></div>
            <div class="form-field"><label for="volume">Volume</label><input id="volume" name="volume" maxlength="50" value="<?= View::escape($value('volume')) ?>"></div>
            <div class="form-field"><label for="issue">Issue</label><input id="issue" name="issue" maxlength="50" value="<?= View::escape($value('issue')) ?>"></div>
            <div class="form-field"><label for="page_range">Pages</label><input id="page_range" name="page_range" maxlength="50" value="<?= View::escape($value('page_range')) ?>"></div>
            <div class="form-field"><label for="isbn">ISBN</label><input id="isbn" name="isbn" maxlength="50" value="<?= View::escape($value('isbn')) ?>"></div>
            <div class="form-field"><label for="doi">DOI</label><input id="doi" name="doi" maxlength="255" value="<?= View::escape($value('doi')) ?>" placeholder="10.1000/example"></div>
            <div class="form-field"><label for="external_url">External publication URL</label><input id="external_url" name="external_url" type="url" maxlength="500" value="<?= View::escape($value('external_url')) ?>" placeholder="https://"></div>
        </div>
    </section>
    <section class="form-section">
        <div class="form-section-heading"><h2>Abstract and citation</h2></div>
        <div class="form-grid">
            <div class="form-field span-2"><label for="abstract">Abstract</label><textarea id="abstract" name="abstract" rows="8"><?= View::escape($value('abstract')) ?></textarea></div>
            <div class="form-field span-2"><label for="citation_text">Preferred citation</label><textarea id="citation_text" name="citation_text" rows="5"><?= View::escape($value('citation_text')) ?></textarea></div>
        </div>
    </section>
    <?php if ($editing): ?><section class="form-section"><div class="form-field"><label for="revision_note">Revision note</label><textarea id="revision_note" name="revision_note" rows="3"><?= View::escape($note) ?></textarea></div></section><?php endif; ?>
    <div class="form-actions">
        <button class="button button-primary" type="submit"><?= $editing ? 'Save changes' : 'Add publication' ?></button>
        <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>my-publications">Cancel</a>
    </div>
</form>
<?php if ($editing): ?>
<section class="admin-panel" id="authors">
    <div class="panel-heading"><h2>Authors</h2></div>
    <?php if ($authors === []): ?>
        <p class="admin-empty compact">No authors added yet.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table class="admin-table">
                <thead><tr><th>Order</th><th>Author</th><th>Affiliation</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($authors as $author): ?>
                        <tr>
                            <td><?= (int) $author['author_order'] ?></td>
                            <th><?= View::escape($authorName($author)) ?><small><?= ($author['is_corresponding'] ? 'Corresponding · ' : '') . ($author['staff_id'] ? 'FAST staff' : 'External author') ?></small></th>
                            <td><?= View::escape((string) ($author['external_affiliation'] ?? 'FAST')) ?></td>
                            <td>
                                <form method="post" action="<?= View::escape($baseUrl) ?>my-publications/<?= $id ?>/authors/<?= (int) $author['id'] ?>/remove" onsubmit="return confirm('Remove this author?')">
                                    <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
                                    <button class="button button-secondary button-compact" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <form class="content-form member-form" method="post" action="<?= View::escape($baseUrl) ?>my-publications/<?= $id ?>/authors">
        <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
        <p class="form-help">Choose one FAST staff co-author or enter one external author name, not both.</p>
        <div class="form-grid">
            <div class="form-field"><label for="staff_id">FAST staff co-author</label><select id="staff_id" name="staff_id"><option value="">External author</option><?php foreach ($staffOptions as $option): ?><option value="<?= (int) $option['id'] ?>"><?= View::escape(trim(implode(' ', array_filter([$option['honorific_title'], $option['first_name'], $option['middle_name'], $option['last_name']])))) ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="external_author_name">External author name</label><input id="external_author_name" name="external_author_name" maxlength="200"></div>
            <div class="form-field"><label for="external_affiliation">External affiliation</label><input id="external_affiliation" name="external_affiliation" maxlength="255"></div>
            <div class="form-field"><label for="external_orcid">External ORCID</label><input id="external_orcid" name="external_orcid" maxlength="50" placeholder="0000-0000-0000-0000"></div>
            <div class="form-field"><label for="author_order">Author order</label><input id="author_order" name="author_order" type="number" min="1" max="65535" value="<?= count($authors) + 1 ?>" required></div>
            <div class="form-field checkbox-field"><label><input name="is_corresponding" type="checkbox" value="1"> Corresponding author</label></div>
        </div>
        <button class="button button-primary" type="submit">Add author</button>
    </form>
</section>
<?php endif; ?>
