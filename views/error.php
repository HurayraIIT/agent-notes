<div class="max-w-md mx-auto text-center py-14 sm:py-20">
    <div class="relative inline-flex items-center justify-center mb-7">
        <span class="absolute inset-0 rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 opacity-15 blur-xl"></span>
        <span class="relative flex items-center justify-center w-20 h-20 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <svg class="w-9 h-9 text-indigo-500 dark:text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 3v4a1 1 0 0 0 1 1h4"/>
                <path d="M18 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-1 2z"/>
                <line x1="9.5" y1="12.5" x2="14.5" y2="17.5"/>
                <line x1="14.5" y1="12.5" x2="9.5" y2="17.5"/>
            </svg>
        </span>
    </div>
    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight mb-2"><?= e($title) ?></h1>
    <p class="text-slate-600 dark:text-slate-400"><?= e($message) ?></p>
    <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="/" class="w-full sm:w-auto rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 text-sm font-medium <?= focus_ring() ?>">← Back home</a>
        <a href="/docs" class="w-full sm:w-auto rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 px-5 py-2.5 text-sm font-medium <?= focus_ring() ?>">Read the docs</a>
    </div>
</div>
