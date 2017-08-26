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

  function getThumbUrl() {
      $file = $this->id . '_' . $this->meshName . '.png';
      return Config::get('static.url') . self::STATIC_THUMB_DIR . "3d/" . $file;
  }

  function thumbFromBase64($base64) {
      $file = $this->id . '_' . $this->meshName . '.png';
      $content = base64_decode($base64);

      $localFile = "thumb_{$file}";
      file_put_contents($localFile, $content . "\n");

      $ftp = new FtpUtil();
      $ftp->staticServerPut($localFile, self::STATIC_THUMB_DIR . "3d/" . $file);

      unlink($localFile);
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

