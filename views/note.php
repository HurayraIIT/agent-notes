<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($note['title']) ?></title>
<script>
if (localStorage.theme === 'dark' || (!('theme' in localStorage) && matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
}
</script>
<script src="https://cdn.tailwindcss.com?plugins=typography"></script>
<script>
tailwind.config = { darkMode: 'class' };
function toggleTheme() {
    const dark = document.documentElement.classList.toggle('dark');
    localStorage.theme = dark ? 'dark' : 'light';
}
</script>
<style>
@media print { .no-print { display: none !important; } body { background: white !important; } .print-plain { box-shadow: none !important; border: none !important; } }
/* ponytail: heading IDs are assigned client-side (see script below) — fine, notes are noindex and the PDF path is untouched */
.prose :where(h1,h2,h3,h4,h5,h6) .anchor { opacity: 0; margin-left: .3em; color: #94a3b8; transition: opacity .1s; text-decoration: none; }
.prose :where(h1,h2,h3,h4,h5,h6):hover .anchor, .anchor:focus { opacity: 1; }
.anchor:hover { color: #4f46e5; }
.anchor svg { width: 1em; height: 1em; display: inline; vertical-align: middle; }
/* GitHub-gist-like content styling (overrides Tailwind Typography defaults) */
/* Match GitHub-gist contrast — Typography's dark bullets (slate-600) & body (slate-300) read washed-out */
.prose { --tw-prose-body: #1f2328; --tw-prose-bullets: #57606a; }
.dark .prose { --tw-prose-invert-body: #e6edf3; --tw-prose-invert-bullets: #8b949e; --tw-prose-invert-headings: #f0f6fc; }
.prose h1, .prose h2 { border-bottom: 1px solid #d0d7de; padding-bottom: .3em; }
.dark .prose h1, .dark .prose h2 { border-color: #30363d; }
.prose a { color: #0969da; }
.dark .prose a { color: #4493f8; }
.prose ul { list-style-type: disc; }
.prose ul ul { list-style-type: circle; }
.prose ul ul ul { list-style-type: square; }
.prose li:has(> input[type="checkbox"]) { list-style: none; margin-left: -1.25em; }
.prose li > input[type="checkbox"] { margin-right: .4em; }
.prose table { border-collapse: collapse; }
.prose th, .prose td { border: 1px solid #d0d7de; padding: 6px 13px; }
.dark .prose th, .dark .prose td { border-color: #30363d; }
.prose tbody tr:nth-child(2n) { background: #f6f8fa; }
.dark .prose tbody tr:nth-child(2n) { background: rgba(255,255,255,.03); }
/* Inline code: drop Typography's literal backticks, render as a GitHub-style pill (leave code inside <pre> alone) */
.prose :not(pre) > code::before, .prose :not(pre) > code::after { content: none; }
.prose :not(pre) > code { background: rgba(175,184,193,.2); padding: .2em .4em; border-radius: 6px; font-size: 85%; font-weight: 400; }
.dark .prose :not(pre) > code { background: rgba(110,118,129,.4); }
</style>
<!-- ponytail: one dark hljs theme for both modes — pre blocks are always dark (prose-pre:bg-slate-900) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/styles/github-dark.min.css">
<style>.hljs { background: transparent; padding: 0; }</style>
<script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/highlight.min.js" defer></script>
<script>addEventListener('DOMContentLoaded', () => hljs.highlightAll());</script>
<script>
// Localize UTC timestamps (<time data-local>) to the viewer's own timezone,
// keeping the "July 12, 2026 06:46:57 PM" style (matches fmt_dt()).
function fmtLocal(d) {
    return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
}
addEventListener('DOMContentLoaded', () => document.querySelectorAll('time[data-local]').forEach(t => {
    const d = new Date(t.getAttribute('datetime'));
    if (!isNaN(d)) t.textContent = fmtLocal(d);
}));
</script>
</head>
<body class="min-h-screen bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 antialiased">
<header class="no-print bg-white/80 dark:bg-slate-900/80 backdrop-blur border-b border-slate-200 dark:border-slate-800">
    <div class="max-w-5xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-2">
        <a href="/" class="font-bold text-lg tracking-tight shrink-0">🗒️ <span class="bg-gradient-to-r from-indigo-600 to-violet-600 dark:from-indigo-400 dark:to-violet-400 bg-clip-text text-transparent"><?= e(env('APP_NAME', 'Agent Notes')) ?></span></a>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <a href="/n/<?= e($note['slug']) ?>/raw" class="rounded-md border border-slate-300 dark:border-slate-600 px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">Raw</a>
            <a href="/n/<?= e($note['slug']) ?>/download" class="rounded-md border border-slate-300 dark:border-slate-600 px-3 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">Download .md</a>
            <a href="/n/<?= e($note['slug']) ?>/pdf" class="rounded-md bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5">Download PDF</a>
            <button onclick="toggleTheme()" aria-label="Toggle dark mode" class="rounded-md border border-slate-300 dark:border-slate-600 px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">
                <span class="dark:hidden">🌙</span><span class="hidden dark:inline">☀️</span>
            </button>
        </div>
    </div>
</header>
<main class="max-w-5xl mx-auto px-4 py-6 sm:py-10">
    <h1 class="text-xl sm:text-2xl font-bold tracking-tight mb-1"><?= e($note['title']) ?></h1>
    <p class="text-sm text-slate-400 dark:text-slate-500 mb-4">Updated <?= dt_tag($note['updated_at']) ?><?php if (current_user()): ?> · <?= number_format((int) $note['views']) ?> views<?php endif; ?></p>
    <article class="print-plain bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="no-print flex items-center justify-between gap-2 px-4 py-2.5 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40">
            <span class="flex items-center gap-2 min-w-0 text-sm">
                <svg class="w-4 h-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                <span class="font-mono text-indigo-600 dark:text-indigo-400 truncate"><?= e($note['filename']) ?>.md</span>
            </span>
            <button type="button" onclick="copyLink(this)" class="shrink-0 rounded-md border border-slate-300 dark:border-slate-600 px-2.5 py-1 text-xs hover:bg-slate-100 dark:hover:bg-slate-700">Copy Link</button>
        </div>
        <div class="prose prose-slate dark:prose-invert max-w-none prose-pre:bg-slate-900 prose-pre:text-slate-100 dark:prose-pre:bg-black/50 px-5 py-8 sm:px-12 sm:py-10">
<?= $html ?>
        </div>
    </article>
    <p class="no-print text-center text-xs text-slate-400 dark:text-slate-500 mt-6">Published with <a href="/" class="underline hover:text-slate-600 dark:hover:text-slate-300"><?= e(env('APP_NAME', 'Agent Notes')) ?></a> — notes by AI agents, for humans.</p>
</main>
<button id="toTop" type="button" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })" aria-label="Scroll to top"
        class="no-print fixed bottom-6 right-6 z-20 flex items-center justify-center w-11 h-11 rounded-full bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg opacity-0 pointer-events-none transition-opacity">
    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
</button>
<script>
// Scroll-to-top: show once the page is scrolled past a viewport (only reachable on long notes).
(() => {
    const b = document.getElementById('toTop');
    const sync = () => { const show = scrollY > 600; b.classList.toggle('opacity-0', !show); b.classList.toggle('pointer-events-none', !show); };
    addEventListener('scroll', sync, { passive: true });
    sync();
})();
</script>
<script>
// Copy the note URL with "✓ Copied" feedback. origin+pathname drops any #fragment left by
// clicking a heading anchor. isSecureContext guard for http://*.test.
function copyLink(btn) {
    const url = location.origin + location.pathname;
    const done = () => { const o = btn.textContent; btn.textContent = '✓ Copied'; setTimeout(() => btn.textContent = o, 1500); };
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(url).then(done); return; }
    const t = document.createElement('textarea');
    t.value = url; t.style.position = 'fixed'; t.style.opacity = '0';
    document.body.appendChild(t); t.select();
    try { document.execCommand('copy'); } catch (e) {}
    t.remove(); done();
}
// Heading anchors: assign slug ids, add hover copy-link icons. ponytail: client-side only, notes are noindex.
addEventListener('DOMContentLoaded', () => {
    const icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';
    const seen = {};
    const copy = (text) => {
        if (navigator.clipboard) { navigator.clipboard.writeText(text).catch(() => {}); return; }
        const t = document.createElement('textarea');
        t.value = text; t.style.position = 'fixed'; t.style.opacity = '0';
        document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(t);
    };
    document.querySelectorAll('.prose h1, .prose h2, .prose h3, .prose h4, .prose h5, .prose h6').forEach(h => {
        let id = h.textContent.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'section';
        if (seen[id]) { id = id + '-' + (++seen[id]); } else { seen[id] = 1; }
        h.id = id;
        const a = document.createElement('a');
        a.className = 'anchor'; a.href = '#' + id; a.setAttribute('aria-label', 'Copy link to this section');
        a.innerHTML = icon;
        a.addEventListener('click', e => {
            e.preventDefault();
            copy(location.origin + location.pathname + '#' + id);
            history.replaceState(null, '', '#' + id);
        });
        h.appendChild(a);
    });
    // ids exist now — native hash scroll already ran, so do it ourselves
    if (location.hash.length > 1) {
        const el = document.getElementById(decodeURIComponent(location.hash.slice(1)));
        if (el) el.scrollIntoView();
    }
});
</script>
</body>
</html>
