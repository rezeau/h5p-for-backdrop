<?php
/**
 * Optional metadata JSON contracts in both getLibraries paths.
 * Standalone memory fixtures: no site bootstrap, credentials or database.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/step2-provenance.php';
function user_access($permission) {
  if ($permission !== 'create restricted h5p content types') { throw new RuntimeException('Unexpected permission'); }
  return FALSE;
}
class MetadataRows implements IteratorAggregate {
  private $rows;
  function __construct($rows) { $this->rows = $rows; }
  function fetchObject() { return $this->rows ? clone $this->rows[0] : FALSE; }
  function getIterator(): Traversable {
    return new ArrayIterator(array_map(function($row) { return clone $row; }, $this->rows));
  }
}
$checks = 0;
function metadataSame($expected,$actual,$message) {
  global $checks; $checks++;
  if (serialize($expected) !== serialize($actual)) { throw new RuntimeException($message); }
}
$cases = array(
  array('NULL', NULL, NULL, JSON_ERROR_NONE),
  array('empty string', '', NULL, JSON_ERROR_NONE),
  array('empty object', '{}', (object) array(), JSON_ERROR_NONE),
  array('settings object', '{"disable":false}', (object) array('disable'=>FALSE), JSON_ERROR_NONE),
  array('array', '[]', array(), JSON_ERROR_NONE),
  array('zero scalar', '0', 0, JSON_ERROR_NONE),
  array('false scalar', 'false', FALSE, JSON_ERROR_NONE),
  array('string scalar', '"Français"', 'Français', JSON_ERROR_NONE),
  array('JSON null', 'null', NULL, JSON_ERROR_NONE),
  array('malformed nonempty', '{"broken":', NULL, JSON_ERROR_SYNTAX),
  array('whitespace nonempty', ' ', NULL, JSON_ERROR_SYNTAX),
  array('invalid UTF-8', '"'.chr(0xB1).'"', NULL, JSON_ERROR_UTF8),
);
$storage = new H5peditorBackdropStorage();
foreach ($cases as $case) {
  list($label,$raw,$expected,$error) = $case;
  foreach (array('specific','selector') as $mode) {
    $row = (object) array('name'=>'H5P.Fixture','title'=>'Fixture','runnable'=>'1',
      'major_version'=>'1','minor_version'=>'0','restricted'=>'0','tutorial_url'=>NULL,'metadata_settings'=>$raw);
    $frozen = serialize($row);
    $GLOBALS['h5p_test_select_handler'] = function($query,$args) use ($row,$mode) {
      if (strpos($query,$mode==='specific'?'SELECT title, runnable':'SELECT machine_name AS name')===FALSE ||
          strpos($query,'patch_version_in_folder_name')!==FALSE) { throw new RuntimeException('Unexpected SELECT'); }
      return new MetadataRows(array($row));
    };
    json_decode('{}'); // Reset native JSON error state before this single decode.
    $request = (object) array('name'=>'H5P.Fixture','majorVersion'=>'1','minorVersion'=>'0');
    $records = $mode==='specific' ? $storage->getLibraries(array($request)) : $storage->getLibraries();
    $decodeError = json_last_error();
    metadataSame(1,count($records),$label.' record count');
    metadataSame($expected,$records[0]->metadataSettings,$mode.' '.$label.' decoded value');
    metadataSame($error,$decodeError,$mode.' '.$label.' JSON error state');
    metadataSame(FALSE,$records[0]->patchVersionInFolderName,'Major/minor folder flag');
    metadataSame($frozen,serialize($row),'Input database fixture unchanged');
    if ($error!==JSON_ERROR_NONE) {
      metadataSame(NULL,$records[0]->metadataSettings,'Malformed remains native NULL, never empty object/array');
    }
  }
}
// Match the observed four selector records with absent optional metadata.
$rows=array();
for($i=0;$i<4;$i++)$rows[]=(object)array('name'=>'H5P.Optional'.$i,'title'=>'Optional','major_version'=>'1',
  'minor_version'=>'0','restricted'=>'0','tutorial_url'=>NULL,'metadata_settings'=>NULL);
$GLOBALS['h5p_test_select_handler']=function($query,$args)use($rows){return new MetadataRows($rows);};
metadataSame(4,count($storage->getLibraries()),'Four optional records decode without warnings/deprecations');
unset($GLOBALS['h5p_test_select_handler']);
$path='modules/h5peditor/h5peditor-backdrop-storage.class.inc';
$patch=json_decode(file_get_contents(H5P_TEST_ROOT.'/docs/h5p-api-1.28/metadata-null-patches.json'),TRUE)['files'][$path];
metadataSame($patch['beforeSha256'],hash('sha256',h5p_metadata_null_baseline_bytes($path,file_get_contents(H5P_TEST_ROOT.'/'.$path))),'Exact reversible adapter provenance');
echo 'PASS: '.$checks.' checks, both record paths, valid/scalar/null/empty/malformed JSON and four-NULL regression; no PHP warnings or deprecations.'.PHP_EOL;