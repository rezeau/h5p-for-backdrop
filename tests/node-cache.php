<?php
/**
 * Actual Backdrop entity caching + H5P load/filter paths, using memory storage.
 * Reads controller source only; never bootstraps a site or opens a database.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/step2-provenance.php';
$root = NULL;
foreach ($argv as $arg) {
  if (strpos($arg, '--backdrop-root=') === 0) { $root = substr($arg, 16); }
}
if (!$root || !is_file($root . '/core/modules/entity/entity.controller.inc')) {
  throw new RuntimeException('Supply --backdrop-root with an existing Backdrop source tree.');
}
require $root . '/core/modules/entity/entity.controller.inc';
$checks = 0;
function ncSame($expected, $actual, $message) {
  global $checks;
  $checks++;
  if (serialize($expected) !== serialize($actual)) { throw new RuntimeException($message); }
}
function cache_clear_all($ids, $bin) {
  if ($bin !== 'cache_entity_node' || !is_array($ids) || !$ids) { throw new RuntimeException('Broad cache invalidation forbidden.'); }
  $GLOBALS['nc_resets'][] = $ids;
  foreach ($ids as $id) { unset($GLOBALS['nc_cache'][$id]); }
}
function cache_flush($bin) { throw new RuntimeException('Cache flush forbidden.'); }
function cache_get_multiple(&$ids, $bin) {
  if ($bin !== 'cache_entity_node') { throw new RuntimeException('Wrong cache bin.'); }
  $found = array();
  foreach ($ids as $id) {
    if (isset($GLOBALS['nc_cache'][$id])) { $found[$id] = (object) array('cid' => $id, 'data' => unserialize($GLOBALS['nc_cache'][$id])); }
  }
  return $found;
}
function cache_set($id, $data, $bin) {
  if ($bin !== 'cache_entity_node') { throw new RuntimeException('Wrong cache bin.'); }
  $GLOBALS['nc_cache'][$id] = serialize($data);
}
function entity_get_controller($type) {
  if ($type !== 'node') { throw new RuntimeException('Wrong entity controller.'); }
  return $GLOBALS['nc_controller'];
}
function url($path, $options = array()) { return 'http://example.test/' . $path; }
class NCNode extends stdClass { function bundle() { return 'h5p_content'; } }
class NCResult {
  private $ids;
  function __construct($ids) { $this->ids = $ids; }
  function execute() { return $this; }
  function fetchAllAssoc($key) {
    $result = array();
    foreach ($this->ids as $id) {
      if (isset($GLOBALS['nc_nodes'][$id])) { $result[$id] = clone $GLOBALS['nc_nodes'][$id]; }
    }
    return $result;
  }
}
class NCController extends DefaultEntityController {
  public $loads = 0;
  function __construct() {
    $this->entityType = 'node'; $this->idKey = 'nid'; $this->revisionKey = 'vid';
    $this->persistentCache = TRUE; $this->staticCache = TRUE;
    $this->entityCache = array(); $this->entityInfo = array();
  }
  protected function buildQuery($ids, $conditions = array(), $revision_id = FALSE) { $this->loads++; return new NCResult($ids); }
  protected function attachLoad(&$entities, $revision_id = FALSE) { h5p_load($entities); }
}
class NCUpdate {
  private $fields, $id;
  function fields($fields) { $this->fields = $fields; return $this; }
  function condition($key, $id) {
    if ($key !== 'content_id') { throw new RuntimeException('Wrong update key.'); }
    $this->id = $id; return $this;
  }
  function execute() {
    if (!empty($GLOBALS['nc_fail_update'])) { throw new RuntimeException('Synthetic failed update'); }
    $GLOBALS['nc_updates']++;
    if (!isset($GLOBALS['nc_rows'][$this->id])) { return 0; }
    $GLOBALS['nc_rows'][$this->id] = array_merge($GLOBALS['nc_rows'][$this->id], $this->fields); return 1;
  }
}
function db_update($table) {
  if ($table !== 'h5p_nodes') { throw new RuntimeException('Unexpected update.'); }
  return new NCUpdate();
}
class NCField {
  private $value;
  function __construct($value) { $this->value = $value; }
  function fetchField() { return $this->value; }
}
$GLOBALS['h5p_test_select_handler'] = function ($query, $args) {
  if ($query !== 'SELECT nid FROM {h5p_nodes} WHERE content_id = :id') { throw new RuntimeException('Unexpected SELECT.'); }
  return new NCField($GLOBALS['nc_rows'][$args[':id']]['nid'] ?? FALSE);
};
class NCFramework extends H5PTestFramework {
  public $filters = 0, $deletes = 0, $dependencies = 0;
  function loadContent($id) {
    if (!isset($GLOBALS['nc_rows'][$id])) { return NULL; }
    $r = $GLOBALS['nc_rows'][$id];
    return array('id' => $id, 'params' => $r['json_content'], 'filtered' => $r['filtered'], 'slug' => $r['slug'],
      'metadata' => $r['metadata'], 'libraryId' => 1, 'libraryName' => 'H5P.CacheFixture',
      'libraryMajorVersion' => 1, 'libraryMinorVersion' => 0, 'libraryEmbedTypes' => 'div',
      'libraryFullscreen' => 0, 'embedType' => 'div', 'disable' => 0, 'shared' => 0, 'synced' => 0, 'contentHubId' => NULL);
  }
  function loadLibrary($name, $major, $minor) { return array('machineName' => $name, 'majorVersion' => $major, 'minorVersion' => $minor); }
  function loadLibrarySemantics($name, $major, $minor) { $this->filters++; return '[{"name":"answer","type":"boolean"}]'; }
  function alterLibrarySemantics(&$semantics, $name, $major, $minor) {}
  function loadAddons() { return array(); }
  function deleteLibraryUsage($id) { $this->deletes++; }
  function saveLibraryUsage($id, $dependencies) { $this->dependencies++; }
}
class NCLegacyFramework extends NCFramework {
  function updateContentFields($id, $fields) {
    // Negative control: the previous adapter leaves both node caches stale.
    db_update('h5p_nodes')->fields($fields)->condition('content_id', $id)->execute();
  }
}
$sandbox = new H5PTestSandbox();
register_shutdown_function(function () use ($sandbox) { $sandbox->close(); });
$GLOBALS['h5p_test_public_files'] = $sandbox->directory('public');
$GLOBALS['h5p_test_config']['h5p.settings'] = array('h5p_default_path' => 'h5p', 'h5p_export' => FALSE, 'h5p_revisioning' => TRUE);
$language = (object) array('langcode' => 'en');
$core = _h5p_get_instance('core');
function ncSeed($framework) {
  global $core;
  $core->h5pF = $framework;
  $GLOBALS['nc_rows'] = array(220 => array('nid' => 22, 'json_content' => '{"answer":false}', 'filtered' => '', 'slug' => 'fixture', 'metadata' => array('title' => 'Preserved', 'license' => 'CC BY')));
  $GLOBALS['nc_nodes'] = array();
  foreach (array(22, 99) as $id) {
    $n = new NCNode(); $n->nid = $id; $n->vid = $id === 22 ? 220 : 990;
    $n->title = 'Unchanged'; $n->langcode = 'en'; $n->filtered = ''; $GLOBALS['nc_nodes'][$id] = $n;
  }
  $GLOBALS['nc_cache'] = array(); $GLOBALS['nc_resets'] = array(); $GLOBALS['nc_updates'] = 0;
  $GLOBALS['nc_controller'] = new NCController();
}
function ncContent($id) {
  $nodes = entity_get_controller('node')->load(array($id));
  return h5p_get_content($nodes[$id]);
}
foreach (array('legacy', 'fixed') as $mode) {
  $framework = $mode === 'legacy' ? new NCLegacyFramework() : new NCFramework(); ncSeed($framework);
  $other = entity_get_controller('node')->load(array(99));
  $otherBytes = $GLOBALS['nc_cache'][99];
  $content = ncContent(22); $raw = $GLOBALS['nc_rows'][220]['json_content']; $metadata = $GLOBALS['nc_rows'][220]['metadata'];
  ncSame('{"answer":false}', $core->filterParameters($content), 'First filter uses actual Core semantics');
  if ($mode === 'fixed') {
    ncSame(array(array(22)), $GLOBALS['nc_resets'], 'Revision ID resolves to owning nid only');
    ncSame(FALSE, isset($GLOBALS['nc_cache'][22]), 'Persistent target invalidated');
    ncSame(2, entity_get_controller('node')->loads, 'Invalidation does not reload or recurse');
  }
  foreach (array('playback', 'editing', 'playback', 'editing') as $path) {
    $content = ncContent(22); ncSame('{"answer":false}', $core->filterParameters($content), 'Repeat ' . $path);
  }
  ncSame($mode === 'fixed' ? 1 : 5, $framework->filters, 'Actual semantics filtering count: ' . $mode);
  ncSame($mode === 'fixed' ? 1 : 5, $framework->deletes, 'Dependency deletion count: ' . $mode);
  ncSame($mode === 'fixed' ? 1 : 5, $framework->dependencies, 'Dependency rebuild count: ' . $mode);
  ncSame($mode === 'fixed' ? 1 : 5, $GLOBALS['nc_updates'], 'Derived field write count: ' . $mode);
  ncSame($raw, $GLOBALS['nc_rows'][220]['json_content'], 'Raw parameters preserved');
  ncSame($metadata, $GLOBALS['nc_rows'][220]['metadata'], 'Metadata preserved');
  ncSame($otherBytes, $GLOBALS['nc_cache'][99], 'Unrelated persistent node unchanged');
  ncSame(TRUE, $other[99] === entity_get_controller('node')->load(array(99))[99], 'Unrelated static node identity unchanged');
  // A fresh controller represents the next request and reads persistent data.
  $GLOBALS['nc_controller'] = new NCController(); $content = ncContent(22);
  $core->filterParameters($content);
  ncSame($mode === 'fixed' ? 1 : 6, $framework->filters, 'Next request warm cache behavior');
}
// Export-enabled warm path must reuse an existing export without regeneration.
$export = new ReflectionProperty('H5PCore', 'exportEnabled'); $export->setAccessible(TRUE); $export->setValue($core, TRUE);
$core->fs = new class extends H5PBackdropFileStorage {
  function __construct() {}
  function hasExport($name) { ncSame('fixture-220.h5p', $name, 'Expected export lookup'); return TRUE; }
};
$content = ncContent(22); $core->filterParameters($content);
ncSame(1, $framework->filters, 'Existing export and filtered parameters reused');
$resets = $GLOBALS['nc_resets'];
$framework->updateContentFields(999, array('filtered' => '{}'));
$GLOBALS['nc_rows'][1000] = array('nid' => NULL);
$framework->updateContentFields(1000, array('filtered' => '{}'));
ncSame($resets, $GLOBALS['nc_resets'], 'Missing record/owner never clears all nodes');
$GLOBALS['nc_fail_update'] = TRUE;
try { $framework->updateContentFields(220, array('filtered' => '{}')); throw new LogicException('Expected update failure'); }
catch (RuntimeException $e) { ncSame('Synthetic failed update', $e->getMessage(), 'Failed persistence propagates'); }
ncSame($resets, $GLOBALS['nc_resets'], 'Failed persistence never invalidates');
$GLOBALS['nc_fail_update'] = FALSE;
// Non-revisioning installations use nid as the H5P content ID.
$GLOBALS['h5p_test_config']['h5p.settings']['h5p_revisioning'] = FALSE;
$GLOBALS['nc_rows'][22] = $GLOBALS['nc_rows'][220];
$framework->updateContentFields(22, array('filtered' => '{"answer":false}', 'slug' => 'fixture'));
ncSame(array(22), end($GLOBALS['nc_resets']), 'Non-revision content maps to the same owning nid');
ncSame(FALSE, isset($GLOBALS['nc_cache'][22]), 'Non-revision persistent target invalidated');
ncSame($raw, $GLOBALS['nc_rows'][22]['json_content'], 'Non-revision raw parameters preserved');
ncSame($metadata, $GLOBALS['nc_rows'][22]['metadata'], 'Non-revision metadata preserved');
$register = json_decode(file_get_contents(H5P_TEST_ROOT . '/docs/h5p-api-1.28/node-cache-patches.json'), TRUE);
ncSame($register['files']['h5p.classes.inc']['beforeSha256'], hash('sha256', h5p_node_cache_baseline_bytes('h5p.classes.inc', file_get_contents(H5P_TEST_ROOT . '/h5p.classes.inc'))), 'Exact reversible adapter provenance');
echo 'PASS: ' . $checks . ' checks; actual Backdrop cache and H5P filtering, targeted static/persistent reset, warm playback/edit reuse, revision mapping, no recursion; memory-only storage.' . PHP_EOL;
