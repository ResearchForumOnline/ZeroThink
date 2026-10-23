/* A guided customer report. All personal conclusions come from the analysis response. */
(() => {
  'use strict';
  const $=id=>document.getElementById(id);
  const text=v=>typeof v==='string'?v:typeof v==='number'?String(v):'';
  const esc=v=>text(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const rows=v=>Array.isArray(v)?v.filter(row=>row&&typeof row==='object'):[];
  const finite=v=>typeof v==='number'&&Number.isFinite(v);
  const number=v=>finite(v)?v.toLocaleString('en-GB',{maximumFractionDigits:1}):'Not assessed';
  const chapterLabels={overview:'Overview',origins:'Origins',ancient:'Ancient world',archaic:'Archaic DNA',traits:'Everyday traits',sound:'DNA Sound',health:'Health research'};
  const regionNames={AFR:'Africa',EUR:'Europe',SAS:'South Asia',EAS:'East Asia',AMR:'American study groups',MEN:'Middle East & North Africa',OCN:'Oceania',MIX:'Mixed reference context'};
  const state={report:null,kind:'',chapter:'overview',stale:false,summary:null};
  const origin=report=>report?.origins||report?.heritage?.origins||{};
  const safeUrl=value=>{try{const u=new URL(text(value));return ['https:','http:'].includes(u.protocol)?u.href:'';}catch{return '';}};
  const link=(url,label)=>safeUrl(url)?`<a href="${esc(safeUrl(url))}" target="_blank" rel="noopener noreferrer">${esc(label||'Published source')} ↗</a>`:esc(label||'Source metadata not supplied');
  const statusLabel=status=>status==='ready'?'Ready to explore':status==='limited'?'Partial result':'Needs input';
  const validChapter=value=>Object.hasOwn(chapterLabels,value)||['quality','sources','evidence','input','portrait','export','lineage'].includes(value)?value:'overview';
  const scroll=node=>{node?.scrollIntoView({behavior:typeof dnaMotionAllowed==='function'&&dnaMotionAllowed()?'smooth':'instant',block:'start'});};
  function focus(node){if(!node)return;node.setAttribute('tabindex','-1');node.focus({preventScroll:true});scroll(node);}
  function classification(report=state.report){const c=origin(report).classification;return c?.available===true&&c.broad&&typeof c.broad.label==='string'?c:null;}
  const spark=chapter=>{
    const paths={ancient:'M0 28L35 28L60 16L85 28L110 10L140 28L175 20L205 28L250 28',archaic:'M0 18Q25 -5 50 18T100 18T150 18T200 18T250 18M0 18Q25 41 50 18T100 18T150 18T200 18T250 18',traits:'M0 25H24L34 13L48 29L67 5L84 30L100 19H124L138 9L153 29L168 13L184 24H250',sound:'M0 18H25L30 7L36 28L43 12L51 25L59 4L67 32L75 10L83 24L91 14L99 22H115L125 2L133 32L141 5L149 29L157 14L165 21H250',health:'M0 20H70L85 4L99 32L112 15L124 20H250'};
    return `<svg class="customer-spark" viewBox="0 0 250 36" aria-hidden="true"><path d="${paths[chapter]||paths.archaic}" stroke="currentColor" fill="none" stroke-width="1.4"/></svg>`;
  };
  const orbit=()=>'<svg class="customer-orbit" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="41" fill="none" stroke="currentColor" stroke-opacity=".25"/><ellipse cx="50" cy="50" rx="19" ry="41" fill="none" stroke="currentColor" transform="rotate(40 50 50)"/><ellipse cx="50" cy="50" rx="19" ry="41" fill="none" stroke="currentColor" transform="rotate(-40 50 50)"/><circle class="orbit-core" cx="50" cy="50" r="5"/><circle class="orbit-core" cx="16" cy="27" r="3"/></svg>';
  function fallbackSummary(report){
    const o=origin(report),modern=o.modern||{},ancient=o.ancient||{},archaic=o.archaic||{},trait=report?.personal_traits,profile=report?.sound_profile;
    const findings=[];
    if(o.modern)findings.push({id:'origins',title:'Your regional picture',result:modern.consumer_summary?.headline||modern.headline||'Your regional comparison',meaning:modern.consumer_summary?.plain||modern.plain||'Explore the reference groups your file could compare.',status:rows(modern.broad_regions).length?'limited':'needs_input',action:{chapter:'origins',label:'Explore origins'}});
    if(o.ancient)findings.push({id:'ancient',title:'Ancient world',result:ancient.headline||'Ancient reference comparisons',meaning:ancient.plain||'Explore the sampled ancient communities.',status:Number(ancient.common_marker_count)>0?'limited':'needs_input',action:{chapter:'ancient',label:'Open the ancient chapter'}});
    if(o.archaic)findings.push({id:'archaic',title:'Archaic DNA',result:archaic.headline||'Neanderthal & Denisovan markers',meaning:archaic.plain||'See observed associated markers and how many your file could test.',status:rows(archaic.categories).some(r=>r.callable_tag_count>0)?'ready':'needs_input',action:{chapter:'archaic',label:'See both marker screens'}});
    if(trait)findings.push({id:'traits',title:'Everyday traits',result:rows(trait.cards).filter(c=>c.status==='observed').length+' trait observations',meaning:'Explore the reported evidence for earwax, bitter taste and adult lactose digestion.',status:trait.status||'limited',action:{chapter:'traits',label:'Explore your traits'}});
    if(profile)findings.push({id:'sound',title:'DNA Sound',result:number(profile.duration_seconds)+' seconds, composed from your file',meaning:'Listen to the artistic sound mapping, then save a WAV. This composition is not a biological measurement.',status:'ready',action:{chapter:'sound',label:'Open your sound player'}});
    return{headline:'Your DNA, a chapter at a time',intro:'Start with these findings, then open the parts you want to explore.',findings,next_steps:[]};
  }
  function summaryFor(report){const s=report?.customer_summary||report?.customer_report;return s&&Array.isArray(s.findings)?s:fallbackSummary(report);}
  function summaryRows(){return rows(state.summary?.findings).slice(0,10).map(row=>({...row,status:['ready','limited','needs_input'].includes(row.status)?row.status:'limited',chapter:validChapter(row.action?.chapter||row.id)}));}
  function miniBars(report){
    if(classification(report))return '';
    const data=rows(origin(report).modern?.broad_regions).filter(r=>finite(r.affinity_score)&&r.affinity_score>=0&&r.affinity_score<=100);
    if(!data.length)return '';
    return `<div class="customer-mini-bars" role="img" aria-label="Regional reference similarity scores, not ancestry percentages: ${esc(data.map(r=>`${regionNames[r.code]||r.label} ${number(r.affinity_score)} out of 100`).join('; '))}">${data.map(r=>`<span><i style="height:${r.affinity_score}%"></i><b>${esc(r.code)}</b></span>`).join('')}</div><div class="customer-mini-caption">Regional similarity /100 · not ancestry percentages</div>`;
  }
  function renderOverview(){
    const s=state.summary,findings=summaryRows();
    $('customer-overview').innerHTML=`<div class="customer-welcome"><div><p class="eyebrow">YOUR RESULTS · START HERE</p><h3 id="customer-title" tabindex="-1">${esc(s.headline||'Your DNA story is ready to explore')}</h3><p>${esc(s.intro||'A few clear findings, with each chapter ready when you want to look closer.')}</p></div>${orbit()}</div><div class="customer-findings">${findings.map(row=>`<article class="customer-card" data-chapter="${row.chapter}" data-status="${row.status}"><div class="customer-card-top"><h4>${esc(row.title||chapterLabels[row.chapter]||'Report finding')}</h4><span class="customer-status">${statusLabel(row.status)}</span></div>${spark(row.chapter)}<b class="customer-card-result">${esc(row.result||'Open this chapter to review the result')}</b><p>${esc(row.meaning||'')}</p>${row.chapter==='origins'?miniBars(state.report):''}<button type="button" data-customer-open="${row.chapter}"${row.chapter==='sound'&&/^play\b/i.test(row.action?.label||'')?' data-customer-play="true"':''}>${esc(row.action?.label||'Explore this chapter')} <span aria-hidden="true">→</span></button></article>`).join('')}</div>${findings.length?'':`<div class="customer-input-help"><h4>This report has a focused result</h4><p>Open the full evidence for this analysis, or run Discover My DNA Story for the complete overview.</p><button type="button" class="btn" data-customer-open="evidence">Read the focused report</button></div>`}${rows(s.next_steps).length?`<section class="customer-next"><div><h4>Where to go next</h4><p>Choose the next chapter that interests you.</p></div><div class="customer-next-list">${rows(s.next_steps).slice(0,3).map(step=>`<button type="button" data-customer-open="${validChapter(step.chapter)}"><span><b>${esc(step.label)}</b><small>${esc(step.detail)}</small></span><span aria-hidden="true">→</span></button>`).join('')}</div></section>`:''}`;
    bind($('customer-overview'));
  }
  function renderNav(){
    const present=new Set(summaryRows().map(r=>r.chapter));
    if(origin(state.report).modern)present.add('origins');if(origin(state.report).ancient)present.add('ancient');if(origin(state.report).archaic)present.add('archaic');
    if(state.report?.personal_traits)present.add('traits');if(state.report?.sound_profile)present.add('sound');
    const chapters=['overview','origins','ancient','archaic','traits','sound','health'].filter(key=>key==='overview'||present.has(key));
    $('customer-nav').innerHTML=chapters.map(key=>`<button type="button" data-customer-open="${key}" aria-pressed="${state.chapter===key}">${esc(chapterLabels[key])}${key==='overview'?'':'<span class="customer-dot" aria-hidden="true"></span>'}</button>`).join('');bind($('customer-nav'));
  }
  function bind(node){node?.querySelectorAll('[data-customer-open]').forEach(button=>button.addEventListener('click',()=>{open(button.getAttribute('data-customer-open'));if(button.getAttribute('data-customer-play')==='true'&&!state.stale)window.DnaDiscovery?.playSound();}));}
  const traitIcon=id=>`<svg class="customer-trait-symbol" viewBox="0 0 48 48" aria-hidden="true"><path d="${id==='earwax'?'M16 34C16 42 25 44 29 35L30 29C42 22 37 7 26 6C13 4 8 19 14 24M21 28C29 28 34 19 27 14C19 10 16 21 21 22':id==='bitter_taste'?'M16 8V20C16 27 32 27 32 20V8M24 8V19M24 27V42M8 8V19M40 8V19':'M18 5H30V11L35 19V41H13V19L18 11ZM13 23H35M19 29H29M19 34H26'}" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
  function traitCard(card,print=false){
    const needsValidation=card.status==='needs_validation'||card.status==='invalid',observed=card.status==='observed',partial=!needsValidation&&(card.status==='ambiguous'||(Number(card.evidence?.observed_markers)>0&&!observed));
    const label=needsValidation?'Needs validation':observed?'Marker evidence observed':partial?'Partial marker evidence':'Needs marker evidence';
    const sources=rows(card.sources),limits=Array.isArray(card.limitations)?card.limitations.filter(v=>typeof v==='string'):[];
    return `<article class="customer-trait-card" data-status="${observed?'ready':partial?'limited':'needs_input'}">${print?'':traitIcon(card.id||card.code)}<span class="customer-status">${label}</span><h${print?3:4}>${esc(card.title||'Everyday trait')}</h${print?3:4}><p class="customer-trait-question">${esc(card.question||'')}</p><p class="customer-trait-finding">${esc(card.result||card.finding||'This file did not provide the complete marker evidence.')}</p><p>${esc(card.meaning||card.what_it_means||'')}</p>${card.does_not_mean?`<p class="customer-trait-limit">${esc(card.does_not_mean)}</p>`:''}${!observed?'<div class="customer-trait-missing">An untested or unclear marker is not a negative trait result. Open the evidence to see what your file could check.</div>':''}<details${print?' open':''}><summary>Evidence &amp; sources</summary><p>${number(card.evidence?.observed_markers)} of ${number(card.evidence?.required_markers)} required markers observed${card.confidence_label?' · '+esc(card.confidence_label):''}.</p>${limits.length?`<ul>${limits.map(v=>`<li>${esc(v)}</li>`).join('')}</ul>`:''}${sources.map(source=>`<p>${link(source.url,source.title)}${source.year?' ('+esc(source.year)+')':''}</p>`).join('')}${!print?rows(card.markers).map(m=>`<p>${esc(m.gene)} · ${link(m.source_url,m.rsid)}<br>${esc(m.label||m.status)}</p>`).join(''):''}</details></article>`;
  }
  function renderTraits(){
    const traits=state.report?.personal_traits;
    $('customer-traits').innerHTML=`<header class="customer-section-head"><p class="eyebrow">EVERYDAY TRAITS · THE DETAILS THAT FEEL FAMILIAR</p><h3 id="customer-traits-title" tabindex="-1">Small variations. Everyday experiences.</h3><p>Explore what the checked markers say about earwax, bitter taste and adult lactose digestion. Each card separates the observed result from what it can mean in daily life.</p></header><div class="customer-trait-grid">${rows(traits?.cards).map(c=>traitCard(c)).join('')}</div>${traits?.boundary?`<p class="origin-explainer">${esc(traits.boundary)}</p>`:''}`;
  }
  function modelMarkup(model,print=false){
    if(!model)return '';
    return `<article class="customer-model-card"><p class="eyebrow">PUBLISHED POPULATION-STRUCTURE MODEL</p><h3 tabindex="-1">${esc(model.headline||model.broad.label)}</h3><p>${esc(model.plain)}</p><div class="customer-model-meta"><span>${esc(model.broad.label)}</span>${model.regional?.label?`<span>${esc(model.regional.label)}</span>`:''}<span>${number(model.matched_markers)} compared markers</span></div><details${print?' open':''}><summary>What this classification means</summary><p>${esc(model.boundary||'A reference-model classification is separate from nationality, cultural identity or an ancestry percentage.')}</p><p>${esc(model.method)}${model.version?' · '+esc(model.version):''}</p><p>${link(model.source_url,'Read the published model source')}</p><p>Consumer confidence probabilities are not supplied for this classification.</p></details></article>`;
  }
  function decorateReferences(){
    const host=$('origins-modern-content');if(!host||typeof originsGuideMode!=='undefined'&&originsGuideMode)return;
    const activeReport=typeof lastReportData!=='undefined'?lastReportData:state.report,c=classification(activeReport),hasComposition=window.DnaCountry?.hasComposition(activeReport);
    if(hasComposition){const note=host.querySelector('.ancestry-estimate-card .ancestry-notice'),boundary=host.querySelector('.ancestry-estimate-card .ancestry-model-note');if(note)note.textContent='These are descriptive reference similarities from a separate comparison panel. The ancestry percentage chart uses the published composition model shown above.';if(boundary)boundary.textContent='Scores in this panel are not ancestry shares. The separate model estimate and these reference comparisons answer different questions.';}
    if((c||hasComposition)&&!host.querySelector('.customer-reference-drawer')){
      const drawer=document.createElement('details');drawer.className='customer-reference-drawer';
      const heading=document.createElement('summary');heading.innerHTML='Explore the reference map &amp; study groups <span>Descriptive reference comparisons are separate from the published model result above.</span>';
      drawer.appendChild(heading);while(host.firstChild)drawer.appendChild(host.firstChild);host.appendChild(drawer);
    }
    const additional=host.querySelector('.ancestry-additional-references');
    if(additional&&!additional.parentElement?.classList.contains('customer-source-drawer')){
      const drawer=document.createElement('details');drawer.className='customer-source-drawer';
      const heading=document.createElement('summary');heading.innerHTML='Additional reference library <span>Browse the public source communities. This panel does not rank your personal origins.</span>';
      additional.before(drawer);drawer.appendChild(heading);drawer.appendChild(additional);
    }
  }
  function originsRendered(){
    decorateReferences();
    if(typeof originsGuideMode!=='undefined'&&originsGuideMode)return;
    const a=typeof currentOriginsData!=='undefined'?currentOriginsData?.ancient:null,host=$('origins-ancient-content');
    if(rows(a?.chromosome_breakdown).length&&host&&!host.querySelector('.customer-explorer-action')){
      host.insertAdjacentHTML('afterbegin','<div class="actions customer-explorer-action"><button type="button" class="btn" onclick="window.DnaDiscovery?.open(\'ancient\')">Explore chromosome evidence <span aria-hidden="true">↗</span></button></div>');
    }
  }
  function renderHealth(){const card=summaryRows().find(r=>r.chapter==='health');$('customer-health').innerHTML=`<header class="customer-section-head"><p class="eyebrow">OPTIONAL HEALTH RESEARCH</p><h3 id="customer-health-title" tabindex="-1">${esc(card?.result||'Your health research evidence')}</h3><p>${esc(card?.meaning||'Review the evidence cards in your focused report. Health research does not diagnose a condition or recommend treatment.')}</p></header><button type="button" class="btn" data-customer-open="evidence">Read health evidence &amp; sources</button>`;bind($('customer-health'));}
  function inputHelp(chapter){
    const card=summaryRows().find(r=>r.chapter===chapter);
    $('customer-input-help').innerHTML=`<p class="eyebrow">${esc(chapterLabels[chapter]||'NEXT STEP')}</p><h3 id="customer-input-title" tabindex="-1">${esc(card?.result||'This chapter needs a full report')}</h3><p>${esc(card?.meaning||'Select your raw DNA file, review processing consent, then run Discover My DNA Story. The report will show which chapters your file can support.')}</p><div class="actions"><button type="button" class="btn primary" data-customer-open="input">Review file &amp; run settings</button><button type="button" class="btn" data-customer-open="quality">Check file coverage</button></div>`;bind($('customer-input-help'));$('customer-input-help').hidden=false;return $('customer-input-title');
  }
  function open(value,shouldFocus=true){
    const chapter=validChapter(value);
    if(chapter==='export'){downloadReport();return;}
    if(chapter==='lineage'){const node=$('lineage-surnames')?.closest('details');if(node){node.open=true;focus(node);}return;}
    if(chapter==='input'){focus($('raw-dna-file'));return;}
    if(['quality','sources','evidence'].includes(chapter)){
      const id={quality:'dna-quality-panel',sources:'dna-sources-panel',evidence:'dna-full-evidence'}[chapter];
      const node=$(id);if(node){node.hidden=false;node.open=true;focus(node);}return;
    }
    if(chapter==='portrait'){window.DnaDiscovery?.open('portrait');return;}
    if(!state.report){if(chapter==='sound')return;return;}
    state.chapter=chapter;$('report-panel').setAttribute('data-customer-chapter',chapter);
    ['customer-overview','customer-classification','customer-traits','customer-health','customer-input-help'].forEach(id=>$(id).hidden=true);
    $('origins-report').hidden=true;
    let target=$('customer-title');
    if(chapter==='overview')$('customer-overview').hidden=false;
    else if(['origins','ancient','archaic'].includes(chapter)){
      if(origin(state.report)[chapter==='origins'?'modern':chapter]){
        if(typeof originsGuideMode!=='undefined'&&originsGuideMode)renderDnaOrigins(state.report,state.kind);
        $('origins-report').hidden=false;activateOriginsTab(chapter==='origins'?'modern':chapter);
        const model=chapter==='origins'?classification():null;
        $('customer-classification').innerHTML=modelMarkup(model);$('customer-classification').hidden=!model;
        if(model){$('origins-title').textContent='Explore the supporting references';$('origins-summary').textContent='The map and study groups provide reference context. Their comparison scores are separate from the model classification.';}
        decorateReferences();target=model?$('customer-classification').querySelector('h3'):$('origins-title');
      }else target=inputHelp(chapter);
    }else if(chapter==='traits'){
      if(rows(state.report.personal_traits?.cards).length){$('customer-traits').hidden=false;target=$('customer-traits-title');}else target=inputHelp(chapter);
    }else if(chapter==='sound'){
      target=$('dna-sound-panel');
      if(!state.report.sound_profile&&!lastDnaSoundProfile)target=inputHelp(chapter);
    }else if(chapter==='health'){$('customer-health').hidden=false;target=$('customer-health-title');}
    renderNav();const placeTarget=window.DnaCountry?.chapterChanged(chapter);if(placeTarget)target=placeTarget;if(shouldFocus)focus(target);
  }
  function setReport(report,kind){
    state.report=report&&typeof report==='object'?report:null;state.kind=kind||'';state.stale=false;state.chapter='overview';
    $('customer-stale').hidden=true;$('customer-export-status').textContent='';
    $('customer-workspace').hidden=!state.report;$('report-panel').classList.toggle('customer-active',!!state.report);
    if(!state.report){state.summary=null;window.DnaCountry?.setReport(null,kind);['customer-overview','customer-traits','customer-health','customer-classification'].forEach(id=>$(id).innerHTML='');return;}
    window.DnaCountry?.setReport(state.report,kind);state.summary=summaryFor(state.report);renderOverview();renderTraits();renderHealth();originsRendered();
    const chapter={ancient:'ancient',heritage:'origins',sound:'sound',health:'health'}[kind]||'overview';open(chapter,false);
  }
  function markStale(message){window.DnaCountry?.clearComparison();if(!state.report)return;state.stale=true;$('customer-stale').textContent=message||'Analysis settings changed. Run a fresh report to update these results.';$('customer-stale').hidden=false;}
  function collectSources(report){
    const result=[],seen=new Set();
    const add=(source,role='Linked result evidence')=>{if(!source||typeof source!=='object'||source.installed===false)return;const url=safeUrl(source.url||source.source_url),title=text(source.title||source.source_name||source.name||source.label||source.display_name||source.panel_name),version=text(source.source_version||source.version||source.pack_version);if(!title&&!url)return;const key=[url||title,version].join('|');if(seen.has(key))return;seen.add(key);result.push({title:title||'Published source',url,version,role,status:text(source.status).replace(/_/g,' ')});};
    rows(report?.source_versions).forEach(row=>add(row,'Reported source metadata'));rows(report?.data_pack_status).forEach(row=>add(row,'Installed reference catalog'));
    const o=origin(report);if(o.classification?.available)add({...o.classification,title:o.classification.method||'Population-structure model'});
    rows(o.ancient?.groups).forEach(row=>{add(row);rows(row.sources).forEach(source=>add(source));});
    rows(o.archaic?.categories).forEach(row=>rows(row.sources).forEach(source=>add(source)));
    rows(o.modern?.regional_refs?.groups).forEach(row=>add(row));rows(o.modern?.study_populations).forEach(row=>add(row));
    rows(report?.personal_traits?.cards).forEach(row=>rows(row.sources).forEach(source=>add(source)));
    rows(o.evidence_cards).forEach(row=>add(row));return result;
  }
  function printChart(report){
    const data=rows(origin(report).modern?.broad_regions).filter(r=>finite(r.affinity_score)&&r.affinity_score>=0&&r.affinity_score<=100);
    if(!data.length)return '';
    const height=data.length*42+28;
    return `<figure><figcaption><b>Regional reference comparison</b><br>Similarity score /100 · not ancestry percentages</figcaption><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 650 ${height}" role="img" aria-label="${esc(data.map(r=>(regionNames[r.code]||r.label)+': '+number(r.affinity_score)+' out of 100').join('; '))}">${data.map((r,i)=>`<text x="0" y="${i*42+25}" font-family="Arial,sans-serif" font-size="13" fill="#193b2b">${esc(regionNames[r.code]||r.label||r.code)}</text><rect x="173" y="${i*42+10}" width="390" height="20" rx="3" fill="#e7eee8"/><rect x="173" y="${i*42+10}" width="${r.affinity_score*3.9}" height="20" rx="3" fill="#548064"/><text x="580" y="${i*42+25}" font-family="Arial,sans-serif" font-size="13" fill="#193b2b">${number(r.affinity_score)}</text>`).join('')}</svg></figure>`;
  }
  function ancientPrint(report){const a=origin(report).ancient||{},groups=rows(a.groups).map(g=>{const place=rows(report.country_context?.ancient).find(p=>p.code===g.code);return place?{...g,source_group_label:g.label,label:place.label||g.label,short_plain:place.plain||g.short_plain}:g;});return `<p>${esc(rows(report.country_context?.ancient).length?'These place names locate the sampled ancient remains in today’s world. The archaeological group names remain below for reference. They do not identify your personal migration route or a named ancestor.':a.plain)}</p>${rows(report.country_context?.ancient).length?'<details class="evidence-extra"><summary>How these ancient comparisons were measured</summary><p>'+esc(a.plain)+'</p></details>':''}${groups.length?`<div class="print-grid">${groups.map(g=>`<article><p class="kicker">${esc(g.era||'Ancient reference')}</p><h3>${esc(g.label||g.code)}</h3>${g.source_group_label?'<p class="kicker">Study label: '+esc(g.source_group_label)+'</p>':''}<p>${esc(g.short_plain||g.region)}</p><p><b>${number(g.matched_markers)} compared markers</b>${finite(g.similarity_score)?'<br>'+number(g.similarity_score)+' /100 allele-sharing score':''}</p></article>`).join('')}</div><p class="boundary">${esc(a.boundary||'Ancient reference comparisons do not measure inherited ancestry percentages or prove descent from a named group.')}</p>`:'<p>No supported ancient comparison was returned in this report.</p>'}`;}
  function archaicPrint(report){const a=origin(report).archaic||{};return `<p>${esc(a.plain)}</p><div class="print-grid">${rows(a.categories).map(g=>`<article><h3>${esc(g.label||g.code)}</h3><p class="print-value">${finite(g.callable_tag_count)&&g.callable_tag_count>0&&finite(g.observed_tag_count)?number(g.observed_tag_count)+' associated markers observed':'Not assessed in this file'}</p><p>${finite(g.callable_tag_count)?number(g.callable_tag_count)+' markers your file could test.':''}${finite(g.panel_tag_count)?' The source panel contains '+number(g.panel_tag_count)+' markers.':''}</p></article>`).join('')}</div><p class="boundary">${esc(a.boundary||'These are marker counts in a research-tag screen, not a whole-genome Neanderthal or Denisovan percentage.')}</p>`;}
  function buildReportHtml(){
    if(!state.report)return '';
    const report=state.report,o=origin(report),s=summaryFor(report),findings=rows(s.findings),c=classification(report),source=collectSources(report),raw=report.raw||report.summary||{};
    const chapters=[];const countryExport=window.DnaCountry?.exportSection(report)||'';if(countryExport)chapters.push(countryExport);
    if(o.modern)chapters.push(`${window.DnaCountry?.hasComposition(report)?'<details class="evidence-extra"><summary>Independent classification and descriptive comparisons</summary>':''}<section><p class="kicker">01 / ORIGINS</p><h2>Your regional picture</h2>${c?modelMarkup(c,true):`<p>${esc(o.modern.consumer_summary?.headline||o.modern.headline)}</p><p>${esc(o.modern.consumer_summary?.plain||o.modern.plain)}</p>`}${c?`<details class="evidence-extra"><summary>Supporting descriptive reference comparison</summary>${printChart(report)}</details>`:printChart(report)}<p class="boundary">${esc(o.modern.boundary||'These comparisons are reference context, not a statement of nationality or cultural identity.')}</p></section>${window.DnaCountry?.hasComposition(report)?'</details>':''}`);
    if(o.ancient)chapters.push(`<section><p class="kicker">02 / ANCIENT WORLD</p><h2>Communities from the ancient record</h2>${ancientPrint(report)}</section>`);
    if(o.archaic)chapters.push(`<section><p class="kicker">03 / ARCHAIC DNA</p><h2>Neanderthal &amp; Denisovan marker evidence</h2>${archaicPrint(report)}</section>`);
    if(report.personal_traits)chapters.push(`<section><p class="kicker">04 / EVERYDAY TRAITS</p><h2>Your everyday trait observations</h2><div class="print-grid">${rows(report.personal_traits.cards).map(row=>traitCard(row,true)).join('')}</div><p class="boundary">${esc(report.personal_traits.boundary)}</p></section>`);
    if(report.sound_profile)chapters.push(`<section><p class="kicker">05 / DNA SOUND</p><h2>Your file, composed</h2><p>Your analysis produced a ${number(report.sound_profile.duration_seconds)}-second composition${finite(report.sound_profile.bpm)?' at '+number(report.sound_profile.bpm)+' beats per minute':''}.</p><p>Open DNA Sound in the lab to play the composition or save a WAV. Sound and portrait visuals are artistic mappings, not physical appearance or biological predictions.</p></section>`);
    const health=findings.find(row=>row.action?.chapter==='health');if(health)chapters.push(`<section><p class="kicker">06 / HEALTH RESEARCH</p><h2>${esc(health.title||'Optional health research')}</h2><p><b>${esc(health.result)}</b></p><p>${esc(health.meaning)}</p></section>`);
    const sourceHtml=source.length?`<ol>${source.map(row=>`<li>${link(row.url,row.title)}${row.version?' · '+esc(row.version):''}<br><small>${esc(row.role)}${row.status?' · '+esc(row.status):''}</small></li>`).join('')}</ol>`:'<p>Exact source metadata was not supplied for this focused report. Open the technical report in the lab for any available source notes.</p>';
    const date=new Date().toLocaleDateString('en-GB',{year:'numeric',month:'long',day:'numeric'});
    return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ZeroThink · Your DNA story</title><style>${printStyles}${window.DnaCountry?.printStyles||''}</style></head><body><div class="print-toolbar"><button type="button" onclick="window.print()">Print / save PDF</button><label><input type="checkbox" onchange="document.body.classList.toggle('print-evidence',this.checked);document.querySelectorAll('.evidence-extra').forEach(node=>node.open=this.checked)"> Include evidence appendix in print</label><p>Choose Save as PDF in your browser’s print dialog. This downloaded HTML also opens without an internet connection.</p></div><main><header><p class="kicker">ZEROTHINK DNA LAB · YOUR READABLE REPORT</p><h1>Your DNA story</h1><p>${esc(date)} · Generated from the current analysis${state.stale?' · PREVIOUS ANALYSIS — file or settings have changed':''}</p><h2>${esc(s.headline||'Your findings, a chapter at a time')}</h2><p>${esc(s.intro)}</p></header>${state.stale?'<p class="boundary"><b>Previous analysis:</b> The file or analysis settings changed after this report. Run a fresh report before treating these findings as current.</p>':''}<section class="print-overview"><p class="kicker">YOUR RESULTS AT A GLANCE</p><div class="print-grid">${findings.map(row=>`<article><p class="kicker">${esc(row.title)}</p><h3>${esc(row.result)}</h3><p>${esc(row.meaning)}</p><p class="print-status">${statusLabel(row.status)}</p></article>`).join('')}</div></section>${chapters.join('')}${rows(s.next_steps).length?`<section><h2>Explore next</h2><ul>${rows(s.next_steps).map(row=>`<li><b>${esc(row.label)}</b> — ${esc(row.detail)}</li>`).join('')}</ul></section>`:''}<section class="source-section"><h2>Sources and reference catalog</h2><p>Linked evidence and reported database releases are listed with their source status. An installed catalog entry does not mean a personal match was found.</p>${sourceHtml}</section><details class="evidence-extra" id="evidence-appendix"><summary>Evidence appendix · optional in print</summary><p>File coverage describes the input; it does not establish the accuracy of every interpretation.</p><dl><dt>Observed usable calls</dt><dd>${number(raw.called_variants??raw.marker_count??raw.parsed_variants)}</dd><dt>Ancient comparison overlap</dt><dd>${number(o.ancient?.common_marker_count)} markers</dd><dt>Everyday trait marker coverage</dt><dd>${number(report.personal_traits?.coverage?.observed_markers)} / ${number(report.personal_traits?.coverage?.total_markers)}</dd></dl>${rows(s.glossary).length?`<h3>Plain-English guide</h3><dl>${rows(s.glossary).map(row=>`<dt>${esc(row.term)}</dt><dd>${esc(row.meaning)}</dd>`).join('')}</dl>`:''}<p>Detailed per-marker checks and the full technical Markdown remain available in the DNA Lab. This readable report does not include raw genotype rows, file names or file hashes.</p></details><footer>Prepared by ZeroThink DNA Lab. Personal research results; share with care. Health findings are research information, not a diagnosis or treatment recommendation. Reference-model and marker comparisons do not establish nationality, cultural identity, a named ancestor or a reconstructed face.</footer></main></body></html>`;
  }
  const printStyles=`:root{color-scheme:light}*{box-sizing:border-box}body{margin:0;background:#edf1eb;color:#263c30;font:15px/1.7 system-ui,-apple-system,Segoe UI,sans-serif}main{max-width:950px;margin:30px auto;background:#fff;padding:56px 62px}header{border-bottom:3px solid #517755;padding-bottom:28px}h1{font-size:48px;line-height:1.1;color:#193e2b;letter-spacing:-.05em;margin:14px 0 20px}h2{font-size:26px;line-height:1.25;color:#224930;letter-spacing:-.025em}h3{font-size:18px;line-height:1.45;margin:9px 0;color:#244631}p{margin:12px 0}a{color:#225a44;overflow-wrap:anywhere}.kicker{font-size:10px;font-weight:750;letter-spacing:.13em;color:#677958;text-transform:uppercase}.print-toolbar{max-width:950px;margin:20px auto;padding:20px;display:flex;gap:15px;align-items:center;flex-wrap:wrap}.print-toolbar p{flex:1 0 100%;font-size:12px;margin:0}button{background:#234d35;color:#fff;border:0;border-radius:8px;padding:13px 18px;font:600 14px system-ui;cursor:pointer}.print-toolbar label{font-size:13px}section{margin:34px 0;padding:0 0 24px;border-bottom:1px solid #dbe2d8}.print-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}article{border:1px solid #d9e1d5;background:#fafbf8;border-radius:10px;padding:20px;break-inside:avoid}.print-grid p{font-size:13px}.print-status{font-size:11px!important;color:#687756}.print-value{font-size:22px!important;font-weight:650}.boundary{border-left:3px solid #a3b68d;padding:9px 15px;font-size:12px;background:#f4f7ef}.customer-model-meta{display:flex;gap:12px;flex-wrap:wrap;font-size:12px;color:#567449}.customer-model-card h3{font-size:30px}.customer-trait-card .customer-status{font-size:10px;color:#637153}.customer-trait-card details{margin-top:20px;font-size:12px}.customer-trait-card details p{font-size:11px}.customer-trait-finding{font-weight:700}.customer-trait-limit{border-left:2px solid #a3b68d;padding-left:10px}.customer-trait-missing{font-size:11px;color:#695f3b}summary{cursor:pointer;font-weight:650;font-size:13px}.source-section li{font-size:11px;line-height:1.7;margin:6px 0}figure{margin:24px 0}figcaption{font-size:12px;line-height:1.7;margin-bottom:12px}svg{width:100%;max-width:690px}dt{font-size:12px;font-weight:700}dd{font-size:12px;margin:3px 0 12px}footer{font-size:10px;border-top:1px solid #dce5d7;margin-top:30px;padding-top:20px;color:#63735e}.evidence-extra{margin:25px 0;padding:18px;border:1px solid #dfe6da;border-radius:8px}.evidence-extra p{font-size:12px}@media(max-width:700px){main{padding:27px 20px;margin:12px}.print-grid{grid-template-columns:1fr}h1{font-size:38px}.print-toolbar{padding:14px}}@page{size:A4;margin:15mm}@media print{body{background:#fff;font-size:11px}main{margin:0;padding:0;max-width:none}.print-toolbar{display:none}h1{font-size:34px}h2{font-size:21px}h3{font-size:15px}section{margin:23px 0;padding-bottom:18px}.print-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}article{padding:14px;break-inside:avoid}p{orphans:3;widows:3}.print-grid p{font-size:10px}h2,h3{break-after:avoid}.evidence-extra{display:none}.print-evidence .evidence-extra{display:block}.print-evidence .evidence-extra>*{display:block}.source-section{break-before:page}a{text-decoration:none}a[href]::after{content:' (' attr(href) ')';font-size:9px;overflow-wrap:anywhere}svg{max-height:240px}footer{font-size:9px}}`;
  function exportReady(){if(state.report)return true;$('customer-export-status').textContent='Run a structured DNA report to create the readable download. A focused technical lookup can still be downloaded as Markdown.';alert('Run a structured DNA report first. For a technical lookup, use Download Markdown.');return false;}
  function downloadReport(){if(!exportReady())return;const blob=new Blob([buildReportHtml()],{type:'text/html;charset=utf-8'}),url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download='zerothink-your-dna-story.html';a.click();setTimeout(()=>URL.revokeObjectURL(url),30000);$('customer-export-status').textContent='Readable HTML report downloaded. Open it anytime, or choose Print / save PDF. The separate Markdown download contains the full technical report.';}
  function printReport(){
    if(!exportReady())return;const popup=window.open('','_blank');
    if(!popup){downloadReport();$('customer-export-status').textContent='Your browser blocked the report window, so the readable HTML was downloaded instead. Open that file and use its Print / save PDF button, or allow the report window and try again.';return;}
    popup.document.open();popup.document.write(buildReportHtml());popup.document.close();popup.focus();
    $('customer-export-status').textContent='The readable report opened in a new window. Use its Print / save PDF button, then choose Save as PDF in the browser dialog.';
  }
  function guideShown(){$('customer-workspace').hidden=true;$('report-panel').classList.remove('customer-active');}
  function restoreResults(){if(!state.report)return;$('customer-workspace').hidden=false;$('report-panel').classList.add('customer-active');open('origins',false);}
  window.DnaCustomer={setReport,open,markStale,downloadReport,printReport,buildReportHtml,originsRendered,guideShown,restoreResults};
  const story=window.DnaAncestryStory;
  if(story){const original=story.render;story.render=(...args)=>{const result=original(...args);decorateReferences();return result;};}
  const discovery=window.DnaDiscovery;
  if(discovery){const original=discovery.open,originalClose=discovery.close,returnFocus=new Map();discovery.open=(kind,...args)=>{if(document.activeElement)returnFocus.set(kind,document.activeElement);if(state.report&&kind==='sound')open('sound',false);return original(kind,...args);};discovery.close=(kind,...args)=>{const result=originalClose(kind,...args);returnFocus.get(kind)?.focus({preventScroll:true});return result;};}
  ['declared-build','reported-orientation'].forEach(id=>$(id)?.addEventListener('change',()=>markStale('Analysis settings changed. These results still describe the previous analysis. Run Discover My DNA Story to update them.')));
  $('origins-overview-link')?.addEventListener('click',event=>{if(state.report){event.preventDefault();open('overview');}});
  setReport(typeof lastReportData!=='undefined'?lastReportData:null,typeof lastReportKind!=='undefined'?lastReportKind:'');
})();
