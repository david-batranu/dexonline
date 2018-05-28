<?php

require_once("../phplib/Core.php");

$user = User::getActive();

if (!$user) {
  Util::redirect(Core::getWwwRoot());
}

$actionAdd = Request::get('add');
$actionDelete = Request::get('delete');

$toDelete = Request::getArray('toDelete');

$addNewName = Request::get('selectionName');
$addNewIds = Request::getArray('sourceId');

if ($actionAdd && $addNewName && $addNewIds) {
  $us = Model::factory('UserSelection')
    ->create();
  $us->userId = $user->id;
  $us->name = $addNewName;
  $us->save();

  foreach($addNewIds as $newId) {
    $ss = Model::factory('SelectionSource')
      ->create();
    $ss->selectionId = $us->id;
    $ss->sourceId = $newId;
    $ss->save();
  }

}

if ($actionDelete && $toDelete) {
  // delete SelectionSources
  Model::factory('SelectionSource')
    ->where_in('selectionId', $toDelete)
    ->delete_many();

  // delete UserSelections
  Model::factory('UserSelection')
    ->where_in('id', $toDelete)
    ->delete_many();
}


$selections = UserSelection::getSelections($user->id);
$sources = Source::getAll();


SmartyWrap::assign('selections', $selections);
SmartyWrap::assign('sources', $sources);

SmartyWrap::display('surseFavorite.tpl');
