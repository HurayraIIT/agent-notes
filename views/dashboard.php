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
                <div class="text-xs text-slate-500 dark:text-slate-400">Updated <?= dt_tag($n['updated_at']) ?> · <?= e(fmt_bytes((int) $n['size_bytes'])) ?><?php if (feature(Feature::HtmlNotes) && note_format($n) === NoteFormat::Html): ?> · HTML<?php endif; ?><?php if (feature(Feature::Visibility)): ?> · <?= note_visibility($n) === Visibility::Public ? 'Public' : 'Tailnet only' ?><?php endif; ?></div>
            </div>
            <button onclick="copyText('<?= e(note_url($n['slug'], $n['visibility'] ?? null)) ?>', this)"
                    class="text-xs rounded-md border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 shrink-0">Copy link</button>
            <?php if (feature(Feature::ShareLinks) && note_visibility($n) !== Visibility::Public): ?>
            <form method="post" action="/share/create" class="flex items-center gap-1 shrink-0">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="slug" value="<?= e($n['slug']) ?>">
                <select name="ttl_hours" aria-label="Share link lifetime" class="text-xs rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-1.5 py-1.5">
                    <?php foreach ([24 => '1 day', 168 => '7 days', 720 => '30 days'] as $h => $label): if ($h > env_int('SHARE_LINK_TTL_MAX_HOURS', 720)) continue; ?>
                    <option value="<?= $h ?>"<?= $h === share_ttl_hours(null)[0] ? ' selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="text-xs rounded-md border border-slate-300 dark:border-slate-600 px-2.5 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800">Share link</button>
            </form>
            <?php endif; ?>
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

<?php if (feature(Feature::ShareLinks)): ?>
<section id="share-links" class="mb-10">
    <h2 class="text-lg font-semibold mb-3">Share links</h2>
    <p class="text-sm text-slate-600 dark:text-slate-400 mb-3">Each link opens one tailnet note, read-only, for someone outside the network. Revoking it works immediately.</p>
    <?php $live = array_filter($share_links, fn($l) => $l['status'] === 'active'); ?>
    <?php if (!$live): ?>
        <div class="rounded-xl border border-dashed border-slate-300 dark:border-slate-700 p-5 text-sm text-slate-500 dark:text-slate-400">No active share links.</div>
    <?php else: ?>
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 divide-y divide-slate-100 dark:divide-slate-800">
        <?php foreach ($live as $l): ?>
        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 sm:gap-3 px-4 py-3">
            <div class="min-w-0 flex-1 basis-full sm:basis-auto">
                <a href="/n/<?= e($l['slug']) ?>" class="font-medium hover:underline truncate block"><?= e($l['title']) ?></a>
                <div class="text-xs text-slate-500 dark:text-slate-400">Expires <?= dt_tag($l['expires_at']) ?> · opened <?= (int) $l['use_count'] ?>×<?php if ($l['last_used_at']): ?>, last <?= dt_tag($l['last_used_at']) ?><?php endif; ?></div>
            </div>
            <form method="post" action="/share/revoke" onsubmit="return confirm('Revoke this share link? It stops working immediately.')">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                <button class="text-xs rounded-md border border-red-200 dark:border-red-900 text-red-600 dark:text-red-400 px-2.5 py-1.5 hover:bg-red-50 dark:hover:bg-red-950/50 shrink-0">Revoke</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

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
