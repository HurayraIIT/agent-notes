<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h1 class="text-2xl font-bold">Admin · Users</h1>
    <form method="post" action="/admin/export">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button class="rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 text-sm font-medium">⬇ Export database (.sql)</button>
    </form>
</div>

<?php if ($export_error): ?>
    <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 text-sm px-4 py-3"><?= e($export_error) ?></div>
<?php endif; ?>

<div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400">
            <tr>
                <th class="px-4 py-3 font-medium">User</th>
                <th class="px-4 py-3 font-medium">Verified</th>
                <th class="px-4 py-3 font-medium">Notes</th>
                <th class="px-4 py-3 font-medium">Last login</th>
                <th class="px-4 py-3 font-medium">IP</th>
                <th class="px-4 py-3 font-medium">Device</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($users as $row): ?>
            <tr>
                <td class="px-4 py-3">
                    <a href="/admin/user/<?= e((string) $row['id']) ?>" class="font-medium hover:underline"><?= e($row['email']) ?></a>
                    <?php if ($row['username']): ?><div class="text-xs text-slate-500 dark:text-slate-400">@<?= e($row['username']) ?></div><?php endif; ?>
                    <?php if ($row['is_admin']): ?><span class="text-xs rounded bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300 px-1.5 py-0.5">admin</span><?php endif; ?>
                </td>
                <td class="px-4 py-3"><?= $row['email_verified_at'] ? '✅' : '<span class="text-xs text-amber-600 dark:text-amber-400">pending</span>' ?></td>
                <td class="px-4 py-3"><?= e((string) $row['note_count']) ?> <span class="text-xs text-slate-500 dark:text-slate-400">· <?= number_format((int) $row['total_views']) ?> views</span></td>
                <td class="px-4 py-3 text-slate-500 dark:text-slate-400"><?= $row['last_login_at'] ? dt_tag($row['last_login_at']) : 'never' ?></td>
                <td class="px-4 py-3 text-slate-500 dark:text-slate-400"><?= e($row['last_login_ip'] ?? '—') ?></td>
                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 max-w-[220px] truncate" title="<?= e($row['last_login_user_agent'] ?? '') ?>"><?= e($row['last_login_user_agent'] ?? '—') ?></td>
                <td class="px-4 py-3 text-right">
                    <?php if (!$row['is_admin']): ?>
                    <form method="post" action="/admin/user/delete" onsubmit="return confirm('Delete this user and ALL their notes, tokens and sessions?')">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                        <button class="text-xs rounded-md border border-red-200 dark:border-red-900 text-red-600 dark:text-red-400 px-2.5 py-1.5 hover:bg-red-50 dark:hover:bg-red-950/50">Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
