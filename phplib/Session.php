<?php

class Session {

  const ONE_MONTH_IN_SECONDS = 30 * 86400;
  const ONE_YEAR_IN_SECONDS = 365 * 86400;

  private $user;

  static function init() {
    if (isset($_COOKIE[session_name()])) {
      session_start();
    }
    if (Request::isWeb()) {
      if (!self::getUser()) {
        self::loadUserFromCookie();
      }
    }
    // Otherwise we're being called by a local script, not a web-based one.
  }

  static function login($user, $openidData) {
    if (!$user) {
      $user = Model::factory('User')->create();
    }
    if (!$user->identity) {
      $user->identity = $openidData['identity'];
    }
    if (!$user->openidConnectSub && isset($openidData['sub'])) {
      $user->openidConnectSub = $openidData['sub'];
    }
    if (!$user->nick && isset($openidData['nickname'])) {
      $user->nick = AdminStringUtil::cleanup($openidData['nickname']);
    }
    if (isset($openidData['fullname'])) {
      $user->name = AdminStringUtil::cleanup($openidData['fullname']);
    }
    if (isset($openidData['email'])) {
      $user->email = $openidData['email'];
    }
    $user->password = null; // no longer necessary after the first OpenID login
    $user->save();

    self::set('user', $user);
    $cookie = Model::factory('Cookie')->create();
    $cookie->userId = $user->id;
    $cookie->cookieString = StringUtil::randomCapitalLetters(12);
    $cookie->save();
    setcookie("prefs[lll]", $cookie->cookieString, time() + self::ONE_YEAR_IN_SECONDS, '/');
    Log::info('Logged in, IP=' . $_SERVER['REMOTE_ADDR']);
    Util::redirect(Core::getWwwRoot());
  }

  static function logout() {
    Log::info('Logged out, IP=' . $_SERVER['REMOTE_ADDR']);
    $cookieString = self::getCookieSetting('lll');
    $cookie = Cookie::get_by_cookieString($cookieString);
    if ($cookie) {
      $cookie->delete();
    }
    setcookie("prefs[lll]", NULL, time() - 3600, '/');
    unset($_COOKIE['prefs']['lll']);
    self::kill();
    Util::redirect(Core::getWwwRoot());
  }

  // Try to load logging information from the long-lived cookie
  static function loadUserFromCookie() {
    $lll = self::getCookieSetting('lll');
    if ($lll) {
      $cookie = Cookie::get_by_cookieString($lll);
      $user = $cookie ? User::get_by_id($cookie->userId) : null;
      if ($user) {
        self::set('user', $user);
      } else {
        // The cookie is invalid.
        setcookie("prefs[lll]", NULL, time() - 3600, '/');
        unset($_COOKIE['prefs']['lll']);
        if ($cookie) {
          $cookie->delete();
        }
      }
    }
  }

  static function getCookieSetting($name, $default = '') {
    if (array_key_exists('prefs', $_COOKIE)) {
      $prefsCookie = $_COOKIE['prefs'];
      if (array_key_exists($name, $prefsCookie)) {
        return $prefsCookie[$name];
      }
    }
    return $default;
  }

  static function getUser() {
    if (self::has('user') &&
        isset($_SESSION['user']->id)) {
      return $_SESSION['user'];
    } else {
      return null;
    }
  }

  static function getUserNick() {
    return self::has('user') && isset($_SESSION['user']->nick)
      ? $_SESSION['user']->nick : "Anonim";
  }

  static function getUserId() {
    return self::has('user') && isset($_SESSION['user']->id)
      ? $_SESSION['user']->id : 0;
  }

  static function userPrefers($pref) {
    if (isset($_SESSION['user'])) {
      return (isset($_SESSION['user']->preferences) &&
              in_array($pref, preg_split('/,/', $_SESSION['user']->preferences)));
    } else {
      $prefs = self::getCookieSetting('anonymousPrefs');
      return in_array($pref, preg_split('/,/', $prefs));
    }
  }

  static function setAnonymousPrefs($pref) {
    $_COOKIE['prefs']['anonymousPrefs'] = $pref;
    setcookie('prefs[anonymousPrefs]', $pref, time() + self::ONE_YEAR_IN_SECONDS, '/');
  }

  static function getAnonymousPrefs() {
    return self::getCookieSetting('anonymousPrefs');
  }

  static function getWidgetMask() {
    return self::getCookieSetting('widgetMask', 0);
  }

  static function setWidgetMask($widgetMask) {
    $_COOKIE['prefs']['widgetMask'] = $widgetMask;
    setcookie('prefs[widgetMask]', $widgetMask, time() + self::ONE_YEAR_IN_SECONDS, '/');
  }

  static function getWidgetCount() {
    return self::getCookieSetting('widgetCount', 0);
  }

  static function setWidgetCount($widgetCount) {
    $_COOKIE['prefs']['widgetCount'] = $widgetCount;
    setcookie('prefs[widgetCount]', $widgetCount, time() + self::ONE_YEAR_IN_SECONDS, '/');
  }

  static function setSourceCookie($source) {
    setcookie('prefs[source]', $source, time() + self::ONE_YEAR_IN_SECONDS, '/');
  }

  static function getDefaultContribSourceId() {
    return self::getCookieSetting('source');
  }

  static function get($name, $default = null) {
    if (isset($_SESSION)){
      if (array_key_exists($name, $_SESSION)) {
        return $_SESSION[$name];
      }
    }
    return $default;
  }

  static function set($var, $value) {
    // Lazy start of the session so we don't send a PHPSESSID cookie unless we have to
    if (!isset($_SESSION)) {
      session_start();
    }
    $_SESSION[$var] = $value;
  }

  static function unsetVar($var) {
    if (isset($_SESSION)) {
      unset($_SESSION[$var]);
      if (!count($_SESSION)) {
        // Note that this will prevent us from creating another session this same request.
        // This does not seem to cause a problem at the moment.
        self::kill();
      }
    }
  }

  static function has($var) {
    return isset($_SESSION) && isset($_SESSION[$var]);
  }

  static function kill() {
    if (!isset($_SESSION)) {
      session_start(); // It has to have been started in order to be destroyed.
    }
    session_unset();
    @session_destroy();
    if (ini_get("session.use_cookies")) {
      setcookie(session_name(), '', time() - 3600, '/'); // expire it
    }
  }

  static function isWotdMode() {
    return isset($_COOKIE['prefs']['wotdMode']);
  }

  static function toggleWotdMode() {
    $on = !self::isWotdMode();

    if ($on) {
      setcookie('prefs[wotdMode]', '1', time() + self::ONE_YEAR_IN_SECONDS, '/');
      FlashMessage::add('Modul WotD activat', 'success');
    } else {
      setcookie('prefs[wotdMode]', '', time() - 3600, '/');
      FlashMessage::add('Modul WotD dezactivat', 'warning');
    }
  }

  static function getSplitLevel() {
    return self::getCookieSetting('splitLevel', LDiff::DEFAULT_SPLIT_LEVEL);
  }

  static function toggleWordHistoryDiffSplitLevel() {
    $currentLevel = self::getSplitLevel();
    $newLevel = LDiff::SPLIT_LEVEL_LETTER + LDiff::SPLIT_LEVEL_WORD - $currentLevel;

    if ($newLevel != LDiff::DEFAULT_SPLIT_LEVEL) {
      setcookie('prefs[splitLevel]', $newLevel, time() + self::ONE_YEAR_IN_SECONDS, '/');
    } else {
      setcookie('prefs[splitLevel]', LDiff::SPLIT_LEVEL_LETTER, time() - 3600, '/');
    }

    if ($newLevel == LDiff::SPLIT_LEVEL_LETTER) {
      FlashMessage::add('Diferențe la nivel de litere', 'warning');
    } else {
      FlashMessage::add('Diferențe la nivel de cuvinte', 'success');
    }
  }
}

