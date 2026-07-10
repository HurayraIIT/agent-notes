<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(isset($title) && $title ? $title . ' · ' : '') ?><?= e(env('APP_NAME', 'Agent Notes')) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=typography"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 flex flex-col">
<header class="border-b border-slate-200 bg-white">
    <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
        <a href="/" class="font-semibold text-lg tracking-tight">🗒️ <?= e(env('APP_NAME', 'Agent Notes')) ?></a>
        <nav class="flex items-center gap-5 text-sm">
            <a href="/docs" class="text-slate-600 hover:text-slate-900">Docs</a>
            <?php if ($u = current_user()): ?>
                <?php if ($u['is_admin']): ?><a href="/admin" class="text-slate-600 hover:text-slate-900">Admin</a><?php endif; ?>
                <a href="/dashboard" class="text-slate-600 hover:text-slate-900">Dashboard</a>
                <form method="post" action="/logout">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <button class="text-slate-500 hover:text-slate-900">Sign out</button>
                </form>
            <?php else: ?>
                <a href="/login" class="rounded-lg bg-slate-900 text-white px-3 py-1.5 hover:bg-slate-700">Sign in</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="flex-1 w-full max-w-4xl mx-auto px-4 py-10">
<?= $content ?>
</main>
<footer class="border-t border-slate-200 bg-white">
    <div class="max-w-4xl mx-auto px-4 py-6 text-sm text-slate-500 flex items-center justify-between">
        <span><?= e(env('APP_NAME', 'Agent Notes')) ?> — a publishing pipe for AI agents.</span>
        <span class="flex gap-4"><a href="/llms.txt" class="hover:text-slate-900">llms.txt</a><a href="/.well-known/mcp/server-card.json" class="hover:text-slate-900">MCP card</a></span>
    </div>
</footer>
</body>
</html>
