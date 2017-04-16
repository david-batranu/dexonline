<?php
require_once("../../phplib/util.php");
User::require(User::PRIV_VISUAL);
util_assertNotMirror();
RecentLink::add('Dicționarul vizual 3D!');

SmartyWrap::display('admin/visual3DTag.tpl');
?>

