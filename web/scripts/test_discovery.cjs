const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
const root=path.resolve(__dirname,'..'),elements=new Map(),exportsMade=[],drawnText=[],geometry=[];
const contexts=new Map();
function element(id){
  if(!elements.has(id))elements.set(id,{id,value:'',checked:false,disabled:false,hidden:id.endsWith('-studio'),innerHTML:'',textContent:'',files:[],width:960,height:1080,style:{setProperty(){}},classList:{toggle(){},add(){},remove(){}},listeners:{},setAttribute(k,v){this[k]=v},addEventListener(k,v){this.listeners[k]=v},querySelectorAll(){return []},querySelector(){return element('fake-heading')},appendChild(){},insertAdjacentHTML(position,html){this.innerHTML+=html},insertAdjacentElement(){},remove(){},getBoundingClientRect(){return{width:800,height:400}},scrollIntoView(){},focus(){},click(){},getContext(){
    if(!contexts.has(id))contexts.set(id,new Proxy({canvas:this,createLinearGradient:()=>({addColorStop(){}}),createRadialGradient:()=>({addColorStop(){}}),fillText:text=>drawnText.push(text)}, {get:(o,k)=>o[k]||(()=>{}),set:(o,k,v)=>(o[k]=v,true)}));return contexts.get(id);
  },toBlob(cb,type){cb(new Blob(['synthetic-canvas'],{type}))},toDataURL(){return 'data:image/png;synthetic'}});
  return elements.get(id);
}
const actions=[element('full-button'),element('discovery-sound-generate')];let sourceStarts=[],sourceStops=0;
class AudioContext{
  constructor(){this.state='running';this.currentTime=0;this.destination={};}
  resume(){this.state='running';return Promise.resolve();}
  createBuffer(channels,frames,sampleRate){const data=Array.from({length:channels},()=>new Float32Array(frames));return{numberOfChannels:channels,length:frames,sampleRate,duration:frames/sampleRate,getChannelData:i=>data[i]};}
  createBufferSource(){return{connect(){return{connect(){}}},disconnect(){},start(_,offset){sourceStarts.push(offset)},stop(){sourceStops++},onended:null};}
  createGain(){return{gain:{value:0,setTargetAtTime(v){this.value=v}},disconnect(){}};}
}
class LocalURL extends URL{static createObjectURL(blob){exportsMade.push(blob);return 'blob:synthetic-test'}static revokeObjectURL(){}}
const window={matchMedia:()=>({matches:false,addEventListener(){}}),addEventListener(){},AudioContext};
const context=vm.createContext({window,document:{getElementById:element,createElement:tag=>element('created-'+tag+'-'+elements.size),querySelector(){return null},querySelectorAll:selector=>selector==='.dna-action'?actions:[],addEventListener(){},hidden:false,documentElement:element('html'),body:element('body')},
  Path2D:class{constructor(data){geometry.push(data)}},requestAnimationFrame:()=>1,cancelAnimationFrame(){},performance:{now:()=>1000},setTimeout:(cb,ms)=>ms<500?setTimeout(cb,0):0,clearTimeout,
  console,URL:LocalURL,Blob,Map,Float32Array,FormData:class{},fetch:async()=>({ok:true,text:async()=>'{"providers":{}}'}),alert(){},navigator:{clipboard:{writeText:async()=>{}}}});
vm.runInContext(fs.readFileSync(path.join(root,'dna.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1].replace(/<\?php[\s\S]*?\?>/g, '"synthetic-test-csrf"'),context);
const discoverySource=fs.existsSync(path.join(root,'assets/dna-discovery/discovery.js'))?path.join(root,'assets/dna-discovery/discovery.js'):path.join(root,'../_dna_discovery_upgrade_20260905/assets/dna-discovery/discovery.js');
vm.runInContext(fs.readFileSync(discoverySource,'utf8'),context);
const run=code=>vm.runInContext(code,context),api=window.DnaDiscovery;
const actual=JSON.parse(fs.readFileSync(path.join(root,'tests/synthetic-chromosome-origins-preview.json'),'utf8'));
const report={...actual,summary:{marker_count:5000,file_name:'DO_NOT_EXPORT_PRIVATE_FILE',file_hash:'DO_NOT_EXPORT_PRIVATE_HASH'},sound_profile:{seed_hash:'DO_NOT_EXPORT_SOUND_SEED',duration_seconds:12,events:[{time:1,frequency:220,duration:.5,gain:.05,pan:0}]},appearance:{plain:'Synthetic marker evidence only',eye_color:{observed_marker_count:1,required_marker_count:6,prediction_available:false,model_validated:false,color:null,markers:[{gene:'HERC2',rsid:'DO_NOT_EXPORT_RSID',status:'observed',label:'Source marker observed'}],research_context:{observation:'One source allele observed',summary:'Association only',limitation:'Does not predict an eye color',source_name:'Synthetic association',source_url:'https://example.com/source'},limitations:['No model installed']}}};
context.testReport=report;let checks=0;const check=(condition,message)=>{assert.ok(condition,message);checks++;};
(async()=>{
  check(element('discovery-sound-play').disabled,'Empty sound profile cannot play');
  check(element('portrait-trait-evidence').innerHTML.includes('neutral illustration'),'Empty traits explained neutrally');
  run("rememberReport('# synthetic full report',testReport,'full',{safe:true})");
  check(!element('discovery-sound-play').disabled,'Full report sound immediately available');
  check(sourceStarts.length===0,'Full report never autoplays');
  check(element('discovery-chromosome-content').innerHTML.match(/data-chromosome=/g).length===132,'Actual engine six groups by all22 chromosomes rendered');
  check(element('discovery-chromosome-content').innerHTML.includes('No unique lead'),'Actual all-heterozygote fixture correctly shows no unique lead');
  check(element('discovery-chromosome-content').innerHTML.includes('No chromosome')===false,'Actual stability explanation present');
  check(element('portrait-trait-evidence').innerHTML.includes('One source allele observed'),'Observed association rendered separately');
  check(element('portrait-trait-evidence').innerHTML.includes('Neutral eyes'),'Association does not set portrait eyes');
  run("runDnaSound()");check(run('lastReport')==='# synthetic full report'&&run('lastAgentContext.safe')===true,'Cached sound retains full report and agent context');
  api.open('ancient');check(!element('dna-chromosome-studio').hidden,'Ancient entry opens measured explorer');
  api.open('portrait');check(!element('dna-portrait-studio').hidden,'Portrait opens');
  const fixedGeometry=geometry.slice(0,9).join('|');
  api.configure('eye','amber');api.configure('palette','ember');api.shuffle();
  check(geometry.slice(0,9).join('|')===fixedGeometry,'Face paths remain fixed');
  api.exportSvg();const svg=await exportsMade.at(-1).text();
  check(svg.includes('DNA-inspired art · not an appearance prediction'),'SVG retains visible artistic caption');
  check(svg.includes('Manually chosen palette'),'SVG labels manual palette');
  check(!/DO_NOT_EXPORT|One source allele|genotype/i.test(svg),'SVG excludes fileidentity seed variant markers and observed details');
  check(!/<image|<script|https?:\/\/[^w]/.test(svg),'SVG uses local code only');
  api.exportPng();check(drawnText.includes('DNA-inspired art · not an appearance prediction'),'Canvas/PNG visible caption');
  await api.exportAnimation();check(element('portrait-export-note').textContent.includes('cannot record WebM'),'Unsupported WebM has clear fallback');
  await api.playSound();check(sourceStarts.length===1&&sourceStarts[0]===0,'Play synthesizes and starts actual audio');
  run('dnaAudioCtx.currentTime=3');await api.playSound();check(element('discovery-sound-time').textContent.startsWith('0:03'),'Pause uses actual AudioContext clock');
  api.seekSound(500);check(element('discovery-sound-time').textContent.startsWith('0:06'),'Seek matches actual duration');
  await api.playSound();check(sourceStarts.at(-1)===6,'Resume starts selected offset');
  await api.downloadSound();const wav=exportsMade.at(-1);check(wav.type==='audio/wav'&&wav.size===12*44100*2*2+44,'WAV is exact existing synth stereo PCM duration');
  const wavBytes=new Uint8Array(await wav.arrayBuffer());check(String.fromCharCode(...wavBytes.slice(0,4))==='RIFF','WAV RIFF header valid');
  api.stopSound();check(element('discovery-sound-time').textContent.startsWith('0:00'),'Stop resets transport');
  await api.playSound();run("updateDnaFileStatus({name:'new-synthetic.txt',size:100})");
  check(run('lastDnaSoundProfile')===null&&run('lastDnaAudioBuffer')===null,'File change drops previous sound profile and buffer');
  check(element('discovery-sound-play').disabled,'New file disables old audio playback');
  check(element('portrait-mode').textContent==='Neutral artwork','New file clears report-driven artwork label');
  check(!element('portrait-trait-evidence').innerHTML.includes('One source allele observed'),'New file removes previous targeted trait observations');
  check(!element('discovery-chromosome-content').innerHTML.includes('data-chromosome='),'New file removes previous chromosome comparisons');
  check(sourceStops>=3,'Sources are stopped on pause seek and invalidation');
  run("rememberReport('# synthetic ancient',testReport,'ancient',null)");
  check(element('origins-panel-modern').hidden&& !element('origins-panel-ancient').hidden,'Focused ancient result opens ancient tab');
  element('declared-build').listeners.change();check(element('discovery-sound-play').disabled,'Build setting change invalidates creative evidence');
  run('dnaMotionPaused=true');api.motionChanged();check(element('portrait-motion').disabled,'Page motion pause applies to portrait');
  api.exportSvg();const neutral=await exportsMade.at(-1).text();check(!neutral.includes('DO_NOT_EXPORT'),'Export after invalidation retains privacy');
  run("rememberReport('# refreshed synthetic full',testReport,'full',{safe:true})");
  run("dnaAudioCtx.state='suspended'");const before=sourceStarts.length;
  await Promise.all([api.playSound(),api.playSound()]);
  check(sourceStarts.length===before+1,'Rapid double Play during suspended context starts exactly one source');
  run("rememberReport('# lookup only',null,'clinvar',null)");
  check(element('discovery-sound-play').disabled&&element('portrait-mode').textContent==='Neutral artwork','Lookup-only null report invalidates earlier creative context');
  const combinedPath=path.join(root,'tests/synthetic-discovery-full-report.json');
  if(fs.existsSync(combinedPath)){
    context.combinedReport=JSON.parse(fs.readFileSync(combinedPath,'utf8').replace(/^\uFEFF/,''));
    run("rememberReport('# actual engine synthetic full',combinedReport,'full',null)");
    check(element('discovery-sound-time').textContent==='0:00 / 0:42','Actual full engine42-second composition attaches automatically');
    check(element('portrait-trait-evidence').innerHTML.includes('0 / 6'),'Actual engine correctly displays zero observed trait loci');
    const actualStarts=sourceStarts.length;check(run('lastDnaAudioBuffer')===null,'New actual profile invalidates prior synthesized audio');
    await api.playSound();
    check(sourceStarts.length===actualStarts+1&&run('lastDnaAudioBuffer.duration')===42,'Actual120-event profile synthesizes full42-second buffer');
    const peak=run("lastDnaAudioBuffer.getChannelData(0).reduce((p,n)=>Math.max(p,Math.abs(n)),0)");
    check(Number.isFinite(peak)&&peak>0&&peak<=.951,'Actual full engine audio samples finite nonzero bounded');
    api.stopSound();
  }
  console.log(`PASS: ${checks} discovery checks: actual22-chromosome contract, fixed neutral portrait, private captioned exports, cached sound, actual transport/WAV, file/build invalidation, ancient default tab, motion controls.`);
})().catch(error=>{console.error(error);process.exitCode=1});
