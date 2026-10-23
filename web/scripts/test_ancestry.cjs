const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
// Reuse the synthetic browser primitives from the discovery regression harness.
const harness=fs.readFileSync(path.join(__dirname,'test_discovery.cjs'),'utf8').split('const actual=')[0];
const scope={require,__dirname,console,fs,vm,assert,path,URL,Blob,setTimeout,clearTimeout};vm.createContext(scope);vm.runInContext(harness.replace("const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');",''),scope);
vm.runInContext("vm.runInContext(fs.readFileSync(path.join(root,'assets/dna-ancestry-story/ancestry-story.js'),'utf8'),context)",scope);
const run=code=>vm.runInContext('run('+JSON.stringify(code)+')',scope);
const el=id=>vm.runInContext('element('+JSON.stringify(id)+')',scope);
const put=value=>{scope.fixtureJson=JSON.stringify(value);vm.runInContext('context.storyFixture=JSON.parse(fixtureJson)',scope);};
let checks=0;function check(value,message){assert.ok(value,message);checks++;}
const groups=[{code:'GBR',label:'British study participants',region_code:'EUR',affinity_score:62,matched_markers:2500,chromosome_count:22},{code:'FIN',label:'Finnish study participants',region_code:'EUR',affinity_score:61,matched_markers:2500,chromosome_count:22},{code:'PJL',label:'Punjabi in Lahore Pakistan',region_code:'SAS',affinity_score:60,matched_markers:2500,chromosome_count:22}];
const fixture={origins:{headline:'Synthetic ancestry fixture',summary:'Synthetic values, not personal DNA.',modern:{headline:'Synthetic reference comparison',plain:'Synthetic comparison fixture',closest_region:{code:'EUR',label:'European reference'},closest_population:groups[0],comparison_tied:false,population_comparison_tied:false,broad_regions:[{code:'EUR',label:'Europe-related references',affinity_score:63,matched_markers:2500,chromosome_count:22,map_location:{lat:51,lon:14}},{code:'SAS',label:'South Asia-related references',affinity_score:58,matched_markers:2500,chromosome_count:22,map_location:{lat:23,lon:78}}],study_populations:groups,consumer_summary:{headline:'Europe is your closest broad comparison',plain:'Synthetic UI fixture only.',comparison_tied:false,population_labels:{GBR:{label:'Britain',detail:'Participants from England and Scotland'},FIN:{label:'Finland',detail:'Finnish study participants'},PJL:{label:'Punjab, Pakistan',detail:'Punjabi study participants from Lahore'}},requested_region_coverage:[{code:'PAKISTAN',label:'Pakistan',status:'direct_reference',plain:'Punjabi reference participants from Lahore.',region_code:'SAS',reference_codes:['PJL']},{code:'IRELAND',label:'Ireland',status:'broader_context',plain:'Broader European references do not separate Ireland.',region_code:'EUR',reference_codes:[]},{code:'ASHKENAZI',label:'Ashkenazi Jewish',status:'not_represented',plain:'No dedicated ancestry reference in this synthetic fixture.',reference_codes:[]}]}},ancient:{groups:[]},archaic:{categories:[]}}};
function show(value){put(value);run("rememberReport('# Synthetic ancestry preview',storyFixture,'full',null)");}
function html(){return el('origins-modern-content').innerHTML;}
show(fixture);
check(!html().includes('ancestry-donut'),'No donut without model percentages');
check(html().includes('Regional reference comparison')&&html().includes('Similarity score /100 · not ancestry percentages'),'Comparison-only result has explicit scored graphic');
check(html().includes('width:63%')&&html().includes('width:58%'),'Reference bar widths use exact affinity outof100 without normalization');
check(html().includes('Europe')&&html().includes('Britain'),'Plain leading region and country-style source label');
check(el('ancestry-region-populations').innerHTML.includes('Britain')&&!el('ancestry-region-populations').innerHTML.includes('Punjab, Pakistan'),'Selected Europe lists only Europe groups');
run('selectOriginsRegion(1)');
check(el('ancestry-region-populations').innerHTML.includes('Punjab, Pakistan')&&!el('ancestry-region-populations').innerHTML.includes('Britain'),'Selected SouthAsia lists its own groups');
check(el('ancestry-region-populations').innerHTML.includes('View comparison evidence'),'Numeric affinity tucked into disclosure');
check(el('ancestry-coverage-grid').innerHTML.includes('Not represented')&&!el('ancestry-coverage-grid').innerHTML.includes('0%'),'Absent reference does not become zero ancestry');
el('ancestry-coverage-search').listeners.input({target:{value:'Ashkenazi'}});
check(el('ancestry-coverage-grid').innerHTML.includes('Ashkenazi Jewish')&&!el('ancestry-coverage-grid').innerHTML.includes('Pakistan'),'Coverage search filters source status');
check(el('dna-quality-panel').open===false&&el('dna-sources-panel').open===false&&el('dna-full-evidence').open===false,'Raw metrics and Markdown collapsed for full report');
run("window.DnaAncestryStory.reveal('dna-full-evidence')");check(el('dna-full-evidence').open===true,'Technical report link opens disclosure');
const estimated=structuredClone(fixture);estimated.origins.modern.ancestry_estimate={available:true,status:'available',model_id:'SYNTHETIC-TEST-MODEL',components:[{code:'EUR',label:'Europe',percent:60,range_low:52,range_high:66},{code:'SAS',label:'South Asia',percent:40,range_low:34,range_high:48}],plain:'Synthetic chart values only.',range_label:'Synthetic sensitivity range'};
show(estimated);check(html().includes('ancestry-donut'),'Explicit valid100% model components render donut');
check(html().includes('60%')&&html().includes('40%'),'Exact returned components rendered');
check(el('origins-title').textContent==='Your ancestry estimate','Available estimate takes primary heading');
check(html().includes('Synthetic sensitivity range'),'Returned range label retained');
const invalidCases=[
  ['null row',e=>e.components[0]=null],['available false',e=>e.available=false],['sum99',e=>e.components[1].percent=39],['negative component',e=>{e.components[0].percent=101;e.components[1].percent=-1;}],['numeric string',e=>e.components[0].percent='60'],['null component',e=>e.components[0].percent=null],['duplicate code',e=>e.components[1].code='EUR'],['empty components',e=>e.components=[]],['chart gate false',e=>e.chart_eligible=false],['sum99.999',e=>e.components[1].percent=39.999]
];
for(const [name,change]of invalidCases){const r=structuredClone(estimated);change(r.origins.modern.ancestry_estimate);show(r);check(!html().includes('ancestry-donut'),'Invalid model not charted: '+name);}
const zero=structuredClone(estimated);zero.origins.modern.ancestry_estimate.components=[{code:'EUR',label:'Europe',percent:100},{code:'SAS',label:'South Asia',percent:0}];show(zero);check(html().includes('ancestry-donut')&&html().includes('South Asia, 0%'),'Explicit modelzero remains actualzero');
const tiny=structuredClone(estimated);tiny.origins.modern.ancestry_estimate.components=[{code:'EUR',label:'Europe',percent:99.999},{code:'SAS',label:'South Asia',percent:.001}];show(tiny);check(html().includes('&lt;0.01%')&&!html().includes('South Asia, 0%'),'Positive small percentage is never rounded to zero');
const tie=structuredClone(fixture);tie.origins.modern.comparison_tied=true;tie.origins.modern.consumer_summary.comparison_tied=true;tie.origins.modern.tied_top_regions=tie.origins.modern.broad_regions;show(tie);check(html().includes('Your regional comparisons overlap')&&!html().includes('Closest named study group'),'Tie suppresses arbitrary closest winner');
show(estimated);run('originsGuideMode=true;window.DnaAncestryStory.render()');check(!html().includes('ancestry-donut'),'Guide never shows personal percentage chart');run('originsGuideMode=false');
const extra=structuredClone(fixture);extra.origins.modern.regional_refs={installed:true,status:'available',headline:'Additional synthetic references',plain:'Separate source comparisons',comparison_tied:true,closest_group:{code:'A',label:'False unique winner'},groups:[{code:'A',label:'Synthetic Ashkenazi reference',similarity_score:62,sample_count:20,matched_markers:2000,chromosome_count:22},{code:'B',label:'Synthetic Egyptian reference',similarity_score:62,sample_count:20,matched_markers:2000,chromosome_count:22}]};show(extra);check(html().includes('Additional synthetic references')&&html().includes('62.0 /100 allele-sharing score'),'Additional source scores kept separate');check(!html().includes('False unique winner'),'Additional-panel tie suppresses unique lead');check(!html().includes('ancestry-donut'),'Additional affinities never become donut percentages');
el('ancestry-coverage-search').listeners.input({target:{value:''}});const many=structuredClone(fixture);many.origins.modern.consumer_summary.requested_region_coverage.push(...Array.from({length:7},(_,i)=>({code:'X'+i,label:'Extra region '+i,status:'not_represented',plain:'Synthetic source coverage'})));show(many);
check(el('ancestry-coverage-grid').innerHTML.match(/<article/g).length===4,'Coverage starts with compact four cards');
el('ancestry-coverage-expand').listeners.click();check(el('ancestry-coverage-grid').innerHTML.match(/<article/g).length===10,'Show all reveals every coverage entry');
el('ancestry-coverage-search').listeners.input({target:{value:'Extra region 6'}});check(el('ancestry-coverage-grid').innerHTML.includes('Extra region 6'),'Search reaches entries beyond collapsed preview');
run("activateOriginsTab('ancient')");check(el('origins-mode').textContent==='Your ancient reference comparisons','Ancient tab presents its own chapter heading');
run("activateOriginsTab('modern')");check(el('origins-mode').textContent==='Your ancestry overview','Return to modern restores ancestry heading');
const malformedBars=structuredClone(fixture);malformedBars.origins.modern.broad_regions[0].affinity_score=101;malformedBars.origins.modern.broad_regions[1].affinity_score=null;show(malformedBars);check(!html().includes('width:101%')&&html().includes('Not assessed'),'Malformed bar values show unavailable ratherthanzero or overflow');
const actualPath=path.join(__dirname,'../tests/synthetic-ancestry-story-report.json');if(fs.existsSync(actualPath)){
const actualFull=JSON.parse(fs.readFileSync(actualPath,'utf8').replace(/^\uFEFF/,''));el('ancestry-coverage-search').listeners.input({target:{value:''}});show(actualFull);
check(!html().includes('ancestry-donut'),'Final actual engine result never claims unsupported percentages');
check((html().match(/class="ancestry-comparison-row(?: active)?"/g)||[]).length===5,'Final actual engine hasfive regional score rows');
check((html().match(/data-regional-code=/g)||[]).length===10,'Final actual extra reference panel hasall10source groups');
check(html().includes('not a personal-origin ranking')&&!html().includes('Closest comparison in this additional panel'),'Final extra panel clearlywithholds personal-origin ranking');
el('ancestry-coverage-search').listeners.input({target:{value:'Ashkenazi'}});check(el('ancestry-coverage-grid').innerHTML.includes('data-story-reference="ASH"'),'Final actual Ashkenazi coverage targets exactASHsource card');
}
check(!el('dna-agent-consent').checked,'Rendering never opts into DNA Agent');
console.log(`PASS: ${checks} ancestry UI checks: strict percentage gating, no affinity normalization, truezero, ties, region drilldown, coverage search, disclosure defaults, extra-panel separation and Agent consent.`);
