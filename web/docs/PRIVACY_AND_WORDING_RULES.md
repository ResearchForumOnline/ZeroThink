# ZeroThink DNA Lab v2 - Privacy and Wording Rules

## Do not leak raw DNA

The DNA Agent and any LLM layer must never receive raw genotype lines.

Allowed AI context:

```json
{
  "quality_band": "good",
  "marker_count": 612345,
  "evidence_cards": [
    {
      "claim_type": "ancestry_signal",
      "claim_label": "East Asian / Han Chinese-related signal",
      "confidence_band": "medium",
      "matched_marker_count": 42381,
      "limitations": "Genetic similarity to installed reference panels only."
    }
  ]
}
```

Forbidden AI context:

```txt
rs123 1 12345 AA
rs456 2 56789 CT
```

## Required health wording

Use:

```txt
Research support only. Not diagnosis or treatment advice.
Discuss concerning findings with a qualified health professional or genetic counsellor.
```

Do not use:

```txt
You have disease X.
You should take drug Y.
You should stop drug Z.
```

## Required ancestry wording

Use:

```txt
signals consistent with...
genetic affinity to installed reference panel...
reference-panel support is limited...
```

Do not use:

```txt
you are Jewish
you are Chinese
you are English
you are Native American
```

## Required royal / lineage wording

Use:

```txt
Current evidence supports a possible research route.
DNA alone cannot prove royal lineage.
Documentary genealogy records are required.
```

Do not use:

```txt
you are royal
royal blood confirmed
proved royal ancestry from DNA
```
