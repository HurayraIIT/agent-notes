<?php
// Only the signed-in owner gets the editor. Same comparison the view-counter skip uses in
// index.php — and everything editor-related is emitted inside if ($can_edit), so a stranger's
// page has no Edit button, no CSRF token and no editor markup to un-hide.
$viewer = current_user();
$can_edit = $viewer && (int) $viewer['id'] === (int) $note['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($note['title']) ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'><stop offset='0' stop-color='%234f46e5'/><stop offset='1' stop-color='%237c3aed'/></linearGradient></defs><rect width='32' height='32' rx='7' fill='url(%23g)'/><g stroke='%23fff' stroke-width='2.5' stroke-linecap='round'><line x1='9' y1='11' x2='23' y2='11'/><line x1='9' y1='16' x2='23' y2='16'/><line x1='9' y1='21' x2='18' y2='21'/></g></svg>">
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
    window.renderMermaid?.(document.body);   // diagrams are baked SVG — they need a re-render
}
</script>
<style>
/* .print-plain is force-shown because the editor hides the article — printing mid-edit would otherwise emit a blank page. */
@media print { .no-print { display: none !important; } body { background: white !important; } .print-plain { display: block !important; box-shadow: none !important; border: none !important; } }
/* ponytail: heading IDs are assigned client-side (see script below) — fine, notes are noindex and the PDF path is untouched */
.prose :where(h1,h2,h3,h4,h5,h6) .anchor { opacity: 0; margin-left: .3em; color: #94a3b8; transition: opacity .1s; text-decoration: none; }
.prose :where(h1,h2,h3,h4,h5,h6):hover .anchor, .anchor:focus { opacity: 1; }
.anchor:hover { color: #4f46e5; }
.anchor svg { width: .8em; height: .8em; display: inline; vertical-align: middle; }
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
/* GitHub treatment: table is its own horizontal scroll box, so wide tables never overflow the card or the page */
.prose table { display: block; width: max-content; max-width: 100%; overflow: auto; border-collapse: collapse; }
.prose th, .prose td { border: 1px solid #d0d7de; padding: 6px 13px; }
.dark .prose th, .dark .prose td { border-color: #30363d; }
.prose tbody tr:nth-child(2n) { background: #f6f8fa; }
.dark .prose tbody tr:nth-child(2n) { background: rgba(255,255,255,.03); }
/* Inline code: drop Typography's literal backticks, render as a GitHub-style pill (leave code inside <pre> alone) */
.prose :not(pre) > code::before, .prose :not(pre) > code::after { content: none; }
.prose :not(pre) > code { background: rgba(175,184,193,.2); padding: .2em .4em; border-radius: 6px; font-size: 85%; font-weight: 400; }
.dark .prose :not(pre) > code { background: rgba(110,118,129,.4); }
/* <details>/<summary> reach the browser through the raw-HTML allowlist in src/markdown.php.
   Typography styles neither, so they get the gist treatment here — borderless, like GitHub. */
.prose details { margin: 1em 0; }
.prose summary { cursor: pointer; font-weight: 600; }
.prose summary::marker { color: #57606a; }
.dark .prose summary::marker { color: #8b949e; }
.prose summary:hover { color: #0969da; }
.dark .prose summary:hover { color: #4493f8; }
.prose details[open] > summary { margin-bottom: .8em; }
/* Typography's margin on the first/last child reads as dead space inside the disclosure. */
.prose details > *:not(summary):first-of-type { margin-top: 0; }
.prose details > *:last-child { margin-bottom: 0; }
/* GitHub alerts (> [!NOTE] …) — markup comes from GithubAlertRenderer, never from note content. */
.prose .md-alert { border-left: .25em solid; padding: 0 1em; margin: 1em 0; }
.prose .md-alert > *:first-child { margin-top: 0; }
.prose .md-alert > *:last-child { margin-bottom: 0; }
.prose .md-alert-title { font-weight: 600; color: inherit; margin-bottom: .4em; }
.prose .md-alert-note { border-color: #0969da; } .prose .md-alert-note .md-alert-title { color: #0969da; }
.prose .md-alert-tip { border-color: #1a7f37; } .prose .md-alert-tip .md-alert-title { color: #1a7f37; }
.prose .md-alert-important { border-color: #8250df; } .prose .md-alert-important .md-alert-title { color: #8250df; }
.prose .md-alert-warning { border-color: #9a6700; } .prose .md-alert-warning .md-alert-title { color: #9a6700; }
.prose .md-alert-caution { border-color: #cf222e; } .prose .md-alert-caution .md-alert-title { color: #cf222e; }
.dark .prose .md-alert-note { border-color: #4493f8; } .dark .prose .md-alert-note .md-alert-title { color: #4493f8; }
.dark .prose .md-alert-tip { border-color: #3fb950; } .dark .prose .md-alert-tip .md-alert-title { color: #3fb950; }
.dark .prose .md-alert-important { border-color: #ab7df8; } .dark .prose .md-alert-important .md-alert-title { color: #ab7df8; }
.dark .prose .md-alert-warning { border-color: #d29922; } .dark .prose .md-alert-warning .md-alert-title { color: #d29922; }
.dark .prose .md-alert-caution { border-color: #f85149; } .dark .prose .md-alert-caution .md-alert-title { color: #f85149; }
/* Mermaid replaces the <pre> contents with an SVG — drop the code-block chrome it inherits. */
.prose pre.mermaid { background: transparent; padding: 0; text-align: center; }
</style>
<!-- ponytail: one dark hljs theme for both modes — pre blocks are always dark (prose-pre:bg-slate-900) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/styles/github-dark.min.css">
<style>.hljs { background: transparent; padding: 0; }</style>
<script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/highlight.min.js" defer></script>
<!-- Highlighting runs through decorateProse() (bottom of page) so re-rendered content gets it too. -->
<script>
// Localize UTC timestamps (<time data-local>) to the viewer's own timezone,
// keeping the "July 12, 2026 06:46:57 PM" style (matches fmt_dt()).
function fmtLocal(d) {
    return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
}
// "5 minutes ago" — coarsest unit that fits. Full local timestamp goes in title (hover tooltip).
function timeAgo(d) {
    const s = Math.max(0, Math.floor((Date.now() - d) / 1000));
    for (const [sec, name] of [[31536000,'year'],[2592000,'month'],[86400,'day'],[3600,'hour'],[60,'minute'],[1,'second']]) {
        const n = Math.floor(s / sec);
        if (n >= 1) return n + ' ' + name + (n > 1 ? 's' : '') + ' ago';
    }
    return 'just now';
}
addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('time[data-local]').forEach(t => {
        const d = new Date(t.getAttribute('datetime'));
        if (!isNaN(d)) t.textContent = fmtLocal(d);
    });
    document.querySelectorAll('time[data-ago]').forEach(t => {
        const d = new Date(t.getAttribute('datetime'));
        if (isNaN(d)) return;
        t.title = fmtLocal(d);
        t.textContent = timeAgo(d);
    });
});
</script>
</head>
<body class="min-h-screen bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 antialiased">
<header class="no-print bg-white/80 dark:bg-slate-900/80 backdrop-blur border-b border-slate-200 dark:border-slate-800">
    <div class="max-w-5xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-2">
        <a href="/" class="font-bold text-lg tracking-tight shrink-0"><?= brand_icon() ?> <span class="bg-gradient-to-r from-indigo-600 to-violet-600 dark:from-indigo-400 dark:to-violet-400 bg-clip-text text-transparent"><?= e(env('APP_NAME', 'Agent Notes')) ?></span></a>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <?php if ($can_edit): ?>
            <button type="button" id="editBtn" onclick="openEditor()" class="h-9 inline-flex items-center gap-1.5 rounded-md border border-slate-300 dark:border-slate-600 px-3 hover:bg-slate-100 dark:hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>Edit
            </button>
            <?php endif; ?>
            <a href="/n/<?= e($note['slug']) ?>/raw" class="h-9 inline-flex items-center rounded-md border border-slate-300 dark:border-slate-600 px-3 hover:bg-slate-100 dark:hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">Raw</a>
            <a href="/n/<?= e($note['slug']) ?>/download" class="h-9 inline-flex items-center rounded-md border border-slate-300 dark:border-slate-600 px-3 hover:bg-slate-100 dark:hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900"><span class="sm:hidden">.md</span><span class="hidden sm:inline">Download .md</span></a>
            <a href="/n/<?= e($note['slug']) ?>/pdf" class="h-9 inline-flex items-center rounded-md bg-indigo-600 hover:bg-indigo-500 text-white px-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900"><span class="sm:hidden">PDF</span><span class="hidden sm:inline">Download PDF</span></a>
            <button onclick="toggleTheme()" aria-label="Toggle dark mode" class="h-9 w-9 inline-flex items-center justify-center rounded-md border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">
                <span class="dark:hidden">🌙</span><span class="hidden dark:inline">☀️</span>
            </button>
        </div>
    </div>
</header>
<main class="max-w-5xl mx-auto px-4 py-6 sm:py-10">
<!-- Dark reading surfaces use GitHub's canvas hexes, not slate: card/editor #0d1117, pre blocks #161b22 (one step
     lighter, so code separates), inner strips a neutral white/5. Slate is blue-tinted and clashes with the
     github-dark hljs theme and the gist palette in the <style> above. The page/header stay slate — they frame, not hold text. -->
    <article class="print-plain bg-white dark:bg-[#0d1117] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="no-print flex items-center justify-between gap-2 px-4 py-2.5 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-white/5">
            <span class="flex items-center gap-2 min-w-0 text-sm">
                <svg class="w-4 h-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                <span class="font-mono text-indigo-600 dark:text-indigo-400 truncate"><?= e($note['filename']) ?>.md</span>
            </span>
            <button type="button" onclick="copyLink(this)" class="shrink-0 rounded-md border border-slate-300 dark:border-slate-600 px-2.5 py-1 text-xs hover:bg-slate-100 dark:hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">Copy Link</button>
        </div>
        <!-- Title lives inside the card (not no-print) so it survives the print/PDF path too. -->
        <div class="px-5 pt-6 pb-4 sm:px-12 sm:pt-8 border-b border-slate-200 dark:border-slate-800">
            <h1 id="noteTitle" class="text-xl sm:text-2xl font-bold tracking-tight"><?= e($note['title']) ?></h1>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-1">Updated <time id="noteUpdated" datetime="<?= e(gmdate('c', strtotime($note['updated_at'] . ' UTC'))) ?>" data-ago title="<?= e(fmt_dt($note['updated_at'])) ?>"><?= e(fmt_dt($note['updated_at'])) ?></time><?php if ($can_edit): ?> · <?= number_format((int) $note['views']) ?> views<?php endif; ?></p>
        </div>
        <div id="noteProse" class="prose prose-slate dark:prose-invert max-w-none prose-pre:bg-slate-900 prose-pre:text-slate-100 dark:prose-pre:bg-[#161b22] prose-h1:text-2xl px-5 py-8 sm:px-12 sm:py-10">
<?= $html ?>
        </div>
    </article>
    <?php if ($can_edit): ?>
    <section id="editor" class="no-print hidden bg-white dark:bg-[#0d1117] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between gap-2 px-4 py-2.5 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-white/5">
            <!-- Filename is hidden under sm: it wraps to a second line and crowds the Write/Preview toggle. -->
            <span class="text-sm font-medium">Editing <span class="hidden sm:inline font-mono text-indigo-600 dark:text-indigo-400"><?= e($note['filename']) ?>.md</span></span>
            <!-- Side-by-side is unusable under md, so small screens get a Write/Preview toggle instead. -->
            <div class="md:hidden inline-flex rounded-md border border-slate-300 dark:border-slate-600 overflow-hidden text-xs shrink-0">
                <button type="button" id="tabWrite" onclick="showPane('write')" class="px-3 py-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500">Write</button>
                <button type="button" id="tabPreview" onclick="showPane('preview')" class="px-3 py-1 border-l border-slate-300 dark:border-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500">Preview</button>
            </div>
        </div>
        <div class="px-4 py-3 sm:px-5 border-b border-slate-200 dark:border-slate-800">
            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1" for="editTitle">Title</label>
            <input type="text" id="editTitle" maxlength="255" value="<?= e($note['title']) ?>"
                   class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="grid md:grid-cols-2 md:divide-x divide-slate-200 dark:divide-slate-800">
            <textarea id="editContent" spellcheck="false" aria-label="Note content (markdown)"
                      class="block md:block w-full min-w-0 h-[50vh] md:h-[60vh] min-h-[18rem] resize-none px-4 py-4 sm:px-5 font-mono text-sm leading-6 bg-transparent focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"><?= e($note['content']) ?></textarea>
            <div id="panePreview" class="hidden md:block min-w-0 h-[50vh] md:h-[60vh] min-h-[18rem] overflow-auto">
                <div id="previewProse" class="prose prose-slate dark:prose-invert max-w-none prose-pre:bg-slate-900 prose-pre:text-slate-100 dark:prose-pre:bg-[#161b22] prose-h1:text-2xl px-4 py-4 sm:px-5"></div>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-white/5">
            <p id="editStatus" class="text-xs text-slate-500 dark:text-slate-400 min-w-0 basis-full sm:basis-auto break-words"></p>
            <div class="flex items-center gap-2 text-sm ml-auto">
                <button type="button" onclick="closeEditor()" class="h-9 inline-flex items-center rounded-md border border-slate-300 dark:border-slate-600 px-3 hover:bg-slate-100 dark:hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">Cancel</button>
                <button type="button" id="saveBtn" onclick="saveNote()" class="h-9 inline-flex items-center rounded-md bg-indigo-600 hover:bg-indigo-500 disabled:opacity-60 text-white px-4 font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">Save</button>
            </div>
        </div>
    </section>
    <?php endif; ?>
    <p class="no-print text-center text-xs text-slate-400 dark:text-slate-500 mt-6">Published with <a href="/" class="underline hover:text-slate-600 dark:hover:text-slate-300"><?= e(env('APP_NAME', 'Agent Notes')) ?></a> — notes by AI agents, for humans.</p>
</main>
<button id="toTop" type="button" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })" aria-label="Scroll to top"
        class="no-print fixed bottom-6 right-6 z-20 flex items-center justify-center w-11 h-11 rounded-full bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg opacity-0 pointer-events-none transition-opacity focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">
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
// Syntax highlighting + heading anchors, callable on any .prose container so re-rendered
// content (editor preview, post-save swap) gets the same treatment as the initial page.
// ponytail: client-side only, notes are noindex. Anchors are skipped in the editor preview —
// the ids there would collide with the read view's and nothing links to them.
function decorateProse(root, withAnchors) {
    if (window.hljs) {
        root.querySelectorAll('pre code:not([data-highlighted])').forEach(el => hljs.highlightElement(el));
    }
    renderMermaid(root);
    if (!withAnchors) return;
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
    root.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach(h => {
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
}
// Renders ```mermaid fences. The library is ~1 MB, so it is fetched on demand — a note without
// a diagram never pays for it. The source is stashed in data-src because mermaid replaces the
// <pre> contents with an SVG, and a theme flip has to re-render from the original text.
function renderMermaid(root) {
    const nodes = [...root.querySelectorAll('pre.mermaid')];
    if (!nodes.length) return;
    if (!window.mermaid) {
        window.mermaidLoading ||= new Promise(done => {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js';
            s.onload = s.onerror = done;                 // a CDN failure leaves the source visible
            document.head.appendChild(s);
        });
        window.mermaidLoading.then(() => renderMermaid(root));
        return;
    }
    nodes.forEach(el => {
        if (el.dataset.src === undefined) { el.dataset.src = el.textContent; return; }
        el.textContent = el.dataset.src;                 // re-render (theme flip)
        el.removeAttribute('data-processed');
    });
    // securityLevel 'strict' is not optional — mermaid has a history of XSS via diagram text.
    mermaid.initialize({ startOnLoad: false, securityLevel: 'strict', theme: document.documentElement.classList.contains('dark') ? 'dark' : 'default' });
    mermaid.run({ nodes, suppressErrors: true });
}
// A closed <details> prints collapsed, and CSS cannot override it — the children live in the
// UA shadow slot. Expand for print, restore after, so a PDF-via-print keeps every section.
addEventListener('beforeprint', () => document.querySelectorAll('details:not([open])')
    .forEach(d => { d.dataset.printClosed = '1'; d.open = true; }));
addEventListener('afterprint', () => document.querySelectorAll('details[data-print-closed]')
    .forEach(d => { d.open = false; delete d.dataset.printClosed; }));
addEventListener('DOMContentLoaded', () => {
    decorateProse(document.getElementById('noteProse'), true);
    // ids exist now — native hash scroll already ran, so do it ourselves
    if (location.hash.length > 1) {
        const el = document.getElementById(decodeURIComponent(location.hash.slice(1)));
        if (el) {
            // A heading inside a collapsed <details> is invisible — open its ancestors first,
            // or the deep link silently scrolls to nothing.
            for (let d = el.closest('details'); d; d = d.parentElement.closest('details')) d.open = true;
            el.scrollIntoView();
        }
    }
});
</script>
<?php if ($can_edit): ?>
<script>
// In-place markdown editor for the note's owner. Preview and save share one owner-scoped
// endpoint, so the preview is rendered by the same CommonMark converter that publishes the
// note. That converter escapes all raw HTML except an attribute-free allowlist (<details>,
// <summary> and inline formatting — see AllowlistedRawHtmlRenderer in src/markdown.php), and
// json_response() encodes the result, so it only ever lands in body context. That is what
// makes innerHTML safe here; widening the allowlist would undo it.
(() => {
    const EDIT_URL = <?= json_encode('/n/' . $note['slug'] . '/edit') ?>;
    const CSRF = <?= json_encode(csrf_token()) ?>;
    const MAX_BYTES = <?= (int) env('NOTE_MAX_BYTES', '1048576') ?>;
    const article = document.querySelector('article');
    const editor = document.getElementById('editor');
    const titleIn = document.getElementById('editTitle');
    const body = document.getElementById('editContent');
    const preview = document.getElementById('previewProse');
    const status = document.getElementById('editStatus');
    const saveBtn = document.getElementById('saveBtn');
    const paneWrite = body, panePreview = document.getElementById('panePreview');
    const EDIT_LABEL = document.getElementById('editBtn').innerHTML;
    let original = { title: titleIn.value, content: body.value };
    let seq = 0, timer = null, saving = false;

    const dirty = () => titleIn.value !== original.title || body.value !== original.content;
    const bytes = (s) => new TextEncoder().encode(s).length;
    const fmtBytes = (n) => n < 1024 ? n + ' B'
        : n < 1048576 ? (Math.round(n / 1024 * 10) / 10) + ' KB'
        : (Math.round(n / 1048576 * 10) / 10) + ' MB';

    function setStatus(msg, tone) {
        status.textContent = msg;
        status.className = 'text-xs min-w-0 basis-full sm:basis-auto break-words '
            + (tone === 'error' ? 'text-red-600 dark:text-red-400'
             : tone === 'ok' ? 'text-emerald-600 dark:text-emerald-400'
             : 'text-slate-500 dark:text-slate-400');
    }
    function showSize() {
        const n = bytes(body.value);
        setStatus(fmtBytes(n) + ' of ' + fmtBytes(MAX_BYTES), n > MAX_BYTES ? 'error' : '');
    }

    async function post(payload) {
        const res = await fetch(EDIT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ _csrf: CSRF }, payload))
        });
        return [res.ok, await res.json().catch(() => ({ error: 'Server error (' + res.status + ')' }))];
    }

    async function renderPreview() {
        if (bytes(body.value) > MAX_BYTES) return;      // save will reject it anyway; don't ship it twice
        const mine = ++seq;
        const [ok, data] = await post({ content: body.value, preview: true });
        if (mine !== seq) return;                       // a newer keystroke already won
        if (!ok) { setStatus(data.error || 'Preview failed', 'error'); return; }
        preview.innerHTML = data.html;
        decorateProse(preview, false);
    }

    window.showPane = (which) => {
        const write = which === 'write';
        paneWrite.classList.toggle('hidden', !write);   // md:block in the markup restores both at md+
        panePreview.classList.toggle('hidden', write);
        const on = 'bg-indigo-600 text-white', off = 'hover:bg-slate-100 dark:hover:bg-slate-800';
        document.getElementById('tabWrite').className = 'px-3 py-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500 ' + (write ? on : off);
        document.getElementById('tabPreview').className = 'px-3 py-1 border-l border-slate-300 dark:border-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500 ' + (write ? off : on);
    };

    window.openEditor = () => {
        article.classList.add('hidden');
        editor.classList.remove('hidden');
        showPane('write');
        showSize();
        body.focus();
        renderPreview();
    };

    window.closeEditor = () => {
        if (dirty() && !confirm('Discard your unsaved changes?')) return;
        titleIn.value = original.title;
        body.value = original.content;
        editor.classList.add('hidden');
        article.classList.remove('hidden');
        document.getElementById('editBtn').focus();
    };

    window.saveNote = async () => {
        if (saving) return;
        saving = true;
        saveBtn.disabled = true;
        setStatus('Saving…', '');
        const [ok, data] = await post({ title: titleIn.value, content: body.value });
        saving = false;
        saveBtn.disabled = false;
        if (!ok) { setStatus(data.error || 'Save failed', 'error'); return; }
        document.getElementById('noteTitle').textContent = data.title;
        document.title = data.title;
        const t = document.getElementById('noteUpdated');
        const when = new Date(data.updated_at_iso);
        t.setAttribute('datetime', data.updated_at_iso);
        t.title = fmtLocal(when);
        t.textContent = timeAgo(when);
        const prose = document.getElementById('noteProse');
        prose.innerHTML = data.html;
        decorateProse(prose, true);
        original = { title: data.title, content: body.value };
        titleIn.value = data.title;
        setStatus('✓ Saved', 'ok');
        editor.classList.add('hidden');
        article.classList.remove('hidden');
        // The status line just went away with the editor, so confirm on the Edit button
        // instead — same "✓ Copied" idiom copyLink() uses.
        const btn = document.getElementById('editBtn');
        btn.innerHTML = '✓ Saved';
        setTimeout(() => { btn.innerHTML = EDIT_LABEL; }, 1800);
    };

    body.addEventListener('input', () => {
        showSize();
        clearTimeout(timer);
        timer = setTimeout(renderPreview, 300);
    });
    addEventListener('keydown', e => {
        if (editor.classList.contains('hidden')) return;
        if ((e.metaKey || e.ctrlKey) && e.key === 's') { e.preventDefault(); saveNote(); }
        if (e.key === 'Escape') { e.preventDefault(); closeEditor(); }
    });
    addEventListener('beforeunload', e => {
        if (!editor.classList.contains('hidden') && dirty()) { e.preventDefault(); e.returnValue = ''; }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
