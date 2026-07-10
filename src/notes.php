<?php

declare(strict_types=1);

function note_url(string $slug): string
{
    return app_url('/n/' . $slug);
}

function slugify(string $title): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
    return substr($slug, 0, 60) ?: 'note';
}

function generate_slug(string $title): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $suffix = '';
    for ($i = 0; $i < 10; $i++) {
        $suffix .= $alphabet[random_int(0, 61)];
    }
    return slugify($title) . '-' . $suffix;
}

/** @return array{0: ?array, 1: ?string} [note, error] */
function create_note(int $userId, string $title, string $content): array
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
    $slug = generate_slug($title);
    db()->prepare('INSERT INTO notes (user_id, slug, title, content, size_bytes) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $slug, substr($title, 0, 255), $content, $size]);
    return [get_note($slug, $userId), null];
}

function get_note(string $slug, ?int $userId = null): ?array
{
    // ponytail: explicit columns so the pdf_cache blob never rides along on page views
    $sql = 'SELECT id, user_id, slug, title, content, size_bytes, views, created_at, updated_at FROM notes WHERE slug = ?';
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
function update_note(int $userId, string $slug, ?string $title, ?string $content): array
{
    $note = get_note($slug, $userId);
    if (!$note) {
        return [null, "note '{$slug}' not found"];
    }
    if ($title === null && $content === null) {
        return [null, 'provide title and/or content to update'];
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
    return [get_note($slug, $userId), null];
}

function list_notes(int $userId, int $limit = 50, int $offset = 0): array
{
    $limit = max(1, min($limit, 200));
    $offset = max(0, $offset);
    $stmt = db()->prepare(
        'SELECT slug, title, size_bytes, views, created_at, updated_at FROM notes
         WHERE user_id = ? ORDER BY updated_at DESC LIMIT ? OFFSET ?'
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function delete_note(int $userId, string $slug): bool
{
    $stmt = db()->prepare('DELETE FROM notes WHERE slug = ? AND user_id = ?');
    $stmt->execute([$slug, $userId]);
    return $stmt->rowCount() > 0;
}
