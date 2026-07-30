<div class="max-w-sm mx-auto mt-8 sm:mt-12">
    <h1 class="text-2xl font-bold mb-2">Sign in</h1>
    <p class="text-slate-600 dark:text-slate-400 text-sm mb-6">Use your password, or get a one-time code by email.</p>

    <?php if ($error): ?>
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 text-sm px-4 py-3"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="flex gap-1 rounded-xl bg-slate-100 dark:bg-slate-800/80 p-1 mb-5 text-sm font-medium" role="tablist">
        <button type="button" role="tab" onclick="showTab('password')" id="tab-password" aria-controls="pane-password">Password</button>
        <button type="button" role="tab" onclick="showTab('otp')" id="tab-otp" aria-controls="pane-otp">Email code</button>
    </div>

    <div id="pane-password">
        <form method="post" action="/login" class="space-y-4">
            <input type="hidden" name="mode" value="password">
            <div>
                <label class="block text-sm font-medium mb-1" for="identifier">Username or email</label>
                <input type="text" id="identifier" name="identifier" value="<?= e($identifier) ?>" required autocomplete="username"
                       class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white py-2.5 font-medium">Sign in</button>
        </form>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-3">Forgot your password? Use the <button type="button" class="underline" onclick="showTab('otp')">email code</button> tab, then set a new one in Account settings.</p>
    </div>

    <div id="pane-otp" class="hidden">
        <?php if ($step === 'code'): ?>
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">We sent a code to <strong class="text-slate-900 dark:text-white"><?= e($email) ?></strong>. It expires in 10 minutes.</p>
        <form method="post" action="/login/verify" class="space-y-4">
            <input type="hidden" name="email" value="<?= e($email) ?>">
            <input type="text" name="code" required autofocus placeholder="123456" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"
                   class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 text-center text-2xl tracking-[0.5em] focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white py-2.5 font-medium">Verify & sign in</button>
        </form>
        <form method="post" action="/login" class="mt-3 text-center">
            <input type="hidden" name="mode" value="otp">
            <input type="hidden" name="email" value="<?= e($email) ?>">
            <button class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white underline">Resend code</button>
        </form>
        <?php else: ?>
        <form method="post" action="/login" class="space-y-4">
            <input type="hidden" name="mode" value="otp">
            <div>
                <label class="block text-sm font-medium mb-1" for="otp-email">Email</label>
                <input type="email" id="otp-email" name="email" value="<?= e($email) ?>" required placeholder="you@example.com" autocomplete="email"
                       class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white py-2.5 font-medium">Email me a code</button>
        </form>
        <?php endif; ?>
    </div>

    <p class="text-sm text-slate-500 dark:text-slate-400 mt-5 text-center">New here? <a href="/register" class="text-indigo-600 dark:text-indigo-400 hover:underline">Create an account</a></p>
</div>

<script>
// Segmented control: one recessed track, the active option rides on a raised pill.
function showTab(name) {
    const base = 'flex-1 rounded-lg py-1.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500';
    const active = base + ' bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm';
    const idle = base + ' text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-slate-700/50';
    document.getElementById('pane-password').classList.toggle('hidden', name !== 'password');
    document.getElementById('pane-otp').classList.toggle('hidden', name !== 'otp');
    for (const [id, on] of [['tab-password', name === 'password'], ['tab-otp', name === 'otp']]) {
        const el = document.getElementById(id);
        el.className = on ? active : idle;
        el.setAttribute('aria-selected', String(on));
    }
}
showTab(<?= json_encode($tab) ?>);
</script>
