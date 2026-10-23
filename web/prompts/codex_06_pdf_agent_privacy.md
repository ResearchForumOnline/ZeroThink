Implement PDF report v2 and DNA Agent privacy safety.

PDF sections:
1. summary
2. raw file quality
3. ancestry/genetic-affinity signals
4. ancestry question results if run
5. royal/lineage clues if run
6. health research cards if run
7. source database versions
8. confidence and limitations
9. next steps
10. privacy note

DNA Agent rules:
- Agent receives only structured report summaries and evidence cards.
- Agent never receives raw genotype rows.
- Agent can ask follow-up questions based on missing evidence.
- For health, agent must not diagnose or recommend treatment.
- For lineage, agent asks for surnames, family side, places, dates, GEDCOM/tree.

Privacy events:
- upload_started
- upload_parsed
- report_generated
- raw_deleted
- private_save_enabled

Add tests/static assertions that raw genotype rows are not passed to logger or agent functions.
