<div class="max-w-sm mx-auto mt-8 sm:mt-12">
    <h1 class="text-2xl font-bold mb-2">Create your account</h1>
    <p class="text-slate-600 dark:text-slate-400 text-sm mb-6">We'll email you a 6-digit code to verify your address before you can sign in.</p>

    <?php if ($error): ?>
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 text-sm px-4 py-3"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/register" class="space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1" for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= e($old['username'] ?? '') ?>" required minlength="3" maxlength="50" pattern="[a-z0-9_-]{3,50}"
                   placeholder="your-handle" autocomplete="username"
                   class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">3–50 chars: lowercase letters, numbers, hyphens, underscores.</p>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1" for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" required placeholder="you@example.com" autocomplete="email"
                   class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1" for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="8" placeholder="At least 8 characters" autocomplete="new-password"
                   class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white py-2.5 font-medium">Create account</button>
    </form>
    <p class="text-sm text-slate-500 dark:text-slate-400 mt-4 text-center">Already have an account? <a href="/login" class="text-indigo-600 dark:text-indigo-400 hover:underline">Sign in</a></p>
</div>
