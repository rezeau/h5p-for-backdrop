<?php
/**
 * Exercise the actual Backdrop asset builder without site bootstrap or writes.
 * Run each language/new-existing combination in its own PHP process.
 */
error_reporting(E_ALL);
set_error_handler(function($n,$m,$f,$l) { throw new ErrorException($m,0,$n,$f,$l); });
$root = dirname(__DIR__);
$code = $argv[1] ?? 'en';
$existing = ($argv[2] ?? 'new') === 'existing';
if (!in_array($code,array('en','fr'),TRUE)) { throw new RuntimeException('Invalid language'); }
$language = (object) array('langcode'=>$code);
$_SESSION = array();
define('JS_DEFAULT',0);
require $root.'/library/h5p.classes.php';

require $root.'/modules/h5peditor/h5peditor/h5peditor.class.php';
require $root.'/modules/h5peditor/h5peditor.module';
$GLOBALS['test_js']=array();
function base_path() { return '/'; }
function backdrop_get_path($type,$name) {
  global $root;
  if($type!=='module'||!in_array($name,array('h5p','h5peditor'),TRUE))throw new RuntimeException('Path lookup');
  return $name==='h5p'?$root:$root.'/modules/h5peditor';
}
function state_get($key,$default) { if($key!=='css_js_query_string')throw new RuntimeException('State lookup'); return 'frozen'; }
function h5p_add_core_assets() {
  global $root; $assets=array('js'=>array(),'css'=>array());
  foreach(H5PCore::$scripts as $p)$assets['js'][]='/'.$root.'/library/'.$p.'?frozen';
  foreach(H5PCore::$styles as $p)$assets['css'][]='/'.$root.'/library/'.$p.'?frozen';
  return $assets;
}
function h5p_add_files_and_settings($a,$b) { if($a!==TRUE||$b!==TRUE)throw new RuntimeException('Integration args'); }
function backdrop_add_js($data,$options=array()) { $GLOBALS['test_js'][]=array($data,$options); }
class AssetValidator {
  function getCopyrightSemantics() { return array(); }
  function getMetadataSemantics() { return array(); }
  function getOption($key,$default) { return $default; }
}
function _h5p_get_instance($name) { if(!in_array($name,array('contentvalidator','interface'),TRUE))throw new RuntimeException('Unexpected instance');return new AssetValidator(); }
class AssetFiles { function getDirectoryPath() { return 'files'; } }
function file_stream_wrapper_get_instance_by_uri($uri) { if($uri!=='public://')throw new RuntimeException('Unexpected files');return new AssetFiles(); }
function h5p_get_content_id($node) { return $node->vid??0; }
function _h5p_get_h5p_path() { return 'files/h5p'; }
function url($path) { global $code;return '/'.($code==='fr'?'fr/':'').$path; }
$node=(object)array();
if($existing){$node->vid=18;$node->json_content='{"params":{"answer":false},"metadata":{"defaultLanguage":"en"}}';}
$form=array('#node'=>$node);
$original=serialize($form);
$built=h5peditor_h5p_content_node_form_after_build($form);
if(serialize($built)!==$original)throw new RuntimeException('Builder mutated content');
$records=$GLOBALS['test_js'];
$settings=end($records)[0]['h5peditor'];
if($settings['uiLanguage']!==$code||$settings['language']!==$code)throw new RuntimeException('Language context');
if($settings['enableContentHub']!==FALSE)throw new RuntimeException('Hub unexpectedly enabled');
if($existing!==isset($settings['nodeVersionId']))throw new RuntimeException('New/existing revision context');
$assets=$settings['assets'];$seen=array();
foreach(array('js','css')as $kind)foreach($assets[$kind]as $url){
  $local=explode('?',substr($url,1))[0];
  if(!is_file($local))throw new RuntimeException('Missing asset '.$local);
  if(in_array($url,$seen,TRUE))throw new RuntimeException('Duplicate asset '.$url);
  $seen[]=$url;
}
$compat='/'.$root.'/modules/h5peditor/scripts/h5peditor-backdrop-compat.js?frozen&h5p_editor=73dfed6073b8-backdrop-1';
if(end($assets['js'])!==$compat)throw new RuntimeException('Compatibility must load last');
$joined=implode("\n",$assets['js']);
if(strpos($joined,'h5peditor-editor.js')!==FALSE)throw new RuntimeException('Creator inside iframe');
if(strpos($joined,'ckeditor/ckeditor.js')===FALSE||strpos($joined,'darkroom')!==FALSE||strpos($joined,'fabric.js')!==FALSE)throw new RuntimeException('Rich-text/image asset set');
if(($code==='fr')!==(strpos($joined,'ckeditor/translations/fr.js')!==FALSE))throw new RuntimeException('French translation loading');
if(strpos($joined,'js/h5p-tooltip.js')===FALSE||strpos(implode("\n",$assets['css']),'h5p-tooltip.css')===FALSE)throw new RuntimeException('Core tooltip missing');
foreach(array_slice($records,0,2)as $record)if($record[1]!==array('preprocess'=>FALSE,'version'=>'73dfed6073b8-backdrop-1'))throw new RuntimeException('Outer editor cache options');
if(count(H5peditor::$scripts)!==33||count(H5peditor::$styles)!==5)throw new RuntimeException('Upstream arrays changed');
echo 'PASS: '.$code.' '.$argv[2].'; actual asset builder, ordered local assets, language/revision settings, no content mutation'.PHP_EOL;