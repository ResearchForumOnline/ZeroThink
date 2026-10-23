<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <title>Privacy Policy | ZeroThink</title>
    <meta name="description" content="Privacy Policy for ZeroThink, QuantumZero, ZMath, DNA Lab integrations, in-house boards, AI workflows, subscriptions, account data, research tools, and security logs.">
    <link rel="canonical" href="https://zerothink.talktoai.org/privacy">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background:#050505; color:#d4d4d8; font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif; }
        a { color:#67e8f9; }
        .panel { background:rgba(255,255,255,.045); border:1px solid rgba(255,255,255,.12); border-radius:14px; }
        h2 { color:#fff; }
    </style>
</head>
<body>
    <main class="mx-auto max-w-5xl px-5 py-10 sm:py-14">
        <a href="/" class="text-sm uppercase tracking-[.25em] text-cyan-300 no-underline">ZeroThink</a>
        <section class="panel mt-6 p-6 sm:p-9">
            <p class="text-sm font-bold uppercase tracking-[.25em] text-emerald-300">Privacy Policy</p>
            <h1 class="mt-3 text-4xl font-black text-white sm:text-5xl">ZeroThink Privacy Policy</h1>
            <p class="mt-5 text-zinc-300">Effective date: 14 June 2026. Last updated: 13 July 2026. This policy explains how ZeroThink, QuantumZero, ZMath, direct-key mode, research tools, subscription tools, upload tools, and in-house boards connected with TalkToAI handle personal information.</p>
            <p class="mt-4 text-zinc-300">Controller contact: <a href="mailto:shaf@talktoai.org">shaf@talktoai.org</a>. Security or privacy requests may also be sent to <a href="mailto:admin@talktoai.org">admin@talktoai.org</a>.</p>
        </section>

        <section class="mt-8 grid gap-5">
            <article class="panel p-6">
                <h2 class="text-2xl font-bold">1. Who this policy covers</h2>
                <p class="mt-3">This policy covers visitors, account holders, subscribers, guests using direct-key mode, people who submit prompts, files, feedback, board posts, support requests, research materials, DNA-related files, ZMath files, or payment-related information through ZeroThink or linked TalkToAI services.</p>
            </article>

            <article class="panel p-6">
                <h2 class="text-2xl font-bold">2. Information collected</h2>
                <ul class="mt-3 list-disc space-y-2 pl-6">
                    <li>Account details, such as name, email address, Google login identifiers, subscription status, account role, and support messages.</li>
                    <li>Technical information, including IP address, user agent, device/browser information, security logs, rate-limit events, and timestamps.</li>
                    <li>User content, including prompts, uploaded files, generated outputs, research notes, chat history, board posts, comments, feedback, and files selected for analysis.</li>
                    <li>Payment-related records, including plan type, subscription status, payment references, cancellation events, and fraud-prevention signals. Full card data is handled by payment processors when applicable.</li>
                    <li>Direct-key mode information. User-supplied API keys may be stored in the user's browser or transmitted to the relevant endpoint only as needed to provide the requested function.</li>
                    <li>DNA Lab or health-research data when a user voluntarily uploads it. This may include sensitive personal data and should only be uploaded by someone with lawful authority to use that file.</li>
                </ul>
            </article>

            <article class="panel p-6">
                <h2 class="text-2xl font-bold">3. How information is used</h2>
                <ul class="mt-3 list-disc space-y-2 pl-6">
                    <li>To provide ZeroThink, QuantumZero, ZMath, DNA Lab, board, research, subscription, upload, export, and support functions.</li>
                    <li>To secure the service, stop abuse, detect automated misuse, investigate suspicious activity, protect intellectual property, and preserve audit logs.</li>
                    <li>To improve reliability, debug errors, test features, maintain model and tool performance, and understand which features are useful.</li>
                    <li>To process subscriptions, cancellations, refunds where applicable, and payment disputes.</li>
                    <li>To comply with legal obligations, enforce terms, respond to lawful requests, and defend against claims.</li>
                </ul>
            </article>

            <article class="panel p-6">
                <h2 class="text-2xl font-bold">4. Lawful bases</h2>
                <p class="mt-3">Depending on the context, information may be processed to perform a contract with the user, to take steps requested by the user, for legitimate interests such as security, service improvement, fraud prevention, IP protection, record keeping, and claim defence, to comply with legal obligations, or with consent where consent is required.</p>
            </article>

            <article class="panel p-6">
                <h2 class="text-2xl font-bold">5. AI, research, and sensitive files</h2>
                <p class="mt-3">Users should not submit confidential, regulated, medical, genetic, legal, financial, government, defence, employer-owned, third-party, or highly sensitive material unless they have permission and accept the processing risk. DNA and health-research outputs are informational research outputs only and are not medical advice, diagnosis, treatment, or clinical decision support.</p>
            </article>

            <article class="panel p-6">
                <h2 class="text-2xl font-bold">5A. DNA Lab data flow and choices</h2>
                <ul class="mt-3 list-disc space-y-2 pl-6">
                    <li><strong class="text-white">Explicit upload:</strong> DNA Lab processes a raw genotype file only after the signed-in user confirms the research-use and privacy notice. Upload only your own file, or a file you have clear authority to process.</li>
                    <li><strong class="text-white">Default handling:</strong> the uploaded raw file is used to generate the requested report and is not kept as a normal library document. The temporary upload is discarded by the server after the request.</li>
                    <li><strong class="text-white">Optional private save:</strong> if the user selects “save,” an eligible file is stored in that account's private, non-web DNA vault. It is classified as sensitive genotype data and is excluded from Zero Library search, retrieval-augmented generation, and ordinary AI context.</li>
                    <li><strong class="text-white">Optional DNA Agent:</strong> a separate consent is required. The Agent receives a minimised derived summary, not the raw file, sample rows, filename, file hash, exact genotypes, or rsID list. The private DNA-Agent lane does not read or write normal chat history, saved memory, or Zero Library context. The minimised summary and the user's question are sent to the model provider identified in the interface, currently Groq.</li>
                    <li><strong class="text-white">Sensitive health screen:</strong> selected sensitive findings are withheld by default and require a separate opt-in. Build, strand/orientation, callability, and quality gates may withhold personalised interpretation.</li>
                    <li><strong class="text-white">No silent imputation:</strong> a missing or no-call marker is not treated as a negative result, and ZeroThink does not silently strand-flip, impute, or clinically confirm an uploaded result.</li>
                </ul>
                <p class="mt-4">To ask about access, correction, deletion, restriction, or withdrawal of consent for DNA-related data, email <a href="mailto:shaf@talktoai.org">shaf@talktoai.org</a> from the account email. Backup and security-log handling is described below.</p>
            </article>

            <article class="panel p-6">
                <h2 class="text-2xl font-bold">6. Sharing and processors</h2>
                <p class="mt-3">Information may be processed by hosting providers, payment providers, email providers, analytics/security tools, AI model providers, API providers, search providers, quantum-cloud or research API providers, and other technical suppliers used to deliver the service. DNA uploads are not sent to an AI provider merely to create a deterministic local report; the separately consented DNA Agent sends only the minimised derived context described above. Information may also be disclosed where required by law, to protect users or systems, to enforce terms, or as part of a business transfer.</p>
            </article>

            <article class="panel p-6">
                <h2 class="text-2xl font-bold">7. In-house boards and public areas</h2>
                <p class="mt-3">Posts, comments, profile names, uploaded public materials, and board activity may be visible to other users or administrators. Users should not post private information, secrets, passwords, keys, personal data about others, unlawful material, or content they do not have the right to publish.</p>
            </article>

            <article class="panel p-6">
                <h2 class="text-2xl font-bold">8. Retention</h2>
                <p class="mt-3">Records are kept only as long as reasonably needed for service delivery, security, account management, payment records, legal compliance, dispute handling, backups, research audit trails, and IP protection. An unsaved DNA upload is temporary for the analysis request. A DNA file saved by the user remains in the account-private vault until it is removed through an available account function or a verified deletion request, subject to limited backup retention and legal obligations. Security logs, abuse records, payment records, and claim-defence records may be retained for longer where necessary; application access logs are designed not to record uploaded file bodies.</p>
            </article>

            <article class="panel p-6">
                <h2 class="text-2xl font-bold">9. User rights</h2>
                <p class="mt-3">Depending on location and the information involved, users may have rights to access, correction, deletion, restriction, objection, portability, withdrawal of consent, and complaint to a regulator. Requests can be sent to <a href="mailto:shaf@talktoai.org">shaf@talktoai.org</a>. Identity checks may be required before action is taken.</p>
            </article>

            <article class="panel p-6">
                <h2 class="text-2xl font-bold">10. Security</h2>
                <p class="mt-3">Reasonable technical and organisational measures are used to protect the service, but no website, AI workflow, blockchain transaction, uploaded-file system, or API integration can be guaranteed completely secure. Users are responsible for keeping account access, API keys, wallets, and files safe.</p>
            </article>

            <article class="panel p-6">
                <h2 class="text-2xl font-bold">11. Changes</h2>
                <p class="mt-3">This policy may be updated as the service changes. Continued use after an update means the user accepts the updated policy where permitted by law.</p>
            </article>
        </section>

        <footer class="mt-10 flex flex-wrap gap-4 text-sm text-zinc-400">
            <a href="/">Home</a>
            <a href="/terms">Terms</a>
            <a href="https://talktoai.org/privacy">TalkToAI Privacy</a>
            <a href="https://talktoai.org/terms">TalkToAI Terms</a>
        </footer>
    </main>
</body>
</html>
