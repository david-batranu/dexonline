<?php
require_once('../../phplib/Core.php');
User::mustHave(User::PRIV_VISUAL);
Util::assertNotMirror();

$fileName = Request::get('fileName');
$id = Request::get('id');
$addTagButton = Request::has('addTagButton');
$clearTagButton = Request::has('clearTagButton');

// Tag the model specified by $fileName. Create a Visual object if one doesn't exist, then redirect to it.
if ($fileName) {
  $v = Visual3D::get_by_path($fileName);
  if (!$v) {
    $v = Visual3D::createFromFile($fileName);
  }
  Util::redirect("?id={$v->id}");
}

$v = Visual3D::get_by_id($id);

function unwrap_mapping($arr, $split_on){
  $result = [];
  foreach($arr as $key => $value) {
    $new_key = explode($split_on, $key)[1];
    $result[$new_key] = $value;
  }
  return $result;
}

if($clearTagButton) {
  $existing = Model::factory('VisualTag3D')
    ->where('modelId', $v->id)
    ->where('meshName', Request::get('clearTagButton'))
    ->find_one();
  if($existing){
    $entry = Entry::get_by_id($existing->entryId);
    $existing->delete();
    Log::info("Deleted 3d tag {$existing->id} ({$existing->meshName}) to {$entry->id} ({$entry->description}) for model {$v->id} ({$v->path}).");
  }

}
else if ($addTagButton) {
  $mapping = unwrap_mapping(Request::getStartsWith('mapping_'),  'mapping_');
  foreach($mapping as $mesh_name => $entry_id) {
    $existing = Model::factory('VisualTag3D')
        ->where('modelId', $v->id)
        ->where('meshName', $mesh_name)
        ->find_one();
    if($existing) {
      if($existing->entryId != $entry_id) {
        $existing->entryId = $entry_id;
        $existing->save();
        $entry = Entry::get_by_id($existing->entryId);
        Log::info("Edited 3d tag {$existing->id} ({$existing->meshName}) to {$entry->id} ({$entry->description}) for model {$v->id} ({$v->path}).");
      }
    }
    else {
      $vt = Model::factory('VisualTag3D')->create();
      $vt->modelId = $v->id;
      $vt->meshName = $mesh_name;
      $vt->entryId = $entry_id;
      $vt->save();

      $entry = Entry::get_by_id($vt->entryId);
      error_log($entry->id);
      Log::info("Added 3d tag {$vt->id} ({$mesh_name}) to {$entry->id} ({$entry->description}) for model {$v->id} ({$v->path}).");
    }
  }
  Util::redirect("?id={$v->id}");
}

SmartyWrap::assign('visual', $v);
SmartyWrap::assign('tags', VisualTag3D::get_all_by_modelId($v->id));

SmartyWrap::addCss('jqueryui', 'admin');
SmartyWrap::addJs('jqueryui', 'select2Dev');
SmartyWrap::display('admin/visualTag3D.tpl');

?>

