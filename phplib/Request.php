<?php

/**
 * This class reads request parameters.
 **/
class Request {
  /* Reads a request parameter. */
  static function get($name, $default = null) {
    return array_key_exists($name, $_REQUEST)
      ? $_REQUEST[$name]
      : $default;
  }

  // Taken from http://stackoverflow.com/a/1939911
  // Function to fix up PHP's messing up input containing dots, etc.
  // `$source` can be either 'POST' or 'GET'
  static function getRealInput($source) {
      $pairs = explode("&", $source == 'POST' ? file_get_contents("php://input") : $_SERVER['QUERY_STRING']);
      $vars = array();
      foreach ($pairs as $pair) {
          $nv = explode("=", $pair);
          $name = urldecode($nv[0]);
          $value = urldecode($nv[1]);
          $vars[$name] = $value;
      }
      return $vars;
  }

  // Wrapper functions specifically for GET and POST:
  static function getRealGET() { return self::getRealInput('GET'); }
  static function getRealPOST() { return self::getRealInput('POST'); }

  static function getStartsWith($name) {
    /* PHP strips dots from $_POST */
    $POST = self::getRealPOST();
    foreach($POST as $key => $value) {
      error_log($key);
      if(preg_match('@^'.$name.'@', $key)) {
        $results[$key] = $value;
      }
    }
    return $results;
  }

  /* Reads a file record from $_FILES. */
  static function getFile($name, $default = null) {
    return array_key_exists($name, $_FILES)
      ? $_FILES[$name]
      : $default;
  }

  /* Reads a present-or-not parameter (checkbox, button etc.). */
  static function has($name) {
    return array_key_exists($name, $_REQUEST);
  }

  /* Returns an array of values from a parameter in CSV format */
  static function getCsv($name) {
    return explode(',', self::get($name, []));
  }

  /**
   * Returns true if this script is running in response to a web request, false
   * otherwise.
   */
  static function isWeb() {
    return isset($_SERVER['REMOTE_ADDR']);
  }

  static function isAjax() {
    return isset($_SERVER['REQUEST_URI']) &&
      StringUtil::startsWith($_SERVER['REQUEST_URI'], Core::getWwwRoot() . 'ajax/');
  }

  static function getFullServerUrl() {
    $host = $_SERVER['SERVER_NAME'];
    $port =  $_SERVER['SERVER_PORT'];
    $path = Core::getWwwRoot();

    return ($port == '80') ? "http://$host$path" : "http://$host:$port$path";
  }

  /**
   * Search engine friendly URLs used for the search page:
   * 1) https://dexonline.ro/definitie[-<sursa>]/<cuvânt>[/<defId>][/paradigma]
   * 2) https://dexonline.ro/lexem[-<sursa>]/<cuvânt>[/<lexemId>][/paradigma]
   * 3) https://dexonline.ro/text[-<sursa>]/<text>
   * Links of the old form (search.php?...) can only come via the search form and
   * should not contain lexemId / definitionId.
   */
  static function redirectToFriendlyUrl($cuv, $entryId, $lexemId, $sourceUrlName, $text,
                                        $showParadigm, $format, $all) {
    if (strpos($_SERVER['REQUEST_URI'], '/search.php?') === false) {
      return;    // The url is already friendly.
    }

    if ($format['name'] != 'html') {
      return;
    }

    $cuv = urlencode($cuv);
    $sourceUrlName = urlencode($sourceUrlName);

    $sourcePart = $sourceUrlName ? "-{$sourceUrlName}" : '';
    $paradigmPart = $showParadigm ? '/paradigma' : '';
    $allPart = ($all && !$showParadigm) ? '/expandat' : '';

    if ($text) {
      $url = "text{$sourcePart}/{$cuv}";
    } else if ($entryId) {
      $e = Entry::get_by_id($entryId);
      if (!$e) {
        Util::redirect(Core::getWwwRoot());
      }
      $short = $e->getShortDescription();
      $url = "intrare{$sourcePart}/{$short}/{$e->id}/{$paradigmPart}";
    } else if ($lexemId) {
      $l = Lexem::get_by_id($lexemId);
      if (!$l) {
        Util::redirect(Core::getWwwRoot());
      }
      $url = "lexem/{$l->formNoAccent}/{$l->id}";
    } else {
      $url = "definitie{$sourcePart}/{$cuv}{$paradigmPart}";
    }

    Util::redirect(Core::getWwwRoot() . $url . $allPart);
  }

}

?>
