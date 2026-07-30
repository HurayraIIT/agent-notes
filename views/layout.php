<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(isset($title) && $title ? $title . ' · ' : '') ?><?= e(env('APP_NAME', 'Agent Notes')) ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'><stop offset='0' stop-color='%234f46e5'/><stop offset='1' stop-color='%237c3aed'/></linearGradient></defs><rect width='32' height='32' rx='7' fill='url(%23g)'/><g stroke='%23fff' stroke-width='2.5' stroke-linecap='round'><line x1='9' y1='11' x2='23' y2='11'/><line x1='9' y1='16' x2='23' y2='16'/><line x1='9' y1='21' x2='18' y2='21'/></g></svg>">
<meta name="description" content="<?= e(env('APP_NAME', 'Agent Notes')) ?> — a publishing pipe for AI agents.">
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "<?= e(env('APP_NAME', 'Agent Notes')) ?>",
  "url": "<?= e(app_url('/')) ?>",
  "description": "<?= e(env('APP_NAME', 'Agent Notes')) ?> — a publishing pipe for AI agents."
}
</script>
<script>
// apply theme before paint (no flash)
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
// clipboard with fallback for non-secure contexts (http://*.test)
function copyText(text, btn) {
    const done = () => {
        const old = btn.textContent;
        btn.textContent = '✓ Copied';
        setTimeout(() => { btn.textContent = old; }, 1500);
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done);
    } else {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        done();
    }
}
function copyCmd(btn) {
    copyText(btn.closest('[data-cmd]').querySelector('pre').innerText, btn);
}
// Mobile menu: toggle, then close on Escape or on any click outside the panel/button.
function menuEl() { return document.getElementById('mobile-menu'); }
function toggleMenu() {
    const open = menuEl().classList.toggle('hidden') === false;
    document.querySelector('[aria-controls="mobile-menu"]')?.setAttribute('aria-expanded', String(open));
}
function closeMenu() {
    menuEl()?.classList.add('hidden');
    document.querySelector('[aria-controls="mobile-menu"]')?.setAttribute('aria-expanded', 'false');
}
addEventListener('keydown', e => { if (e.key === 'Escape') closeMenu(); });
addEventListener('click', e => {
    const m = menuEl();
    if (m && !m.classList.contains('hidden') && !e.target.closest('#mobile-menu, [aria-controls="mobile-menu"]')) closeMenu();
});
// Full local timestamp, "July 12, 2026 06:46:57 PM" style (matches fmt_dt()) — used for the hover title.
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
addEventListener('DOMContentLoaded', () => document.querySelectorAll('time[data-ago]').forEach(t => {
    const d = new Date(t.getAttribute('datetime'));
    if (isNaN(d)) return;
    t.title = fmtLocal(d);
    t.textContent = timeAgo(d);
}));
</script>
</head>
<?php
$in_admin = nav_active('/admin');
// One box for both theme toggles and the Register CTA, so they share a height and optical baseline.
$btn_h = 'h-9 inline-flex items-center justify-center';
$toggle_cls = $btn_h . ' w-9 rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 ' . focus_ring();
?>
<body class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col antialiased">
<header class="border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur sticky top-0 z-30">
    <div class="<?= $in_admin ? 'max-w-6xl' : 'max-w-4xl' ?> mx-auto px-4 py-3 flex items-center gap-2">
        <a href="/" class="font-bold text-lg tracking-tight shrink-0 flex items-center gap-2 rounded <?= focus_ring() ?>"><?= brand_icon() ?> <span class="bg-gradient-to-r from-indigo-600 to-violet-600 dark:from-indigo-400 dark:to-violet-400 bg-clip-text text-transparent"><?= e(env('APP_NAME', 'Agent Notes')) ?></span></a>
        <?php if ($in_admin): ?><span class="hidden sm:inline shrink-0 rounded-md bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 text-[10px] font-bold uppercase tracking-widest px-2 py-1">Admin</span><?php endif; ?>

        <!-- Mobile Toggle -->
        <div class="flex items-center gap-2 md:hidden ml-auto">
            <button onclick="toggleTheme()" aria-label="Toggle dark mode" class="<?= $toggle_cls ?>">
                <span class="dark:hidden">🌙</span><span class="hidden dark:inline">☀️</span>
            </button>
            <button onclick="toggleMenu()" aria-label="Toggle mobile menu" aria-controls="mobile-menu" aria-expanded="false" class="<?= $btn_h ?> w-9 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white <?= focus_ring() ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
        </div>

        <!-- Desktop Nav -->
        <nav class="hidden md:flex items-center gap-4 text-sm justify-end ml-auto">
            <?= nav_link('/docs', 'Docs', 'px-1') ?>
            <?php if ($u = current_user()): ?>
                <?php if ($u['is_admin']): ?><?= nav_link('/admin', 'Admin', 'px-1') ?><?php endif; ?>
                <?= nav_link('/dashboard', 'Dashboard', 'px-1') ?>
                <form method="post" action="/logout">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <button class="rounded px-1 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white <?= focus_ring() ?>">Sign out</button>
                </form>
            <?php else: ?>
                <?= nav_link('/login', 'Sign in', 'px-1') ?>
                <a href="/register" class="<?= $btn_h ?> rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-3 font-medium <?= focus_ring() ?>">Register</a>
            <?php endif; ?>
            <button onclick="toggleTheme()" aria-label="Toggle dark mode" class="<?= $toggle_cls ?>">
                <span class="dark:hidden">🌙</span><span class="hidden dark:inline">☀️</span>
            </button>
        </nav>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden md:hidden border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 absolute inset-x-0 z-40 shadow-lg">
        <nav class="flex flex-col px-4 py-4 gap-4 text-sm">
            <?= nav_link('/docs', 'Docs', 'font-medium') ?>
            <?php if ($u = current_user()): ?>
                <?php if ($u['is_admin']): ?><?= nav_link('/admin', 'Admin', 'font-medium') ?><?php endif; ?>
                <?= nav_link('/dashboard', 'Dashboard', 'font-medium') ?>
                <form method="post" action="/logout" class="block w-full">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <button class="text-left w-full rounded text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium <?= focus_ring() ?>">Sign out</button>
                </form>
            <?php else: ?>
                <?= nav_link('/login', 'Sign in', 'font-medium') ?>
                <a href="/register" class="inline-block text-center rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-2 font-medium <?= focus_ring() ?>">Register</a>
            <?php endif; ?>
            <button onclick="toggleTheme()" class="flex items-center gap-2 rounded text-left font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border-t border-slate-200 dark:border-slate-800 pt-4 <?= focus_ring() ?>">
                <span class="dark:hidden">🌙 Dark mode</span><span class="hidden dark:inline">☀️ Light mode</span>
            </button>
        </nav>
    </div>
</header>
<?php if ($in_admin): ?><div class="h-0.5 bg-gradient-to-r from-amber-400 via-indigo-500 to-violet-500"></div><?php endif; ?>
<main class="flex-1 w-full <?= $in_admin ? 'max-w-6xl' : 'max-w-4xl' ?> mx-auto px-4 py-8 sm:py-10">
<?php if ($in_admin): ?>
<?php
// Admin shell: sidebar + content. Stacks into a horizontal scroll row above the content on mobile.
// /admin/user/N still belongs to the "Users" section, so the item matches the section, not the path.
$side = 'whitespace-nowrap rounded-lg px-3 py-2 text-sm ' . focus_ring();
$side_on = $side . ' bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 font-semibold';
$side_off = $side . ' text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800';
?>
<div class="flex flex-col md:flex-row gap-6">
    <aside class="md:w-52 shrink-0">
        <div class="md:sticky md:top-20 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-2">
            <p class="hidden md:block px-3 pt-2 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">Admin</p>
            <nav class="flex md:flex-col gap-1 overflow-x-auto">
                <a href="/admin" class="<?= $side_on ?>" aria-current="page">Users</a>
                <form method="post" action="/admin/export" class="contents">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <button class="<?= $side_off ?> text-left">Export database</button>
                </form>
                <span class="hidden md:block border-t border-slate-200 dark:border-slate-800 my-1"></span>
                <a href="/dashboard" class="<?= $side_off ?>">← Back to app</a>
            </nav>
        </div>
    </aside>
    <div class="min-w-0 flex-1"><?= $content ?></div>
</div>
<?php else: ?>
<?= $content ?>
<?php endif; ?>
</main>
<footer class="border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
    <div class="<?= $in_admin ? 'max-w-6xl' : 'max-w-4xl' ?> mx-auto px-4 py-6 text-sm text-slate-500 dark:text-slate-400 flex flex-wrap items-center justify-between gap-3">
        <span><?= e(env('APP_NAME', 'Agent Notes')) ?> — a publishing pipe for AI agents.</span>
        <span class="flex gap-4"><a href="/llms.txt" class="rounded hover:text-slate-900 dark:hover:text-white <?= focus_ring() ?>">llms.txt</a><a href="/.well-known/mcp/server-card.json" class="rounded hover:text-slate-900 dark:hover:text-white <?= focus_ring() ?>">MCP card</a></span>
    </div>
</footer>
<script>
// WebMCP: expose the note tools to browser-based agents (no-op where unsupported)
if (navigator.modelContext) {
    const csrfToken = <?= json_encode(csrf_token()) ?>;
    const tools = <?= json_encode(mcp_tools()) ?>.map(tool => ({
        name: tool.name,
        description: tool.description,
        inputSchema: tool.inputSchema,
        async execute(args) {
            const res = await fetch('/webmcp', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _csrf: csrfToken, name: tool.name, arguments: args, id: 1 })
            });
            const data = await res.json();
            if (data.error) throw new Error(data.error.message || 'Tool call failed');
            return data.result.content[0].text;
        }
    }));
    if (navigator.modelContext.registerTool) {
        tools.forEach(t => navigator.modelContext.registerTool(t));
    } else if (navigator.modelContext.provideContext) {
        navigator.modelContext.provideContext({ tools });
    }
}
</script>
</body>
</html>
