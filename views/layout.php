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
<body class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col antialiased">
<header class="border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur sticky top-0 z-20">
    <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between gap-2">
        <a href="/" class="font-bold text-lg tracking-tight shrink-0 flex items-center gap-2"><?= brand_icon() ?> <span class="bg-gradient-to-r from-indigo-600 to-violet-600 dark:from-indigo-400 dark:to-violet-400 bg-clip-text text-transparent"><?= e(env('APP_NAME', 'Agent Notes')) ?></span></a>
        
        <!-- Mobile Toggle -->
        <div class="flex items-center gap-2 md:hidden">
            <button onclick="toggleTheme()" aria-label="Toggle dark mode" class="rounded-lg border border-slate-300 dark:border-slate-700 px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">
                <span class="dark:hidden">🌙</span><span class="hidden dark:inline">☀️</span>
            </button>
            <button onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" aria-label="Toggle mobile menu" class="p-2 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
        </div>

        <!-- Desktop Nav -->
        <nav class="hidden md:flex items-center gap-4 text-sm justify-end">
            <a href="/docs" class="text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white px-1">Docs</a>
            <?php if ($u = current_user()): ?>
                <?php if ($u['is_admin']): ?><a href="/admin" class="text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white px-1">Admin</a><?php endif; ?>
                <a href="/dashboard" class="text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white px-1">Dashboard</a>
                <form method="post" action="/logout">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <button class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white px-1">Sign out</button>
                </form>
            <?php else: ?>
                <a href="/login" class="text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white px-1">Sign in</a>
                <a href="/register" class="rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 font-medium">Register</a>
            <?php endif; ?>
            <button onclick="toggleTheme()" aria-label="Toggle dark mode" class="rounded-lg border border-slate-300 dark:border-slate-700 px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">
                <span class="dark:hidden">🌙</span><span class="hidden dark:inline">☀️</span>
            </button>
        </nav>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden md:hidden border-t border-slate-200 dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur absolute w-full shadow-lg">
        <nav class="flex flex-col px-4 py-4 gap-4 text-sm">
            <a href="/docs" class="text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-medium">Docs</a>
            <?php if ($u = current_user()): ?>
                <?php if ($u['is_admin']): ?><a href="/admin" class="text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-medium">Admin</a><?php endif; ?>
                <a href="/dashboard" class="text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-medium">Dashboard</a>
                <form method="post" action="/logout" class="block w-full">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <button class="text-left w-full text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium">Sign out</button>
                </form>
            <?php else: ?>
                <a href="/login" class="text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white font-medium">Sign in</a>
                <a href="/register" class="inline-block text-center rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-2 font-medium">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="flex-1 w-full max-w-4xl mx-auto px-4 py-8 sm:py-10">
<?= $content ?>
</main>
<footer class="border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
    <div class="max-w-4xl mx-auto px-4 py-6 text-sm text-slate-500 dark:text-slate-400 flex flex-wrap items-center justify-between gap-3">
        <span><?= e(env('APP_NAME', 'Agent Notes')) ?> — a publishing pipe for AI agents.</span>
        <span class="flex gap-4"><a href="/llms.txt" class="hover:text-slate-900 dark:hover:text-white">llms.txt</a><a href="/.well-known/mcp/server-card.json" class="hover:text-slate-900 dark:hover:text-white">MCP card</a></span>
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
