<?php
/**
 * Resizer regression using Backdrop's real locale URL rewriter, without a site
 * bootstrap, settings, database, sessions or network requests.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_error_handler(function ($severity, $message, $file, $line) {
  if (error_reporting() & $severity) {
    throw new ErrorException($message, 0, $severity, $file, $line);
  }
  return FALSE;
});

$moduleRoot = dirname(__DIR__);
$backdropRoot = dirname($moduleRoot, 2);
foreach ($argv as $argument) {
  if (strpos($argument, '--backdrop-root=') === 0) {
    $backdropRoot = substr($argument, strlen('--backdrop-root='));
  }
}
$localeFile = $backdropRoot . '/core/includes/locale.inc';
if (!is_file($localeFile)) {
  fwrite(STDERR, "Pass --backdrop-root=PATH to an existing Backdrop checkout.\n");
  exit(2);
}

function backdrop_get_path($type, $name) {
  if ($type !== 'module' || $name !== 'h5p') {
    throw new RuntimeException('Unexpected module path lookup.');
  }
  return 'modules/h5p';
}
function &backdrop_static($name) {
  static $values = array();
  if (!array_key_exists($name, $values)) { $values[$name] = NULL; }
  return $values[$name];
}
function language_list($onlyEnabled = FALSE) {
  return array('en' => (object) array('langcode' => 'en'),
    'fr' => (object) array('langcode' => 'fr'));
}
function config_get($name, $key) {
  if ($name === 'locale.settings' && $key === 'language_negotiation_url_part') {
    return LANGUAGE_NEGOTIATION_URL_PREFIX;
  }
  if ($name === 'locale.settings' && $key === 'language_negotiation_url_prefixes') {
    return array('en' => '', 'fr' => 'fr');
  }
  throw new RuntimeException('Unexpected configuration lookup.');
}
function url($path, array $options = array()) {
  $options += array('absolute' => FALSE, 'prefix' => '');
  // The consumer must see the adapter's actual object. A missing langcode
  // triggers the error handler above, including on PHP 7.4.
  locale_language_url_rewrite_url($path, $options);
  return ($options['absolute'] ? 'http://127.0.0.1:8092/' : '/') .
    $options['prefix'] . $path;
}

require $localeFile;
require $moduleRoot . '/h5p.module';
$failures = array();
foreach (array('en', 'fr') as $langcode) {
  $language = $language_url = (object) array('langcode' => $langcode);
  try {
    // Positive control: French rewriting remains active for ordinary links.
    $expectedLink = ($langcode === 'fr' ? '/fr/' : '/') . 'node/22';
    if (url('node/22') !== $expectedLink) {
      throw new RuntimeException('Ordinary language-prefixed URL is incorrect.');
    }
    $expectedResizer = 'http://127.0.0.1:8092/modules/h5p/library/js/h5p-resizer.js';
    if (h5p_get_resize_url() !== $expectedResizer) {
      throw new RuntimeException('Resizer URL must be absolute and unprefixed.');
    }
    echo 'PASS: ' . $langcode . ' context: no PHP warnings, active language rewriting, unprefixed resizer URL.' . PHP_EOL;
  }
  catch (Throwable $error) {
    $failures[] = $langcode . ': ' . $error->getMessage();
  }
}
restore_error_handler();
foreach ($failures as $failure) { fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL); }
echo '2 language contexts; ' . count($failures) . ' failure(s).' . PHP_EOL;
exit(count($failures) ? 1 : 0);
