<?php

class UserSelection extends BaseObject implements DatedObject {
  public static $_table = 'UserSelection';

  const PREFIX = 'userSelection';

  function getSources() {
    return Model::factory('Source')
      ->select('Source.*')
      ->join('SelectionSource', ['Source.id', '=', 'SelectionSource.sourceId'])
      ->where('SelectionSource.selectionId', $this->id)
      ->order_by_asc('Source.name')
      ->find_many();
  }

  static function getSelections($userId) {
    return $userId
      ? Model::factory('UserSelection')
          ->where('userId', $userId)
          ->order_by_asc('name')
          ->find_many()
      : [];
  }
};
