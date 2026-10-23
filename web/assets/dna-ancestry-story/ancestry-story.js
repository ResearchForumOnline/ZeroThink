/* Consumer ancestry presentation. Model percentages and reference affinities remain separate. */
(() => {
  'use strict';
  const $=id=>document.getElementById(id);
  const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const finite=value=>typeof value==='number'&&Number.isFinite(value);
  const count=value=>value!==null&&value!==undefined&&value!==''&&finite(Number(value))?Number(value).toLocaleString('en-GB'):'—';
  const palette={AFR:'#d9ad6d',EUR:'#83d7b2',EAS:'#b0a0e5',SAS:'#77c9dd',AMR:'#e6a68e'};
  const broadLabels={AFR:'Africa',EUR:'Europe',EAS:'East Asia',SAS:'South Asia',AMR:'American study groups'};
  let catalog=null,query='',showAllCoverage=false;
  const modern=()=>currentOriginsData?.modern||{};
  const summary=()=>modern().consumer_summary||{};
  const regions=()=>Array.isArray(modern().broad_regions)?modern().broad_regions:[];
  const populations=()=>Array.isArray(modern().study_populations)?modern().study_populations:[];
  const cleanLabel=row=>summary().regions?.find(r=>r.code===row?.code)?.label||broadLabels[row?.code]||row?.label||'Regional reference';
  const populationLabel=row=>summary().population_labels?.[row?.code]?.label||catalog?.population_labels?.[row?.code]?.label||row?.label||row?.code||'Study reference';
  const populationDetail=row=>summary().population_labels?.[row?.code]?.detail||catalog?.population_labels?.[row?.code]?.detail||row?.label||'Public study reference';
  const sourceLink=(url,text)=>{try{const u=new URL(String(url));return ['https:','http:'].includes(u.protocol)?`<a href="${esc(u.href)}" target="_blank" rel="noopener noreferrer">${esc(text)} ↗</a>`:esc(text);}catch{return esc(text);}};
  function validEstimate(estimate){
    if(originsGuideMode||estimate?.available!==true||estimate.chart_eligible===false)return false;
    const parts=estimate.components;if(!Array.isArray(parts)||parts.length<1||parts.length>20)return false;
    const codes=new Set();let total=0;
    for(const part of parts){
      if(!part||typeof part!=='object'||typeof part.code!=='string'||!part.code.trim()||codes.has(part.code)||!finite(part.percent)||part.percent<0||part.percent>100)return false;
      codes.add(part.code);total+=part.percent;
    }
    // Only tolerate binary floating-point addition, never rescale or invent a remainder.
    return Math.abs(total-100)<=1e-6;
  }
  const percent=value=>value>0&&value<.01?'<0.01%':value<100&&value>99.99?'>99.99%':Number(value).toLocaleString('en-GB',{maximumFractionDigits:2})+'%';
  function range(part,estimate){
    const lo=part.range_low??part.interval_low,hi=part.range_high??part.interval_high;
    if(!finite(lo)||!finite(hi)||lo<0||hi>100||lo>part.percent||hi<part.percent)return '';
    return `<small>${esc(estimate.range_label||'Reported model range')}: ${esc(percent(lo))}–${esc(percent(hi))}</small>`;
  }
  function estimateCard(){
    const data=modern(),consumer=summary(),estimate=data.ancestry_estimate;
    if(validEstimate(estimate)){
      const parts=estimate.components,circumference=2*Math.PI*102;let offset=0;
      const circles=parts.map((part,i)=>{
        const length=part.percent/100*circumference,start=offset;offset+=length;
        if(part.percent===0)return '';
        return `<circle cx="150" cy="150" r="102" fill="none" stroke="${palette[part.code]||['#d3b183','#a0c7d9','#b7c599'][i%3]}" stroke-width="35" stroke-dasharray="${length.toFixed(8)} ${(circumference-length).toFixed(8)}" stroke-dashoffset="${(-start).toFixed(8)}" transform="rotate(-90 150 150)"/>`;
      }).join('');
      return `<article class="ancestry-estimate-card"><p class="eyebrow">YOUR REGIONAL ESTIMATE</p><h4>Ancestry at a glance</h4><svg class="ancestry-donut" viewBox="0 0 300 300" role="img" aria-label="Ancestry model estimate: ${esc(parts.map(p=>`${p.label||p.code} ${percent(p.percent)}`).join('; '))}"><g aria-hidden="true"><circle cx="150" cy="150" r="102" fill="none" stroke="#193025" stroke-width="35"/>${circles}<text x="150" y="145" text-anchor="middle" fill="#c7e3d3" font-size="16" font-family="system-ui,sans-serif">Your ancestry</text><text x="150" y="168" text-anchor="middle" fill="#94b8a2" font-size="10" font-family="system-ui,sans-serif">REFERENCE MODEL ESTIMATE</text></g></svg><div class="ancestry-estimate-list">${parts.map((part,i)=>`<button type="button" data-story-region="${esc(part.code)}" aria-label="${esc(part.label||part.code)}, ${percent(part.percent)}. Explore regional references."><i aria-hidden="true" style="background:${palette[part.code]||['#d3b183','#a0c7d9','#b7c599'][i%3]}"></i><span><b>${esc(part.label||part.code)}</b>${range(part,estimate)}</span><strong>${esc(percent(part.percent))}</strong></button>`).join('')}</div><p class="ancestry-model-note">${esc(estimate.plain||'An estimate from the installed reference model. Regional detail depends on the reference groups and usable markers.')}</p><details><summary>About this estimate</summary><p>${esc(estimate.boundary||'Reference-model percentages do not determine nationality, culture or identity.')}</p><p>Model: ${esc(estimate.model_id||estimate.model||'Reported ancestry model')}${estimate.version?' · '+esc(estimate.version):''}</p>${estimate.coverage?`<p>${count(estimate.coverage.common_markers??estimate.coverage.matched_markers)} usable markers${estimate.coverage.chromosomes!=null?' · '+count(estimate.coverage.chromosomes)+' chromosomes':''}.</p>`:''}</details></article>`;
    }
    const tied=consumer.comparison_tied===true||data.comparison_tied===true;
    const lead=tied?null:(data.closest_region||regions().find(r=>r.code===consumer.leading_region_code));
    const leadingPopulation=tied||data.population_comparison_tied?null:(data.closest_population||populations().find(p=>p.code===consumer.leading_population_code));
    const tiedRegions=Array.isArray(data.tied_top_regions)?data.tied_top_regions:[];
    const top=originsGuideMode?'Explore your regional references':tied?'Your regional comparisons overlap':lead?cleanLabel(lead):'Your regional reference overview';
    const detail=originsGuideMode?'Choose a region to explore its study groups. This guide contains no personal DNA results.':tied?(tiedRegions.length?tiedRegions.map(cleanLabel).join(' · '):'No single leading region is resolved at the displayed precision.'):lead?'The strongest broad comparison among the installed reference groups.':data.plain||'A usable reference comparison will appear after file validation.';
    return `<article class="ancestry-estimate-card"><p class="eyebrow">${originsGuideMode?'EDUCATIONAL GUIDE':'YOUR CURRENT REFERENCE FINDINGS'}</p><h4>${originsGuideMode?'Where your comparison begins':'Your regional picture'}</h4><div class="ancestry-closest-stack"><div class="ancestry-closest-card"><small>${originsGuideMode?'Choose a region':tied?'Comparable references':lead?'Closest broad reference':'Reference overview'}</small><b>${esc(top)}</b><span>${esc(detail)}</span></div>${leadingPopulation?`<div class="ancestry-closest-card ancestry-named-lead"><small>Closest named study group</small><b>${esc(populationLabel(leadingPopulation))}</b><span>${esc(populationDetail(leadingPopulation))}</span></div>`:''}</div>${referenceChart()}<div class="ancestry-notice">${originsGuideMode?'The map and group labels explain reference coverage. Upload your own file to see measured results.':esc(estimate?.plain||'These are reference comparisons. An ancestry percentage estimate is not available for this result.')}</div><p class="ancestry-model-note">${originsGuideMode?'Educational locations are separate from your results.':'A missing percentage is not 0% ancestry. Regional and population labels describe the references used for comparison.'}</p></article>`;
  }
  function referenceChart(){
    if(originsGuideMode||!regions().length)return '';
    const data=modern(),tiedCodes=new Set((data.tied_top_regions||[]).map(row=>row.code));
    return `<section class="ancestry-reference-chart" aria-labelledby="ancestry-comparison-title"><h5 id="ancestry-comparison-title">Regional reference comparison</h5><p id="ancestry-comparison-unit">Similarity score /100 · not ancestry percentages</p><div class="ancestry-comparison-rows">${regions().map((row,index)=>{
      const value=row.affinity_score,valid=finite(value)&&value>=0&&value<=100;
      const score=valid?(value>0&&value<.1?'<0.1':value<100&&value>99.9?'>99.9':value.toFixed(1)):'Not assessed';
      const low=String(row.confidence_band||'').toLowerCase().includes('limited')||(finite(row.matched_markers)&&row.matched_markers<1000)||(finite(row.chromosome_count)&&row.chromosome_count<10);
      const note=data.comparison_tied&&tiedCodes.has(row.code)?'Comparable leading reference':low?'Limited marker coverage':'Reference comparison';
      return `<button type="button" class="ancestry-comparison-row${index===originsRegionIndex?' active':''}" data-story-region="${esc(row.code)}" aria-pressed="${index===originsRegionIndex}" aria-describedby="ancestry-comparison-unit"><span class="ancestry-comparison-label"><b>${esc(cleanLabel(row))}</b><strong>${esc(score)}${valid?'<small> /100</small>':''}</strong></span><span class="ancestry-comparison-track" aria-hidden="true">${valid?`<i style="width:${value}%;background:${palette[row.code]||'#abc4b5'}"></i>`:''}</span><span class="ancestry-comparison-caption">${esc(note)}</span></button>`;
    }).join('')}</div></section>`;
  }
  function regionTabs(){return regions().map((row,i)=>`<button type="button" class="origin-region-button${i===originsRegionIndex?' active':''}" data-story-region="${esc(row.code)}" aria-pressed="${i===originsRegionIndex}"><i class="ancestry-region-dot" aria-hidden="true" style="background:${palette[row.code]||'#b6cbbd'}"></i>${esc(cleanLabel(row))}</button>`).join('');}
  function coverageRows(){return Array.isArray(summary().requested_region_coverage)?summary().requested_region_coverage:(catalog?.requested_region_coverage||[]);}
  function coverageMarkup(rows,prefix='ancestry'){
    if(!rows.length)return '';
    return `<section class="ancestry-coverage" aria-labelledby="${prefix}-coverage-title"><h4 id="${prefix}-coverage-title">Can this report distinguish the places you care about?</h4><p>Search for a region or community to see the actual reference coverage. A missing reference means this report cannot answer that question; it does not mean that ancestry is absent.</p><div class="ancestry-coverage-search"><label for="${prefix}-coverage-search">Find a region or community</label><input id="${prefix}-coverage-search" type="search" value="${esc(prefix==='ancestry'?query:'')}" placeholder="Try Pakistan, Ireland, Ashkenazi, Egypt…" autocomplete="off"></div><div class="ancestry-coverage-grid" id="${prefix}-coverage-grid"></div><p class="ancestry-coverage-result-count" id="${prefix}-coverage-count" role="status"></p></section>`;
  }
  function renderCoverage(prefix='ancestry',search=query){
    const host=$(`${prefix}-coverage-grid`);if(!host)return;
    const list=prefix==='ancestry'?coverageRows():catalog?.requested_region_coverage||[],needle=String(search).trim().toLowerCase();
    const filtered=list.filter(row=>[row.code,row.label,row.plain,...(Array.isArray(row.reference_codes)?row.reference_codes:[])].join(' ').toLowerCase().includes(needle));
    const rows=prefix==='ancestry'&&!needle&&!showAllCoverage?filtered.slice(0,4):filtered;
    const names={direct_reference:'Direct study reference',broader_context:'Broader reference context',not_represented:'Not represented'};
    host.innerHTML=rows.length?rows.map(row=>`<article class="ancestry-coverage-card ${row.status==='broader_context'?'context':row.status==='not_represented'?'absent':''}"><h5>${esc(row.label||row.code)}</h5><span>${esc(names[row.status]||'Not evaluated')}</span><p>${esc(row.plain||'Review the installed reference coverage.')}</p>${prefix==='ancestry'&&row.file_note?`<p>${esc(row.file_note)}</p>`:''}${prefix==='ancestry'?coverageAction(row):''}</article>`).join(''):'<p class="ancestry-empty-coverage">No matching coverage entry. This search does not create an ancestry finding.</p>';
    $(`${prefix}-coverage-count`).textContent=`${rows.length<filtered.length?'Showing '+rows.length+' of '+filtered.length:rows.length+' of '+list.length} coverage entries${prefix!=='ancestry'?' · reference catalog, not personal results':''}`;
    if(prefix==='ancestry'&&!needle&&list.length>4){
      host.insertAdjacentHTML('beforeend',`<button type="button" class="ancestry-coverage-toggle" id="ancestry-coverage-expand">${showAllCoverage?'Show fewer entries':'Show all '+list.length+' regions & communities'} ↓</button>`);
      $('ancestry-coverage-expand').addEventListener('click',()=>{showAllCoverage=!showAllCoverage;renderCoverage();});
    }
    bindRegionButtons(host);
    host.querySelectorAll('[data-story-reference]').forEach(button=>button.addEventListener('click',()=>openRegional(button.dataset.storyReference)));
  }
  function coverageAction(row){
    const groups=modern().regional_refs?.groups||[],codes=Array.isArray(row.reference_codes)?row.reference_codes:[];
    const specific=groups.find(group=>codes.includes(group.code));
    if(specific)return `<button type="button" data-story-reference="${esc(specific.code)}">Explore ${esc(specific.label||row.label)} →</button>`;
    const broad=regions().find(r=>r.code===row.region_code);
    return broad?`<button type="button" data-story-region="${esc(row.region_code)}" data-story-scroll="true">Explore ${esc(cleanLabel(broad))} references →</button>`:'';
  }
  function openRegional(code){reveal('ancestry-regional-details');document.querySelectorAll('[data-regional-code]').forEach(card=>{if(card.dataset.regionalCode===code){card.scrollIntoView({behavior:typeof dnaMotionAllowed==='function'&&dnaMotionAllowed()?'smooth':'auto',block:'center'});card.tabIndex=-1;card.focus({preventScroll:true});}});}
  function bindRegionButtons(host){host.querySelectorAll('[data-story-region]').forEach(button=>button.addEventListener('click',()=>{selectRegion(button.dataset.storyRegion);if(button.dataset.storyScroll){const groupArea=$('ancestry-region-populations');groupArea.scrollIntoView({behavior:typeof dnaMotionAllowed==='function'&&dnaMotionAllowed()?'smooth':'auto',block:'start'});const heading=groupArea.querySelector('h4');if(heading){heading.tabIndex=-1;heading.focus({preventScroll:true});}}}));}
  function selectRegion(code){const index=regions().findIndex(r=>r.code===code);if(index<0)return;selectOriginsRegion(index);}
  function renderLocation(){
    const row=regions()[originsRegionIndex],target=$('origins-location-details');if(!target)return;
    if(!row){target.innerHTML='<p>Regional reference details appear when the file has a usable comparison.</p>';renderPopulations();return;}
    const region=summary().regions?.find(r=>r.code===row.code);
    const context=region?.plain||row.short_plain||'Explore the actual study groups used within this broad region.';
    target.innerHTML=`<p class="eyebrow">${originsGuideMode?'EXPLORE A REGION':'SELECTED REFERENCE REGION'}</p><h4>${esc(cleanLabel(row))}</h4><p>${esc(context)}</p>${!originsGuideMode?`<div class="ancestry-map-stats"><span>${count(row.matched_markers??row.matched_marker_count)} compared markers</span>${row.chromosome_count!=null?`<span>${count(row.chromosome_count)} chromosomes</span>`:''}</div>`:''}`;
    renderPopulations();
    $('origins-modern-content')?.querySelectorAll('.ancestry-region-tabs [data-story-region], .ancestry-comparison-row').forEach(button=>{const selected=button.dataset.storyRegion===row.code;button.classList.toggle('active',selected);button.setAttribute('aria-pressed',String(selected));});
  }
  function renderPopulations(){
    const host=$('ancestry-region-populations');if(!host)return;
    const selected=regions()[originsRegionIndex],data=modern();
    const rows=populations().filter(p=>p.region_code===selected?.code||(summary().population_labels?.[p.code]?.region_code===selected?.code));
    const lead=data.population_comparison_tied?null:data.closest_population?.code;
    const ties=new Set((data.tied_top_populations||[]).map(p=>p.code));
    host.innerHTML=`<div class="ancestry-reference-heading"><div><h4>${esc(selected?cleanLabel(selected):'Regional')} · a closer look</h4><p>${originsGuideMode?'These labels describe the study references behind the map.':'The named groups below belong to the selected region. They are reference matches, separate from model percentages.'}</p></div></div><div class="ancestry-populations">${rows.length?rows.map(row=>`<article class="ancestry-population"><p class="eyebrow">${esc(cleanLabel(selected))} / STUDY GROUP</p><h5>${esc(populationLabel(row))}</h5><p>${esc(populationDetail(row))}</p>${!originsGuideMode?`<span class="ancestry-match-badge">${row.code===lead?'Closest named comparison':ties.has(row.code)&&data.population_comparison_tied?'Comparable leading reference':'Reference comparison'}</span><details><summary>View comparison evidence</summary><p>${finite(row.affinity_score)?Number(row.affinity_score).toFixed(1)+' /100 affinity · ':''}${count(row.matched_markers??row.matched_marker_count)} markers${row.chromosome_count!=null?' · '+count(row.chromosome_count)+' chromosomes':''}.</p><p>${esc(row.confidence_band||'Research comparison')}. Affinity is not an ancestry percentage.</p><p>Original source label: ${esc(row.label||row.code)}.<br>${sourceLink(row.source_url,row.source_name||'Reference source')} · ${esc(row.source_version||'Release not supplied')}</p></details>`:''}</article>`).join(''):`<div class="ancestry-population"><h5>${originsGuideMode?'Educational regional context':'No named group comparison returned'}</h5><p>${originsGuideMode?'Named population results appear after a file comparison.':'This result does not resolve a finer named reference within this region.'}</p></div>`}</div>`;
  }
  function render(){
    const data=modern(),consumer=summary(),host=$('origins-modern-content');
    $('origins-report').classList.toggle('ancestry-mode',!originsGuideMode);
    tabChanged(typeof originsActiveTab!=='undefined'?originsActiveTab:'modern');
    host.innerHTML=`<div class="ancestry-summary-grid">${estimateCard()}<div class="ancestry-reference-map"><div class="origin-map-title"><strong>Explore your regional references</strong><span>${originsGuideMode?'Educational guide':'Choose a region to see its study groups'}</span></div><svg id="origins-world-map" class="origin-world-map" viewBox="0 0 720 360" role="group" aria-label="Map of reference sampling regions"></svg><div class="ancestry-region-tabs">${regionTabs()}</div><p class="origin-map-caption">Points show broad reference contexts. Fine labels describe study participants, including diaspora samples; the map is not a personal migration route.</p><div class="origin-location-details" id="origins-location-details"></div></div></div><section id="ancestry-region-populations" aria-label="Study populations in the selected region"></section>${additionalReferences()}${coverageMarkup(coverageRows())}<p class="ancestry-proportion-boundary">${esc(data.boundary||'Reference comparisons and model percentages answer different questions. Neither determines nationality, culture or personal identity.')}</p>`;
    bindRegionButtons(host);renderOriginsMap();renderLocation();renderCoverage();
    $('ancestry-coverage-search')?.addEventListener('input',event=>{query=event.target.value;renderCoverage();});
  }
  function tabChanged(tab){
    if(originsGuideMode||!currentOriginsData)return;
    if(tab==='ancient'||tab==='archaic'){
      const chapter=currentOriginsData[tab]||{};
      $('origins-mode').textContent=tab==='ancient'?'Your ancient reference comparisons':'Your archaic marker evidence';
      $('origins-title').textContent=chapter.headline||(tab==='ancient'?'Explore your ancient comparisons':'Neanderthal & Denisovan evidence');
      $('origins-summary').textContent=chapter.plain||'Explore the measured evidence for this part of your report.';return;
    }
    const data=modern(),consumer=summary(),hasEstimate=validEstimate(data.ancestry_estimate),tied=consumer.comparison_tied===true||data.comparison_tied===true;
    $('origins-mode').textContent='Your ancestry overview';
    $('origins-title').textContent=hasEstimate?'Your ancestry estimate':tied?'Your regional comparisons overlap':consumer.headline||data.headline||'Your regional ancestry overview';
    $('origins-summary').textContent=hasEstimate?(data.ancestry_estimate.plain||consumer.plain||'Explore your estimated regional components and the supporting references.'):tied?'The available regional references do not resolve a single leading comparison. Explore their named study groups and coverage below.':(consumer.plain||data.plain||'Explore the regional picture and then the named reference groups.');
  }
  function additionalReferences(){
    const panel=modern().regional_refs;
    if(!panel||panel.installed!==true||!Array.isArray(panel.groups)||!panel.groups.length)return '';
    return `<section class="ancestry-coverage ancestry-additional-references"><p class="eyebrow">ADDITIONAL REGIONAL STUDY PANEL</p><h4>${esc(panel.headline||'Explore more regional communities')}</h4><p>${esc(panel.plain||'These additional source groups are compared separately from the main ancestry model.')}</p><div class="ancestry-notice">Internal testing did not separate these source groups reliably. Scores are descriptive sharing measures, not a personal-origin ranking.</div><details class="ancestry-raw-details" id="ancestry-regional-details"><summary>Explore ${panel.groups.length} regional reference groups <span>Source groups and comparison evidence · separate from the ancestry estimate</span></summary><div class="ancestry-populations">${panel.groups.map(row=>`<article class="ancestry-population" data-regional-code="${esc(row.code)}"><p class="eyebrow">${esc(row.region||'Regional reference')}</p><h5>${esc(row.label||row.code)}</h5><p>${count(row.sample_count)} source participants${row.reference_role?' · '+esc(String(row.reference_role).replace(/_/g,' ')):''}</p><details><summary>View reference comparison</summary><p>${finite(row.similarity_score)?Number(row.similarity_score).toFixed(1)+' /100 allele-sharing score · ':''}${count(row.matched_markers)} markers${row.chromosome_count!=null?' · '+count(row.chromosome_count)+' chromosomes':''}.</p><p>${esc(row.limitations||panel.boundary||'Reference comparison only, not an ancestry percentage.')}</p><p>${sourceLink(row.source_url,row.source_version||'Published study reference')}</p></details></article>`).join('')}</div><p class="ancestry-proportion-boundary">${esc(panel.boundary||'This additional source panel is evaluated separately. Its scores must not be mixed with the main ancestry model or other reference panels.')}</p></details></section>`;
  }
  function reveal(id){const node=$(id);if(node){node.hidden=false;if(node.tagName==='DETAILS'||'open'in node)node.open=true;}}
  function reportChanged(data,kind){
    if(!data){query='';showAllCoverage=false;}
    const agent=$('dna-agent-details');if(agent)agent.hidden=!data||$('agent-box').style.display==='none';
    const raw=$('dna-full-evidence');if(raw)raw.hidden=!lastReport;
    ['dna-quality-panel','dna-sources-panel','dna-full-evidence','dna-agent-details'].forEach(id=>{const detail=$(id);if(detail)detail.open=false;});
    if(kind==='raw')reveal('dna-quality-panel');
    const before=$('ancestry-catalog-preview');if(before)before.hidden=!!data||!catalog;
  }
  function setCatalog(data){
    if(!data||typeof data!=='object')return;catalog=data;
    let node=$('ancestry-catalog-preview');
    if(!node){node=document.createElement('details');node.id='ancestry-catalog-preview';node.className='ancestry-raw-details';$('empty-state').insertAdjacentElement('afterend',node);}
    node.hidden=!!lastReportData;
    node.innerHTML='<summary>Check available regional references <span>Search the reference catalog before uploading · no personal result</span></summary>'+coverageMarkup(catalog.requested_region_coverage||[],'catalog');
    renderCoverage('catalog','');$('catalog-coverage-search')?.addEventListener('input',event=>renderCoverage('catalog',event.target.value));
    if(currentOriginsData)render();
  }
  window.DnaAncestryStory={render,renderLocation,reportChanged,reveal,setCatalog,tabChanged};
  reportChanged(typeof lastReportData!=='undefined'?lastReportData:null,typeof lastReportKind!=='undefined'?lastReportKind:'');
  if(currentOriginsData)render();
  if(typeof dnaAncestryCatalog!=='undefined'&&dnaAncestryCatalog)setCatalog(dnaAncestryCatalog);
})();
