<?php
/**
 * Editor adapter contracts. No site bootstrap, credentials or database access.
 */
$editorRoot = NULL;
foreach ($argv as $argument) {
  if (strpos($argument, '--editor-root=') === 0) {
    $editorRoot = substr($argument, strlen('--editor-root='));
  }
}
if ($editorRoot !== NULL) {
  $lock = json_decode(file_get_contents(dirname(__DIR__) . '/docs/h5p-api-1.28/upstream-sources.lock.json'), TRUE);
  foreach ($lock['editor']['files'] as $file) {
    $bytes = file_get_contents($editorRoot . '/' . $file['path']);
    if (sha1('blob ' . strlen($bytes) . "\0" . $bytes) !== $file['gitBlob']) {
      throw new RuntimeException('Pinned editor source mismatch: ' . $file['path']);
    }
  }
  define('H5P_TEST_EDITOR_ROOT', $editorRoot);
}
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/step2-provenance.php';
$assertions = 0;
function check($condition, $message) {
  global $assertions;
  $assertions++;
  if (!$condition) { throw new RuntimeException($message); }
}
function same($expected, $actual, $message) { check($expected === $actual, $message); }
function user_access($permission) {
  same('create restricted h5p content types', $permission, 'Expected permission check');
  return FALSE;
}
class H5PEditorFixtureRows implements IteratorAggregate {
  private $rows;
  public function __construct($rows) { $this->rows = $rows; }
  public function fetchAll() { return $this->rows; }
  public function fetchObject() { return $this->rows ? $this->rows[0] : FALSE; }
  public function getIterator(): Traversable { return new ArrayIterator($this->rows); }
}
function selectFixture($needle, $rows) {
  $GLOBALS['h5p_test_select_handler'] = function ($query, $arguments) use ($needle, $rows) {
    check(strpos($query, $needle) !== FALSE, 'Expected SELECT fixture: ' . $needle);
    check(strpos($query, 'patch_version_in_folder_name') === FALSE, 'No nonexistent column queried');
    return new H5PEditorFixtureRows($rows);
  };
}
class H5PEditorFixtureCore extends H5PCore {
  public $fixtureLibrary;
  public function loadLibrary($name, $majorVersion, $minorVersion) { return $this->fixtureLibrary; }
  public function loadLibrarySemantics($name, $majorVersion, $minorVersion) { return '[]'; }
}
class H5PEditorFixtureStorage extends H5peditorBackdropStorage {
  public function getAvailableLanguages($name, $majorVersion, $minorVersion) { return array('en', 'fr'); }
  public function alterLibraryFiles(&$files, $libraries) {}
}
class H5PEditorFixture extends H5peditor {
  public function findEditorLibraries($name, $majorVersion, $minorVersion) { return array(); }
  public function getLibraryLanguage($name, $majorVersion, $minorVersion, $languageCode) { return NULL; }
}

$sandbox = new H5PTestSandbox();
register_shutdown_function(function () use ($sandbox) { $sandbox->close(); });
try {
  $base = $sandbox->directory('h5p');
  $alternate = $sandbox->directory('h5peditor');
  $storage = new H5PBackdropFileStorage($base, $alternate);
  $parent = new H5PDefaultStorage($base, $alternate);
  $library = array('machineName' => 'H5P.Fixture', 'majorVersion' => '1',
    'minorVersion' => '0', 'patchVersion' => '7', 'patchVersionInFolderName' => FALSE);
  same(NULL, $storage->getUpgradeScript($library), 'Missing array lookup returns NULL');
  same($parent->getUpgradeScript('H5P.Fixture', 1, 0),
    $storage->getUpgradeScript('H5P.Fixture', 1, 0), 'Missing legacy lookup delegates unchanged');
  $folder = $sandbox->directory('h5p/libraries/H5P.Fixture-1.0');
  file_put_contents($folder . '/upgrades.js', '// Fixture is never executed.');
  $beforeFile = hash_file('sha256', $folder . '/upgrades.js');
  same('/libraries/H5P.Fixture-1.0/upgrades.js', $storage->getUpgradeScript($library), 'Array excludes patch');
  same($parent->getUpgradeScript('H5P.Fixture', 1, 0),
    $storage->getUpgradeScript('H5P.Fixture', 1, 0), 'Existing legacy lookup delegates unchanged');
  $alias = $library;
  unset($alias['machineName']);
  $alias['name'] = 'H5P.Fixture';
  same($storage->getUpgradeScript($library), $storage->getUpgradeScript($alias), 'Core name alias supported');
  $patched = $library;
  $patched['patchVersionInFolderName'] = TRUE;
  same(NULL, $storage->getUpgradeScript($patched), 'Explicit patch flag does not fall back to wrong directory');
  $patchFolder = $sandbox->directory('h5p/libraries/H5P.Fixture-1.0.7');
  file_put_contents($patchFolder . '/upgrades.js', '// Synthetic patch folder.');
  same('/libraries/H5P.Fixture-1.0.7/upgrades.js', $storage->getUpgradeScript($patched), 'Synthetic patch flag respected');
  same($beforeFile, hash_file('sha256', $folder . '/upgrades.js'), 'Lookup does not mutate script');
  foreach (array(array('H5P.Fixture'), array('H5P.Fixture', 1)) as $arguments) {
    try { $storage->getUpgradeScript(...$arguments); throw new LogicException('Incomplete legacy call accepted'); }
    catch (ArgumentCountError $expected) { check(TRUE, 'Legacy required-argument behavior preserved'); }
  }
  foreach (array(array(), $library + array('unused' => TRUE)) as $index => $candidate) {
    if ($index === 1) { same($storage->getUpgradeScript($library), $storage->getUpgradeScript($candidate), 'Additional metadata preserved'); continue; }
    try { $storage->getUpgradeScript($candidate); throw new LogicException('Invalid array accepted'); }
    catch (InvalidArgumentException $expected) { check(TRUE, 'Incomplete array rejected'); }
  }
  $unsafe = $library;
  $unsafe['machineName'] = '../outside';
  try { $storage->getUpgradeScript($unsafe); throw new LogicException('Escaping name accepted'); }
  catch (InvalidArgumentException $expected) { check(TRUE, 'Escaping array name rejected'); }
  same(3, (new ReflectionMethod('H5PDefaultStorage', 'getUpgradeScript'))->getNumberOfRequiredParameters(), 'Core method contract stays three arguments');
  same(3, (new ReflectionMethod('H5PFileStorage', 'getUpgradeScript'))->getNumberOfRequiredParameters(), 'Core interface contract unchanged');
  same('h5p-backdrop-file-storage.class.inc', h5p_autoload_info()['H5PBackdropFileStorage'], 'Adapter registered');
  echo "PASS: Both storage calling conventions, folder flags and pinned Core contract\n";

  $GLOBALS['h5p_test_public_files'] = $sandbox->directory('public');
  $GLOBALS['h5p_test_config']['h5p.settings'] = array('h5p_default_path' => 'h5p');
  $local = (object) array('id' => '17', 'machine_name' => 'H5P.Fixture', 'title' => 'Fixture',
    'major_version' => '1', 'minor_version' => '0', 'patch_version' => '7', 'has_icon' => '1', 'restricted' => '0');
  selectFixture('SELECT hl4.library_id', array($local));
  $localRecords = (new H5PEditorBackdropAjax())->getLatestLibraryVersions();
  same(FALSE, $localRecords[0]->patch_version_in_folder_name, 'AJAX record supplies strict false');
  same('7', $localRecords[0]->patch_version, 'Patch metadata retained');
  selectFixture('SELECT hl4.library_id', array());
  same(array(), (new H5PEditorBackdropAjax())->getLatestLibraryVersions(), 'Empty latest-library response preserved');
  $core = new H5PEditorFixtureCore(new H5PTestFramework(), $storage, '/test/', 'fr');
  $core->fixtureLibrary = $library + array('title' => 'Fixture');
  $editor = new H5PEditorFixture($core, new H5PEditorFixtureStorage(), new H5PEditorBackdropAjax());
  $data = $editor->getLibraryData('H5P.Fixture', 1, 0, 'fr', '/prefix');
  same($core->url . '/prefix/libraries/H5P.Fixture-1.0/upgrades.js', $data->upgradesScript, 'Actual editor library-data call succeeds');
  same(array('en', 'fr'), $data->languages, 'Language data retained');
  $cache = array();
  $editor->mergeLocalLibsIntoCachedLibs($localRecords, $cache);
  check(strpos($cache[0]['icon'], '/libraries/H5P.Fixture-1.0/icon.svg') !== FALSE, 'Actual editor icon merge retains major/minor path');
  echo "PASS: Actual editor library-data and cached-icon initialization\n";

  $editorStorage = new H5peditorBackdropStorage();
  $details = (object) array('title' => 'Fixture', 'runnable' => '1', 'restricted' => '1',
    'tutorial_url' => 'local-help', 'metadata_settings' => '{"disable":false}');
  selectFixture('SELECT title, runnable', array($details));
  $request = (object) array('name' => 'H5P.Fixture', 'majorVersion' => '1', 'minorVersion' => '0');
  $specific = $editorStorage->getLibraries(array($request));
  same(FALSE, $specific[0]->patchVersionInFolderName, 'Specific editor library flag');
  same(TRUE, $specific[0]->restricted, 'Restrictions preserved');
  same('local-help', $specific[0]->tutorialUrl, 'Tutorial metadata preserved');
  same(FALSE, $specific[0]->metadataSettings->disable, 'Metadata settings preserved');
  same('H5P.Fixture-1.0', H5PCore::libraryToFolderName((array) $specific[0]), 'Specific record folder');
  selectFixture('SELECT title, runnable', array());
  same(array(), $editorStorage->getLibraries(array($request)), 'Unavailable specific library omitted');
  selectFixture('SELECT machine_name AS name', array((object) array('name' => 'H5P.Fixture',
    'title' => 'Fixture', 'major_version' => '1', 'minor_version' => '0', 'restricted' => '0',
    'tutorial_url' => NULL, 'metadata_settings' => '{}')));
  $all = $editorStorage->getLibraries();
  same(FALSE, $all[0]->patchVersionInFolderName, 'Selector list flag');
  same(FALSE, $all[0]->restricted, 'Unrestricted selector record preserved');
  echo "PASS: Specific and selector library records, permissions and metadata\n";

  $row = (object) array('library_id' => '17', 'machine_name' => 'H5P.Fixture', 'title' => 'Fixture',
    'major_version' => '1', 'minor_version' => '0', 'patch_version' => '7', 'semantics' => '[]');
  $GLOBALS['h5p_test_select_handler'] = function ($query, $arguments) use ($row) {
    check(strpos($query, 'patch_version_in_folder_name') === FALSE, 'Framework queries no new column');
    return new H5PEditorFixtureRows(strpos($query, 'SELECT library_id,') !== FALSE ? array($row) : array());
  };
  $loaded = (new H5PBackdrop())->loadLibrary('H5P.Fixture', 1, 0);
  same(FALSE, $loaded['patchVersionInFolderName'], 'Framework array explicitly excludes patch');
  same('7', $loaded['patchVersion'], 'Framework patch metadata retained');
  same('[]', $loaded['semantics'], 'Semantics untouched');
  same('H5P.Fixture-1.0', H5PCore::libraryToFolderName($loaded), 'Framework legacy directory');
  selectFixture('SELECT library_id,', array());
  same(FALSE, (new H5PBackdrop())->loadLibrary('H5P.Missing', 1, 0), 'Missing framework library remains FALSE');
  unset($GLOBALS['h5p_test_select_handler']);
  echo "PASS: Framework library-load path without a schema change\n";

  $register = json_decode(file_get_contents(H5P_TEST_ROOT . '/docs/h5p-api-1.28/phase-3b-step2-patches.json'), TRUE);
  foreach ($register['files'] as $path => $patch) {
    same($patch['beforeSha256'], hash('sha256', h5p_step2_baseline_bytes($path, file_get_contents(H5P_TEST_ROOT . '/' . $path))), 'Exact reversible adapter patch ' . $path);
  }
  foreach ($register['newFiles'] as $path => $expectedHash) {
    same($expectedHash, hash('sha256', h5p_step3_baseline_bytes($path, file_get_contents(H5P_TEST_ROOT . '/' . $path))), 'Exact new adapter source ' . $path);
  }
  echo $assertions . " assertions; 0 failures; editor source: " . 'locked target' . ".\n";
}
catch (Throwable $error) {
  fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
  exit(1);
}
