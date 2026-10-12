<?php
/**
 * Actual submission/import/storage paths with memory-only database doubles.
 * No site bootstrap, credentials, live records or installed libraries.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/step2-provenance.php';
$checks=0;
function cmSame($expected,$actual,$message) {
  global $checks; $checks++;
  if(serialize($expected)!==serialize($actual))throw new RuntimeException($message);
}
function cmReject($call,$message) {
  global $checks; $checks++;
  try{$call();}catch(UnexpectedValueException $e){return;}
  throw new RuntimeException('Expected malformed-data rejection: '.$message);
}
class H5PEvent {
  function __construct(...$args) { $GLOBALS['cm_events'][]=$args; }
}
class CMQuery {
  private $table,$fields=array(),$conditions=array(),$action;
  function __construct($table,$action){$this->table=$table;$this->action=$action;}
  function fields($fields){$this->fields=$fields;return $this;}
  function condition($key,$value){$this->conditions[$key]=$value;return $this;}
  function execute(){$GLOBALS['cm_writes'][]=array('table'=>$this->table,'action'=>$this->action,'fields'=>$this->fields,'conditions'=>$this->conditions);return 1;}
}
function db_insert($table){if($table!=='h5p_nodes')throw new RuntimeException('Unexpected memory INSERT');return new CMQuery($table,'insert');}
function db_update($table){if(!in_array($table,array('h5p_nodes','h5p_content_user_data'),TRUE))throw new RuntimeException('Unexpected memory UPDATE');return new CMQuery($table,'update');}
function user_access($permission){if($permission!=='update h5p libraries')throw new RuntimeException('Unexpected permission');return FALSE;}
class CMResult {private $value;function __construct($value){$this->value=$value;}function fetchField(){return $this->value;}}
$GLOBALS['h5p_test_select_handler']=function($query,$args){
  if(strpos($query,'SELECT content_id FROM {h5p_nodes}')!==FALSE)return new CMResult($GLOBALS['cm_existing']?$args[':content_id']:FALSE);
  if(strpos($query,'SELECT library_id')!==FALSE&&strpos($query,'FROM {h5p_libraries}')!==FALSE)return new CMResult(42);
  throw new RuntimeException('Unexpected SELECT: '.$query);
};
$sandbox=new H5PTestSandbox();register_shutdown_function(function()use($sandbox){$sandbox->close();});
$GLOBALS['h5p_test_public_files']=$sandbox->directory('public');
$GLOBALS['h5p_test_config']['h5p.settings']=array('h5p_default_path'=>'h5p','h5p_export'=>FALSE,'h5p_revisioning'=>TRUE);
$language=(object)array('langcode'=>'en');$_SESSION=array();
$core=_h5p_get_instance('core');
$params=(object)array('question'=>'<p>Été &amp; vrai</p>','answer'=>FALSE,'zero'=>0);
$metadata=(object)array('title'=>'Titre français','authors'=>array((object)array('name'=>'Alice','role'=>'Author')),'license'=>'CC BY','defaultLanguage'=>'fr','changes'=>array((object)array('date'=>'2026-10-12','log'=>'Revision')),'yearFrom'=>0);
$original=serialize($metadata);
foreach(array(NULL,array(),(object)array())as$value)cmSame(array(),H5PBackdrop::normalizeContentMetadata($value),'Absent/empty structured values');
cmSame((array)$metadata,H5PBackdrop::normalizeContentMetadata($metadata),'Valid metadata preserves fields');
cmSame($original,serialize($metadata),'Metadata input unchanged');
foreach(array(FALSE,0,1,'','{"broken":',array('bad'))as$bad)cmReject(function()use($bad){H5PBackdrop::normalizeContentMetadata($bad);},'scalar/list metadata');
foreach(array('new','edit','null','empty','valid')as$mode){
  $payload=(object)array('params'=>$params);$prior=(object)array('license'=>'CC BY-SA','defaultLanguage'=>'fr');
  if($mode==='valid')$payload->metadata=$metadata;
  if($mode==='null')$payload->metadata=NULL;
  if($mode==='empty')$payload->metadata=(object)array();
  $form=array('#node'=>(object)($mode==='edit'||$mode==='null'?array('metadata'=>$prior):array()));
  $state=array('values'=>array('disable'=>0,'json_content'=>json_encode($payload)));
  _h5p_form_submit($form,$state);
  cmSame(serialize($params),serialize(json_decode($state['values']['json_content'])),'Parameters preserved: '.$mode);
  $expected=$mode==='valid'?(array)$metadata:($mode==='edit'||$mode==='null'?(array)$prior:array());
  cmSame($expected,$state['values']['metadata'],'Metadata optional/valid policy: '.$mode);
}
foreach(array(FALSE,'bad',array('bad'))as$bad){
  $state=array('values'=>array('disable'=>0,'json_content'=>json_encode(array('params'=>$params,'metadata'=>$bad))));
  $old=$state['values']['json_content'];
  cmReject(function()use(&$state){_h5p_form_submit(array('#node'=>(object)array()),$state);},'Malformed submitted metadata');
  cmSame($old,$state['values']['json_content'],'Malformed submission not unpacked/saved');
}
$state=array('values'=>array('disable'=>0,'json_content'=>'{"broken":','metadata'=>(array)$metadata));
_h5p_form_submit(array('#node'=>(object)array()),$state);
cmSame(FALSE,array_key_exists('json_content',$state['values']),'Existing invalid-JSON safeguard retains old parameters');
cmSame((array)$metadata,$state['values']['metadata'],'Invalid JSON does not erase valid metadata');
$state=array('values'=>array('disable'=>0,'json_content'=>'{"question":"not an envelope"}'));
cmReject(function()use(&$state){_h5p_form_submit(array('#node'=>(object)array()),$state);},'Malformed submission envelope');
$state=array('values'=>array('disable'=>0,'json_content'=>json_encode(array('params'=>$params)),'metadata'=>(array)$metadata));
_h5p_form_submit(array('#node'=>(object)array()),$state);
cmSame((array)$metadata,$state['values']['metadata'],'Already available metadata preserved');
$framework=new H5PBackdrop();
$base=array('id'=>77,'title'=>'Node title','params'=>json_encode($params),'library'=>array('libraryId'=>42,'machineName'=>'H5P.Fixture','majorVersion'=>1,'minorVersion'=>0),'disable'=>0);
foreach(array('missing','null','empty','valid')as$mode)foreach(array('insert','update')as$action){
  $content=$base;if($mode==='null')$content['metadata']=NULL;if($mode==='empty')$content['metadata']=array();if($mode==='valid')$content['metadata']=$metadata;
  $frozen=serialize($content);$GLOBALS['cm_writes']=array();$GLOBALS['cm_events']=array();$GLOBALS['cm_existing']=TRUE;
  $method=$action==='insert'?'insertContent':'updateContent';$framework->$method($content,9);
  cmSame(1,count($GLOBALS['cm_writes']),'One content write in memory');
  $fields=$GLOBALS['cm_writes'][0]['fields'];
  $expected=$action==='update'&&($mode==='missing'||$mode==='null')?array():H5PMetadata::toDBArray($mode==='valid'?$metadata:array(),FALSE);
  foreach($expected as$key=>$value)cmSame($value,$fields[$key],'Mapped valid/default field '.$key);
  if($action==='update'&&($mode==='missing'||$mode==='null'))foreach(array('authors','license','default_language','changes')as$key)cmSame(FALSE,array_key_exists($key,$fields),'Omission does not clear stored '.$key);
  cmSame($base['params'],$fields['json_content'],'Raw parameter bytes retained');
  cmSame($frozen,serialize($content),'Caller content unchanged');
}
foreach(array('insertContent','updateContent')as$method)foreach(array(FALSE,'{"broken":',array('bad'))as$bad){
  $GLOBALS['cm_writes']=array();$content=$base;$content['metadata']=$bad;
  cmReject(function()use($framework,$method,$content){$framework->$method($content,9);},'Malformed stored metadata');
  cmSame(array(),$GLOBALS['cm_writes'],'Malformed metadata rejected before writes');
}
// Run every upload hook through actual pinned Core storage and the adapter.
foreach(array('new','replace','revision')as$mode){
  $upload=$sandbox->directory('upload-'.$mode);mkdir($upload.'/content');
  $raw='{"question":"Été imported","answer":false}';file_put_contents($upload.'/content/content.json',$raw);
  $_SESSION=array('h5p_upload'=>'memory-fixture','h5p_upload_folder'=>$upload);
  $core->mainJsonData=array('title'=>'Titre importé','language'=>'fr','mainLibrary'=>'H5P.Fixture','preloadedDependencies'=>array(array('machineName'=>'H5P.Fixture','majorVersion'=>1,'minorVersion'=>0)),'authors'=>array(array('name'=>'Auteur','role'=>'Author')),'license'=>'CC BY','defaultLanguage'=>'fr','changes'=>array(array('log'=>'Original')),'yearFrom'=>2020);
  $header=serialize($core->mainJsonData);$GLOBALS['cm_writes']=array();$GLOBALS['cm_events']=array();$GLOBALS['cm_existing']=$mode==='replace';
  $GLOBALS['h5p_test_config']['h5p.settings']['h5p_revisioning']=$mode!=='replace';
  $node=(object)array('nid'=>101,'vid'=>201,'title'=>'Node title','disable'=>0,'revision'=>$mode==='revision');
  $id=h5p_get_content_id($node);
  if($mode==='new')h5p_insert($node);else h5p_update($node);
  $writes=array_values(array_filter($GLOBALS['cm_writes'],function($r){return $r['table']==='h5p_nodes';}));
  cmSame(1,count($writes),'One import content write: '.$mode);
  foreach(H5PMetadata::toDBArray($core->mainJsonData,FALSE)as$key=>$value)cmSame($value,$writes[0]['fields'][$key],'Imported metadata '.$mode.' '.$key);
  cmSame($raw,$writes[0]['fields']['json_content'],'Imported raw parameters unchanged');
  cmSame($raw,file_get_contents($GLOBALS['h5p_test_public_files'].'/h5p/content/'.$id.'/content.json'),'Only owned temporary storage receives unchanged fixture files');
  cmSame($header,serialize($core->mainJsonData),'Validated package header unchanged');
  cmSame($mode==='replace'?'update':'insert',$writes[0]['action'],'Correct import branch');
}
// Legacy form upload gets a title even without a metadata envelope.
$core->mainJsonData=array('title'=>'Titre du paquet');
$state=array('values'=>array('disable'=>0,'json_content'=>'{"params":{}}'));
_h5p_form_submit(array('#node'=>(object)array()),$state);
cmSame('Titre du paquet',$state['values']['metadata']['title'],'Legacy package title fallback');
cmSame('Titre du paquet',$state['values']['title'],'Legacy node title');
$_SESSION=array();$GLOBALS['cm_existing']=FALSE;$GLOBALS['cm_writes']=array();
// Missing metadata on a direct hook remains safe without inventing metadata.
$node=(object)array('nid'=>333,'vid'=>444,'title'=>'Node','json_content'=>'{}','embed_type'=>'div','disable'=>0,'main_library_id'=>42,'main_library'=>array('name'=>'H5P.Fixture','majorVersion'=>1,'minorVersion'=>0));
h5p_insert($node);
cmSame('insert',$GLOBALS['cm_writes'][0]['action'],'Direct hook with absent metadata');
$register=json_decode(file_get_contents(H5P_TEST_ROOT.'/docs/h5p-api-1.28/content-metadata-patches.json'),TRUE);
foreach($register['files']as$rel=>$patch)cmSame($patch['beforeSha256'],hash('sha256',h5p_content_metadata_baseline_bytes($rel,file_get_contents(H5P_TEST_ROOT.'/'.$rel))),'Exact reversible metadata patch');
echo 'PASS: '.$checks.' checks; actual submit/insert/update/import paths, optional and malformed metadata, preserved fields/parameters; memory-only database and owned temporary storage.'.PHP_EOL;
?>
