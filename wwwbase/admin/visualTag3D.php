<?php
require_once('../../phplib/Core.php');
User::mustHave(User::PRIV_VISUAL);
Util::assertNotMirror();

$fileName = Request::get('fileName');
$id = Request::get('id');

$tagEntryId = Request::get('tagEntryId');
$tagComplete = Request::has('tagComplete');
$addTagButton = Request::has('addTagButton');

// Tag the image specified by $fileName. Create a Visual object if one doesn't exist, then redirect to it.
if ($fileName) {
  $v = Visual3D::get_by_path($fileName);
  if (!$v) {
    $v = Visual3D::createFromFile($fileName);
  }
  Util::redirect("?id={$v->id}");
}

$v = Visual3D::get_by_id($id);

if ($addTagButton) {
  $vt = Model::factory('VisualTag3D')->create();
  $vt->modelId = $v->id;
  $vt->entryId = $tagEntryId;
  $vt->complete = $tagComplete;
  $vt->save();

  $entry = Entry::get_by_id($vt->entryId);
  Log::info("Added 3d tag {$vt->id} ({$entry->description}) to image {$v->id} ({$v->path})");
  Util::redirect("?id={$v->id}");
}

SmartyWrap::assign('visual', $v);
SmartyWrap::assign('entry', Entry::get_by_id($v->entryId));

SmartyWrap::addCss('jqueryui', 'admin');
SmartyWrap::addJs('jqueryui', 'select2Dev');
SmartyWrap::display('admin/visualTag3D.tpl');

?>

