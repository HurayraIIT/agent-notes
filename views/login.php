<div class="max-w-sm mx-auto mt-12">
    <h1 class="text-2xl font-bold mb-2">Sign in</h1>
    <p class="text-slate-600 text-sm mb-6">No passwords. We'll email you a 6-digit code. New emails get an account automatically.</p>

    <?php if ($error): ?>
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($step === 'email'): ?>
    <form method="post" action="/login" class="space-y-4">
        <input type="email" name="email" value="<?= e($email) ?>" required autofocus placeholder="you@example.com"
               class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-slate-400">
        <button class="w-full rounded-lg bg-slate-900 text-white py-2.5 font-medium hover:bg-slate-700">Email me a code</button>
    </form>
    <?php else: ?>
    <p class="text-sm text-slate-600 mb-4">We sent a code to <strong><?= e($email) ?></strong>. It expires in 10 minutes.</p>
    <form method="post" action="/login/verify" class="space-y-4">
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <input type="text" name="code" required autofocus placeholder="123456" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
               class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-center text-2xl tracking-[0.5em] focus:outline-none focus:ring-2 focus:ring-slate-400">
        <button class="w-full rounded-lg bg-slate-900 text-white py-2.5 font-medium hover:bg-slate-700">Verify & sign in</button>
    </form>
    <form method="post" action="/login" class="mt-3 text-center">
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <button class="text-sm text-slate-500 hover:text-slate-900 underline">Resend code</button>
    </form>
    <?php endif; ?>
</div>
