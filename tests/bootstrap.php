<?php
/**
 * Standalone test doubles. Never load Backdrop bootstrap or site settings.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_error_handler(function ($severity, $message, $file, $line) {
  if (error_reporting() & $severity) {
    throw new ErrorException($message, 0, $severity, $file, $line);
  }
  return FALSE;
});

define('H5P_TEST_ROOT', dirname(__DIR__));
define('BACKDROP_VERSION', '1.35.1-test');
define('VERSION', '7.0-test');
$GLOBALS['h5p_test_config'] = array('h5p.settings' => array());
$GLOBALS['h5p_test_alters'] = array();
$GLOBALS['h5p_test_db_calls'] = array();

class H5PTestConfig {
  private $name;
  public function __construct($name) { $this->name = $name; }
  public function get($key) { return config_get($this->name, $key); }
}
function config($name) { return new H5PTestConfig($name); }
function backdrop_get_path($type, $name) {
  if ($type !== 'module' || $name !== 'h5p') {
    throw new RuntimeException('Unexpected module path lookup.');
  }
  return H5P_TEST_ROOT;
}
function config_get($name, $key) {
  return isset($GLOBALS['h5p_test_config'][$name][$key])
    ? $GLOBALS['h5p_test_config'][$name][$key] : NULL;
}
function config_set($name, $key, $value) {
  $GLOBALS['h5p_test_config'][$name][$key] = $value;
}
function base_path() { return '/test/'; }
function file_stream_wrapper_get_instance_by_uri($uri) {
  if ($uri !== 'public://') { throw new RuntimeException('Unexpected stream wrapper.'); }
  return new H5PTestPublicFiles();
}
class H5PTestPublicFiles {
  public function getDirectoryPath() { return $GLOBALS['h5p_test_public_files']; }
}
function db_query($query, $arguments = array()) {
  // Optional SELECT-only fixture handler; never a real database connection.
  if (isset($GLOBALS['h5p_test_select_handler'])) {
    if (!preg_match('/^\s*SELECT\s/i', $query)) {
      throw new RuntimeException('Non-SELECT operation rejected by the fixture handler.');
    }
    return $GLOBALS['h5p_test_select_handler']($query, $arguments);
  }
  // Only permit the platform-information query against an in-memory result.
  if ($query !== "SELECT info FROM {system} WHERE name = 'h5p'") {
    throw new RuntimeException('Unexpected database operation in standalone test.');
  }
  $GLOBALS['h5p_test_db_calls'][] = $query;
  return new H5PTestSystemInfo();
}
class H5PTestSystemInfo {
  public function fetchField() { return serialize(array('version' => 'test-module')); }
}
function backdrop_alter($type, &$value, $libraries, $mode) {
  $GLOBALS['h5p_test_alters'][] = array($type, $libraries, $mode);
  $value[] = (object) array('path' => 'test-' . $type);
}

require_once H5P_TEST_ROOT . '/library/h5p.classes.php';
require_once H5P_TEST_ROOT . '/library/h5p-file-storage.interface.php';
require_once H5P_TEST_ROOT . '/library/h5p-default-storage.class.php';
require_once H5P_TEST_ROOT . '/h5p-backdrop-file-storage.class.inc';
require_once H5P_TEST_ROOT . '/library/h5p-development.class.php';
require_once H5P_TEST_ROOT . '/library/h5p-metadata.class.php';
require_once H5P_TEST_ROOT . '/h5p.classes.inc';
require_once H5P_TEST_ROOT . '/h5p.module';
$h5p_test_editor_root = defined('H5P_TEST_EDITOR_ROOT') ? H5P_TEST_EDITOR_ROOT
  : H5P_TEST_ROOT . '/modules/h5peditor/h5peditor';
require_once $h5p_test_editor_root . '/h5peditor-storage.interface.php';
require_once $h5p_test_editor_root . '/h5peditor-ajax.interface.php';
require_once $h5p_test_editor_root . '/h5peditor-ajax.class.php';
require_once $h5p_test_editor_root . '/h5peditor.class.php';
require_once H5P_TEST_ROOT . '/modules/h5peditor/h5peditor-backdrop-storage.class.inc';
require_once H5P_TEST_ROOT . '/modules/h5peditor/h5peditor-backdrop-ajax.class.inc';

class H5PTestFramework extends H5PBackdrop {
  public $errors = array();
  public $messages = array();
  public $uploadDirectory;
  public $uploadPath;
  public $options = array();
  public $installed = array();
  public $httpStatus = 403;

  public function getUploadedH5pFolderPath() { return $this->uploadDirectory; }
  public function getUploadedH5pPath() { return $this->uploadPath; }
  public function setErrorMessage($message, $code = NULL) {
    $this->errors[] = array('code' => $code, 'message' => $message);
  }
  public function setInfoMessage($message) { $this->messages[] = $message; }
  public function t($message, $replacements = array()) { return strtr($message, $replacements); }
  public function mayUpdateLibraries() { return TRUE; }
  public function isInDevMode() { return FALSE; }
  public function hasPermission($permission, $content_id = NULL) { return TRUE; }
  public function getOption($name, $default = NULL) {
    return isset($this->options[$name]) ? $this->options[$name] : $default;
  }
  public function setOption($name, $value) { $this->options[$name] = $value; }
  public function getLibraryId($machineName, $majorVersion = NULL, $minorVersion = NULL) {
    $key = $machineName . ' ' . $majorVersion . '.' . $minorVersion;
    return isset($this->installed[$key]) ? $this->installed[$key] : FALSE;
  }
  public function libraryHasUpgrade($library) { return FALSE; }
  public function getWhitelist($isLibrary, $defaultContentWhitelist, $defaultLibraryWhitelist) {
    return $defaultContentWhitelist . ($isLibrary ? ' ' . $defaultLibraryWhitelist : '');
  }
  public function fetchExternalData($url, $data = NULL, $blocking = TRUE, $stream = NULL,
      $allData = FALSE, $headers = array(), $files = array(), $method = 'POST') {
    return array('status' => $this->httpStatus, 'data' => '{"data":{"name":"Test"}}', 'headers' => array());
  }
}

/**
 * Every destructive validator/storage operation is confined to this owned root.
 */
class H5PTestSandbox {
  public $root;
  public function __construct() {
    $this->root = sys_get_temp_dir() . '/h5p-core-128-test-' . bin2hex(random_bytes(8));
    if (!mkdir($this->root)) { throw new RuntimeException('Cannot create test sandbox.'); }
  }
  public function directory($name) {
    $path = $this->root . '/' . $name;
    $this->assertOwned($path);
    if (!is_dir($path) && !mkdir($path, 0777, TRUE)) {
      throw new RuntimeException('Cannot create test directory.');
    }
    return $path;
  }
  public function assertOwned($path) {
    $root = realpath($this->root);
    if ($root === FALSE) { throw new RuntimeException('Test sandbox is missing.'); }
    $normalized = str_replace('\\', '/', $path);
    $prefix = str_replace('\\', '/', $root) . '/';
    if (strpos($normalized, $prefix) !== 0 || preg_match('~(^|/)\.\.(/|$)~', $normalized)) {
      throw new RuntimeException('Test path escapes its sandbox: ' . $path);
    }
  }
  public function close() {
    if (!is_dir($this->root)) { return; }
    $root = realpath($this->root);
    if (strpos(basename($root), 'h5p-core-128-test-') !== 0 ||
        realpath(dirname($root)) !== realpath(sys_get_temp_dir())) {
      throw new RuntimeException('Refusing to clean an unowned test directory.');
    }
    // Reject links before asking upstream's deletion helper to recurse.
    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
      if ($file->isLink()) { throw new RuntimeException('Link found inside test sandbox.'); }
    }
    H5PCore::deleteFileTree($root);
  }
}
