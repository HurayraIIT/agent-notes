<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-2xl font-bold">Users</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5"><?= number_format((int) ($total ?? count($users))) ?> <?= ($search ?? '') !== '' ? 'matching' : 'registered' ?></p>
    </div>
    <!-- Export lives in the admin sidebar — it is a global admin action, not a users-page one. -->
</div>

<div class="mb-4">
    <form method="get" action="/admin" class="flex flex-1">
        <input type="text" name="q" value="<?= e($search ?? '') ?>" placeholder="Search users by email or username..."
               class="w-full max-w-sm rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <button type="submit" class="ml-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 px-4 py-2 text-sm font-medium <?= focus_ring() ?>">Search</button>
    </form>
</div>

<?php if ($export_error): ?>
    <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 text-sm px-4 py-3"><?= e($export_error) ?></div>
<?php endif; ?>

<div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400">
            <tr>
                <th class="px-2 sm:px-4 py-3 font-medium">User</th>
                <th class="px-4 py-3 font-medium hidden sm:table-cell">Verified</th>
                <th class="px-4 py-3 font-medium hidden sm:table-cell">Notes</th>
                <th class="px-4 py-3 font-medium hidden md:table-cell">Last login</th>
                <th class="px-4 py-3 font-medium hidden lg:table-cell">IP</th>
                <th class="px-2 sm:px-4 py-3 text-right"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($users as $row): ?>
            <tr>
                <td class="px-2 sm:px-4 py-3">
                    <div class="truncate max-w-[140px] sm:max-w-[300px]">
                        <a href="/admin/user/<?= e((string) $row['id']) ?>" class="font-medium hover:underline" title="<?= e($row['email']) ?>"><?= e($row['email']) ?></a>
                    </div>
                    <?php if ($row['username']): ?><div class="text-xs text-slate-500 dark:text-slate-400 truncate max-w-[140px] sm:max-w-none">@<?= e($row['username']) ?></div><?php endif; ?>
                    <?php if ($row['is_admin']): ?><span class="text-xs rounded bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300 px-1.5 py-0.5">admin</span><?php endif; ?>
                </td>
                <td class="px-4 py-3 hidden sm:table-cell"><?= $row['email_verified_at'] ? '✅' : '<span class="text-xs text-amber-600 dark:text-amber-400">pending</span>' ?></td>
                <td class="px-4 py-3 hidden sm:table-cell"><?= e((string) $row['note_count']) ?> <span class="text-xs text-slate-500 dark:text-slate-400 hidden sm:inline">· <?= number_format((int) $row['total_views']) ?> views</span></td>
                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 hidden md:table-cell"><?= $row['last_login_at'] ? dt_tag($row['last_login_at']) : 'never' ?></td>
                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 hidden lg:table-cell"><?= e($row['last_login_ip'] ?? '—') ?></td>
                <td class="px-2 sm:px-4 py-3 text-right">
                    <?php if (!$row['is_admin']): ?>
                    <form method="post" action="/admin/user/delete" onsubmit="return confirm('Delete this user and ALL their notes, tokens and sessions?')">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                        <button class="text-xs rounded-md border border-red-200 dark:border-red-900 text-red-600 dark:text-red-400 px-2 sm:px-2.5 py-1.5 hover:bg-red-50 dark:hover:bg-red-950/50">Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (empty($users)): ?>
        <div class="px-4 py-8 text-center text-slate-500 dark:text-slate-400 text-sm">No users found.</div>
    <?php endif; ?>
</div>

<?php if (isset($pages) && $pages > 1): ?>
<div class="mt-6 flex flex-wrap gap-2 justify-center">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?p=<?= $i ?>&q=<?= urlencode($search ?? '') ?>" class="px-3 py-1.5 rounded-md border text-sm font-medium <?= $i === ($page ?? 1) ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>
</div>
<?php endif; ?>
