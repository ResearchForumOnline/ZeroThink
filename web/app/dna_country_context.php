<?php
// Geography is explanatory metadata. This module never reads DNA or runs a classifier.
function zlc_source_version() { return '1.0.0 / b5e51c78b5004a8388418ce56755bb2f71f24eb5'; }
function zlc_source_url() { return 'https://github.com/jimmy-penn/grafanc/blob/b5e51c78b5004a8388418ce56755bb2f71f24eb5/GrafAncDocumentation.md'; }
function zlc_boundary() {
    return 'Place names explain the model category and its documented comparison groups. They are not separate countries detected in your DNA, inherited percentages, nationality, religion or community membership. A country where study participants were born or recruited is not the same as their ancestors\' origin. Neighboring populations overlap, and this model does not distinguish every origin.';
}
function zlc_group_catalog() {
    static $catalog = null;
    if ($catalog !== null) { return $catalog; }
    // Exact label/scope pairs extracted from the SHA-256-pinned deployed bundle manifest.
    $rows = json_decode(<<<'ZLC_GROUPS'
{"101":{"label":"Nigeria","reference_scope":"Nigeria, HGDP-Yoruba, TGP-ESN, TGP-YRI"},"102":{"label":"Western Africa","reference_scope":"Gambia, Ghana, Guinea, Ivory Coast, Liberia, Senegal, Sierra Leone, Togo, HGDP-Mandenka, TGP-GWD, TGP-MSL"},"103":{"label":"Central Africa","reference_scope":"Angola, Burundi, Cameroon, Congo, Malawi, Rwanda, Tanzania, Uganda, Zambia"},"104":{"label":"Kenya","reference_scope":"Kenya, HGDP-Bantu Kenya, HGDP-Biaka, TGP-LWK"},"105":{"label":"Southern Africa","reference_scope":"Botswana, South Africa, Zimbabwe, HGDP-Bantu South Africa, HGDP-Mbuti"},"106":{"label":"Northeastern Africa","reference_scope":"Eritrea, Ethiopia, Somalia, Sudan"},"107":{"label":"African American","reference_scope":"Antigua, Aruba, Barbados, Caribbean, Guianas, West Indies, TGP-ACB, TGP-ASW"},"108":{"label":"Other Africa","reference_scope":"Mozambique"},"201":{"label":"Northern Africa","reference_scope":"Algeria, Libya, Morocco, Tunisia, HGDP-Mozabite"},"202":{"label":"Middle East 1","reference_scope":"Cyprus, Egypt, Israel, Jordan, Kuwait, Lebanon, Palestine, Syria, Yemen, HGDP-Bedouin, HGDP-Druze, HGDP-Palestinian"},"203":{"label":"Middle East 2","reference_scope":"Armenia, Azerbaijan, Iran, Iraq, Turkey"},"301":{"label":"Finland","reference_scope":"Finland, TGP-FIN"},"302":{"label":"Northern Europe","reference_scope":"Denmark, Iceland, Norway, Sweden"},"303":{"label":"Western Europe","reference_scope":"Austria, Belgium, British, France, Germany, Irish, Luxembourg, Netherlands, Switzerland, United Kingdom, HGDP-French, HGDP-Orcadian, TGP-CEU, TGP-GBR"},"304":{"label":"Southern Europe","reference_scope":"Italy, Portugal, Spain, HGDP-Basque, HGDP-Bergamo Italian, HGDP-Sardinian, HGDP-Tuscan, TGP-IBS, TGP-TSI"},"305":{"label":"Northeastern Europe","reference_scope":"Estonia, Latvia, Lithuania, Poland, Russia, Ukraine, HGDP-Russian"},"306":{"label":"Southeastern Europe","reference_scope":"Bulgaria, Croatia, Czech, Hungary, Macedonia, Romania, Serbia, Slovakia, Slovenia"},"307":{"label":"Balkans","reference_scope":"Albania, Greece, Kosovo"},"308":{"label":"Other Europe","reference_scope":"HGDP-Adygei"},"401":{"label":"Asian India","reference_scope":"Mauritius, TGP-ITU, TGP-PJL"},"402":{"label":"Gujarati in India","reference_scope":"TGP-GIH"},"403":{"label":"Northern South Asia","reference_scope":"Afghanistan, India, Kashmir, Pakistan, HGDP-Balochi, HGDP-Brahui, HGDP-Burusho, HGDP-Kalash, HGDP-Makrani, HGDP-Pathan, HGDP-Sindhi"},"404":{"label":"Sri Lanka","reference_scope":"Sri Lanka, TGP-STU"},"405":{"label":"Bangladesh","reference_scope":"Bangladesh, TGP-BEB"},"501":{"label":"Japan Ryukyu","reference_scope":""},"502":{"label":"Japan Main Islands","reference_scope":"Japan, HGDP-Japanese, TGP-JPT"},"503":{"label":"Korea","reference_scope":"North Korea, South Korea"},"504":{"label":"Northern Asia","reference_scope":"Kyrgyzstan, Mongolia, Nepal, HGDP-Hazara, HGDP-Uygur, HGDP-Yakut"},"505":{"label":"Northern China 1","reference_scope":"China, HGDP-Northern Han, TGP-CHB"},"506":{"label":"Northern China 2","reference_scope":"HGDP-Daur, HGDP-Hezhen, HGDP-Mongolian, HGDP-Oroqen, HGDP-Xibo"},"507":{"label":"Southern China 1","reference_scope":"Brunei, Hong Kong, Indonesia, Malaysia, Singapore, Taiwan, HGDP-Han, HGDP-Miao, HGDP-She, HGDP-Tujia, TGP-CHS"},"508":{"label":"Southern China 2","reference_scope":"HGDP-Naxi, HGDP-Tu, HGDP-Yi"},"509":{"label":"Southeast Asia","reference_scope":"Cambodia, Philippines, Vietnam , HGDP-Dai, HGDP-Lahu, TGP-CDX, TGP-KHV"},"510":{"label":"Thailand","reference_scope":"Laos, Thailand, HGDP-Cambodian"},"511":{"label":"Other East Asia","reference_scope":""},"601":{"label":"Latin American 1","reference_scope":"Brazil, TGP-PUR"},"602":{"label":"Latin American 2","reference_scope":"Argentina, Bolivia, Chile, Colombia, Ecuador, Mexico, Peru, Venezuela, TGP-CLM, TGP-MXL"},"603":{"label":"Native American","reference_scope":"HGDP-Colombian, HGDP-Karitiana, HGDP-Maya, HGDP-Pima, HGDP-Surui, TGP-PEL"},"700":{"label":"Oceania","reference_scope":"HGDP-Bougainville, HGDP-Papuan Highlands"},"800":{"label":"Multiracial","reference_scope":""}}
ZLC_GROUPS
    , true);
    $friendly = [
        '202'=>'Egypt, the Levant and nearby countries',
        '203'=>'Iran, Iraq, Türkiye and the South Caucasus',
        '303'=>'Western Europe, including British and Irish comparisons',
        '308'=>'Adygei comparison group in the model\'s Other Europe category',
        '401'=>'South Asian group with Punjabi and Telugu comparisons',
        '402'=>'Gujarati-origin comparison group',
        '403'=>'Pakistan, northern India and nearby South Asia',
        '501'=>'Ryukyu Islands, Japan',
        '505'=>'Northern China — comparison group 1',
        '506'=>'Northern China — comparison group 2',
        '507'=>'Southern China and neighboring places — comparison group 1',
        '508'=>'Southern China — comparison group 2',
        '601'=>'Latin American comparison group 1',
        '602'=>'Latin American comparison group 2',
        '603'=>'Indigenous American comparison group',
        '700'=>'Bougainville and the Papuan Highlands',
        '800'=>'Multiple ancestry backgrounds'
    ];
    $catalog = [];
    foreach ($rows as $code => $row) {
        $places = []; $comparisons = [];
        foreach (array_filter(array_map('trim', explode(',', $row['reference_scope']))) as $term) {
            if (preg_match('/^(HGDP|TGP)-/', $term)) { $comparisons[] = $term; continue; }
            $kind = 'country_of_birth_comparison';
            $relationship = 'Country-of-birth comparison group listed by the model author; not a detected country of ancestry.';
            $label = $term;
            if (in_array($term, ['British','Irish'], true)) {
                $kind = 'comparison_population_label';
                $label = $term === 'British' ? 'Britain (British comparison)' : 'Ireland (Irish comparison)';
                $relationship = 'Comparison population label in the author\'s Western Europe group; British and Irish origins are not separated by this category.';
            } elseif (in_array($term, ['Caribbean','Guianas','West Indies','Kashmir','Hong Kong','Taiwan','Congo','Macedonia'], true)) {
                $kind = 'documented_region';
                $relationship = 'Geographic term in the author\'s comparison-group table; not a separate country assignment.';
                if ($term === 'Congo') { $label = 'Congo (country unspecified in the source label)'; }
            } elseif ($term === 'Turkey') { $label = 'Türkiye (Turkey in the source)'; }
            elseif ($term === 'Czech') { $label = 'Czechia (Czech in the source)'; }
            $places[] = ['label'=>$label,'relationship'=>$relationship,'source_term'=>$term,'kind'=>$kind,'source_url'=>zlc_source_url()];
        }
        $extra = [
            '401'=>[['Lahore, Pakistan','TGP-PJL','Punjabi comparison population sampled in Lahore, Pakistan.'],['India (Telugu reference)','TGP-ITU','Indian Telugu comparison population sampled in the United Kingdom; origin and recruitment location differ.']],
            '402'=>[['Gujarat, India','TGP-GIH','Gujarati-origin comparison population sampled in Houston, Texas, USA; the collection location is not an ancestry assignment.']],
            '601'=>[['Puerto Rico','TGP-PUR','Puerto Rican study population in the author\'s comparison table.']],
            '603'=>[['Lima, Peru','TGP-PEL','Peruvian study population sampled in Lima, listed alongside several Indigenous American comparisons.']]
        ];
        foreach ($extra[(string)$code] ?? [] as $place) {
            $places[] = ['label'=>$place[0],'relationship'=>$place[2],'source_term'=>$place[1],'kind'=>'study_population_origin','source_url'=>'https://www.internationalgenome.org/data-portal/population/'.substr($place[1],4)];
        }
        if (in_array((string)$code, ['501','506','508'], true)) {
            $places[] = ['label'=>(string)$code === '501' ? 'Ryukyu Islands, Japan' : 'China','relationship'=>'Geography named by the model category; the author does not supply separate country-of-birth examples here.','source_term'=>$row['label'],'kind'=>'model_region','source_url'=>zlc_source_url()];
        }
        if ((string)$code === '700') {
            foreach (['Bougainville','Papuan Highlands'] as $place) { $places[] = ['label'=>$place,'relationship'=>'Geographic name of an HGDP comparison population in the author\'s table.','source_term'=>$place,'kind'=>'documented_region','source_url'=>zlc_source_url()]; }
        }
        $catalog[(string)$code] = ['code'=>(string)$code,'label'=>$friendly[(string)$code] ?? $row['label'],'model_label'=>$row['label'],'reference_scope'=>$row['reference_scope'],'countries'=>$places,'comparison_groups'=>$comparisons,'source_url'=>zlc_source_url(),'source_version'=>zlc_source_version()];
    }
    return $catalog;
}
function zlc_requested_places() {
    $rows = [
        ['england','England / English',['english'],['303'],'region_only','The British study population includes England and Scotland. GrafAnc places it with Irish and other Western European comparisons; this report cannot select English ancestry separately.'],
        ['ireland','Ireland / Irish',['irish'],['303'],'region_only','Irish comparisons are explicitly included in Western Europe. The model does not separate an Irish category from British and other Western European comparisons.'],
        ['scotland','Scotland / Scottish',['scottish','scots'],['303'],'region_only','British samples from England and Scotland and an Orcadian comparison appear in Western Europe. Their inclusion does not provide a separate Scottish ancestry result.'],
        ['wales','Wales / Welsh',['welsh'],['303'],'no_separate_category','No Welsh category or explicitly Welsh comparison is listed in this pinned model table. A broad British or Western European result cannot establish or exclude Welsh ancestry.'],
        ['egypt','Egypt / Egyptian',['egyptian'],['202'],'region_only','Egypt is explicitly listed in the same model category as several Levantine and nearby country comparisons. The model does not separate Egypt from Israel, Palestine, Jordan, Lebanon and the other places in that category.'],
        ['israel','Israel / Israeli',['israeli'],['202'],'region_only','Israel is a documented country-of-birth comparison within Middle East 1. That category also includes Egypt, Palestine and neighboring places; it is not an Israeli, Jewish or religious-identity test.'],
        ['palestine','Palestine / Palestinian',['palestinian'],['202'],'region_only','Palestine and the HGDP Palestinian comparison are documented in Middle East 1. The result cannot distinguish Palestinian ancestry from the other populations in this shared model category.'],
        ['iraq','Iraq / Iraqi',['iraqi'],['203'],'region_only','Iraq shares Middle East 2 with Iran, Türkiye, Armenia and Azerbaijan. This grouping cannot select Iraqi ancestry separately.'],
        ['iran','Iran / Iranian',['iranian','persian'],['203'],'region_only','Iran is explicitly represented in Middle East 2, alongside Iraq, Türkiye, Armenia and Azerbaijan. The result is a regional comparison, not a distinct Iranian or Persian identity assignment.'],
        ['pakistan','Pakistan / Pakistani',['pakistani','punjabi','punjab'],['403','401'],'region_only','Pakistan and several Pakistani population comparisons appear in Northern South Asia. Punjabi samples from Lahore are also listed in the author\'s separate Asian India category. The category names do not map one-to-one to countries, and GrafAnc does not infer a Pakistani percentage. The separate composition model includes a Pakistani reference component.'],
        ['ashkenazi','Ashkenazi Jewish',['ashkenazi jewish','jewish'],[],'no_separate_category','GrafAnc defines no Ashkenazi category. A European, Balkan or Middle Eastern result cannot establish or exclude Ashkenazi ancestry or Jewish identity. Any separate research-reference comparison is a different method, not a GrafAnc identity result.'],
        ['khazar','Khazar',['khazars','khazarian'],[],'historical_claim_unsupported','This model has no Khazar reference category. Similarity to Caucasus, Steppe or modern regional comparisons cannot establish descent from the historical Khazar polity.']
    ];
    $out = [];
    foreach ($rows as $row) { $out[] = ['query'=>$row[0],'label'=>$row[1],'aliases'=>$row[2],'related_group_codes'=>$row[3],'status'=>$row[4],'answer'=>$row[5],'relationship'=>'Model resolution guide; not a personal finding','source_url'=>zlc_source_url(),'next_step'=>'Use family records and documented genealogy for places or identities that this model does not separate.']; }
    return $out;
}
function zlc_ancient_catalog() {
    // Places are from selected_samples in the final verified AADR pack manifest, not the spread of a culture.
    $rows = [
        ['WHG','Spain, Switzerland and Luxembourg — hunter-gatherer references',[['La Braña-Arintero','Spain'],['Grotte du Bichon','Switzerland'],['Loschbour','Luxembourg']],'These three reference individuals come from different western European sites and periods.'],
        ['EHG','Karelia and Samara, Russia — hunter-gatherer references',[['Yuzhny Oleniy Ostrov, Karelia','Russia'],['Chekalino-4, Samara region','Russia'],['Lebyazhinka, Samara region','Russia']],'The installed eastern hunter-gatherer set uses four ancient individuals from sites in present-day Russia.'],
        ['CHG','Western Georgia — Caucasus hunter-gatherer references',[['Kotias Klde','Georgia'],['Satsurblia','Georgia']],'The two reference individuals come from cave sites in present-day Georgia. Caucasus is a geographic region, not a single nationality.'],
        ['ANATOLIA_N','Barcın, Türkiye — early farmer references',[['Barcın-Höyük, Bursa','Türkiye']],'All twelve individuals selected for this installed group have Barcın-Höyük as their recorded site. Anatolia means the Asian peninsula of present-day Türkiye.'],
        ['STEPPE_EMBA','Samara, Russia — early Bronze Age Steppe references',[['Samara Oblast archaeological sites','Russia']],'This installed set contains twelve ancient individuals from the Samara region. Steppe describes grassland geography; this reference set does not represent every place reached by Steppe-associated populations.'],
        ['IRAN_N','Ganj Dareh, Iran — early farmer references',[['Ganj Dareh, Zagros Mountains','Iran']],'These four ancient reference individuals come from Ganj Dareh. Ancient sharing with this set is not a modern Iranian ancestry assignment.']
    ];
    $out = [];
    foreach ($rows as $row) {
        $places = [];
        foreach ($row[2] as $place) { $places[] = ['label'=>$place[0],'country'=>$place[1],'relationship'=>'Recorded archaeological site in the selected ancient reference set; country is its present-day location.']; }
        $out[$row[0]] = ['code'=>$row[0],'label'=>$row[1],'places'=>$places,'plain'=>$row[3],'comparison_available'=>false,'status'=>'reference_geography_only','source_version'=>'AADR v66.p1 compatibility_HO / ancient-transversions-20260905.2','source_url'=>'https://doi.org/10.7910/DVN/FFIDCW','boundary'=>'These are the locations of reference remains, not a personal migration route, direct ancestor or detected modern country.'];
    }
    return $out;
}
function zl_dna_country_context_report($origins = []) {
    $out = ['version'=>'dna-country-context-v1','available'=>false,'status'=>'reference_guide_only','model_headline'=>'What the place names mean','region'=>null,'countries'=>[],'plain'=>'A personal model category is not available for this report. The place-name and model-resolution guides below remain useful reference context.','boundary'=>zlc_boundary(),'unresolved'=>zlc_requested_places(),'ancient'=>[],'glossary'=>[
        ['term'=>'Model category','meaning'=>'A research grouping predicted from observed DNA markers; several populations and places can share it.'],
        ['term'=>'Country-of-birth comparison','meaning'=>'Study participants grouped by where they were born. This is not proof that their ancestry comes only from that country.'],
        ['term'=>'Reference population','meaning'=>'A study comparison group used to understand genetic patterns; it does not cover everyone in a country or community.'],
        ['term'=>'Ancient reference site','meaning'=>'The recorded archaeological location of the people included in a comparison, expressed with present-day place names.'],
        ['term'=>'Steppe','meaning'=>'Broad temperate grasslands. The installed early Bronze Age reference set is specifically from Samara, Russia.'],
        ['term'=>'Anatolia','meaning'=>'The Asian peninsula of present-day Türkiye. The installed early farmer group uses Barcın-Höyük.'],
        ['term'=>'Levant','meaning'=>'An eastern Mediterranean region. In this guide it helps describe a shared model category; it is not a separate country prediction.'],
        ['term'=>'Unresolved','meaning'=>'The method cannot separate the requested origins. It does not mean an origin is absent from your family history.']
    ],'sources'=>[
        ['title'=>'GrafAnc author documentation — category definitions','url'=>zlc_source_url(),'version'=>zlc_source_version()],
        ['title'=>'GrafAnc methods paper','url'=>'https://doi.org/10.1016/j.xhgg.2025.100530','version'=>'2025'],
        ['title'=>'IGSR study population descriptions','url'=>'https://www.internationalgenome.org/category/population/','version'=>'1000 Genomes population definitions'],
        ['title'=>'Allen Ancient DNA Resource — selected reference site metadata','url'=>'https://doi.org/10.7910/DVN/FFIDCW','version'=>'v66.p1 / selected 37-individual ancient reference set']
    ]];
    $model = is_array($origins['classification'] ?? null) ? $origins['classification'] : [];
    $catalog = zlc_group_catalog();
    $broad_map = ['AFR'=>'Africa','MEN'=>'Middle East and North Africa','EUR'=>'Europe','SAS'=>'South Asia','EAS'=>'East Asia','AMR'=>'The Americas','OCN'=>'Oceania','MIX'=>'Multiple ancestry backgrounds'];
    $broad_prefix = ['AFR'=>1,'MEN'=>2,'EUR'=>3,'SAS'=>4,'EAS'=>5,'AMR'=>6,'OCN'=>7,'MIX'=>8];
    $broad = (string)($model['broad']['code'] ?? '');
    if (($model['available'] ?? false) === true && ($model['version'] ?? '') === zlc_source_version() && isset($broad_map[$broad]) && in_array($model['status'] ?? '', ['broad_model_classification','regional_model_classification','mixed_model_classification'], true) && is_numeric($model['matched_markers'] ?? null) && $model['matched_markers'] >= 10000) {
        $out['available'] = true; $out['status'] = 'broad_geography_context';
        $out['region'] = ['code'=>$broad,'label'=>$broad_map[$broad],'model_label'=>$broad_map[$broad]];
        $out['model_headline'] = $broad_map[$broad].' — model geography';
        $out['plain'] = 'The model supports a broad '.$broad_map[$broad].' comparison. No more specific country is selected here.';
        $regional = $model['regional'] ?? null;
        $code = is_array($regional) ? (string)($regional['code'] ?? '') : '';
        if (isset($catalog[$code]) && intdiv((int)$code,100) === $broad_prefix[$broad] && ($regional['label'] ?? '') === $catalog[$code]['model_label'] && $model['matched_markers'] >= 50000) {
            $entry = $catalog[$code]; $out['status'] = 'regional_geography_context';
            $out['region'] = ['code'=>$code,'label'=>$entry['label'],'model_label'=>$entry['model_label']];
            $out['model_headline'] = $entry['label'].' — model geography';
            $out['countries'] = $entry['countries']; $out['reference_scope'] = $entry['reference_scope'];
            $out['comparison_groups'] = $entry['comparison_groups'];
            $out['plain'] = 'Your model category is '.$entry['model_label'].'. The places below explain which comparison groups the author associates with that category. They are examples within one grouping, not separate origins detected in your file.';
            if (!$entry['countries']) { $out['plain'] .= ' The author provides no separate country examples for this category.'; }
        } elseif ($regional !== null && $code !== '') {
            $out['available'] = false; $out['status'] = 'model_context_mismatch'; $out['region'] = null;
            $out['model_headline'] = 'Model geography needs a matching source definition';
            $out['plain'] = 'The returned regional category does not match this guide\'s pinned source definition. No country context is attached to it.';
        }
    }
    $ancient = is_array($origins['ancient'] ?? null) ? $origins['ancient'] : [];
    $ancient_catalog = zlc_ancient_catalog();
    foreach ((array)($ancient['groups'] ?? []) as $group) {
        if (!is_array($group)) { continue; }
        $code = (string)($group['code'] ?? '');
        if (!isset($ancient_catalog[$code]) || ($group['source_version'] ?? '') !== $ancient_catalog[$code]['source_version']) { continue; }
        if (($ancient['status'] ?? '') === 'descriptive_affinity_available' && is_numeric($group['matched_markers'] ?? null) && $group['matched_markers'] > 0) {
            $ancient_catalog[$code]['comparison_available'] = true;
            $ancient_catalog[$code]['status'] = 'reference_comparison_available';
        }
    }
    $out['ancient'] = array_values($ancient_catalog);
    return $out;
}
function zl_dna_country_context_catalog() {
    $guide = zl_dna_country_context_report([]);
    return ['version'=>$guide['version'],'status'=>'reference_geography_guide','personal_result'=>false,'model_group_count'=>count(zlc_group_catalog()),'model_groups'=>array_values(zlc_group_catalog()),'unresolved'=>$guide['unresolved'],'ancient'=>$guide['ancient'],'glossary'=>$guide['glossary'],'boundary'=>$guide['boundary'],'sources'=>$guide['sources']];
}
function zl_dna_country_context_markdown($context) {
    $lines = ['## Places behind the GrafAnc comparisons','','**'.($context['model_headline'] ?? 'Reference geography').'**','',(string)($context['plain'] ?? ''),'',(string)($context['boundary'] ?? zlc_boundary())];
    foreach ((array)($context['countries'] ?? []) as $place) { $lines[] = '- '.$place['label'].': '.$place['relationship']; }
    $lines[] = ''; $lines[] = '### What GrafAnc can distinguish'; $lines[] = '';
    foreach ((array)($context['unresolved'] ?? []) as $row) { $lines[] = '- **'.$row['label'].'**: '.$row['answer']; }
    $lines[] = ''; $lines[] = '### Ancient reference places'; $lines[] = '';
    foreach ((array)($context['ancient'] ?? []) as $row) {
        $lines[] = '- **'.$row['label'].'**: '.$row['plain'].' '.($row['comparison_available'] ? 'A measured group comparison is available in this report.' : 'Reference geography only; no measured group comparison is attached here.').' '.$row['boundary'];
    }
    $lines[] = ''; $lines[] = '### Sources'; $lines[] = '';
    foreach ((array)($context['sources'] ?? []) as $source) { $lines[] = '- ['.$source['title'].']('.$source['url'].') — '.$source['version']; }
    return implode("\n", $lines);
}
function zl_dna_country_context_agent_context($context) {
    // Availability only; the existing model-summary path owns individual model labels.
    return ['available'=>($context['available'] ?? false) === true,'boundary'=>zlc_boundary(),'resolution_note'=>'Country examples are contextual comparison groups, never independent country assignments.'];
}

function zl_dna_composition_country_context($composition = []) {
    // This is a second model's source guide. Never borrow GrafAnc's category-country table.
    $url = 'https://raw.githubusercontent.com/privefl/freq-ancestry/main/paper-ancestry-prop-supp.tex';
    $version = 'Figshare16858534.v3 / bigsnpr4bc7f817';
    $relationship = 'Country of birth listed for members of this published reference group; not a separate country percentage or proof of ancestry from that country.';
    $spec = [
        ['AFR_W','West Africa',['Nigeria','Ghana','Sierra Leone','Ivory Coast','Liberia','Togo'],'The reference is a genetic comparison group whose members have several recorded birthplaces, including West African countries and the Caribbean.'],
        ['AFR_S','Southern Africa',['Zimbabwe','Uganda','South Africa','Zambia','Malawi','Tanzania'],'The published Southern Africa label covers this selected comparison group. Its members were not all born in the geographical south of Africa.'],
        ['AFR_E','East Africa',['Somalia','Ethiopia','Sudan','Eritrea'],'This component uses the retained eastern African reference group, described as Africa (East 1) during source construction.'],
        ['AFR_N','North Africa',['Algeria','Morocco','Egypt','Libya','Tunisia'],'Egypt is represented within this broader North African reference. The model does not divide this component into separate countries.'],
        ['MENA','Middle East',['Iraq','Iran','Türkiye','Syria'],'The reference group is mainly participants born in Iraq and Iran, with Türkiye and Syria also listed. It is not an Iraqi-versus-Iranian breakdown.'],
        ['ASH','Ashkenazi',[],'This is an explicitly defined Ashkenazi diaspora reference group, selected by its genetic position in the study. Members were born in several countries; those birthplaces are not separate ancestry components. It is not a test of religion, citizenship or community membership.'],
        ['EUR_IT','Italy',['Italy'],'The author selected a reference group named for Italy. Its fitted share describes this genetic reference, not legal nationality or a documented Italian ancestor.'],
        ['EUR_E','East Europe',['Romania','Serbia/Montenegro (source label)','Bosnia and Herzegovina','Bulgaria','Croatia','Hungary','Italy','Macedonia (source label)','Russia','Poland','Ukraine','Latvia','Germany','Kazakhstan','Lithuania'],'The author combines northeastern and southeastern European reference coefficients because the groups are close. The listed birthplaces describe the combined reference membership, not separate country results.'],
        ['EUR_FIN','Finland',['Finland'],'The published reference combines the selected Finnish UK Biobank group with the 1000 Genomes Finnish population.'],
        ['EUR_NW','Northwest Europe',['United Kingdom','Ireland','Denmark','Norway','Sweden','Germany','Netherlands','Iceland'],'The author requires the UK, Irish and Scandinavian reference coefficients to be combined. England, Scotland and Wales are not separate model components. Germany and the Netherlands appear as some Scandinavian-reference members\' birthplaces.'],
        ['EUR_SW','Southwest Europe',['Spain','Portugal','France','Gibraltar'],'The reference contains members with these recorded birthplaces. The fitted share does not separately identify Spanish, Portuguese or French ancestry.'],
        ['AMR','South America',['Colombia','Chile','Mexico','Peru','Ecuador','Bolivia','Venezuela','Brazil','United Kingdom','United States','Argentina'],'The source combines its South America reference with the 1000 Genomes Peruvian population. Some reference members were born elsewhere; this component is not a specific country or Indigenous-community membership test.'],
        ['SAS_SL','Sri Lanka',['Sri Lanka'],'The author defines a Sri Lankan reference group. Related South Asian populations can share variation with it.'],
        ['SAS_PK','Pakistan',['Pakistan'],'A Pakistani reference group is explicitly included. A fitted share is relative to that study reference and does not resolve every Pakistani population or neighboring origin.'],
        ['SAS_BD','Bangladesh',['Bangladesh'],'The published reference combines a Bangladeshi UK Biobank group with the 1000 Genomes Bengali population.'],
        ['EAS','East Asia',['Hong Kong','Malaysia','China','Singapore','Vietnam','Indonesia','Taiwan','Thailand','Brunei','Macau'],'These recorded birthplaces describe a single broad East Asian reference group, not a list of separately estimated countries.'],
        ['EAS_JP','Japan',['Japan'],'The published reference combines the selected Japanese UK Biobank group with the 1000 Genomes Japanese population.'],
        ['EAS_PH','Philippines',['Philippines'],'A Philippine reference group is included. Its fitted share is a model comparison rather than proof of nationality.']
    ];
    $groups = [];
    foreach ($spec as $row) {
        $places = [];
        foreach ($row[2] as $label) { $places[] = ['label'=>$label,'relationship'=>$relationship]; }
        $groups[] = ['code'=>$row[0],'label'=>$row[1],'places'=>$places,'plain'=>$row[3],'source_url'=>$url];
    }
    $questions = [
        ['england','England / English',['english'],['EUR_NW'],'merged_reference_group','The model reports Northwest Europe, combining UK, Irish and Scandinavian references. It does not estimate an English share separately.'],
        ['ireland','Ireland / Irish',['irish'],['EUR_NW'],'merged_reference_group','Irish reference participants are included, but the author requires Ireland to be merged with the UK and Scandinavia. The chart therefore cannot provide an Irish-only percentage.'],
        ['scotland','Scotland / Scottish',['scottish','scots'],['EUR_NW'],'merged_reference_group','There is no separate Scottish component. The combined Northwest European estimate does not divide the UK into Scotland, England or Wales.'],
        ['wales','Wales / Welsh',['welsh'],['EUR_NW'],'merged_reference_group','There is no separate Welsh component. A Northwest European estimate cannot establish or exclude Welsh ancestry.'],
        ['egypt','Egypt / Egyptian',['egyptian'],['AFR_N'],'regional_reference_only','Egyptian-born participants are included in the North African reference, together with several neighboring countries. The model estimates the broader reference share, not an Egyptian percentage.'],
        ['israel','Israel / Israeli',['israeli'],[],'no_separate_reference_component','There is no Israeli component. Some members of the Ashkenazi reference were born in Israel, but that birthplace does not make the Ashkenazi component an Israeli ancestry percentage.'],
        ['palestine','Palestine / Palestinian',['palestinian'],[],'no_separate_reference_component','There is no separate Palestinian component. Middle Eastern or North African fitted shares cannot be relabeled as a Palestinian percentage, and an unresolved label does not exclude family origins there.'],
        ['iraq','Iraq / Iraqi',['iraqi'],['MENA'],'regional_reference_only','Iraqi-born participants are a major part of the Middle Eastern reference, alongside Iranian and other participants. Iraq and Iran are not estimated separately.'],
        ['iran','Iran / Iranian',['iranian','persian'],['MENA'],'regional_reference_only','Iranian-born participants are included in the Middle Eastern reference. This is a combined reference estimate rather than an Iranian-only or Persian-identity percentage.'],
        ['pakistan','Pakistan / Pakistani',['pakistani','punjabi','punjab'],['SAS_PK'],'included_reference_component','The model includes an explicitly named Pakistani reference component. Its estimate describes fit to that study group; it does not separately identify every community in Pakistan or prove nationality.'],
        ['ashkenazi','Ashkenazi Jewish',['ashkenazi jewish','jewish'],['ASH'],'included_reference_component','This composition model includes an explicit Ashkenazi diaspora reference component. Its estimated share is a genetic model result, not a test of religious belief or community membership. Small fitted shares can shift between nearby reference groups.'],
        ['khazar','Khazar',['khazars','khazarian'],[],'historical_claim_unsupported','No Khazar reference component is defined. An Ashkenazi, Middle Eastern or ancient Steppe/Caucasus comparison cannot be converted into a claim of descent from the historical Khazar polity.']
    ];
    $lookup = [];
    foreach ($questions as $row) { $lookup[] = ['query'=>$row[0],'label'=>$row[1],'aliases'=>$row[2],'related_component_codes'=>$row[3],'status'=>$row[4],'answer'=>$row[5],'relationship'=>'bigsnpr composition-model reference scope; separate from GrafAnc category definitions.','source_url'=>$url]; }
    $ready = is_array($composition) && ($composition['available'] ?? false) === true && ($composition['status'] ?? '') === 'reference_model_proportions' && ($composition['source_version'] ?? '') === $version;
    return ['version'=>'dna-composition-place-context-v1','model'=>'bigsnpr','available'=>$ready,'personal_result'=>$ready,'status'=>$ready?'composition_reference_context':'composition_source_guide_only','groups'=>$groups,'unresolved'=>$lookup,'boundary'=>'Places describe this model\'s published reference membership. Country-of-birth metadata are not separate inherited country shares. A fitted component is relative to the included genetic references; unsupported origins may be redistributed, and unresolved labels do not mean family origins are absent.','sources'=>[
        ['title'=>'Privé 2022 — reference construction and country-of-birth metadata','url'=>$url,'version'=>'Supplement to doi:10.1093/bioinformatics/btac348; source checked 2026-09-05'],
        ['title'=>'Author tutorial — individual composition and required group merges','url'=>'https://privefl.github.io/bigsnpr/articles/ancestry.html','version'=>'Published 2022 tutorial'],
        ['title'=>'Published aggregate allele frequencies and projection weights','url'=>'https://doi.org/10.6084/m9.figshare.16858534.v3','version'=>$version]
    ]];
}

function zl_dna_composition_country_markdown($context) {
    $lines = ['## Places behind the bigsnpr composition references','','This source guide explains the reference groups used for the composition estimate. It is separate from the GrafAnc regional-classifier guide.','',(string)($context['boundary'] ?? '')];
    if (empty($context['personal_result'])) { $lines[] = ''; $lines[] = 'Reference guide only: no supported personal composition result is attached to this context.'; }
    $lines[] = ''; $lines[] = '### Published reference groups and place examples'; $lines[] = '';
    foreach ((array)($context['groups'] ?? []) as $group) {
        $labels = [];
        foreach ((array)($group['places'] ?? []) as $place) { $labels[] = (string)$place['label']; }
        $lines[] = '- **'.$group['label'].'**: '.$group['plain'].($labels ? ' Recorded reference birthplaces include: '.implode(', ',$labels).'.' : ' No birthplace list is treated as a country-ancestry assignment.');
    }
    $lines[] = ''; $lines[] = '### What the bigsnpr composition model can distinguish'; $lines[] = '';
    foreach ((array)($context['unresolved'] ?? []) as $row) { $lines[] = '- **'.$row['label'].'**: '.$row['answer']; }
    $lines[] = ''; $lines[] = '### Composition reference sources'; $lines[] = '';
    foreach ((array)($context['sources'] ?? []) as $source) { $lines[] = '- ['.$source['title'].']('.$source['url'].') — '.$source['version']; }
    return implode("\n",$lines);
}
