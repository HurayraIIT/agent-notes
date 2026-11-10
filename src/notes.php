<?php

declare(strict_types=1);

/** A note's URL: on the public host when the note is public (FEATURE_VISIBILITY + PUBLIC_URL), else on the app. */
function note_url(string $slug, ?string $visibility = null): string
{
    if ($visibility === Visibility::Public->value && feature(Feature::Visibility) && public_url() !== null) {
        return public_url() . '/n/' . $slug;
    }
    return app_url('/n/' . $slug);
}

/**
 * Columns every note read selects. The optional ones are read only when their feature is on, so an
 * install that never ran the migration keeps working with every flag off (tests/features_off.php).
 */
function note_columns(): string
{
    $cols = 'id, user_id, slug, title, filename, content, size_bytes, views, created_at, updated_at';
    if (feature(Feature::HtmlNotes)) {
        $cols .= ', format';
    }
    if (feature(Feature::Visibility)) {
        $cols .= ', visibility';
    }
    return $cols;
}

/** The note's format; an html note reads as markdown (escaped) while FEATURE_HTML_NOTES is off. */
function note_format(array $note): NoteFormat
{
    return feature(Feature::HtmlNotes) && ($note['format'] ?? '') === NoteFormat::Html->value ? NoteFormat::Html : NoteFormat::Markdown;
}

function note_visibility(array $note): Visibility
{
    return Visibility::tryFrom((string) ($note['visibility'] ?? '')) ?? Visibility::Tailnet;
}

function slugify(string $title): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
    return substr($slug, 0, 60) ?: 'note';
}

/**
 * Slug length in base62 characters. SLUG_BYTES (16–32; any set value is raised to at least 16, i.e.
 * 128 bits) sets the entropy in bytes, rounded up to whole characters (16 bytes: 22 characters, 131
 * bits). Unset, the original 8 characters (about 47 bits). Existing notes keep their slugs. Longer
 * slugs need migration 002, which also makes notes.slug case-sensitive (ascii_bin): under the old
 * case-insensitive collation a lookup matches any letter case, which would cut the entropy.
 */
function slug_length(): int
{
    $bytes = env_int('SLUG_BYTES', 0);
    return $bytes === 0 ? 8 : (int) ceil(min(max($bytes, 16), 32) * 8 / log(62, 2));
}

/** A random base62 slug from the CSPRNG. It never carries the note's title or any other text. */
function generate_slug(): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $slug = '';
    for ($i = 0, $n = slug_length(); $i < $n; $i++) {
        $slug .= $alphabet[random_int(0, 61)];
    }
    return $slug;
}

/** @return array{0: ?array, 1: ?string} [note, error] */
function create_note(int $userId, string $title, string $content, ?string $filename = null, ?NoteFormat $format = null, ?Visibility $visibility = null): array
{
    $title = trim($title);
    if ($title === '') {
        return [null, 'title must not be empty'];
    }
    $size = strlen($content);
    $max = (int) env('NOTE_MAX_BYTES', '1048576');
    if ($size > $max) {
        return [null, "content is {$size} bytes; the maximum is {$max} bytes (1 MB)"];
    }
    $fname = ($filename !== null && trim($filename) !== '') ? slugify($filename) : slugify($title);
    $cols = 'user_id, slug, title, filename, content, size_bytes';
    $extra = [];
    if (feature(Feature::HtmlNotes)) {
        $cols .= ', format';
        $extra[] = ($format ?? NoteFormat::Markdown)->value;
    }
    if (feature(Feature::Visibility)) {
        $cols .= ', visibility';
        $extra[] = ($visibility ?? Visibility::Tailnet)->value;
    }
    $stmt = db()->prepare("INSERT INTO notes ({$cols}) VALUES (" . rtrim(str_repeat('?, ', 6 + count($extra)), ', ') . ')');
    // ponytail: retry loop is the cheap fix for the smaller 8-char space; 62^8 ≈ 2e14 keeps collisions near-never.
    for ($try = 0; ; $try++) {
        $slug = generate_slug();
        try {
            $stmt->execute([$userId, $slug, substr($title, 0, 255), $fname, $content, $size, ...$extra]);
            break;
        } catch (PDOException $e) {
            if ($try < 5 && str_contains($e->getMessage(), 'slug')) {
                continue; // duplicate slug — try another
            }
            throw $e;
        }
    }
    return [get_note($slug, $userId), null];
}

function get_note(string $slug, ?int $userId = null): ?array
{
    // ponytail: explicit columns so the pdf_cache blob never rides along on page views
    $sql = 'SELECT ' . note_columns() . ' FROM notes WHERE slug = ?';
    $params = [$slug];
    if ($userId !== null) {
        $sql .= ' AND user_id = ?';
        $params[] = $userId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

/** @return array{0: ?array, 1: ?string} [note, error] */
function update_note(int $userId, string $slug, ?string $title, ?string $content, ?string $filename = null, ?NoteFormat $format = null, ?Visibility $visibility = null): array
{
    $note = get_note($slug, $userId);
    if (!$note) {
        return [null, "note '{$slug}' not found"];
    }
    $format = feature(Feature::HtmlNotes) ? $format : null;
    $visibility = feature(Feature::Visibility) ? $visibility : null;
    if ($title === null && $content === null && $filename === null && $format === null && $visibility === null) {
        return [null, 'provide title, content, and/or filename to update'];
    }
    if ($content !== null) {
        $size = strlen($content);
        $max = (int) env('NOTE_MAX_BYTES', '1048576');
        if ($size > $max) {
            return [null, "content is {$size} bytes; the maximum is {$max} bytes (1 MB)"];
        }
        db()->prepare('UPDATE notes SET content = ?, size_bytes = ?, pdf_cache = NULL WHERE id = ?')
            ->execute([$content, $size, $note['id']]);
    }
    if ($title !== null && trim($title) !== '') {
        db()->prepare('UPDATE notes SET title = ?, pdf_cache = NULL WHERE id = ?')
            ->execute([substr(trim($title), 0, 255), $note['id']]);
    }
    if ($filename !== null && trim($filename) !== '') {
        db()->prepare('UPDATE notes SET filename = ?, pdf_cache = NULL WHERE id = ?')
            ->execute([slugify($filename), $note['id']]);
    }
    if ($format !== null) {
        db()->prepare('UPDATE notes SET format = ?, pdf_cache = NULL WHERE id = ?')->execute([$format->value, $note['id']]);
    }
    if ($visibility !== null) { // only ever an explicit argument: nothing changes visibility implicitly
        // pdf_cache = NULL: the PDF prints the note's URL, which changes host with visibility
        db()->prepare('UPDATE notes SET visibility = ?, pdf_cache = NULL WHERE id = ?')->execute([$visibility->value, $note['id']]);
    }
    return [get_note($slug, $userId), null];
}

function list_notes(int $userId, int $limit = 50, int $offset = 0): array
{
    $limit = max(1, min($limit, 200));
    $offset = max(0, $offset);
    $extra = (feature(Feature::HtmlNotes) ? ', format' : '') . (feature(Feature::Visibility) ? ', visibility' : '');
    $stmt = db()->prepare(
        "SELECT slug, title, filename, size_bytes, views, created_at, updated_at{$extra} FROM notes
         WHERE user_id = ? ORDER BY updated_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function delete_note(int $userId, string $slug): bool
{
    return delete_notes('slug = ? AND user_id = ?', [$slug, $userId]) > 0;
}

/**
 * Every note deletion goes through here, so a note's asset directory always goes with it. Share links
 * cascade in the database. The rows are locked and deleted in one transaction, the same row lock
 * store_asset() takes, so no upload can land after the purge. The files go after the commit: if
 * removing them fails, they are unreachable (no note, no route) rather than a note pointing at
 * missing files.
 */
function delete_notes(string $where, array $params): int
{
    return count(delete_locked($where, $params));
}

/** Deletes a user and everything they own (notes and their assets; tokens and sessions cascade). */
function delete_user(int $userId): void
{
    delete_locked('user_id = ?', [$userId], $userId);
}

/**
 * With $userId, the user row is locked first, which blocks new notes for them (the notes foreign-key
 * check waits on it), and deleted with their notes.
 * @return list<int> the deleted note ids
 */
function delete_locked(string $where, array $params, ?int $userId = null): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($userId !== null) {
            $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$userId]);
        }
        $stmt = $pdo->prepare("SELECT id FROM notes WHERE {$where} FOR UPDATE");
        $stmt->execute($params);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($ids) {
            $pdo->exec('DELETE FROM notes WHERE id IN (' . implode(',', $ids) . ')');
        }
        if ($userId !== null) {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    foreach ($ids as $id) {
        purge_note_assets($id);
    }
    return $ids;
}
