<h1 class="text-2xl font-bold mb-1">Dashboard</h1>
<p class="text-slate-600 dark:text-slate-400 text-sm mb-8"><?= e($user['username'] ? '@' . $user['username'] . ' · ' : '') ?><?= e($user['email']) ?></p>

<?php if ($settings_saved): ?>
    <div class="mb-6 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm px-4 py-3">Account settings saved.</div>
<?php endif; ?>

<section class="mb-10">
    <h2 class="text-lg font-semibold mb-3">Notes <span class="text-slate-400 dark:text-slate-500 font-normal">(<?= count($notes) ?>)</span></h2>
    <?php if (!$notes): ?>
        <div class="rounded-xl border border-dashed border-slate-300 dark:border-slate-700 p-8 text-center text-slate-500 dark:text-slate-400 text-sm">
            No notes yet. Your agent publishes them — see the <a href="/docs" class="underline hover:text-slate-900 dark:hover:text-white">setup docs</a>.
        </div>
    <?php else: ?>
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 divide-y divide-slate-100 dark:divide-slate-800">
        <?php foreach ($notes as $n): ?>
        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 sm:gap-3 px-4 py-3">
            <div class="min-w-0 flex-1 basis-full sm:basis-auto">
                <a href="/n/<?= e($n['slug']) ?>" class="font-medium hover:underline truncate block"><?= e($n['title']) ?></a>
                <div class="text-xs text-slate-500 dark:text-slate-400">Updated <?= dt_tag($n['updated_at']) ?> · <?= e(fmt_bytes((int) $n['size_bytes'])) ?></div>
            </div>
            <button onclick="copyText('<?= e(note_url($n['slug'])) ?>', this)"
                    class="text-xs rounded-md border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 shrink-0">Copy link</button>
            <form method="post" action="/notes/delete" onsubmit="return confirm('Delete this note? Its URL will stop working.')">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="slug" value="<?= e($n['slug']) ?>">
                <button class="text-xs rounded-md border border-red-200 dark:border-red-900 text-red-600 dark:text-red-400 px-2.5 py-1.5 hover:bg-red-50 dark:hover:bg-red-950/50 shrink-0">Delete</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="mb-10">
    <h2 class="text-lg font-semibold mb-3">API tokens</h2>
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5">
        <div class="<?= $tokens ? 'rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 divide-y divide-slate-100 dark:divide-slate-800' : '' ?> mb-4">
            <?php foreach ($tokens as $t): ?>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-4 py-3">
                <div class="min-w-0 flex-1">
                    <span class="font-medium"><?= e($t['name']) ?></span>
                    <code class="text-xs bg-slate-100 dark:bg-slate-800 rounded px-1.5 py-0.5 ml-2"><?= e($t['prefix']) ?>…</code>
                    <div class="text-xs text-slate-500 dark:text-slate-400">Created <?= dt_tag($t['created_at']) ?> · Last used <?= $t['last_used_at'] ? dt_tag($t['last_used_at']) : 'never' ?></div>
                </div>
                <form method="post" action="/tokens/revoke" onsubmit="return confirm('Revoke this token? Agents using it will lose access.')">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= e((string) $t['id']) ?>">
                    <button class="text-xs rounded-md border border-red-200 dark:border-red-900 text-red-600 dark:text-red-400 px-2.5 py-1.5 hover:bg-red-50 dark:hover:bg-red-950/50">Revoke</button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php if (!$tokens): ?>
            <div class="rounded-lg border border-dashed border-slate-300 dark:border-slate-700 px-4 py-6 text-center">
                <svg class="w-6 h-6 mx-auto mb-2 text-slate-400 dark:text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="3.5"/><line x1="10" y1="13" x2="20" y2="3"/><line x1="17" y1="6" x2="19" y2="8"/></svg>
                <p class="text-sm text-slate-500 dark:text-slate-400">No active tokens yet.</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Create one below to connect an agent.</p>
            </div>
            <?php endif; ?>
        </div>
        <form method="post" action="/tokens/create" class="flex flex-col sm:flex-row gap-2">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="text" name="name" placeholder="Token name (e.g. laptop-claude)"
                   class="flex-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <button class="rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 text-sm font-medium">New token</button>
        </form>
    </div>
</section>

<section class="mb-10">
    <h2 class="text-lg font-semibold mb-3">Account settings</h2>
    <?php if ($settings_error): ?>
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 text-sm px-4 py-3"><?= e($settings_error) ?></div>
    <?php endif; ?>
    <form method="post" action="/settings" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 space-y-4 max-w-lg">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div>
            <label class="block text-sm font-medium mb-1" for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= e($user['username'] ?? '') ?>" minlength="3" maxlength="50" pattern="[a-z0-9_-]{3,50}" placeholder="your-handle"
                   class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1" for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password"
                       placeholder="<?= $user['password_hash'] ? 'Required to change password' : 'No password set yet' ?>"
                       class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="new_password">New password</label>
                <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password" placeholder="Leave blank to keep"
                       class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>
        <button class="rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 text-sm font-medium">Save settings</button>
    </form>
</section>

<section>
    <h2 class="text-lg font-semibold mb-3 text-slate-900 dark:text-slate-200">Danger zone</h2>
    <div class="rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50/50 dark:bg-red-950/20 p-5 max-w-lg">
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Deleting your account removes every note, token and session. This cannot be undone.</p>
        <form method="post" action="/account/delete" onsubmit="return confirm('Delete your account, all notes, and all tokens? This cannot be undone.')">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <button class="rounded-lg border border-red-300 dark:border-red-600 bg-white dark:bg-red-950/40 text-red-600 dark:text-red-300 px-4 py-2 text-sm font-medium transition-colors hover:bg-red-600 hover:border-red-600 hover:text-white dark:hover:bg-red-600 dark:hover:border-red-500 dark:hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-950">Delete account & all data</button>
        </form>
    </div>
</section>
