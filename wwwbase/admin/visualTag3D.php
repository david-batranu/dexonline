<?php
require_once('../../phplib/Core.php');
User::mustHave(User::PRIV_VISUAL);
Util::assertNotMirror();

$fileName = Request::get('fileName');
$id = Request::get('id');
$addTagButton = Request::has('addTagButton');

// Create a Visual3D object if one doesn't exist, then redirect to it.
if ($fileName) {
    $v = Visual3D::get_by_path($fileName);
    if (!$v) {
        $v = Visual3D::createFromFile($fileName);
    }
    Util::redirect("?id={$v->id}");
}

$v = Visual3D::get_by_id($id);


if ($addTagButton) {
  $data = json_decode(Request::get('jsondata'));
  foreach($data as $mesh_name => $mesh_data) {
    $entry_id = $mesh_data->word->id;

    $camera_position = $mesh_data->camera;
    $thumbnail = $mesh_data->thumb ? $mesh_data->thumb.explode(',') : null;

    $existing = Model::factory('VisualTag3D')
        ->where('modelId', $v->id)
        ->where('meshName', $mesh_name)
        ->find_one();

    if ($existing && !$entry_id) {
      $existing->delete();
      // TODO: Delete thumbnail!
      Log::info("Deleted 3d tag {$existing->id} ({$existing->meshName}) for model {$v->id} ({$v->path}).");
    }
    elseif ($existing && $entry_id) {
      $changed = false;
      if($existing->entryId != $entry_id) {
        $existing->entryId = $entry_id;
        $changed = true;
      }
      if($existing->camera != $camera_position) {
        $existing->camera = $camera_position;
        $changed = true;
      }
      if($changed) {
        $existing->save();
        if ($thumbnail) {
          $existing->thumbFromBase64($thumbnail);
        }
        $entry = Entry::get_by_id($existing->entryId);
        Log::info("Edited 3d tag {$existing->id} ({$existing->meshName}) to {$entry->id} ({$entry->description}) for model {$v->id} ({$v->path}).");
      }
    }
    elseif (!$existing && $entry_id) {
      $vt = Model::factory('VisualTag3D')->create();
      $vt->modelId = $v->id;
      $vt->meshName = $mesh_name;
      $vt->entryId = $entry_id;
      $vt->camera = $camera_position;
      $vt->save();

      if ($thumbnail) {
          $vt->thumbFromBase64($thumbnail);
      }

      $entry = Entry::get_by_id($vt->entryId);
      error_log($entry->id);
      Log::info("Added 3d tag {$vt->id} ({$mesh_name}) to {$entry->id} ({$entry->description}) for model {$v->id} ({$v->path}).");
    }
  }
  Util::redirect("?id={$v->id}");
}

$tags = VisualTag3D::get_all_by_modelId($v->id);
$tags_json = [];

foreach($tags as $tag) {
  $tags_json[$tag->meshName] = [
    "label" => "",
    "camera" => $tag->camera,
    "word" => [
      "id" => $tag->entryId,
      "label" => $tag->getTitle(),
    ],
    "thumbnail"=> "",
  ];
}

SmartyWrap::assign('visual', $v);
SmartyWrap::assign('jsondata', $tags_json ? json_encode($tags_json) : "{}");

SmartyWrap::addCss('jqueryui', 'admin');
SmartyWrap::addJs('jqueryui', 'select2Dev');
SmartyWrap::display('admin/visualTag3D.tpl');

?>

