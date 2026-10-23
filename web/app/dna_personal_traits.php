<?php
// Direct, sourced everyday-trait associations. No imputation, phenotype probabilities,
// genome-wide map, raw-call output, network requests, or individual findings in AI context.
require_once __DIR__ . '/dna_traits.php';

function zlp_marker_catalog() {
    // NCBI RefSNP GRCh37.p13 / GRCh38.p14 forward SPDI, verified 2026-09-05.
    // Only the paper's studied allele pair is accepted, not every dbSNP allele.
    return [
        'rs17822931' => ['gene'=>'ABCC11','chromosome'=>'16','positions'=>['GRCh37'=>48258198,'GRCh38'=>48224287],'ref'=>'C','alt'=>'T'],
        'rs713598' => ['gene'=>'TAS2R38','chromosome'=>'7','positions'=>['GRCh37'=>141673345,'GRCh38'=>141973545],'ref'=>'C','alt'=>'G'],
        'rs1726866' => ['gene'=>'TAS2R38','chromosome'=>'7','positions'=>['GRCh37'=>141672705,'GRCh38'=>141972905],'ref'=>'G','alt'=>'A'],
        'rs10246939' => ['gene'=>'TAS2R38','chromosome'=>'7','positions'=>['GRCh37'=>141672604,'GRCh38'=>141972804],'ref'=>'T','alt'=>'C'],
        'rs4988235' => ['gene'=>'MCM6 / LCT regulatory region','chromosome'=>'2','positions'=>['GRCh37'=>136608646,'GRCh38'=>135851076],'ref'=>'G','alt'=>'A','persistence_allele'=>'A'],
        'rs145946881' => ['gene'=>'MCM6 / LCT regulatory region','chromosome'=>'2','positions'=>['GRCh37'=>136608746,'GRCh38'=>135851176],'ref'=>'C','alt'=>'G','persistence_allele'=>'G'],
        'rs41380347' => ['gene'=>'MCM6 / LCT regulatory region','chromosome'=>'2','positions'=>['GRCh37'=>136608651,'GRCh38'=>135851081],'ref'=>'A','alt'=>'C','persistence_allele'=>'C'],
        'rs41525747' => ['gene'=>'MCM6 / LCT regulatory region','chromosome'=>'2','positions'=>['GRCh37'=>136608643,'GRCh38'=>135851073],'ref'=>'G','alt'=>'C','persistence_allele'=>'C'],
    ];
}

function zlp_add_record(&$states, $record, $catalog, $coordinates, $assembly) {
    $id = strtolower(trim((string) ($record['rsid'] ?? '')));
    $targets = isset($catalog[$id]) ? [$id] : [];
    $chr = preg_replace('/^chr/i', '', trim((string) ($record['chromosome'] ?? '')));
    $pos = trim((string) ($record['position'] ?? ''));
    if (ctype_digit($pos) && isset($coordinates[$chr . ':' . (int) $pos])) { $targets[] = $coordinates[$chr . ':' . (int) $pos]; }
    foreach (array_unique($targets) as $target) {
        $item = zlt_check_marker_record($record, $target, $catalog[$target], $assembly);
        if (isset($states[$target]) && $states[$target] !== $item) { $states[$target] = ['status'=>'conflicting_records']; }
        elseif (!isset($states[$target])) { $states[$target] = $item; }
    }
}

function zlp_coordinate_index($catalog, $assembly) {
    $index = [];
    foreach ($catalog as $id=>$marker) { $index[$marker['chromosome'] . ':' . $marker['positions'][$assembly]] = $id; }
    return $index;
}

function zlp_scan_path($path, $summary, $max_rows = 1200000) {
    $gate = zlt_file_gate($summary); $states = [];
    if (!$gate['ready']) { return ['states'=>[], 'summary'=>$summary, 'scanned_rows'=>0]; }
    $handle = is_file($path) && is_readable($path) ? fopen($path, 'rb') : false;
    if (!$handle) { $summary['personal_traits_scan_error'] = 'file_unavailable'; return ['states'=>[], 'summary'=>$summary, 'scanned_rows'=>0]; }
    $catalog = zlp_marker_catalog(); $coordinates = zlp_coordinate_index($catalog, $gate['assembly']);
    $header = []; $vcf_mode = false; $data_lines = 0;
    // One streaming pass retains at most eight per-locus states. Every contradictory
    // target record remains effective, including aliases and late duplicate rows.
    while (($line = fgets($handle, 65538)) !== false) {
        if (strlen($line) >= 65537 && substr($line, -1) !== "\n" && !feof($handle)) { $summary['personal_traits_scan_error'] = 'line_limit_reached'; break; }
        $line = trim($line);
        if ($line === '' || strpos($line, '##') === 0) { continue; }
        if (strpos($line, '#') === 0) {
            if (stripos($line, '#CHROM') === 0) { $header = zl_dna_raw_header_map(zl_dna_raw_split_row(ltrim($line, '#'))); $vcf_mode = true; }
            continue;
        }
        $cols = zl_dna_raw_split_row($line);
        $candidate_header = zl_dna_raw_header_map($cols);
        if ((isset($candidate_header['rsid']) || isset($candidate_header['snp']) || isset($candidate_header['snp_name'])) && (isset($candidate_header['chromosome']) || isset($candidate_header['chrom']) || isset($candidate_header['chr']))) {
            $header = $candidate_header; $vcf_mode = false; continue;
        }
        if (++$data_lines > $max_rows) { $summary['truncated'] = true; break; }
        $is_vcf = $vcf_mode || (count($cols) >= 8 && is_numeric((string) ($cols[1] ?? '')));
        if ($is_vcf) {
            $id = zl_dna_raw_pick($cols,$header,['id','rsid','snp'],2);
            $chr = zl_dna_raw_pick($cols,$header,['chrom','chromosome'],0);
            $pos = zl_dna_raw_pick($cols,$header,['pos','position'],1);
        } else {
            $id = zl_dna_raw_pick($cols,$header,['rsid','snp','snp_name','id'],0);
            $chr = zl_dna_raw_pick($cols,$header,['chromosome','chrom','chr'],1);
            $pos = zl_dna_raw_pick($cols,$header,['position','pos'],2);
        }
        $id = strtolower(trim($id)); $chr = preg_replace('/^chr/i','',trim($chr)); $pos = trim($pos);
        $target = ctype_digit($pos) ? ($coordinates[$chr . ':' . (int) $pos] ?? null) : null;
        if (!isset($catalog[$id]) && $target === null) { continue; }
        $record = ['rsid'=>$id,'chromosome'=>$chr,'position'=>$pos,'source_record_format'=>$is_vcf?'vcf':'consumer_raw'];
        if ($is_vcf) {
            $ref = strtoupper(trim(zl_dna_raw_pick($cols,$header,['ref'],3)));
            $alt = strtoupper(trim(zl_dna_raw_pick($cols,$header,['alt'],4)));
            $record['reference_allele'] = $ref; $record['alternate_alleles'] = $alt;
            $sample = count($cols) === 10 && in_array(strtoupper(trim((string) ($cols[6] ?? ''))), ['PASS','.'], true) ? (string) $cols[9] : '';
            $record['genotype'] = zl_dna_vcf_sample_genotype($sample,$ref,$alt,(string)($cols[8] ?? ''));
            if ($target !== null && ($id === '.' || $id === '')) {
                if ($ref === $catalog[$target]['ref'] && $alt === $catalog[$target]['alt']) { $record['rsid'] = $target; }
                else { $record['source_alignment_conflict'] = true; }
            } elseif (!preg_match('/^rs\d+$/',$id)) { $record['source_identity_conflict'] = true; }
        } else {
            $gt = zl_dna_raw_pick($cols,$header,['result','genotype','alleles','allele'],count($cols)-1);
            if (isset($header['allele1']) || isset($header['allele_1']) || isset($header['allele2']) || isset($header['allele_2'])) {
                $gt = zl_dna_raw_pick($cols,$header,['allele1','allele_1']) . zl_dna_raw_pick($cols,$header,['allele2','allele_2']);
            } elseif (!$header && count($cols) === 5) { $gt = (string)$cols[3] . (string)$cols[4]; }
            // Do not strip malformed characters into a plausible diploid genotype.
            $record['genotype'] = strtoupper(trim($gt));
            if (!preg_match('/^rs\d+$/',$id)) { $record['source_identity_conflict'] = true; }
        }
        zlp_add_record($states,$record,$catalog,$coordinates,$gate['assembly']);
    }
    fclose($handle);
    return ['states'=>$states,'summary'=>$summary,'scanned_rows'=>$data_lines];
}

function zlp_taste_diplotypes($calls) {
    // Forward genomic allele -> amino acid. TAS2R38 is on the reverse strand.
    // NCBI NM_176817.5 / NP_789787.5 cross-placement verification accompanies tests.
    $maps = [['C'=>'A','G'=>'P'],['G'=>'A','A'=>'V'],['T'=>'I','C'=>'V']];
    $pairs = [['','']];
    foreach (['rs713598','rs1726866','rs10246939'] as $i=>$id) {
        $call = (string)($calls[$id] ?? '');
        if (strlen($call) !== 2 || !isset($maps[$i][$call[0]],$maps[$i][$call[1]])) { return []; }
        $next=[];
        foreach ($pairs as $pair) {
            foreach ([[0,1],[1,0]] as $order) { $next[] = [$pair[0].$maps[$i][$call[$order[0]]],$pair[1].$maps[$i][$call[$order[1]]]]; }
        }
        $pairs=$next;
    }
    $unique=[];
    foreach ($pairs as $pair) { sort($pair); $unique[implode('/',$pair)] = $pair; }
    ksort($unique); return array_values($unique);
}

function zlp_report_from_states($states, $summary) {
    $catalog=zlp_marker_catalog(); $gate=zlt_file_gate($summary);
    if (!empty($summary['personal_traits_scan_error'])) { $gate['ready']=false; $gate['reasons'][]=(string)$summary['personal_traits_scan_error']; }
    $defs = [
        'earwax'=>['title'=>'Earwax type','question'=>'Does my DNA point towards dry or wet earwax?','ids'=>['rs17822931'],'sources'=>[['title'=>'Yoshiura et al. (2006): ABCC11 and earwax type','url'=>'https://doi.org/10.1038/ng1733','year'=>2006]],'limits'=>['This common variant has a strong association with earwax type; rare ABCC11 changes not tested here can produce exceptions.','This is not an ear-health assessment and says nothing about hygiene or ancestry.']],
        'bitter_taste'=>['title'=>'Bitter taste sensitivity','question'=>'Might certain bitter tastes seem stronger to me?','ids'=>['rs713598','rs1726866','rs10246939'],'sources'=>[['title'=>'Bufe et al. (2005): receptor function and PTC/PROP bitterness','url'=>'https://pubmed.ncbi.nlm.nih.gov/15723792/','year'=>2005],['title'=>'Mennella et al. (2010): age modifies bitter taste sensitivity','url'=>'https://doi.org/10.1186/1471-2156-11-60','year'=>2010]],'limits'=>['The finding concerns PTC and PROP, the specific bitter compounds studied; it does not describe every bitter food or drink.','Age and other biological factors affect taste. This does not establish a supertaster label or food preferences.','Alleles are not statistically phased. Every arrangement compatible with the three direct diploid calls is considered; rare arrangements are not discarded.']],
        'lactase_persistence'=>['title'=>'Adult milk-digestion tendency','question'=>'Is there a tested clue to continued lactase production?','ids'=>['rs4988235','rs145946881','rs41380347','rs41525747'],'sources'=>[['title'=>'Enattah et al. (2002): lactase-persistence-associated regulatory variant','url'=>'https://doi.org/10.1038/ng826','year'=>2002],['title'=>'Tishkoff et al. (2007): independent lactase-persistence variants','url'=>'https://doi.org/10.1038/ng1946','year'=>2007],['title'=>'Liebert et al. (2017): functional variants and their worldwide distribution','url'=>'https://pubmed.ncbi.nlm.nih.gov/29063188/','year'=>2017]],'limits'=>['Four regulatory variants are checked. Other lactase-persistence variants exist, including variants absent from many consumer DNA arrays.','The first marker was established in European studies; the additional variants were studied in African and Middle Eastern contexts. They do not provide equally complete coverage of every population.','Continued lactase production and symptoms after milk are different questions. This result neither diagnoses lactose intolerance nor establishes what you can comfortably eat.']],
    ];
    $cards=[]; $total_observed=0;
    foreach ($defs as $id=>$def) {
        $markers=[]; $calls=[]; $invalid=false;
        foreach ($def['ids'] as $rsid) {
            $item=$gate['ready']?($states[$rsid]??['status'=>'not_observed']):['status'=>'withheld_pending_validation'];
            $status=$item['status']; $m=$catalog[$rsid];
            if ($status==='observed') { $calls[$rsid]=$item['internal_call']; $total_observed++; }
            elseif (!in_array($status,['not_observed','not_callable'],true)) { $invalid=true; }
            $markers[]=['rsid'=>$rsid,'gene'=>$m['gene'],'status'=>$status,'label'=>zlt_marker_status_label($status),'build'=>$gate['assembly']?:null,'chromosome'=>$m['chromosome'],'position'=>$gate['assembly']?$m['positions'][$gate['assembly']]:null,'source_url'=>'https://www.ncbi.nlm.nih.gov/snp/'.$rsid];
        }
        $n=count($calls); $required=count($def['ids']);
        $card=['id'=>$id,'title'=>$def['title'],'question'=>$def['question'],'status'=>$invalid?'needs_validation':($n?'ambiguous':'missing'),'result'=>$invalid?'The DNA calls need validation':($n?'Some relevant markers are available':'This file has no usable result for this trait'),'meaning'=>$invalid?'A file or marker check did not pass, so a trait conclusion is not made.':($n?'The available calls do not yet support a simple trait conclusion.':'Missing or incomplete calls are a limit of this file; they do not mean the trait is absent.'),'does_not_mean'=>'This is not a diagnosis or a certainty about your everyday experience.','confidence_label'=>'No trait conclusion','limitations'=>$def['limits'],'sources'=>$def['sources'],'markers'=>$markers,'evidence'=>['observed_markers'=>$n,'required_markers'=>$required,'complete'=>$n===$required],'source_version'=>'Everyday traits v1 / NCBI RefSNP checked 2026-09-05'];
        if (!$invalid && $id==='earwax' && $n===1) {
            $dry=$calls['rs17822931']==='TT';
            $card['status']='observed'; $card['result']=$dry?'A strong genetic clue for dry earwax':'A strong genetic clue for wet earwax';
            $card['meaning']=$dry?'Your checked ABCC11 pattern is associated with dry, flaky earwax in the original research.':'Your checked ABCC11 pattern is associated with wet, sticky earwax in the original research.';
            $card['confidence_label']='Strong published single-variant association';
            $card['does_not_mean']='It does not assess ear health, body odour, hygiene or ancestry.';
        } elseif (!$invalid && $id==='bitter_taste' && $n===3) {
            $pairs=zlp_taste_diplotypes($calls); $only=count($pairs)===1?implode('/',$pairs[0]):'';
            $card['evidence']['compatible_arrangement_count']=count($pairs);
            if (in_array($only,['PAV/PAV','AVI/AVI'],true)) {
                $high=$only==='PAV/PAV'; $card['status']='observed';
                $card['result']=$high?'A tendency to notice certain bitter compounds more strongly':'A tendency to notice certain bitter compounds less strongly';
                $card['meaning']=$high?'Your checked taste-receptor pattern is associated with stronger responses to PTC and PROP, two substances used in taste research. This does not mean every bitter food or drink will taste stronger.':'Your checked taste-receptor pattern is associated with weaker responses to PTC and PROP, two substances used in taste research. This does not mean every bitter food or drink will taste weaker.';
                $card['confidence_label']='Published three-marker taste association';
            } else {
                $card['result']=count($pairs)>1?'Mixed bitter-taste variants; their arrangement is unresolved':'A less common bitter-taste pattern';
                $card['meaning']=count($pairs)>1?'All three markers were read, but unphased DNA allows more than one arrangement across your two chromosome copies. Assigning a single stronger or weaker taste result would require an unsupported assumption.':'All three markers were read, but they do not form either of the two common same-copy patterns used for a simple stronger or weaker result. The rarer combinations can behave differently.';
                $card['confidence_label']='Markers observed; simple interpretation unresolved';
            }
            $card['does_not_mean']='It does not establish a supertaster label or predict whether you like coffee, vegetables or other foods.';
        } elseif (!$invalid && $id==='lactase_persistence' && $n>0) {
            $associated=false;
            foreach ($calls as $rsid=>$call) { if (strpos($call,$catalog[$rsid]['persistence_allele'])!==false) { $associated=true; } }
            if ($associated) {
                $card['status']='observed'; $card['result']='A tested clue for continued lactase production';
                $card['meaning']='At least one directly observed variant is associated with keeping lactase production into adulthood. Lactase is the enzyme that breaks down the milk sugar lactose.';
                $card['confidence_label']='Published regulatory-variant association';
            } else {
                $card['status']=$n===$required?'observed':'ambiguous';
                $card['result']=$n===$required?'None of the four tested persistence variants was observed':'No persistence variant found among the available calls';
                $card['meaning']=$n===$required?'The tested variants do not provide a persistence clue. This limited panel cannot rule out other genetic routes to continued lactase production.':'The available calls do not show a tested persistence variant, and '.($required-$n).' of the four markers remain unread. This cannot establish reduced lactase production.';
                $card['confidence_label']='Limited variant check; absence is not a diagnosis';
            }
            if ($n<$required) { $card['limitations'][]='Only '.$n.' of four markers has a usable call. Any positive finding refers to that observed evidence, not a complete genetic assessment.'; }
            $card['does_not_mean']='It does not diagnose lactose intolerance, explain symptoms, assess milk allergy or recommend a diet.';
        }
        $cards[]=$card;
    }
    $usable=count(array_filter($cards,function($c){return in_array($c['status'],['observed','ambiguous'],true)&&$c['evidence']['observed_markers']>0;}));
    $resolved=count(array_filter($cards,function($c){return $c['status']==='observed';}));
    return ['schema_version'=>1,'version'=>'everyday-traits-1','status'=>!$gate['ready']||!$total_observed?'needs_input':($resolved===3?'ready':'limited'),'available'=>$usable>0,'cards'=>$cards,'coverage'=>['observed_markers'=>$total_observed,'total_markers'=>count($catalog),'cards_with_evidence'=>$usable,'cards_with_findings'=>$resolved],'analysis_gate'=>$gate,'boundary'=>'Directly observed, nonclinical trait associations. No phenotype probabilities, inferred genotypes or ancestry conclusions. Missing evidence is never treated as a negative result.','privacy'=>['raw_genotypes_in_output'=>false,'raw_genotypes_in_agent_context'=>false,'individual_trait_results_in_agent_context'=>false,'external_requests_performed'=>false,'storage_performed'=>false]];
}

function zl_dna_personal_traits_report_path($path, $name='raw-dna.txt', $summary=[]) {
    $scan=zlp_scan_path($path,(array)$summary);
    return zlp_report_from_states($scan['states'],$scan['summary']);
}

function zl_dna_personal_traits_agent_context($report) {
    $report=(array)($report['personal_traits']??$report);
    // Availability only: no trait IDs, targeted-locus counts, marker IDs, alleles,
    // coordinates, individual trait interpretations, filenames or source raw text.
    return ['type'=>'everyday_traits_availability_only','report_available'=>!empty($report['available']),'boundary'=>'The private report may contain directly observed everyday-trait associations. No individual trait result or targeted genetic evidence is supplied here; do not infer it.'];
}

function zl_dna_personal_traits_markdown($report) {
    $report=(array)($report['personal_traits']??$report); $text="\n### Everyday DNA findings\n\n";
    $text.=(string)($report['boundary']??'Direct nonclinical trait associations only.')."\n";
    foreach ((array)($report['cards']??[]) as $card) {
        $text.="\n#### ".$card['title']."\n\n**".$card['result']."**\n\n".$card['meaning']."\n\nWhat this does not mean: ".$card['does_not_mean']."\n\nEvidence: ".$card['evidence']['observed_markers'].' of '.$card['evidence']['required_markers'].' markers observed. '.$card['confidence_label'].".\n";
        foreach ($card['limitations'] as $limit) { $text.="\n- ".$limit; }
        $text.="\n\nSources: "; $sources=[];
        foreach ($card['sources'] as $source) { $sources[]='['.$source['title'].']('.$source['url'].')'; }
        $text.=implode('; ',$sources).".\n\nMarker checks (".$card['source_version']."):\n";
        foreach ($card['markers'] as $marker) { $text.="\n- [".$marker['rsid'].']('.$marker['source_url'].'): '.$marker['label'].($marker['build']?' ('.$marker['build'].' chromosome '.$marker['chromosome'].', position '.$marker['position'].')':'').'.'; }
        $text.="\n";
    }
    return $text;
}
