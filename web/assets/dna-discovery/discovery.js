/* DNA-scoped creative and evidence tools. No raw variants, identifiers, or remote requests. */
(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const finite = value => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value));
  const number = value => finite(value) ? Number(value).toLocaleString('en-GB') : '—';
  const bounded = (value, min, max) => Math.min(max, Math.max(min, Number(value) || 0));
  const label = value => String(value ?? '').replace(/_/g, ' ');
  const caption = 'DNA-inspired art · not an appearance prediction';
  const palettes = {aurora:['#84efd0','#89cde1','#e5f5ec'],ember:['#efb974','#cf8b66','#f5e6cd'],nebula:['#b6a1f7','#daafdf','#f0edf7'],silver:['#c1d2cd','#91a9af','#edf2ef']};
  const eyes = {neutral:'#becdc8',teal:'#6cddcf',amber:'#eac27b',violet:'#b4a2e4'};
  const state = {palette:'aurora',eye:'neutral',style:'constellation',energy:60,seed:1,paused:false,hasReport:false,coverage:0,appearance:null,ancient:null,selected:null,heatmapMode:'relative',visible:false,raf:0,lastFrame:0,recording:null,revision:0};
  const sound = {profile:null,buffer:null,peaks:null,source:null,gain:null,playing:false,offset:0,startedAt:0,volume:.55,preparing:false,starting:false,revision:0};
  let waveVisible = true;
  const canvas = $('dna-portrait-canvas');
  const ctx = canvas?.getContext('2d');
  const path = data => new Path2D(data);
  // Fixed illustrative geometry: never adjusted from a DNA report.
  const facePaths = [
    'M480 231 C381 228 336 306 346 423 C350 493 370 559 400 613 Q441 676 480 690 Q519 676 560 613 C590 559 610 493 614 423 C624 306 579 228 480 231 Z',
    'M411 626 C415 674 409 720 369 742 C291 770 217 801 176 891 M549 626 C545 674 551 720 591 742 C669 770 743 801 784 891',
    'M369 742 Q480 809 591 742 M400 685 Q480 758 560 685',
    'M358 411 Q402 378 447 415 M513 415 Q558 378 602 411',
    'M374 435 Q405 418 435 438 Q405 455 374 435 Z M525 438 Q555 418 586 435 Q555 455 525 438 Z',
    'M470 427 C468 461 456 489 449 515 Q458 531 480 529 Q502 531 511 515 C504 489 492 461 490 427',
    'M459 511 Q480 520 501 511 M440 573 Q459 563 480 569 Q501 563 520 573 Q480 598 440 573 Z',
    'M439 575 Q480 579 521 575 M456 629 Q480 637 504 629',
    'M350 415 Q326 400 333 459 Q340 494 357 495 M610 415 Q634 400 627 459 Q620 494 603 495'
  ];
  const geometry = facePaths.map(path);
  const shoulderPath = path('M411 647 L549 647 C545 711 578 741 621 752 Q745 793 800 921 L160 921 Q215 793 339 752 C382 741 415 711 411 647 Z');
  const faceNodes = [[480,259],[415,274],[545,274],[372,325],[588,325],[480,321],[406,337],[554,337],[360,391],[600,391],[443,385],[517,385],[480,390],[365,478],[595,478],[421,488],[539,488],[448,528],[512,528],[480,549],[386,541],[574,541],[413,595],[547,595],[480,614],[442,650],[518,650],[480,675]];
  const meshEdges = [[0,1],[0,2],[0,5],[1,3],[1,6],[1,5],[2,4],[2,7],[2,5],[3,6],[3,8],[4,7],[4,9],[5,6],[5,7],[5,12],[6,10],[6,8],[6,12],[7,11],[7,9],[7,12],[8,13],[9,14],[10,12],[11,12],[10,15],[11,16],[13,15],[14,16],[15,17],[16,18],[13,20],[14,21],[17,19],[18,19],[17,20],[18,21],[20,22],[21,23],[19,22],[19,23],[19,24],[22,24],[23,24],[22,25],[23,26],[24,25],[24,26],[25,27],[26,27]];
  const random = (i, seed=state.seed) => { const n = Math.sin(i * 127.1 + seed * 91.7) * 43758.5453; return n - Math.floor(n); };
  const canMove = () => !state.paused && (typeof dnaMotionAllowed !== 'function' || dnaMotionAllowed());
  const linkedSource = (url, text) => {
    try { const u = new URL(String(url)); if(u.protocol !== 'https:' && u.protocol !== 'http:') return escape(text); return `<a href="${escape(u.href)}" target="_blank" rel="noopener noreferrer">${escape(text)} ↗</a>`; }
    catch { return escape(text); }
  };
  function drawPortrait(target, seconds=0){
    const c=target, w=c.canvas.width, h=c.canvas.height;
    const [primary, secondary, pearl]=palettes[state.palette];
    c.save(); c.scale(w/960,h/1080); c.fillStyle='#030a09'; c.fillRect(0,0,960,1080);
    const aura=c.createRadialGradient(480,444,50,480,444,500);
    aura.addColorStop(0,primary+'23'); aura.addColorStop(.5,secondary+'0c'); aura.addColorStop(1,'#030a0900');
    c.fillStyle=aura;c.fillRect(0,0,960,1080);
    const intensity=state.energy/100;
    const starCount=Math.round(55+intensity*85+state.coverage*24);
    for(let i=0;i<starCount;i++){
      const x=50+random(i*4+1)*860, y=100+random(i*4+2)*810;
      const pulse=.35+.3*Math.sin(seconds*.65+random(i*4+3)*6);
      c.globalAlpha=bounded(pulse, .15,.7);c.fillStyle=i%4===0?secondary:primary;
      c.beginPath();c.arc(x,y,random(i*4+4)*1.3+.55,0,Math.PI*2);c.fill();
    }
    c.globalAlpha=1;
    // Orbital paths encode artistic motion, never a genomic measurement.
    for(let orbit=0;orbit<4;orbit++){
      c.save();c.translate(480,480);c.rotate((orbit-1.5)*.39+Math.sin(seconds*.12)*.045);
      c.strokeStyle=(orbit%2?secondary:primary)+'33';c.lineWidth=.9;
      c.beginPath();c.ellipse(0,0,270+orbit*35,367-orbit*21,0,0,Math.PI*2);c.stroke();
      const angle=seconds*(.11+orbit*.024)+orbit*1.8;
      c.fillStyle=pearl;c.shadowColor=primary;c.shadowBlur=17;c.beginPath();c.arc(Math.cos(angle)*(270+orbit*35),Math.sin(angle)*(367-orbit*21),2.5,0,Math.PI*2);c.fill();c.restore();
    }
    // A pair of decorative, slowly turning helixes frames the fixed portrait.
    for(let side=0;side<2;side++){
      const mid=side?781:179;
      for(let strand=0;strand<2;strand++){
        c.beginPath();for(let k=0;k<=100;k++){
          const y=260+k*5.9, x=mid+Math.sin(k*.14+seconds*.22+strand*Math.PI)*29;
          if(k===0)c.moveTo(x,y);else c.lineTo(x,y);
        }c.strokeStyle=(strand?secondary:primary)+'65';c.lineWidth=1.25;c.stroke();
      }
      c.strokeStyle=primary+'35';c.lineWidth=.8;
      for(let k=0;k<=100;k+=4){let x=Math.sin(k*.14+seconds*.22)*29;c.beginPath();c.moveTo(mid-x,260+k*5.9);c.lineTo(mid+x,260+k*5.9);c.stroke();}
    }
    // Shoulder field.
    const shoulder=c.createLinearGradient(0,696,0,947);shoulder.addColorStop(0,primary+'10');shoulder.addColorStop(1,'#030a0900');
    c.fillStyle=shoulder;c.fill(shoulderPath);
    c.fillStyle='#07120fe8';c.fill(geometry[0]);
    const head=c.createLinearGradient(330,0,630,0);head.addColorStop(0,primary+'3c');head.addColorStop(.5,pearl+'b8');head.addColorStop(1,secondary+'3c');
    c.strokeStyle=head;c.lineWidth=1.7;c.shadowColor=primary;c.shadowBlur=8;
    geometry.forEach((p,i)=>{c.globalAlpha=i>2?.8:1;c.stroke(p);});c.globalAlpha=1;c.shadowBlur=0;
    if(state.style!=='minimal'){
      c.strokeStyle=primary+(state.style==='wireframe'?'50':'23');c.lineWidth=.8;
      meshEdges.forEach(([a,b])=>{c.beginPath();c.moveTo(...faceNodes[a]);c.lineTo(...faceNodes[b]);c.stroke();});
      faceNodes.forEach(([x,y],i)=>{c.fillStyle=pearl;c.globalAlpha=.3+.4*Math.sin(seconds*.35+i*.53)**2;c.beginPath();c.arc(x,y,state.style==='wireframe'?1.6:2.2,0,Math.PI*2);c.fill();});c.globalAlpha=1;
    }
    for(const x of [405,555]){
      c.save();c.fillStyle=eyes[state.eye];c.shadowColor=eyes[state.eye];c.shadowBlur=state.eye==='neutral'?5:17;
      c.globalAlpha=.75;c.beginPath();c.ellipse(x,436,6.4,8,0,0,Math.PI*2);c.fill();c.fillStyle='#04110c';c.beginPath();c.arc(x,436,2.9,0,Math.PI*2);c.fill();c.restore();
    }
    // Repeated torso points are decorative and fixed across all reports.
    if(state.style!=='minimal')for(let row=0;row<7;row++)for(let col=0;col<13;col++){
      const x=243+col*39, y=788+row*17+Math.abs(col-6)*6;
      c.fillStyle=primary+'35';c.beginPath();c.arc(x,y,1.1,0,Math.PI*2);c.fill();
    }
    c.strokeStyle=primary+'45';c.beginPath();c.moveTo(110,941);c.lineTo(850,941);c.stroke();
    c.textAlign='center';c.fillStyle=pearl;c.font='500 23px system-ui, sans-serif';c.fillText('DNA PORTRAIT STUDIO',480,982);
    c.font='16px system-ui, sans-serif';c.fillStyle='#afc8bb';c.fillText(caption,480,1021);
    c.font='12px system-ui, sans-serif';c.fillStyle='#77968a';c.fillText('Fixed facial geometry  ·  Manually chosen palette',480,1048);
    c.restore();
  }
  function elapsed(){return sound.playing ? Math.min(sound.buffer?.duration||0,sound.offset+(dnaAudioCtx.currentTime-sound.startedAt)) : sound.offset;}
  const time = value => `${Math.floor(Math.max(0,value||0)/60)}:${String(Math.floor(Math.max(0,value||0)%60)).padStart(2,'0')}`;
  function transport(){
    const duration=sound.buffer?.duration||Number(sound.profile?.duration_seconds)||0, position=elapsed();
    $('discovery-sound-time').textContent=`${time(position)} / ${time(duration)}`;
    $('discovery-sound-seek').value=duration?Math.round(position/duration*1000):0;
    $('discovery-sound-seek').setAttribute('aria-valuetext',`${time(position)} of ${time(duration)}`);
    $('discovery-sound-seek').disabled=!sound.buffer;
    $('discovery-sound-play').innerHTML=sound.playing?'❚❚ Pause DNA Sound':(sound.offset?'▶ Resume DNA Sound':'▶ Play DNA Sound');
    $('discovery-sound-play').disabled=!sound.profile||sound.preparing||sound.starting;
    $('discovery-sound-download').disabled=!sound.profile||sound.preparing||sound.starting;
  }
  function drawWave(){
    const wave=$('dna-wave'), c=wave.getContext('2d'),w=wave.width,h=wave.height;
    if(!sound.peaks){
      if(sound.profile){drawDnaWave(sound.profile);return;}
      c.fillStyle='#04100d';c.fillRect(0,0,w,h);c.strokeStyle='#799d8844';c.beginPath();c.moveTo(18,h/2);c.lineTo(w-18,h/2);c.stroke();
      c.fillStyle='#9eb5a8';c.font='15px system-ui, sans-serif';c.textAlign='center';c.fillText('Your composition appears here after analysis',w/2,h/2-18);return;
    }
    c.fillStyle='#03100b';c.fillRect(0,0,w,h);
    const pos=(sound.buffer?.duration?elapsed()/sound.buffer.duration:0)*w, bw=w/sound.peaks.length;
    sound.peaks.forEach((v,i)=>{
      const bh=Math.max(2,v*(h-28));c.fillStyle=i*bw<=pos?'#a3efd2':'#597c705e';c.fillRect(i*bw,h/2-bh/2,Math.max(1,bw-.75),bh);
    });
    c.strokeStyle='#e5d2aa';c.lineWidth=1.5;c.beginPath();c.moveTo(pos,8);c.lineTo(pos,h-8);c.stroke();
  }
  function tick(now){
    state.raf=0;
    if(!document.hidden && now-state.lastFrame>32){
      state.lastFrame=now;
      if(state.visible && ctx)drawPortrait(ctx,canMove()?now/1000:0);
      if(sound.playing && waveVisible){transport();drawWave();}
    }
    schedule();
  }
  function schedule(){if(!state.raf&&!document.hidden&&((state.visible&&canMove())||(sound.playing&&waveVisible)))state.raf=requestAnimationFrame(tick);}
  function motionChanged(){
    const allowed=canMove();if(!allowed)cancelRecording(false);$('portrait-motion').textContent=allowed?'Pause portrait':'Resume portrait';$('portrait-motion').setAttribute('aria-pressed',String(!allowed));
    const reduced=typeof dnaReducedMotion!=='undefined'&&dnaReducedMotion.matches;
    $('portrait-motion').disabled=reduced|| (typeof dnaMotionPaused!=='undefined'&&dnaMotionPaused);
    if(reduced)$('portrait-motion').textContent='Reduced motion is on';
    else if(typeof dnaMotionPaused!=='undefined'&&dnaMotionPaused)$('portrait-motion').textContent='Page motion is paused';
    if(ctx)drawPortrait(ctx,0);schedule();
  }
  function updatePortraitNotes(){
    $('portrait-mode').textContent=state.hasReport?'Summary-inspired artwork':'Neutral artwork';
    $('portrait-pattern-note').textContent=state.hasReport?'The surrounding star density reflects broad report-coverage bands. Facial geometry stays fixed; your palette and eye glow remain artistic choices.':'Neutral illustration. Run a full report to let summary-level evidence shape the surrounding aura.';
  }
  function renderAppearance(){
    const a=state.appearance, eye=a?.eye_color;
    if(!eye){$('portrait-trait-evidence').innerHTML='<p>No appearance result is attached to the current selection. Run a full report to inspect the observed research markers. The portrait remains a neutral illustration.</p>';return;}
    const markers=Array.isArray(eye.markers)?eye.markers:[], research=eye.research_context;
    const context=research?`<div class="trait-research"><strong>${escape(research.observation||'Observed HERC2 research marker')}</strong><p>${escape(research.summary)}</p><p>${escape(research.limitation)}</p><div class="trait-source">${linkedSource(research.source_url,research.source_name||'Published association')} · ${escape(research.population_context||'Population context is limited')}</div></div>`:'';
    $('portrait-trait-evidence').innerHTML=`<span class="trait-coverage">${number(eye.observed_marker_count)} / ${number(eye.required_marker_count)} research markers observed</span><p>${escape(a.plain||'Exact source-marker checks are available. A validated appearance prediction is not installed.')}</p><p><strong>Neutral eyes in the portrait.</strong> Marker observations do not set the illustration’s eye color.</p>${context}<details><summary>Inspect markers and sources</summary><div class="trait-markers">${markers.map(m=>`<div><b>${escape(m.gene||m.rsid)}</b><span>${escape(m.rsid)} · ${escape(m.label||label(m.status))}</span>${linkedSource(m.source_url,'Source record')}</div>`).join('')}</div><p class="trait-source">${escape(eye.source_name||'Source-marker evidence')} · ${escape(eye.source_version||'')}</p><ul>${(Array.isArray(eye.limitations)?eye.limitations:[]).map(v=>`<li>${escape(v)}</li>`).join('')}</ul></details>`;
  }
  function stopSource(){
    const source=sound.source;sound.source=null;sound.playing=false;
    if(source){source.onended=null;try{source.stop();}catch{}try{source.disconnect();}catch{}}
    if(sound.gain){try{sound.gain.disconnect();}catch{}sound.gain=null;}
    dnaAudioSource=null;
  }
  function stopSound(){sound.revision++;sound.preparing=false;sound.starting=false;stopSource();sound.offset=0;transport();drawWave();$('discovery-sound-state').textContent=sound.profile?'Stopped':'Ready after analysis';}
  function soundReady(profile){
    const changed=profile!==sound.profile;
    if(changed){stopSound();sound.profile=profile||null;sound.buffer=null;sound.peaks=null;sound.offset=0;}
    $('discovery-sound-download').disabled=!profile;
    $('discovery-sound-state').textContent=profile?'Composition ready · press Play':'Ready after analysis';
    if(!profile)$('dna-sound-note').textContent='A full report automatically prepares your DNA composition. Choose Play when you are ready, or use Generate DNA Sound after selecting your file and confirming consent.';
    transport();drawWave();
  }
  async function prepareSound(){
    if(sound.buffer)return sound.buffer;
    if(!sound.profile)throw new Error('Select your file and run a full report or Generate DNA Sound first.');
    const revision=sound.revision;sound.preparing=true;$('discovery-sound-state').textContent='Synthesizing sound locally…';transport();
    try{
      // Yield once so the honest synthesis state is painted before the DSP work.
      await new Promise(resolve=>setTimeout(resolve,20));
      if(revision!==sound.revision)return null;
      const buffer=await buildDnaAudioBuffer();
      if(revision!==sound.revision){if(lastDnaAudioBuffer===buffer)lastDnaAudioBuffer=null;return null;}
      sound.buffer=buffer;
      const channel=buffer.getChannelData(0), bins=350, step=Math.max(1,Math.floor(channel.length/bins));
      const peaks=[];
      for(let b=0;b<bins;b++){let peak=0;for(let i=b*step;i<Math.min(channel.length,(b+1)*step);i+=8)peak=Math.max(peak,Math.abs(channel[i]));peaks.push(peak);}
      const max=Math.max(.01,...peaks);sound.peaks=peaks.map(v=>v/max*.87);
      return buffer;
    }finally{if(revision===sound.revision){sound.preparing=false;transport();drawWave();}}
  }
  async function playSound(){
    if(sound.preparing||sound.starting)return;
    if(sound.playing){sound.offset=elapsed();stopSource();transport();drawWave();$('discovery-sound-state').textContent='Paused';return;}
    const revision=sound.revision;sound.starting=true;transport();
    try{
      const audio=ensureAudioContext();if(audio.state==='suspended')await audio.resume();
      if(revision!==sound.revision)return;
      const buffer=await prepareSound();if(!buffer||revision!==sound.revision)return;
      if(sound.offset>=buffer.duration)sound.offset=0;
      const source=audio.createBufferSource(),gain=audio.createGain();source.buffer=buffer;gain.gain.value=sound.volume;source.connect(gain).connect(audio.destination);
      sound.source=source;sound.gain=gain;sound.startedAt=audio.currentTime;sound.playing=true;dnaAudioSource=source;
      source.onended=()=>{if(sound.source!==source)return;stopSource();sound.offset=buffer.duration;transport();drawWave();$('discovery-sound-state').textContent='Finished';};
      source.start(0,sound.offset);$('discovery-sound-state').textContent='Playing · actual audio waveform';transport();drawWave();schedule();
    }catch(error){sound.preparing=false;$('discovery-sound-state').textContent=error.message||'Audio could not be played.';}
    finally{if(revision===sound.revision){sound.starting=false;transport();}}
  }
  function seekSound(value){if(!sound.buffer)return;const playing=sound.playing;stopSource();sound.offset=bounded(value,0,1000)/1000*sound.buffer.duration;transport();drawWave();if(playing)playSound();else $('discovery-sound-state').textContent='Position selected';}
  async function downloadSound(){
    if(sound.preparing||sound.starting)return;const revision=sound.revision;
    try{const buffer=await prepareSound();if(!buffer||revision!==sound.revision)return;download(audioBufferToWav(buffer),'dna-sound.wav');$('discovery-sound-state').textContent=sound.playing?'Playing · WAV saved':'WAV saved locally';}
    catch(error){$('discovery-sound-state').textContent=error.message||'The WAV could not be created.';}
  }
  function volume(value){sound.volume=bounded(value,0,100)/100;$('discovery-sound-volume').setAttribute('aria-valuetext',`${Math.round(sound.volume*100)} percent`);if(sound.gain)sound.gain.gain.setTargetAtTime(sound.volume,dnaAudioCtx.currentTime,.04);}
  function chapterRows(){return (Array.isArray(state.ancient?.chromosome_breakdown)?state.ancient.chromosome_breakdown:[]).filter(row=>/^(?:[1-9]|1\d|2[0-2])$/.test(String(row.chromosome)));}
  function chapterGroups(){return Array.isArray(state.ancient?.groups)?state.ancient.groups:[];}
  function heatColor(value){
    if(!finite(value))return '#18241e';
    const v=state.heatmapMode==='relative'?Number(value):(Number(value)-50);
    const strength=Math.min(.85,.10+Math.abs(v)/12*.65);
    return v>0?`rgba(190,133,62,${strength.toFixed(3)})`:v<0?`rgba(63,147,136,${strength.toFixed(3)})`:'#263b31';
  }
  const score = value => finite(value)?Number(value).toFixed(1):'—';
  const signed = value => finite(value)?`${Number(value)>0?'+':''}${Number(value).toFixed(1)}`:'—';
  function renderChromosomeSelection(){
    const el=$('discovery-chromosome-selection');if(!el)return;
    const rows=chapterRows(), groups=chapterGroups(), selected=state.selected;
    const row=rows.find(v=>String(v.chromosome)===String(selected?.chromosome)), group=groups.find(g=>g.code===selected?.code);
    const result=row?.groups?.find(g=>g.code===group?.code);
    if(!row||!group||!result){el.innerHTML='<p>Select a colored cell to inspect the markers supporting that comparison. Gray cells have no reported comparison.</p>';return;}
    el.innerHTML=`<p class="eyebrow">CHROMOSOME ${escape(row.chromosome)} · SOURCE REFERENCE</p><h3>${escape(group.label||group.code)}</h3><p><strong>${score(result.similarity_score)} / 100 similarity</strong> · ${signed(result.relative_to_panel_mean)} points from the panel mean on this chromosome.</p><p>${number(row.matched_markers)} shared markers tested · ${number(row.homozygous_calls)} homozygous calls · ${number(row.heterozygous_calls)} heterozygous calls.</p><p>${number(row.differentiating_loci)} tested markers can distinguish these source groups in this comparison. All groups use the same markers for this chromosome.</p><p class="trait-source">${escape(group.region||'Ancient reference group')} · ${escape(group.era||'')} · ${linkedSource(group.source_url,group.source_version||'Ancient reference source')}</p>`;
  }
  function renderChromosomes(){
    const host=$('discovery-chromosome-content'), rows=chapterRows(),groups=chapterGroups();
    if(!rows.length||!groups.length){host.innerHTML=`<div class="chromosome-empty-track" aria-hidden="true">${'<i></i>'.repeat(22)}</div><div class="discovery-explainer"><strong>Your evidence, across 22 autosomes.</strong> Run a full or ancient report to compare the ancient reference groups on each chromosome. This view uses actual shared-marker results when available.</div>`;return;}
    if(!state.selected||!groups.some(g=>g.code===state.selected.code))state.selected={code:groups[0].code,chromosome:String(rows[0].chromosome)};
    const diag=state.ancient.score_diagnostics||{},rank=state.ancient.rank_stability||{};
    const retained=finite(rank.unique_lead_retained_count)?`${number(rank.unique_lead_retained_count)} / ${number(rank.evaluated_omissions)}`:(rank.status==='no_unique_pooled_lead'?'No unique lead':number(rank.evaluated_omissions));
    host.innerHTML=`<div class="chromosome-toolbar"><p>Every column compares groups on exactly the same usable markers.<br>Use the arrow keys to explore the table, or select any cell.</p><label class="sr-only" for="discovery-heatmap-mode">Chromosome color scale</label><select id="discovery-heatmap-mode"><option value="relative"${state.heatmapMode==='relative'?' selected':''}>Contrast · points from the panel mean</option><option value="absolute"${state.heatmapMode==='absolute'?' selected':''}>Similarity · score out of 100</option></select></div><div class="chromosome-metrics"><div><b>${number(diag.common_marker_count??rows.reduce((sum,r)=>sum+Number(r.matched_markers||0),0))}</b><span>Shared ancient markers tested</span></div><div><b>${rows.length} / 22</b><span>Autosomes with a comparison</span></div><div><b>${retained}</b><span>${finite(rank.unique_lead_retained_count)?'Omissions retaining the same unique lead':rank.status==='no_unique_pooled_lead'?'Top groups overlap in the pooled score':'Chromosome omission comparisons'}</span></div></div><div class="chromosome-heatmap-wrap" tabindex="0" role="region" aria-label="Scrollable ancient chromosome evidence comparison"><table class="chromosome-heatmap"><caption class="sr-only">Ancient reference similarity by chromosome. Scores are not ancestry percentages.</caption><thead><tr><th scope="col">Ancient reference</th>${Array.from({length:22},(_,i)=>`<th scope="col">${i+1}</th>`).join('')}</tr></thead><tbody>${groups.map((g,gi)=>`<tr><th scope="row">${escape(g.label||g.code)}</th>${Array.from({length:22},(_,i)=>{
      const row=rows.find(r=>Number(r.chromosome)===i+1),v=row?.groups?.find(r=>r.code===g.code), value=state.heatmapMode==='relative'?v?.relative_to_panel_mean:v?.similarity_score,selected=g.code===state.selected.code&&String(i+1)===String(state.selected.chromosome);
      return `<td><button type="button" data-chromosome="${i+1}" data-group="${escape(g.code)}" data-row="${gi}" tabindex="${selected?'0':'-1'}" class="${selected?'selected':''}" style="background:${heatColor(value)}" aria-label="${escape(g.label||g.code)}, chromosome ${i+1}: ${finite(value)?(state.heatmapMode==='relative'?signed(value)+' points from panel mean':score(value)+' out of 100 similarity')+', '+number(row.matched_markers)+' markers tested':'no comparison'}" aria-pressed="${selected}">${finite(value)?(state.heatmapMode==='relative'?signed(value):score(value)):'–'}</button></td>`;
    }).join('')}</tr>`).join('')}</tbody></table></div><div class="heatmap-key"><span><i style="background:#327d73"></i>${state.heatmapMode==='relative'?'Below panel mean':'Below 50'}</span><span><i style="background:#263b31"></i>${state.heatmapMode==='relative'?'At panel mean':'50 similarity'}</span><span><i style="background:#b17a39"></i>${state.heatmapMode==='relative'?'Above panel mean':'Above 50'}</span><span>Gray · no data</span></div><div class="chromosome-selection" id="discovery-chromosome-selection" aria-live="polite"></div><div class="discovery-explainer"><strong>What moves these scores?</strong> ${escape(diag.score_explainer||'Heterozygous calls contribute equally to all reference groups; differences come from informative homozygous calls.')} ${escape(diag.contrast_explainer||'')}</div><details class="chromosome-method"><summary>Coverage, stability and method</summary><p>${escape(rank.plain||'No chromosome omission stability result is available for this report.')}</p><p>Leaving one chromosome out measures sensitivity to coverage. It is not a probability or a calibrated confidence score.</p><p>${escape(rank.equal_chromosome_weighting?.method||'')}</p><p>${number(diag.differentiating_loci)} differentiating loci · ${number(diag.heterozygous_calls)} heterozygous calls · ${number(diag.homozygous_calls)} homozygous calls.</p>${diag.dominant_chromosome?`<p>Chromosome ${escape(diag.dominant_chromosome.chromosome)} supplies ${number(diag.dominant_chromosome.matched_markers)} markers (${(bounded(diag.dominant_chromosome.fraction_of_common_loci,0,1)*100).toFixed(1)}% of the tested marker coverage).</p>`:''}<p>${escape(diag.shared_alleles_explainer||'')}</p></details>`;
    $('discovery-heatmap-mode').addEventListener('change',e=>{state.heatmapMode=e.target.value;renderChromosomes();});
    host.querySelectorAll('[data-chromosome]').forEach(button=>{
      button.addEventListener('click',()=>selectChromosome(button));
      button.addEventListener('keydown',event=>{
        let r=Number(button.dataset.row),c=Number(button.dataset.chromosome);const keys={ArrowLeft:[0,-1],ArrowRight:[0,1],ArrowUp:[-1,0],ArrowDown:[1,0]};if(!keys[event.key])return;
        event.preventDefault();r=bounded(r+keys[event.key][0],0,groups.length-1);c=bounded(c+keys[event.key][1],1,22);
        const next=host.querySelector(`[data-row="${r}"][data-chromosome="${c}"]`);if(next){selectChromosome(next);next.focus();}
      });
    });
    renderChromosomeSelection();
  }
  function selectChromosome(button){state.selected={code:button.dataset.group,chromosome:button.dataset.chromosome};$('discovery-chromosome-content').querySelectorAll('[data-chromosome]').forEach(b=>{const selected=b===button;b.classList.toggle('selected',selected);b.tabIndex=selected?0:-1;b.setAttribute('aria-pressed',String(selected));});renderChromosomeSelection();}
  function setReport(report,kind){
    if(!report||typeof report!=='object'){invalidate('The current lookup has no creative evidence attached. Run a full report to update this studio.');return;}
    state.revision++;cancelRecording(false);stopSound();
    const origins=typeof getDnaOrigins==='function'?getDnaOrigins(report):report.origins;
    // Focused reports can reuse already measured creative results for this selection.
    // File/build/orientation changes explicitly invalidate them before another report.
    state.ancient=origins?.ancient||state.ancient;state.appearance=report.appearance||state.appearance;
    state.hasReport=true;
    const coverage=Number(state.ancient?.score_diagnostics?.common_marker_count||report.summary?.variant_count||report.summary?.unique_variants||0);
    // Broad bands intentionally avoid encoding file hashes or individual variants.
    state.coverage=coverage>20000?3:coverage>3000?2:coverage>0?1:0;
    state.selected=null;
    const profile=report.sound_profile||(kind==='sound'?report.profile:null)||sound.profile||null;
    if(profile!==sound.profile){lastDnaAudioBuffer=null;}
    lastDnaSoundProfile=profile;renderDnaSound(profile);
    updatePortraitNotes();renderAppearance();renderChromosomes();if(ctx)drawPortrait(ctx,0);schedule();
  }
  function invalidate(message){
    state.revision++;cancelRecording(false);stopSound();state.hasReport=false;state.coverage=0;state.appearance=null;state.ancient=null;state.selected=null;
    lastDnaSoundProfile=null;lastDnaAudioBuffer=null;soundReady(null);updatePortraitNotes();renderAppearance();renderChromosomes();
    if(message){$('portrait-pattern-note').textContent=message;$('discovery-sound-state').textContent='Current selection needs analysis';}
    if(ctx)drawPortrait(ctx,0);
  }
  function openStudio(kind){
    if(kind==='sound'){$('dna-sound-panel').scrollIntoView({behavior:canMove()?'smooth':'auto',block:'center'});$('discovery-sound-play').focus({preventScroll:true});return;}
    const panel=$(kind==='ancient'?'dna-chromosome-studio':'dna-portrait-studio');panel.hidden=false;
    if(kind==='portrait'){state.visible=true;if(ctx)drawPortrait(ctx,0);schedule();}else renderChromosomes();
    panel.scrollIntoView({behavior:canMove()?'smooth':'auto',block:'start'});const heading=panel.querySelector('h2');heading.tabIndex=-1;heading.focus({preventScroll:true});
  }
  function closeStudio(kind){const panel=$(kind==='ancient'?'dna-chromosome-studio':'dna-portrait-studio');panel.hidden=true;if(kind==='portrait'){state.visible=false;cancelRecording(false);}document.querySelector(`.discovery-launch button[onclick*="'${kind}'"]`)?.focus();}
  function configure(name,value){
    if(name==='palette'&&palettes[value])state.palette=value;
    if(name==='eye'&&eyes[value])state.eye=value;
    if(name==='style'&&['constellation','wireframe','minimal'].includes(value))state.style=value;
    if(name==='energy')state.energy=bounded(value,0,100);
    if(ctx)drawPortrait(ctx,0);
  }
  function download(blob,filename){const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=filename;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);}
  function exportPng(){const revision=state.revision,out=document.createElement('canvas');out.width=960;out.height=1080;drawPortrait(out.getContext('2d'),0);out.toBlob(blob=>{if(revision!==state.revision)return;if(blob){download(blob,'dna-portrait-art.png');$('portrait-export-note').textContent='PNG saved with its artistic label. No DNA rows or file identifiers are embedded.';}else $('portrait-export-note').textContent='PNG export is unavailable in this browser.';},'image/png');}
  function svgText(){
    const [primary,secondary,pearl]=palettes[state.palette];
    const stars=Array.from({length:Math.round(55+state.energy*.85+state.coverage*24)},(_,i)=>`<circle cx="${(50+random(i*4+1)*860).toFixed(1)}" cy="${(100+random(i*4+2)*810).toFixed(1)}" r="${(random(i*4+4)*1.3+.55).toFixed(2)}" fill="${i%4?primary:secondary}" opacity=".45"/>`).join('');
    const mesh=state.style==='minimal'?'':meshEdges.map(([a,b])=>`<path d="M${faceNodes[a].join(' ')} L${faceNodes[b].join(' ')}" stroke="${primary}" opacity=".25"/>`).join('')+faceNodes.map(([x,y])=>`<circle cx="${x}" cy="${y}" r="2" fill="${pearl}" opacity=".65"/>`).join('');
    const helix=[179,781].map(mid=>[0,1].map(strand=>`<path d="${Array.from({length:101},(_,k)=>`${k?'L':'M'}${(mid+Math.sin(k*.14+strand*Math.PI)*29).toFixed(2)} ${(260+k*5.9).toFixed(2)}`).join(' ')}" stroke="${strand?secondary:primary}" opacity=".5"/>`).join('')).join('');
    return `<svg xmlns="http://www.w3.org/2000/svg" width="960" height="1080" viewBox="0 0 960 1080" role="img" aria-labelledby="title desc"><title id="title">DNA Portrait Studio — stylized art</title><desc id="desc">${caption}. Fixed facial geometry and manually chosen colors. No genetic rows or personal file identifiers are included.</desc><defs><radialGradient id="aura"><stop stop-color="${primary}" stop-opacity=".15"/><stop offset="1" stop-color="#030a09" stop-opacity="0"/></radialGradient></defs><rect width="960" height="1080" fill="#030a09"/><ellipse cx="480" cy="444" rx="490" ry="480" fill="url(#aura)"/>${stars}<g fill="none" stroke-width="1">${[0,1,2,3].map(i=>`<ellipse cx="480" cy="480" rx="${270+i*35}" ry="${367-i*21}" transform="rotate(${(i-1.5)*22.3} 480 480)" stroke="${i%2?secondary:primary}" opacity=".22"/>`).join('')}${helix}</g><path d="${facePaths[0]}" fill="#07120f"/><g stroke="${pearl}" stroke-width="1.5" fill="none" opacity=".75">${facePaths.map(d=>`<path d="${d}"/>`).join('')}</g><g fill="none">${mesh}</g>${[405,555].map(x=>`<ellipse cx="${x}" cy="436" rx="6.4" ry="8" fill="${eyes[state.eye]}"/><circle cx="${x}" cy="436" r="2.9" fill="#04110c"/>`).join('')}<path d="M110 941 H850" stroke="${primary}" opacity=".4"/><g text-anchor="middle" font-family="system-ui,sans-serif"><text x="480" y="982" fill="${pearl}" font-size="23">DNA PORTRAIT STUDIO</text><text x="480" y="1021" fill="#afc8bb" font-size="16">${caption}</text><text x="480" y="1048" fill="#77968a" font-size="12">Fixed facial geometry · Manually chosen palette</text></g></svg>`;
  }
  function exportSvg(){download(new Blob([svgText()],{type:'image/svg+xml;charset=utf-8'}),'dna-portrait-art.svg');$('portrait-export-note').textContent='SVG saved: editable vector artwork with its visible artistic label.';}
  function cancelRecording(save){
    const rec=state.recording;if(!rec)return;rec.save=!!save;cancelAnimationFrame(rec.raf);clearTimeout(rec.timer);if(rec.recorder.state!=='inactive')rec.recorder.stop();else rec.stream.getTracks().forEach(track=>track.stop());
    state.recording=null;$('portrait-record').disabled=false;$('portrait-record').textContent='Save animated WebM';
    if(!save)$('portrait-export-note').textContent='Animation export cancelled. Your local selection changed or the studio closed.';
  }
  async function exportAnimation(){
    if(state.recording){cancelRecording(false);return;}
    const mime=['video/webm;codecs=vp9','video/webm;codecs=vp8','video/webm'].find(type=>window.MediaRecorder?.isTypeSupported(type));
    if(!mime||!canvas?.captureStream){$('portrait-export-note').textContent='This browser cannot record WebM canvas animation. PNG and SVG exports are available.';return;}
    if(!canMove()){$('portrait-export-note').textContent='Animation export follows your motion setting. Resume motion to record, or save a still PNG or SVG.';return;}
    let pendingStream=null;
    try{
      const out=document.createElement('canvas');out.width=720;out.height=810;const c=out.getContext('2d');drawPortrait(c,0);
      const stream=out.captureStream(24);pendingStream=stream;
      const track=stream.getVideoTracks()[0],recorder=new MediaRecorder(stream,{mimeType:mime,videoBitsPerSecond:3000000}),chunks=[];
      const rec={recorder,stream,raf:0,timer:0,save:false,start:0,lastDraw:0,revision:state.revision};state.recording=rec;
      recorder.ondataavailable=event=>{if(event.data.size)chunks.push(event.data);};
      recorder.onstop=()=>{stream.getTracks().forEach(t=>t.stop());if(rec.save&&rec.revision===state.revision&&chunks.length){download(new Blob(chunks,{type:mime}),'dna-portrait-art.webm');$('portrait-export-note').textContent='WebM saved locally, with the artistic label. No audio or camera recording was used.';}};
      recorder.onerror=()=>{cancelRecording(false);$('portrait-export-note').textContent='Animation recording failed. PNG and SVG exports are still available.';};
      const frame=now=>{if(state.recording!==rec)return;const sec=(now-rec.start)/1000;if(now-rec.lastDraw>=1000/24){drawPortrait(c,sec);track.requestFrame?.();rec.lastDraw=now;$('portrait-record').textContent=`Recording · ${Math.min(6,Math.floor(sec))}s · cancel`;}rec.raf=requestAnimationFrame(frame);};
      recorder.onstart=()=>{if(state.recording!==rec)return;rec.start=performance.now();drawPortrait(c,0);track.requestFrame?.();rec.raf=requestAnimationFrame(frame);rec.timer=setTimeout(()=>{if(state.recording!==rec)return;cancelAnimationFrame(rec.raf);drawPortrait(c,6);track.requestFrame?.();rec.timer=setTimeout(()=>cancelRecording(true),120);},6000);};
      recorder.start();$('portrait-record').textContent='Starting recording · cancel';
      $('portrait-export-note').textContent='Recording a short code-generated animation in your browser. Keep this page visible; capture duration and frame rate depend on your device.';
    }catch(error){cancelRecording(false);pendingStream?.getTracks().forEach(track=>track.stop());$('portrait-export-note').textContent='Animation export could not start: '+(error.message||'unsupported browser feature');}
  }
  window.DnaDiscovery={open:openStudio,close:closeStudio,configure,shuffle(){state.seed=(state.seed+1)%1000;if(ctx)drawPortrait(ctx,0);},toggleMotion(){state.paused=!state.paused;if(state.paused)cancelRecording(false);motionChanged();},motionChanged,setReport,invalidate,soundReady,playSound,stopSound,seekSound,volume,downloadSound,exportPng,exportSvg,exportAnimation};
  if(window.IntersectionObserver){
    const observer=new IntersectionObserver(entries=>{for(const entry of entries){if(entry.target===canvas)state.visible=entry.isIntersecting&&!$('dna-portrait-studio').hidden;else waveVisible=entry.isIntersecting;}schedule();},{threshold:0});
    observer.observe(canvas);observer.observe($('dna-wave'));
  }
  document.addEventListener('visibilitychange',()=>{if(document.hidden){cancelRecording(false);if(state.raf){cancelAnimationFrame(state.raf);state.raf=0;}}else{transport();schedule();}});
  window.addEventListener('pagehide',()=>{cancelRecording(false);stopSound();if(state.raf)cancelAnimationFrame(state.raf);state.raf=0;lastDnaAudioBuffer=null;sound.buffer=null;sound.peaks=null;});
  window.addEventListener('pageshow',()=>{transport();drawWave();motionChanged();schedule();});
  // Source-alignment changes invalidate earlier creative evidence just like a new file.
  ['declared-build','reported-orientation'].forEach(id=>$(id)?.addEventListener('change',()=>invalidate('Alignment settings changed. Run a report to update the evidence for this selection.')));
  renderAppearance();renderChromosomes();soundReady(typeof lastDnaSoundProfile!=='undefined'?lastDnaSoundProfile:null);updatePortraitNotes();motionChanged();
  if(typeof lastReportData!=='undefined'&&lastReportData)setReport(lastReportData,typeof lastReportKind!=='undefined'?lastReportKind:'full');
})();
