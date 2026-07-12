<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(isset($title) && $title ? $title . ' · ' : '') ?><?= e(env('APP_NAME', 'Agent Notes')) ?></title>
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
// Localize UTC timestamps (<time data-local>) to each viewer's own timezone,
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
<body class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 flex flex-col antialiased">
<header class="border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur sticky top-0 z-10">
    <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between gap-2">
        <a href="/" class="font-bold text-lg tracking-tight shrink-0">🗒️ <span class="bg-gradient-to-r from-indigo-600 to-violet-600 dark:from-indigo-400 dark:to-violet-400 bg-clip-text text-transparent"><?= e(env('APP_NAME', 'Agent Notes')) ?></span></a>
        <nav class="flex items-center gap-2 sm:gap-4 text-sm flex-wrap justify-end">
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
