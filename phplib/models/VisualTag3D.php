<?php

class VisualTag3D extends BaseObject implements DatedObject {
  public static $_table = 'VisualTag3D';
  const STATIC_THUMB_DIR = 'img/visual/thumb/';

  function getTitle() {
    if ($this->entry === null) {
      $this->entry = Entry::get_by_id($this->entryId);
    }
    return $this->entry ? $this->entry->description : '';
  }

  function getModel() {
    return Visual3D::get_by_id($this->modelId);
  }

  function thumbFromBase64($base64) {
      $host = Config::get('static.host');
      $user = Config::get('static.user');
      $pass = Config::get('static.password');
      $path = Config::get('static.path') . self::STATIC_THUMB_DIR;
      $file = $this->id . '_' . $this->meshName . '.png';
      $hostname = $user . ":" . $pass . "@" . $host . $path . $file;

      $content = base64_decode($base64);

      /* create a stream context telling PHP to overwrite the file */
      $options = array('ftp' => array('overwrite' => true));
      $stream = stream_context_create($options);
      // XXX: FIGURE OUT WHT THIS DOESN'T WORK!
      file_put_contents($hostname, $content, 0, $stream);
  }

  static function loadAllForEntries($entries) {
    if (empty($entries)) {
      return [];
    }

    $entryIds = Util::objectProperty($entries, 'id');

    $vts = Model::factory('VisualTag3D')
      ->where_in('entryId', $entryIds)
      ->find_many();

    $map = [];
    foreach ($vts as $vt) {
      $map[$vt->id] = $vt;
    }

    return array_values($map);
  }

}

?>

