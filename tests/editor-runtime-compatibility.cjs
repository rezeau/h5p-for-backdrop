'use strict';
// Actual pinned source/configuration and adapter tests. No DOM, network or saves.
const fs=require('fs'),path=require('path'),vm=require('vm'),assert=require('assert'),crypto=require('crypto');
const root=path.resolve(__dirname,'..'),vendor=path.join(root,'modules/h5peditor/h5peditor');
const option=process.argv.find(a=>a.startsWith('--libraries='));
const libraries=option?path.resolve(option.slice(12)):path.join(__dirname,'fixtures/editor-html');
const lock=JSON.parse(fs.readFileSync(path.join(root,'docs/h5p-api-1.28/upstream-sources.lock.json')));
let checks=0;function yes(value){checks++;assert(value);}function same(a,b){checks++;assert.deepStrictEqual(a,b);}
for(const f of lock.editor.files){const b=fs.readFileSync(path.join(vendor,f.path));same(crypto.createHash('sha1').update(Buffer.from('blob '+b.length+'\0')).update(b).digest('hex'),f.gitBlob);}
const adapter=require(path.join(root,'modules/h5peditor/scripts/h5peditor-backdrop-compat.js'));
const fields=[];for(const dir of fs.readdirSync(libraries)){const p=path.join(libraries,dir,'semantics.json');if(!fs.existsSync(p))continue;const semantics=JSON.parse(fs.readFileSync(p));function walk(v){if(!v||typeof v!=='object')return;if(v.widget==='html')fields.push({library:dir,field:v});if(Array.isArray(v))v.forEach(walk);else Object.values(v).forEach(walk);}walk(semantics);}
yes(fields.length>0);
const build=fs.readFileSync(path.join(vendor,'ckeditor5/src/ckeditor.ts'),'utf8');
const plugins=build.match(/builtinPlugins\s*=\s*\[([\s\S]*?)\];/)[1].split(',').map(s=>s.trim()).filter(Boolean);
yes(build.includes('TableColumnResize'));yes(build.includes('TableCellProperties'));
const pkg=JSON.parse(fs.readFileSync(path.join(vendor,'ckeditor5/package.json')));
for(const [p,v]of Object.entries(pkg.dependencies)){if(p.startsWith('@ckeditor/'))same(v,'43.3.0');}
const ns={$:{merge:(a,b)=>a.concat(b),inArray:(v,a)=>a.indexOf(v)},widgets:{},t:(_c,k)=>k};
vm.runInNewContext(fs.readFileSync(path.join(vendor,'scripts/h5peditor-html.js'),'utf8'),{ns,H5PEditor:ns,H5PIntegration:{editor:{wysiwygButtons:[]}}});
const original=ns.Html.prototype.getCKEditorConfig;
same(adapter.installRichTextLanguage(ns),true);const installed=ns.Html.prototype.getCKEditorConfig;
same(adapter.installRichTextLanguage(ns),false);same(installed,ns.Html.prototype.getCKEditorConfig);
for(const ui of ['en','fr'])for(const content of ['en','fr'])for(const {library,field}of fields){
 ns.uiLanguage=ui;ns.defaultLanguage=content;const before=JSON.stringify(field);const html='<p>Été *answer* &amp; vrai</p>';
 const actualField=JSON.parse(before); const widget=new ns.Html({},actualField,html,()=>{throw Error('Unexpected content write');});
 const cfg=widget.getCKEditorConfig();
 same(cfg.language,{ui,content});same(widget.value,html);same(JSON.stringify(field),before);
 yes(cfg.plugins.filter(p=>typeof p==='string').every(p=>plugins.includes(p)));
 const expectedField=JSON.parse(before); const unwrapped=new ns.Html({},expectedField,html,()=>{}),expected=original.call(unwrapped);
 delete cfg.language;same(JSON.stringify(cfg),JSON.stringify(expected));same(actualField,expectedField);
}
delete ns.defaultLanguage;ns.uiLanguage='fr';ns.contentLanguage='en';same(new ns.Html({},fields[0].field,'',()=>{}).getCKEditorConfig().language,{ui:'fr',content:'en'});
ns.uiLanguage='unknown';delete ns.contentLanguage;same(new ns.Html({},fields[0].field,'',()=>{}).getCKEditorConfig().language,{ui:'en',content:'en'});
for(const invalid of [null,{}, {Html:function(){}}]){checks++;assert.throws(()=>adapter.installRichTextLanguage(invalid),/Load the H5P HTML widget/);}
const fr={window:{}};vm.runInNewContext(fs.readFileSync(path.join(vendor,'ckeditor/translations/fr.js'),'utf8'),fr);yes(Object.keys(fr.window.CKEDITOR_TRANSLATIONS.fr.dictionary).length>50);
// Group regression with jQuery 3's empty array parse result, null, text and HTML nodes.
const groupNs={widgets:{},$:{extend:Object.assign,parseHTML:()=>[]}};
function Dispatcher(){};Dispatcher.prototype={};
vm.runInNewContext(fs.readFileSync(path.join(vendor,'scripts/h5peditor-group.js'),'utf8'),{ns:groupNs,H5P:{EventDispatcher:Dispatcher}});
for(const [parsed,expected]of [[[], 'Translation settings'],[null,'Translation settings'],[{nodeValue:'Hello'},'Hello']]){
 const nodes=Array.isArray(parsed)||parsed===null?parsed:[parsed];groupNs.$.parseHTML=()=>nodes;let rendered,triggered;
 const object={field:{label:'Translation settings'},trigger:(event,value)=>{same(event,'summary');triggered=value;},$title:{text:v=>{rendered=v;}}};
 groupNs.Group.prototype.setSummary.call(object,'');same(rendered,expected);same(triggered,nodes&&nodes.length?nodes[0].nodeValue:undefined);
}
groupNs.getWidgetName=f=>f.widget;
let summary;
const group={children:[{field:{name:'question',widget:'html'},$input:{change:()=>{}}}],params:{question:'<p>Été <strong>question</strong></p>'},hasSingleChild:()=>false,isSubContent:()=>false,setSummary:v=>{summary=v;}};
groupNs.Group.prototype.findSummary.call(group);same(summary,'Été question');
const creator=fs.readFileSync(path.join(vendor,'scripts/h5peditor-editor.js'),'utf8');
yes(creator.indexOf('iframeLoaded.call(this.contentWindow)')<creator.indexOf('var LibrarySelector'));
const app=fs.readFileSync(path.join(root,'modules/h5peditor/scripts/application.js'),'utf8');
yes(app.includes('ns.uiLanguage = Backdrop.settings.h5peditor.uiLanguage;'));
const callback=app.match(/new ns\.Editor\(library, \$params\.val\(\), \$editor\[0\], (function \(\) \{[\s\S]*?\n          \})\);/)[1];
const calls=[];const frame={H5PEditor:ns,H5PBackdropEditorCompatibility:{installNestedLanguageUpdates:e=>{same(e,ns);calls.push('nested');},installRichTextLanguage:e=>{same(e,ns);calls.push('rich');}}};
vm.runInNewContext('('+callback+')').call(frame);same(calls,['nested','rich']);
// Local CSS dependency closure, with only two precisely recorded upstream omissions.
const missing=[];let refs=0;
function walk(dir){for(const n of fs.readdirSync(dir)){const p=path.join(dir,n);if(fs.statSync(p).isDirectory())walk(p);else if(p.endsWith('.css')){for(const m of fs.readFileSync(p,'utf8').matchAll(/url\(\s*['"]?([^'")]+)['"]?\s*\)/g)){const u=m[1].replace(/[?#].*$/,'');if(/^(data:|https?:|\/\/)/.test(u))continue;refs++;if(!fs.existsSync(path.resolve(path.dirname(p),u)))missing.push([path.relative(vendor,p).replace(/\\/g,'/'),m[1]]);}}}}
walk(vendor);same(missing.sort(),[['styles/css/cke-contents.css','../../ckeditor/contents.css'],['styles/css/h5p-hub-client.css','6df02be3700adb89fc38.svg']].sort());
const crop=fs.readdirSync(path.join(vendor,'images/cropper')).filter(n=>n.endsWith('.svg'));same(crop.length,5);
for(const p of ['libs/cropper.js','libs/cropper.css','libs/zebra_datepicker.min.js','styles/css/libs/zebra_datepicker.min.css'])yes(fs.existsSync(path.join(vendor,p)));
console.log('PASS: '+checks+' checks; 214 exact blobs, '+fields.length+' synthetic/caller-supplied HTML fields in four language combinations, group summary regression, explicit callback order and local asset closure ('+refs+' CSS references).');
console.log('Known upstream omissions: '+JSON.stringify(missing)+'; inactive CK4 stylesheet and disabled-Hub illustration.');
console.log('Limit: source/configuration tests, no ClassicEditor DOM initialization or saved HTML round trip.');
