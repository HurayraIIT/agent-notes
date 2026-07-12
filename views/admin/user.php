<a href="/admin" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">← All users</a>
<h1 class="text-2xl font-bold mt-2 mb-1"><?= e($target['email']) ?></h1>
<p class="text-sm text-slate-500 dark:text-slate-400 mb-6"><?= e($target['username'] ? '@' . $target['username'] : 'no username set') ?> · <?= $target['email_verified_at'] ? 'verified' : 'unverified' ?></p>

<div class="grid sm:grid-cols-2 gap-4 mb-8 text-sm">
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
        <h2 class="font-semibold mb-2">Account</h2>
        <dl class="space-y-1 text-slate-600 dark:text-slate-400">
            <div><dt class="inline text-slate-400 dark:text-slate-500">Joined:</dt> <dd class="inline"><?= dt_tag($target['created_at']) ?></dd></div>
            <div><dt class="inline text-slate-400 dark:text-slate-500">Verified:</dt> <dd class="inline"><?= $target['email_verified_at'] ? dt_tag($target['email_verified_at']) : 'not yet' ?></dd></div>
            <div><dt class="inline text-slate-400 dark:text-slate-500">Last login:</dt> <dd class="inline"><?= $target['last_login_at'] ? dt_tag($target['last_login_at']) : 'never' ?></dd></div>
            <div><dt class="inline text-slate-400 dark:text-slate-500">IP:</dt> <dd class="inline"><?= e($target['last_login_ip'] ?? '—') ?></dd></div>
            <div><dt class="inline text-slate-400 dark:text-slate-500">Device:</dt> <dd class="inline break-all"><?= e($target['last_login_user_agent'] ?? '—') ?></dd></div>
        </dl>
    </div>
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
        <h2 class="font-semibold mb-2">Tokens</h2>
        <?php if (!$tokens): ?><p class="text-slate-500 dark:text-slate-400">None.</p><?php endif; ?>
        <ul class="space-y-1 text-slate-600 dark:text-slate-400">
            <?php foreach ($tokens as $t): ?>
            <li><code class="text-xs bg-slate-100 dark:bg-slate-800 rounded px-1"><?= e($t['prefix']) ?>…</code> <?= e($t['name']) ?>
                <?= $t['revoked_at'] ? '<span class="text-red-500 text-xs">(revoked)</span>' : '' ?>
                <span class="text-xs text-slate-400 dark:text-slate-500">last used <?= $t['last_used_at'] ? dt_tag($t['last_used_at']) : 'never' ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<h2 class="text-lg font-semibold mb-3">Notes <span class="text-slate-400 dark:text-slate-500 font-normal">(<?= count($notes) ?>)</span></h2>
<div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 divide-y divide-slate-100 dark:divide-slate-800">
    <?php foreach ($notes as $n): ?>
    <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 sm:gap-3 px-4 py-3">
        <div class="min-w-0 flex-1 basis-full sm:basis-auto">
            <a href="/n/<?= e($n['slug']) ?>" class="font-medium hover:underline truncate block"><?= e($n['title']) ?></a>
            <div class="text-xs text-slate-500 dark:text-slate-400">Updated <?= dt_tag($n['updated_at']) ?> · <?= e(fmt_bytes((int) $n['size_bytes'])) ?> · <?= number_format((int) $n['views']) ?> views</div>
        </div>
        <form method="post" action="/admin/note/delete" onsubmit="return confirm('Delete this note?')">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="slug" value="<?= e($n['slug']) ?>">
            <input type="hidden" name="back" value="/admin/user/<?= e((string) $target['id']) ?>">
            <button class="text-xs rounded-md border border-red-200 dark:border-red-900 text-red-600 dark:text-red-400 px-2.5 py-1.5 hover:bg-red-50 dark:hover:bg-red-950/50">Delete</button>
        </form>
    </div>
    <?php endforeach; ?>
    <?php if (!$notes): ?><div class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">No notes.</div><?php endif; ?>
</div>
