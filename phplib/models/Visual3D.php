<?php

class Visual3D extends BaseObject implements DatedObject {
  public static $_table = 'Visual3D';

  const STATIC_DIR = 'img/visual/';

  private $entry = null;

  static function createFromFile($fileName) {
    $v = Model::factory('Visual3D')->create();
    $v->path = $fileName;
    $v->userId = Session::getUserId();

    $url = Config::get('static.url') . self::STATIC_DIR . $fileName;
    $v->save();

    return $v;
  }

  function getTitle() {
    if ($this->entry === null) {
      $this->entry = Entry::get_by_id($this->entryId);
    }
    return $this->entry ? $this->entry->description : '';
  }

  function getUrl() {
    return Config::get('static.url') . self::STATIC_DIR . $this->path;
  }

  // Loads all Visuals that are associated with one of the entries,
  // either directly or through a VisualTag.
  static function loadAllForEntries($entries) {
    if (empty($entries)) {
      return [];
    }

    $map = [];
    $entryIds = Util::objectProperty($entries, 'id');

    $vs = Model::factory('Visual3D')
        ->where_in('entryId', $entryIds)
        ->find_many();
    foreach ($vs as $v) {
      $map[$v->id] = $v;
    }

    $vts = Model::factory('VisualTag3D')
         ->where_in('entryId', $entryIds)
         ->find_many();
    foreach ($vts as $vt) {
      $v = Visual3D::get_by_id($vt->modelId);
      $map[$v->id] = $v;
    }

    return array_values($map);
  }

  function delete() {
    // TODO: Delete thumbnail and its directory (if it becomes empty)
    VisualTag3D::delete_all_by_modelId($this->id);
    return parent::delete();
  }
}

?>

