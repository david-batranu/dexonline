<?php

require_once("../../phplib/util.php");
User::require(User::PRIV_ADMIN);

session_toggleWordHistoryDiffSplitLevel();

$target = isset($_SERVER['HTTP_REFERER'])
    ? $_SERVER['HTTP_REFERER']
    : util_getWwwRoot();
util_redirect($target);