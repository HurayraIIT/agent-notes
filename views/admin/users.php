<h1 class="text-2xl font-bold mb-6">Admin · Users</h1>
<div class="rounded-lg border border-slate-200 bg-white overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-slate-500">
            <tr>
                <th class="px-4 py-3 font-medium">Email</th>
                <th class="px-4 py-3 font-medium">Notes</th>
                <th class="px-4 py-3 font-medium">Last login</th>
                <th class="px-4 py-3 font-medium">IP</th>
                <th class="px-4 py-3 font-medium">Device</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($users as $row): ?>
            <tr>
                <td class="px-4 py-3">
                    <a href="/admin/user/<?= e((string) $row['id']) ?>" class="font-medium hover:underline"><?= e($row['email']) ?></a>
                    <?php if ($row['is_admin']): ?><span class="ml-1 text-xs rounded bg-amber-100 text-amber-800 px-1.5 py-0.5">admin</span><?php endif; ?>
                </td>
                <td class="px-4 py-3"><?= e((string) $row['note_count']) ?></td>
                <td class="px-4 py-3 text-slate-500"><?= e($row['last_login_at'] ?? 'never') ?></td>
                <td class="px-4 py-3 text-slate-500"><?= e($row['last_login_ip'] ?? '—') ?></td>
                <td class="px-4 py-3 text-slate-500 max-w-[220px] truncate" title="<?= e($row['last_login_user_agent'] ?? '') ?>"><?= e($row['last_login_user_agent'] ?? '—') ?></td>
                <td class="px-4 py-3 text-right">
                    <?php if (!$row['is_admin']): ?>
                    <form method="post" action="/admin/user/delete" onsubmit="return confirm('Delete this user and ALL their notes, tokens and sessions?')">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $row['id']) ?>">
                        <button class="text-xs rounded border border-red-200 text-red-600 px-2 py-1 hover:bg-red-50">Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
