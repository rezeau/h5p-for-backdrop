<?php
/**
 * Backdrop update discovery with memory-only system rows. No site bootstrap,
 * settings, update.php, update-hook execution, database connection or DDL.
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
foreach (array('core/includes/install.inc', 'core/includes/update.inc',
    'core/includes/module.inc', 'core/modules/system/system.install') as $path) {
  if (!is_file($backdropRoot . '/' . $path)) {
    fwrite(STDERR, "Pass --backdrop-root=PATH to an existing Backdrop checkout.\n");
    exit(2);
  }
}
define('BACKDROP_CORE_COMPATIBILITY', '1.x');
$GLOBALS['schema_fixture_version'] = -1;
$GLOBALS['schema_fixture_writes'] = 0;
$checks = 0;
function schemaSame($expected, $actual, $message) {
  global $checks;
  $checks++;
  if ($expected !== $actual) { throw new RuntimeException($message); }
}
function &backdrop_static($name, $default = NULL) {
  static $values = array();
  if (!array_key_exists($name, $values)) { $values[$name] = $default; }
  return $values[$name];
}
function module_list() { return array('h5p' => 'h5p'); }
function module_invoke($module, $hook) {
  if ($module !== 'h5p' || $hook !== 'update_last_removed') {
    throw new RuntimeException('Unexpected hook invocation.');
  }
  return h5p_update_last_removed();
}
function system_rebuild_theme_data() { return array(); }
function system_rebuild_module_data() {
  return array('h5p' => (object) array('info' => array('backdrop' => '1.x', 'php' => '7.4')));
}
function db_query($query, $arguments = array()) {
  if ($query !== 'SELECT name, schema_version FROM {system} WHERE type = :type'
      || $arguments !== array(':type' => 'module')) {
    throw new RuntimeException('Unexpected SQL; only the memory system row is available.');
  }
  return array((object) array('name' => 'h5p',
    'schema_version' => $GLOBALS['schema_fixture_version']));
}
class SchemaFixtureUpdate {
  private $version;
  function fields($fields) {
    if (array_keys($fields) !== array('schema_version')) {
      throw new RuntimeException('Unexpected system fields.');
    }
    $this->version = $fields['schema_version'];
    return $this;
  }
  function condition($field, $value) {
    if ($field !== 'name' || $value !== 'h5p') {
      throw new RuntimeException('Unexpected system row.');
    }
    return $this;
  }
  function execute() {
    $GLOBALS['schema_fixture_version'] = (int) $this->version;
    $GLOBALS['schema_fixture_writes']++;
    return 1;
  }
}
function db_update($table) {
  if ($table !== 'system') { throw new RuntimeException('Unexpected update table.'); }
  return new SchemaFixtureUpdate();
}
function db_create_table() { throw new RuntimeException('DDL execution forbidden.'); }
function db_add_field() { throw new RuntimeException('DDL execution forbidden.'); }
function config_install_default_config($module) {
  if ($module !== 'h5p') { throw new RuntimeException('Unexpected default config install.'); }
}
function schemaFixtureVersion($version) {
  $GLOBALS['schema_fixture_version'] = $version;
  backdrop_get_installed_schema_version(NULL, TRUE);
}
/** Extract exact source blocks by unique anchors, independent of line numbers. */
function schemaSourceBlock($path, $start, $end) {
  $source = file_get_contents($path);
  if (substr_count($source, $start) !== 1 || substr_count($source, $end) !== 1) {
    throw new RuntimeException('Backdrop source anchors changed: ' . $path);
  }
  $offset = strpos($source, $start);
  $limit = strpos($source, $end, $offset);
  if ($limit === FALSE) { throw new RuntimeException('Backdrop source block changed.'); }
  return substr($source, $offset, $limit - $offset);
}
function schemaFreshInstallVersion() {
  global $backdropRoot;
  $module = 'h5p';
  // Only evaluate version selection and storage, with memory-only collaborators.
  // The surrounding install lifecycle and h5p_install() are never executed.
  eval(schemaSourceBlock($backdropRoot . '/core/includes/module.inc',
    '// Set the schema version to the number of the last update provided',
    '// Allow the module to perform install tasks.'));
  return backdrop_get_installed_schema_version('h5p');
}
function schemaStatusWarnings() {
  global $backdropRoot;
  eval(schemaSourceBlock($backdropRoot . '/core/modules/system/system.install',
    '// Check installed modules.', '    if (!empty($modules_update_required)) {'));
  return $modules_update_required;
}
function schemaPending() {
  $updates = update_get_update_list();
  return isset($updates['h5p']['pending']) ? array_keys($updates['h5p']['pending']) : array();
}

require $backdropRoot . '/core/includes/install.inc';
require $backdropRoot . '/core/includes/update.inc';
require $moduleRoot . '/h5p.install';
require __DIR__ . '/step2-provenance.php';

// The existing frozen baseline still checks every other installation byte.
$baseline = json_decode(file_get_contents($moduleRoot . '/docs/h5p-api-1.28/local-baseline.json'), TRUE);
$installRecord = array_values(array_filter($baseline['moduleFiles'], function ($file) {
  return $file['path'] === 'h5p.install';
}));
schemaSame(1, count($installRecord), 'Single installation baseline');
$restored = h5p_step2_baseline_bytes('h5p.install', file_get_contents($moduleRoot . '/h5p.install'));
schemaSame($installRecord[0]['sha256'], hash('sha256', $restored), 'Only the six empty hooks were removed');
foreach (range(6100, 6105) as $number) {
  schemaSame(1, preg_match('/function h5p_update_' . $number . '\([^)]*\) \{ \}/', $restored),
    'Historical hook has a genuinely empty body');
  schemaSame(FALSE, function_exists('h5p_update_' . $number), 'Obsolete hook is absent');
}
foreach (array('h5p_update_1000', 'h5p_update_7123', 'h5p_update_last_removed') as $function) {
  schemaSame(TRUE, function_exists($function), 'Retained hook exists: ' . $function);
}
schemaSame(7122, h5p_update_last_removed(), 'Removed-update boundary preserved');

$expectedOrder = array_merge(range(7100, 7123), array(1000));
$versions = array_map('intval', backdrop_get_schema_versions('h5p'));
schemaSame($expectedOrder, $versions, 'Exact Backdrop discovery order');
echo 'Discovery: ' . implode(', ', $versions) . PHP_EOL;

// Fresh installs already define every schema element supplied by migration 7123.
$schema = h5p_schema();
schemaSame(TRUE, isset($schema['h5p_content_hub_metadata_cache']), 'Fresh Hub cache table');
foreach (array('shared', 'synced', 'content_hub_id', 'a11y_title') as $field) {
  schemaSame(TRUE, isset($schema['h5p_nodes']['fields'][$field]), 'Fresh content field: ' . $field);
}
schemaFixtureVersion(SCHEMA_UNINSTALLED);
schemaSame(1000, schemaFreshInstallVersion(), 'Actual fresh-install selection records 1000');
schemaSame(array(), schemaStatusWarnings(), 'Fresh installation is current');
schemaSame(array(), schemaPending(), 'Fresh installation has no pending hooks');
echo 'Fresh install: stored=1000; status=current; pending=none' . PHP_EOL;

schemaFixtureVersion(6105);
schemaSame(array('h5p (0 → 1000)'), schemaStatusWarnings(), 'Existing 6105 still requires correction');
schemaSame($expectedOrder, schemaPending(), '6105 queues legacy hooks before 1000');
$queued = update_get_update_function_list(array('h5p' => 7100));
schemaSame($expectedOrder, array_keys($queued['h5p']), 'Actual queued order for 6105');
schemaSame(6105, backdrop_get_installed_schema_version('h5p'), 'Discovery does not repair installed rows');
echo 'Existing 6105: status=0 -> 1000; pending=7100-7123,1000; separate correction required' . PHP_EOL;
// Model an independently verified site's bookkeeping correction in memory only.
backdrop_set_installed_schema_version('h5p', 1000);
schemaSame(array(), schemaPending(), 'Verified 6105 correction clears pending hooks');
schemaSame(array(), schemaStatusWarnings(), 'Verified correction clears status warning');

schemaFixtureVersion(1000);
schemaSame(array(), schemaPending(), 'Existing 1000 remains current');
schemaSame(array(), schemaStatusWarnings(), 'Existing 1000 has no status warning');
echo 'Existing 1000: status=current; pending=none' . PHP_EOL;

schemaFixtureVersion(7122);
schemaSame(array(7123, 1000), schemaPending(), 'Genuine 7123 migration remains pending');
$queued = update_get_update_function_list(array('h5p' => 7123));
schemaSame(array(7123, 1000), array_keys($queued['h5p']), '7123 queues before configuration conversion');
schemaSame('h5p_update_7123', $queued['h5p'][7123], 'Original migration function remains queued');
echo 'Existing 7122: pending=7123,1000; migration 7123 remains discoverable' . PHP_EOL;

schemaFixtureVersion(0);
$updates = update_get_update_list();
schemaSame(TRUE, isset($updates['h5p']['warning']), 'A genuinely stored 0 retains the removed-update warning');
schemaSame(array(), schemaPending(), 'Stored 0 does not bypass the removed-update boundary');
schemaSame(2, $GLOBALS['schema_fixture_writes'], 'Only fresh selection and explicit correction wrote memory rows');
echo 'Stored 0: removed-update boundary still blocks automatic migration' . PHP_EOL;
echo 'PASS: ' . $checks . ' checks; actual Backdrop discovery/selection; memory-only storage; no migrations executed.' . PHP_EOL;
