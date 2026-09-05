<?php declare(strict_types=1);use FastWebsite\Core\View;$x=static fn(string$f):string=>(string)($values[$f]??'');$edit=is_int($itemId);$insertImages=array_map(static fn(array$p):array=>['url'=>$baseUrl.ltrim(str_replace('public/','',(string)$p['file_path']),'/'),'alt'=>(string)($p['alt_text']??'')],$gallery);?>
<section class="admin-page-heading"><div><p class="eyebrow">Newsroom</p><h1><?=$edit?'Edit news':'Create news'?></h1><p>Published news can still be edited. Saving does not unpublish it.</p></div><a class="button button-secondary" href="<?=View::escape($baseUrl)?>admin/news">Cancel</a></section><?php if($errors!==[]):?><div class="form-alert"><ul><?php foreach($errors as$e):?><li><?=View::escape($e)?></li><?php endforeach;?></ul></div><?php endif;?>
<form class="content-form" enctype="multipart/form-data" method="post" action="<?=View::escape($baseUrl)?>admin/news<?=$edit?'/'.$itemId:''?>"><input type="hidden" name="_token" value="<?=View::escape($csrfToken)?>"><section class="form-section"><div class="form-grid"><div class="form-field span-2"><label>Title *</label><input name="title" maxlength="300" required value="<?=View::escape($x('title'))?>"></div><div class="form-field"><label>Category *</label><select name="category_id" required><?php foreach($categories as$c):?><option value="<?=(int)$c['id']?>"<?=$x('category_id')===(string)$c['id']?' selected':''?>><?=View::escape($c['name'])?></option><?php endforeach;?></select></div><div class="form-field"><label>Lead department</label><select name="department_id"><option value="">Faculty-wide</option><?php foreach($departments as$d):?><option value="<?=(int)$d['id']?>"<?=$x('department_id')===(string)$d['id']?' selected':''?>><?=View::escape($d['name'])?></option><?php endforeach;?></select></div><div class="form-field"><label>Article date *</label><input type="date" name="article_date" required value="<?=View::escape($x('article_date'))?>"></div><div class="form-field"><label>Public URL</label><input name="slug" maxlength="320" value="<?=View::escape($x('slug'))?>"></div><div class="form-field span-2"><label>Summary</label><textarea name="summary" rows="3"><?=View::escape($x('summary'))?></textarea></div><div class="form-field span-2"><label>Story *</label><textarea name="body" id="news-body" rows="12" required data-insert-images="<?=View::escape(json_encode($insertImages,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT))?>"<?php if($edit):?> data-gallery-upload-url="<?=View::escape($baseUrl.'admin/news/'.$itemId.'/gallery')?>"<?php endif;?>><?=View::escape($x('body'))?></textarea><?php if(!$edit):?><small>Save this story first, then you can upload and insert photos directly from the toolbar above.</small><?php endif;?></div><label><input type="checkbox" name="is_featured" value="1"<?=$x('is_featured')==='1'?' checked':''?>> Feature this story</label><label><input type="checkbox" name="allow_sharing" value="1"<?=$x('allow_sharing')==='1'?' checked':''?>> Allow sharing</label></div></section><section class="form-section"><h2>Featured image</h2><div class="form-grid"><div class="form-field span-2"><label>Reuse an image</label><select name="featured_media_id"><option value="">No image</option><?php foreach($images as$i):?><option value="<?=(int)$i['id']?>"<?=$x('featured_media_id')===(string)$i['id']?' selected':''?>><?=View::escape($i['original_name'])?></option><?php endforeach;?></select></div><div class="form-field"><label>Upload image</label><input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp,image/gif"></div><div class="form-field"><label>Image description</label><input name="image_alt_text" maxlength="255"></div></div></section><div class="form-actions"><button class="button button-secondary" name="submit_action" value="save">Save</button><?php if($canPublish):?><button class="button button-primary" name="submit_action" value="publish">Save and publish</button><?php endif;?></div></form>

<?php if ($edit): ?>
    <section class="admin-card" id="gallery">
        <div class="page-section-manager-heading">
            <div><p class="eyebrow">Inline article photos</p><h2>Gallery photos</h2><p>Upload photos here to make them available in the Story editor's <strong>Insert photo</strong> tool above, so you can place each one exactly where you want it in the text. The featured image above is shown separately and does not need to be added here again.</p></div>
        </div>

        <div id="gallery-empty" class="admin-empty" <?= $gallery === [] ? '' : 'hidden' ?>><p>No gallery photos yet. Use the toolbar above or the form below to add the first one.</p></div>

        <div class="table-wrap" id="gallery-table-wrap" <?= $gallery === [] ? 'hidden' : '' ?>>
            <table>
                <thead><tr><th>Photo</th><th>Description</th><th>Actions</th></tr></thead>
                <tbody id="gallery-table-body">
                    <?php foreach ($gallery as $photo): ?>
                        <tr>
                            <td><img src="<?= View::escape($baseUrl . ltrim(str_replace('public/', '', (string) $photo['file_path']), '/')) ?>" alt="" width="96" style="height:64px;object-fit:cover;border-radius:4px;"></td>
                            <td><?= View::escape((string) ($photo['alt_text'] ?? '')) ?></td>
                            <td>
                                <form method="post" action="<?= View::escape($baseUrl) ?>admin/news/<?= $itemId ?>/gallery/<?= (int) $photo['id'] ?>/remove">
                                    <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
                                    <button class="link-button">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <details class="page-section-add" <?= $gallery === [] ? 'open' : '' ?>>
            <summary>Add a gallery photo</summary>
            <form class="content-form" enctype="multipart/form-data" method="post" action="<?= View::escape($baseUrl) ?>admin/news/<?= $itemId ?>/gallery">
                <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
                <div class="form-grid">
                    <div class="form-field"><label>Upload photo</label><input type="file" name="gallery_photo" accept="image/jpeg,image/png,image/webp,image/gif"></div>
                    <div class="form-field"><label>Photo description *</label><input name="gallery_alt_text" maxlength="255"></div>
                </div>
                <button class="button button-primary">Add photo</button>
            </form>
        </details>
    </section>
<?php endif; ?>
