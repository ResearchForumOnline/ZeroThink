<?php
// Production errors are logged server-side, never rendered to visitors.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/app/bootstrap.php';

$return_to = sanitize_local_return_path($_GET['return'] ?? '', '/studio');

if (isset($_SESSION['user_id'])) {
    header('Location: ' . $return_to);
    exit;
}

$google_sign_in_for = static function ($path) {
    return '/auth/google?return=' . htmlspecialchars(
        rawurlencode(sanitize_local_return_path($path, '/studio')),
        ENT_QUOTES,
        'UTF-8'
    );
};

$google_sign_in = $google_sign_in_for($return_to);
$google_sign_in_dna = $google_sign_in_for('/dna');
$google_sign_in_media = $google_sign_in_for('/media');
$google_sign_in_library = $google_sign_in_for('/library');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#071013">
    <title>ZeroThink | AI Research and Evidence Workbench</title>

    <meta name="description" content="ZeroThink brings AI workspaces, claim testing, DNA evidence tools, research drafting, media workflows and direct model access into one practical workbench.">
    <meta property="og:title" content="ZeroThink | AI Research and Evidence Workbench">
    <meta property="og:description" content="Move from a question to evidence, analysis and a finished output with ZeroThink Studio, ZeroKill, DNA Lab and specialist research tools.">
    <meta property="og:image" content="https://zerothink.talktoai.org/assets/img/zerothink-workbench-2026.png">
    <meta property="og:url" content="https://zerothink.talktoai.org/">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="ZeroThink | AI Research and Evidence Workbench">
    <meta name="twitter:description" content="AI workspaces, adversarial claim testing, DNA evidence tools, research drafting and practical utilities in one system.">
    <meta name="twitter:image" content="https://zerothink.talktoai.org/assets/img/zerothink-workbench-2026.png">

    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <link rel="preload" as="image" href="/assets/img/zerothink-workbench-2026.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --page: #071013;
            --surface: #0d171a;
            --surface-strong: #111e22;
            --line: #26383c;
            --line-strong: #3b555a;
            --text: #f3f7f6;
            --muted: #9fb0b3;
            --quiet: #718488;
            --aqua: #41ddc7;
            --aqua-dark: #052b27;
            --coral: #ff8065;
            --lime: #b6dd66;
            --focus: #f7d36b;
            --max: 1180px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            overflow-x: hidden;
        }

        body {
            margin: 0;
            min-width: 320px;
            overflow-x: hidden;
            color: var(--text);
            background: var(--page);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.5;
        }

        a {
            color: inherit;
        }

        img {
            display: block;
            max-width: 100%;
        }

        .skip-link {
            position: fixed;
            top: 8px;
            left: 8px;
            z-index: 100;
            padding: 10px 14px;
            color: #041310;
            background: var(--focus);
            border-radius: 4px;
            font-weight: 800;
            transform: translateY(-150%);
        }

        .skip-link:focus {
            transform: translateY(0);
        }

        :focus-visible {
            outline: 3px solid var(--focus);
            outline-offset: 3px;
        }

        .shell {
            width: min(var(--max), calc(100% - 40px));
            margin: 0 auto;
        }

        .site-header {
            position: absolute;
            inset: 0 0 auto;
            z-index: 20;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(5, 12, 14, 0.9);
            backdrop-filter: blur(14px);
        }

        .header-inner {
            min-height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--text);
            text-decoration: none;
            font-size: 1rem;
            font-weight: 850;
        }

        .brand img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .brand small {
            color: var(--muted);
            font-size: 0.7rem;
            font-weight: 650;
        }

        .desktop-nav {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .desktop-nav a,
        .mobile-menu a {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 11px;
            border: 1px solid transparent;
            border-radius: 4px;
            color: #d7e1df;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 730;
        }

        .desktop-nav a:hover,
        .mobile-menu a:hover {
            border-color: var(--line-strong);
            background: rgba(255, 255, 255, 0.06);
        }

        .desktop-nav .sign-in,
        .mobile-menu .sign-in {
            margin-left: 5px;
            color: #041310;
            background: var(--aqua);
            border-color: var(--aqua);
        }

        .desktop-nav .sign-in:hover,
        .mobile-menu .sign-in:hover {
            color: #041310;
            background: #76e9d8;
            border-color: #76e9d8;
        }

        .mobile-menu {
            display: none;
            position: relative;
        }

        .mobile-menu summary {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border: 1px solid var(--line-strong);
            border-radius: 4px;
            color: var(--text);
            cursor: pointer;
            list-style: none;
        }

        .mobile-menu summary::-webkit-details-marker {
            display: none;
        }

        .mobile-menu nav {
            position: absolute;
            top: 50px;
            right: 0;
            width: min(280px, calc(100vw - 32px));
            display: grid;
            gap: 4px;
            padding: 10px;
            border: 1px solid var(--line-strong);
            border-radius: 6px;
            background: #081215;
            box-shadow: 0 18px 42px rgba(0, 0, 0, 0.45);
        }

        .mobile-menu nav a {
            width: 100%;
            justify-content: flex-start;
        }

        .hero {
            position: relative;
            min-height: 760px;
            display: flex;
            align-items: center;
            overflow: hidden;
            isolation: isolate;
            border-bottom: 1px solid var(--line);
            background: #071013;
        }

        .hero-art {
            position: absolute;
            inset: 0;
            z-index: -2;
            background-image: url("/assets/img/zerothink-workbench-2026.png");
            background-position: center;
            background-size: cover;
        }

        .hero::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            background: rgba(3, 9, 11, 0.56);
            pointer-events: none;
        }

        .hero-content {
            width: min(700px, 62%);
            padding: 138px 0 90px;
        }

        .hero-mark {
            width: 64px;
            height: 64px;
            margin-bottom: 24px;
            object-fit: contain;
            filter: drop-shadow(0 8px 24px rgba(65, 221, 199, 0.18));
        }

        .eyebrow {
            margin: 0 0 16px;
            color: var(--aqua);
            font-size: 0.78rem;
            font-weight: 850;
            text-transform: uppercase;
        }

        h1,
        h2,
        h3,
        p {
            margin-top: 0;
        }

        h1 {
            max-width: 680px;
            margin-bottom: 22px;
            font-size: 5.2rem;
            line-height: 0.95;
            letter-spacing: 0;
        }

        .hero-lede {
            max-width: 620px;
            margin-bottom: 30px;
            color: #d6e0df;
            font-size: 1.22rem;
            line-height: 1.7;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
        }

        .button {
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 11px 17px;
            border: 1px solid var(--line-strong);
            border-radius: 5px;
            color: var(--text);
            background: rgba(8, 18, 21, 0.86);
            text-decoration: none;
            font-weight: 820;
        }

        .button:hover {
            border-color: #6d8d92;
            background: #122327;
        }

        .button.primary {
            color: #041310;
            background: var(--aqua);
            border-color: var(--aqua);
        }

        .button.primary:hover {
            background: #76e9d8;
            border-color: #76e9d8;
        }

        .capability-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .capability-list li {
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 10px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 4px;
            color: #c2cfcd;
            background: rgba(5, 13, 15, 0.72);
            font-size: 0.77rem;
            font-weight: 720;
        }

        .capability-list i {
            color: var(--lime);
        }

        .brand-visual {
            padding: 58px 0;
            border-bottom: 1px solid var(--line);
            background: #04090c;
        }

        .brand-visual img {
            display: block;
            width: min(100%, 1040px);
            height: auto;
            margin: 0 auto;
            border: 1px solid rgba(255, 255, 255, 0.24);
            background: #090613;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.38);
        }

        .section {
            padding: 88px 0;
            border-bottom: 1px solid var(--line);
        }

        .section.alt {
            background: #0a1417;
        }

        .section-heading {
            max-width: 760px;
            margin-bottom: 38px;
        }

        .section-heading h2 {
            margin-bottom: 12px;
            font-size: 2.6rem;
            line-height: 1.1;
            letter-spacing: 0;
        }

        .section-heading p {
            margin-bottom: 0;
            color: var(--muted);
            font-size: 1.05rem;
            line-height: 1.7;
        }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            border-top: 1px solid var(--line);
            border-left: 1px solid var(--line);
        }

        .service {
            min-height: 252px;
            display: flex;
            flex-direction: column;
            padding: 24px;
            border-right: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            color: inherit;
            background: var(--surface);
            text-decoration: none;
            transition: background 160ms ease, border-color 160ms ease;
        }

        .service:hover {
            position: relative;
            z-index: 1;
            background: var(--surface-strong);
            box-shadow: inset 0 0 0 1px var(--line-strong);
        }

        .service.featured {
            background: #0d211e;
        }

        .service-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            margin-bottom: 34px;
            border: 1px solid var(--line-strong);
            border-radius: 5px;
            color: var(--aqua);
            background: #071114;
            font-size: 1rem;
        }

        .service.featured .service-icon {
            color: var(--coral);
        }

        .service h3 {
            margin-bottom: 8px;
            font-size: 1.12rem;
            letter-spacing: 0;
        }

        .service p {
            margin-bottom: 20px;
            color: var(--muted);
            font-size: 0.88rem;
            line-height: 1.6;
        }

        .service .open {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: auto;
            color: var(--aqua);
            font-size: 0.8rem;
            font-weight: 820;
        }

        .workflow {
            display: grid;
            grid-template-columns: 0.8fr 1.2fr;
            gap: 70px;
            align-items: start;
        }

        .workflow-copy {
            position: sticky;
            top: 100px;
        }

        .workflow-copy h2 {
            margin-bottom: 16px;
            font-size: 2.6rem;
            line-height: 1.1;
            letter-spacing: 0;
        }

        .workflow-copy p {
            color: var(--muted);
            line-height: 1.7;
        }

        .workflow-steps {
            border-top: 1px solid var(--line-strong);
        }

        .workflow-step {
            display: grid;
            grid-template-columns: 54px 1fr auto;
            gap: 18px;
            align-items: start;
            padding: 24px 0;
            border-bottom: 1px solid var(--line);
        }

        .step-number {
            color: var(--coral);
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
            font-size: 0.82rem;
            font-weight: 850;
        }

        .workflow-step h3 {
            margin-bottom: 6px;
            font-size: 1rem;
        }

        .workflow-step p {
            margin-bottom: 0;
            color: var(--muted);
            font-size: 0.88rem;
            line-height: 1.6;
        }

        .workflow-step a {
            color: var(--aqua);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .directory-cta {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 28px;
            align-items: center;
            padding: 36px 0;
        }

        .directory-cta h2 {
            margin-bottom: 7px;
            font-size: 1.55rem;
            letter-spacing: 0;
        }

        .directory-cta p {
            margin-bottom: 0;
            color: var(--muted);
        }

        .site-footer {
            padding: 34px 0 44px;
            background: #050b0d;
        }

        .footer-main {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 34px;
        }

        .footer-brand {
            max-width: 340px;
        }

        .footer-brand p {
            margin: 12px 0 0;
            color: var(--quiet);
            font-size: 0.82rem;
            line-height: 1.6;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px 18px;
        }

        .footer-links a {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 720;
        }

        .footer-links a:hover {
            color: var(--text);
        }

        .copyright {
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid var(--line);
            color: var(--quiet);
            font-size: 0.75rem;
        }

        @media (max-width: 960px) {
            .desktop-nav {
                display: none;
            }

            .mobile-menu {
                display: block;
            }

            .hero {
                min-height: 720px;
            }

            .hero-content {
                width: min(680px, 80%);
            }

            h1 {
                font-size: 4.3rem;
            }

            .service-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .workflow {
                grid-template-columns: 1fr;
                gap: 36px;
            }

            .workflow-copy {
                position: static;
            }
        }

        @media (max-width: 640px) {
            .shell {
                width: min(100% - 28px, var(--max));
            }

            .header-inner {
                min-height: 64px;
            }

            .brand small {
                display: none;
            }

            .hero {
                min-height: 690px;
                align-items: flex-end;
            }

            .hero-art {
                background-position: 62% center;
            }

            .hero::after {
                background: rgba(3, 9, 11, 0.72);
            }

            .hero-content {
                width: 100%;
                padding: 112px 0 54px;
            }

            .hero-mark {
                width: 50px;
                height: 50px;
                margin-bottom: 18px;
            }

            h1 {
                font-size: 3.3rem;
            }

            .hero-lede {
                font-size: 1.02rem;
                line-height: 1.6;
            }

            .hero-actions {
                display: grid;
                grid-template-columns: 1fr;
            }

            .button {
                width: 100%;
            }

            .capability-list {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .capability-list li {
                min-width: 0;
            }

            .brand-visual {
                padding: 28px 0;
            }

            .section {
                padding: 62px 0;
            }

            .section-heading {
                margin-bottom: 28px;
            }

            .section-heading h2,
            .workflow-copy h2 {
                font-size: 2rem;
            }

            .section-heading p {
                font-size: 0.95rem;
            }

            .service-grid {
                grid-template-columns: 1fr;
            }

            .service {
                min-height: 218px;
                padding: 22px;
            }

            .service-icon {
                margin-bottom: 24px;
            }

            .workflow-step {
                grid-template-columns: 42px 1fr;
            }

            .workflow-step a {
                grid-column: 2;
            }

            .directory-cta {
                grid-template-columns: 1fr;
            }

            .directory-cta .button {
                width: 100%;
            }

            .footer-main {
                display: grid;
            }

            .footer-links {
                justify-content: flex-start;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                transition: none !important;
            }
        }
    </style>
</head>
<body>
<a class="skip-link" href="#services">Skip to services</a>

<header class="site-header">
    <div class="shell header-inner">
        <a class="brand" href="/" aria-label="ZeroThink home">
            <img src="/assets/img/logo_nobg.png" alt="">
            <span>ZeroThink <small>AI workbench</small></span>
        </a>

        <nav class="desktop-nav" aria-label="Primary navigation">
            <a href="<?= $google_sign_in ?>"><i class="fa-solid fa-comments"></i> Studio</a>
            <a href="/zerokill"><i class="fa-solid fa-crosshairs"></i> ZeroKill</a>
            <a href="<?= $google_sign_in_dna ?>"><i class="fa-solid fa-dna"></i> DNA Lab</a>
            <a href="/research-tools"><i class="fa-solid fa-book-open"></i> Research</a>
            <a href="/tools"><i class="fa-solid fa-table-cells-large"></i> All tools</a>
            <a class="sign-in" href="<?= $google_sign_in ?>"><i class="fa-brands fa-google"></i> Sign in</a>
        </nav>

        <details class="mobile-menu">
            <summary aria-label="Open navigation"><i class="fa-solid fa-bars"></i></summary>
            <nav aria-label="Mobile navigation">
                <a href="<?= $google_sign_in ?>"><i class="fa-solid fa-comments"></i> Studio</a>
                <a href="/zerokill"><i class="fa-solid fa-crosshairs"></i> ZeroKill</a>
                <a href="<?= $google_sign_in_dna ?>"><i class="fa-solid fa-dna"></i> DNA Lab</a>
                <a href="/research-tools"><i class="fa-solid fa-book-open"></i> Research &amp; Papers</a>
                <a href="<?= $google_sign_in_media ?>"><i class="fa-solid fa-photo-film"></i> Media Lab</a>
                <a href="/tools"><i class="fa-solid fa-table-cells-large"></i> All tools</a>
                <a class="sign-in" href="<?= $google_sign_in ?>"><i class="fa-brands fa-google"></i> Sign in</a>
            </nav>
        </details>
    </div>
</header>

<main>
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-art" role="img" aria-label="AI research workbench showing evidence, DNA, code and document analysis"></div>
        <div class="shell">
            <div class="hero-content">
                <img class="hero-mark" src="/assets/img/logo_nobg.png" alt="">
                <p class="eyebrow">One workbench. Multiple specialist agents.</p>
                <h1 id="hero-title">ZeroThink</h1>
                <p class="hero-lede">Move from a difficult question to evidence, analysis and a finished output. Use the main AI workspace or open a specialist tool for claim testing, DNA research, papers, media and technical work.</p>

                <div class="hero-actions">
                    <a class="button primary" href="<?= $google_sign_in ?>">
                        <i class="fa-brands fa-google"></i> Open ZeroThink
                    </a>
                    <a class="button" href="/guest">
                        <i class="fa-solid fa-key"></i> Use direct mode
                    </a>
                    <a class="button" href="#services">
                        <i class="fa-solid fa-arrow-down"></i> Choose a service
                    </a>
                </div>

                <ul class="capability-list" aria-label="Core capabilities">
                    <li><i class="fa-solid fa-check"></i> Multi-model AI</li>
                    <li><i class="fa-solid fa-check"></i> Evidence-led research</li>
                    <li><i class="fa-solid fa-check"></i> Bring your own key</li>
                    <li><i class="fa-solid fa-check"></i> Exportable work</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="brand-visual" aria-label="ZeroThink original artwork">
        <div class="shell">
            <img
                src="/assets/img/zerothinkmain.png"
                width="963"
                height="537"
                alt="ZeroThink artwork with a luminous neural brain, an infinity circuit symbol and the ZeroThink name"
                decoding="async"
                fetchpriority="high"
            >
        </div>
    </section>

    <section class="section" id="services" aria-labelledby="services-title">
        <div class="shell">
            <div class="section-heading">
                <p class="eyebrow">Start with the job</p>
                <h2 id="services-title">Choose the right ZeroThink service.</h2>
                <p>The main services are visible here. The complete directory remains available when you need a specialist utility or account tool.</p>
            </div>

            <div class="service-grid">
                <a class="service featured" href="<?= $google_sign_in ?>">
                    <span class="service-icon"><i class="fa-solid fa-comments"></i></span>
                    <h3>ZeroThink Studio</h3>
                    <p>The main signed-in workspace for conversations, provider settings, saved history, files and advanced agent lanes.</p>
                    <span class="open">Sign in to Studio <i class="fa-solid fa-arrow-right"></i></span>
                </a>

                <a class="service featured" href="/zerokill">
                    <span class="service-icon"><i class="fa-solid fa-crosshairs"></i></span>
                    <h3>ZeroKill</h3>
                    <p>Put a claim on trial. Try to falsify it, strengthen it or produce a balanced for-and-against assessment.</p>
                    <span class="open">Stress-test a claim <i class="fa-solid fa-arrow-right"></i></span>
                </a>

                <a class="service featured" href="<?= $google_sign_in_dna ?>">
                    <span class="service-icon"><i class="fa-solid fa-dna"></i></span>
                    <h3>DNA Lab</h3>
                    <p>Run local-first raw DNA quality checks, research evidence cards, heritage signals, lineage clues and report exports.</p>
                    <span class="open">Sign in to DNA Lab <i class="fa-solid fa-arrow-right"></i></span>
                </a>

                <a class="service" href="/research-tools">
                    <span class="service-icon"><i class="fa-solid fa-book-open"></i></span>
                    <h3>Research &amp; Papers</h3>
                    <p>Find literature, track provenance, build claim maps, draft papers, run critique passes and export reviewed work.</p>
                    <span class="open">Open research hub <i class="fa-solid fa-arrow-right"></i></span>
                </a>

                <a class="service" href="<?= $google_sign_in_media ?>">
                    <span class="service-icon"><i class="fa-solid fa-photo-film"></i></span>
                    <h3>Media Lab</h3>
                    <p>Work with supported voice, image and video-generation routes, then download the resulting media assets.</p>
                    <span class="open">Sign in to Media Lab <i class="fa-solid fa-arrow-right"></i></span>
                </a>

                <a class="service" href="/cli">
                    <span class="service-icon"><i class="fa-solid fa-terminal"></i></span>
                    <h3>Agent Zero CLI</h3>
                    <p>Use the command-line workbench for larger local files, archives, research jobs and server-oriented workflows.</p>
                    <span class="open">Open CLI guide <i class="fa-solid fa-arrow-right"></i></span>
                </a>

                <a class="service" href="/guest">
                    <span class="service-icon"><i class="fa-solid fa-key"></i></span>
                    <h3>Direct Mode</h3>
                    <p>Use a supported model without a Google account by bringing a provider key for a transient browser session.</p>
                    <span class="open">Start direct mode <i class="fa-solid fa-arrow-right"></i></span>
                </a>

                <a class="service" href="<?= $google_sign_in_library ?>">
                    <span class="service-icon"><i class="fa-solid fa-box-archive"></i></span>
                    <h3>Zero Library</h3>
                    <p>Keep private files and reusable context packs available for your signed-in ZeroThink workflows.</p>
                    <span class="open">Sign in to Library <i class="fa-solid fa-arrow-right"></i></span>
                </a>

                <a class="service" href="/quantumzero">
                    <span class="service-icon"><i class="fa-solid fa-atom"></i></span>
                    <h3>QuantumZero</h3>
                    <p>A specialist research lane that combines classical AI reasoning with connected quantum-cloud job context and telemetry.</p>
                    <span class="open">Open QuantumZero <i class="fa-solid fa-arrow-right"></i></span>
                </a>
            </div>
        </div>
    </section>

    <section class="section alt" aria-labelledby="workflow-title">
        <div class="shell workflow">
            <div class="workflow-copy">
                <p class="eyebrow">A connected workflow</p>
                <h2 id="workflow-title">From source to decision.</h2>
                <p>ZeroThink is more useful when its tools are treated as stages of one job: gather material, challenge the central claim, build the output and keep the work available for review.</p>
                <a class="button" href="/research-tools"><i class="fa-solid fa-diagram-project"></i> Open research workflow</a>
            </div>

            <div class="workflow-steps">
                <div class="workflow-step">
                    <span class="step-number">01</span>
                    <div>
                        <h3>Discover and record sources</h3>
                        <p>Use the research routes to capture titles, URLs, snippets, timestamps and evidence status.</p>
                    </div>
                    <a href="/docs/serper-search">Provenance guide</a>
                </div>

                <div class="workflow-step">
                    <span class="step-number">02</span>
                    <div>
                        <h3>Challenge the important claim</h3>
                        <p>Run ZeroKill before publication to expose weak assumptions, missing evidence and stronger counterarguments.</p>
                    </div>
                    <a href="/zerokill">Run ZeroKill</a>
                </div>

                <div class="workflow-step">
                    <span class="step-number">03</span>
                    <div>
                        <h3>Build the reviewed output</h3>
                        <p>Use Paper Creator or PDF Creator to turn the evidence ledger into a structured, exportable artifact.</p>
                    </div>
                    <a href="/research-paper-creator">Create a paper</a>
                </div>

                <div class="workflow-step">
                    <span class="step-number">04</span>
                    <div>
                        <h3>Continue in the main workspace</h3>
                        <p>Return to Studio for follow-up analysis, saved history, files and the model route that fits the next task.</p>
                    </div>
                    <a href="<?= $google_sign_in ?>">Open Studio</a>
                </div>
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="directory-title">
        <div class="shell directory-cta">
            <div>
                <h2 id="directory-title">Need a different tool?</h2>
                <p>Browse every ZeroThink workspace, research utility, specialist lab, document tool and help destination in one directory.</p>
            </div>
            <a class="button primary" href="/tools"><i class="fa-solid fa-table-cells-large"></i> View all tools</a>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="shell">
        <div class="footer-main">
            <div class="footer-brand">
                <a class="brand" href="/">
                    <img src="/assets/img/logo_nobg.png" alt="">
                    <span>ZeroThink</span>
                </a>
                <p>AI research and evidence workbench from TalkToAI. Important outputs still require source checking and appropriate human review.</p>
            </div>

            <nav class="footer-links" aria-label="Footer navigation">
                <a href="/tools">All tools</a>
                <a href="/docs">Docs</a>
                <a href="/faq">FAQ</a>
                <a href="https://research.talktoai.org">Research Hub</a>
                <a href="https://talktoai.org">TalkToAI</a>
                <a href="/privacy">Privacy</a>
                <a href="/terms">Terms</a>
            </nav>
        </div>

        <div class="copyright">&copy; 2026 TalkToAI / ZeroThink</div>
    </div>
</footer>
</body>
</html>
