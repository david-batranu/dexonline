<?php

require_once("../phplib/Core.php");

$user = User::getActive();

if (!$user) {
  Util::redirect(Core::getWwwRoot());
}


$addNewName = Request::get('selectionName');
$addNewIds = Request::getArray('sourceId');

if ($addNewName && $addNewIds) {
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


$selections = UserSelection::getSelections($user->id);
$sources = Source::getAll();


SmartyWrap::assign('selections', $selections);
SmartyWrap::assign('sources', $sources);

SmartyWrap::display('surseFavorite.tpl');
