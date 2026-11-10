<?php

// Note slugs: random base62 from the CSPRNG, length set by SLUG_BYTES, never derived from the title.
// Run: php tests/slugs.php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$failed = 0;
$fail = function (string $why) use (&$failed): void {
    $failed++;
    echo "FAIL: $why\n";
};

unset($_ENV['SLUG_BYTES']);
slug_length() === 8 || $fail('unset SLUG_BYTES no longer gives the original 8 characters');
$cases = ['' => 8, '16' => 22, '32' => 43, '64' => 43, '1' => 9, 'abc' => 8];
foreach ($cases as $bytes => $chars) {
    $_ENV['SLUG_BYTES'] = (string) $bytes;
    slug_length() === $chars || $fail("SLUG_BYTES=" . var_export($bytes, true) . " gives " . slug_length() . " characters, want {$chars}");
}

$_ENV['SLUG_BYTES'] = '16';
$seen = [];
for ($i = 0; $i < 2000; $i++) {
    $s = generate_slug();
    preg_match('/^[A-Za-z0-9]{22}$/D', $s) || $fail("slug '{$s}' is not 22 base62 characters");
    $seen[$s] = true;
}
count($seen) === 2000 || $fail('2000 slugs were not all distinct');

// The slug never comes from the title: create_note() only ever calls generate_slug().
$src = file_get_contents(__DIR__ . '/../src/notes.php');
preg_match('/\$slug\s*=\s*generate_slug\(\);/', $src) || $fail('create_note no longer takes its slug from generate_slug()');
preg_match('/\$slug\s*=\s*[^;]*\$title/', $src) && $fail('a slug is built from the title');
str_contains(file_get_contents(__DIR__ . '/../schema.sql'), 'slug VARCHAR(43)') || $fail('schema.sql notes.slug is not VARCHAR(43)');

if ($failed) {
    exit(1);
}
echo "ok: slugs\n";
