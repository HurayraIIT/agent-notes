<h1 class="text-2xl font-bold mb-1">Dashboard</h1>
<p class="text-slate-600 text-sm mb-8"><?= e($user['email']) ?></p>

<section class="mb-10">
    <h2 class="text-lg font-semibold mb-3">Notes <span class="text-slate-400 font-normal">(<?= count($notes) ?>)</span></h2>
    <?php if (!$notes): ?>
        <div class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500 text-sm">
            No notes yet. Your agent publishes them — see the <a href="/docs" class="underline hover:text-slate-900">setup docs</a>.
        </div>
    <?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100">
        <?php foreach ($notes as $n): ?>
        <div class="flex items-center gap-3 px-4 py-3">
            <div class="min-w-0 flex-1">
                <a href="/n/<?= e($n['slug']) ?>" class="font-medium hover:underline truncate block"><?= e($n['title']) ?></a>
                <div class="text-xs text-slate-500">Updated <?= e($n['updated_at']) ?> · <?= number_format((int) $n['size_bytes']) ?> bytes</div>
            </div>
            <button onclick="navigator.clipboard.writeText('<?= e(note_url($n['slug'])) ?>').then(()=>this.textContent='Copied!')"
                    class="text-xs rounded border border-slate-300 px-2 py-1 hover:bg-slate-100 shrink-0">Copy link</button>
            <form method="post" action="/notes/delete" onsubmit="return confirm('Delete this note? Its URL will stop working.')">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="slug" value="<?= e($n['slug']) ?>">
                <button class="text-xs rounded border border-red-200 text-red-600 px-2 py-1 hover:bg-red-50 shrink-0">Delete</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="mb-10">
    <h2 class="text-lg font-semibold mb-3">API tokens</h2>
    <div class="rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 mb-3">
        <?php foreach ($tokens as $t): ?>
        <div class="flex items-center gap-3 px-4 py-3">
            <div class="min-w-0 flex-1">
                <span class="font-medium"><?= e($t['name']) ?></span>
                <code class="text-xs bg-slate-100 rounded px-1.5 py-0.5 ml-2"><?= e($t['prefix']) ?>…</code>
                <div class="text-xs text-slate-500">Created <?= e($t['created_at']) ?> · Last used <?= e($t['last_used_at'] ?? 'never') ?></div>
            </div>
            <form method="post" action="/tokens/revoke" onsubmit="return confirm('Revoke this token? Agents using it will lose access.')">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e((string) $t['id']) ?>">
                <button class="text-xs rounded border border-red-200 text-red-600 px-2 py-1 hover:bg-red-50">Revoke</button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php if (!$tokens): ?><div class="px-4 py-3 text-sm text-slate-500">No active tokens.</div><?php endif; ?>
    </div>
    <form method="post" action="/tokens/create" class="flex gap-2">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="text" name="name" placeholder="Token name (e.g. laptop-claude)" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <button class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-700">New token</button>
    </form>
</section>

<section>
    <h2 class="text-lg font-semibold mb-3 text-red-700">Danger zone</h2>
    <form method="post" action="/account/delete" onsubmit="return confirm('Delete your account, all notes, and all tokens? This cannot be undone.')">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button class="rounded-lg border border-red-300 text-red-600 px-4 py-2 text-sm hover:bg-red-50">Delete account & all data</button>
    </form>
</section>
