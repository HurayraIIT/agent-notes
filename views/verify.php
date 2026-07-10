<div class="max-w-sm mx-auto mt-8 sm:mt-12">
    <h1 class="text-2xl font-bold mb-2">Verify your email</h1>
    <p class="text-slate-600 dark:text-slate-400 text-sm mb-6">Enter the 6-digit code we sent to <strong class="text-slate-900 dark:text-white"><?= e($email) ?></strong>. It expires in 10 minutes.</p>

    <?php if ($notice): ?>
        <div class="mb-4 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-200 dark:border-indigo-900 text-indigo-700 dark:text-indigo-300 text-sm px-4 py-3"><?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 text-sm px-4 py-3"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/verify" class="space-y-4">
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <input type="text" name="code" required autofocus placeholder="123456" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"
               class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 text-center text-2xl tracking-[0.5em] focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white py-2.5 font-medium">Verify</button>
    </form>
    <form method="post" action="/verify" class="mt-3 text-center">
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <input type="hidden" name="resend" value="1">
        <button class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white underline">Resend code</button>
    </form>
</div>
