<div class="max-w-2xl mx-auto mt-6 sm:mt-10">
    <h1 class="text-2xl font-bold mb-2">Share link created</h1>
    <p class="text-slate-600 dark:text-slate-400 text-sm mb-6">
        This link is shown <strong class="text-slate-900 dark:text-white">only once</strong>. Anyone holding it can read
        <strong class="text-slate-900 dark:text-white"><?= e($link['title']) ?></strong> until <?= dt_tag($link['expires_at']) ?>, so send it only to the person it is for.
        You can revoke it from the dashboard at any time.
    </p>
    <?= cmd_block($link['url'], 'Share link') ?>
    <a href="/dashboard#share-links" class="inline-block mt-6 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-slate-900">Back to dashboard →</a>
</div>
