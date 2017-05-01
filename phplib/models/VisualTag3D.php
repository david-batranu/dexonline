<?php

class VisualTag3D extends BaseObject implements DatedObject {
  public static $_table = 'VisualTag3D';

  function getTitle() {
    if ($this->entry === null) {
      $this->entry = Entry::get_by_id($this->entryId);
    }
    return $this->entry ? $this->entry->description : '';
  }

}

?>

