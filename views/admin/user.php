<a href="/admin" class="text-sm text-slate-500 hover:text-slate-900">← All users</a>
<h1 class="text-2xl font-bold mt-2 mb-6"><?= e($target['email']) ?></h1>

<div class="grid sm:grid-cols-2 gap-4 mb-8 text-sm">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <h2 class="font-semibold mb-2">Account</h2>
        <dl class="space-y-1 text-slate-600">
            <div><dt class="inline text-slate-400">Joined:</dt> <dd class="inline"><?= e($target['created_at']) ?></dd></div>
            <div><dt class="inline text-slate-400">Last login:</dt> <dd class="inline"><?= e($target['last_login_at'] ?? 'never') ?></dd></div>
            <div><dt class="inline text-slate-400">IP:</dt> <dd class="inline"><?= e($target['last_login_ip'] ?? '—') ?></dd></div>
            <div><dt class="inline text-slate-400">Device:</dt> <dd class="inline break-all"><?= e($target['last_login_user_agent'] ?? '—') ?></dd></div>
        </dl>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <h2 class="font-semibold mb-2">Tokens</h2>
        <?php if (!$tokens): ?><p class="text-slate-500">None.</p><?php endif; ?>
        <ul class="space-y-1 text-slate-600">
            <?php foreach ($tokens as $t): ?>
            <li><code class="text-xs bg-slate-100 rounded px-1"><?= e($t['prefix']) ?>…</code> <?= e($t['name']) ?>
                <?= $t['revoked_at'] ? '<span class="text-red-500 text-xs">(revoked)</span>' : '' ?>
                <span class="text-xs text-slate-400">last used <?= e($t['last_used_at'] ?? 'never') ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<h2 class="text-lg font-semibold mb-3">Notes <span class="text-slate-400 font-normal">(<?= count($notes) ?>)</span></h2>
<div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
    <?php foreach ($notes as $n): ?>
    <div class="flex items-center gap-3 px-4 py-3">
        <div class="min-w-0 flex-1">
            <a href="/n/<?= e($n['slug']) ?>" class="font-medium hover:underline truncate block"><?= e($n['title']) ?></a>
            <div class="text-xs text-slate-500">Updated <?= e($n['updated_at']) ?> · <?= number_format((int) $n['size_bytes']) ?> bytes</div>
        </div>
        <form method="post" action="/admin/note/delete" onsubmit="return confirm('Delete this note?')">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="slug" value="<?= e($n['slug']) ?>">
            <input type="hidden" name="back" value="/admin/user/<?= e((string) $target['id']) ?>">
            <button class="text-xs rounded border border-red-200 text-red-600 px-2 py-1 hover:bg-red-50">Delete</button>
        </form>
    </div>
    <?php endforeach; ?>
    <?php if (!$notes): ?><div class="px-4 py-3 text-sm text-slate-500">No notes.</div><?php endif; ?>
</div>
