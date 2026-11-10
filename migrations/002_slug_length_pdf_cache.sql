-- Longer slugs (SLUG_BYTES, up to 32 bytes = 43 base62 characters), compared case-sensitively: under the
-- table's case-insensitive collation a lookup matched any letter case, so a 22-character slug carried
-- about 114 bits instead of 131. Existing slugs (base62, so plain ASCII) are unchanged and keep working
-- in the case they were issued in; the unique key on slug is kept. Needed only before setting SLUG_BYTES.
ALTER TABLE notes MODIFY slug VARCHAR(43) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

-- Cached PDFs are rebuilt on their next download, with this release's URL rules (a public note's PDF
-- prints its public URL). Clearing them once is safe: the cache is only a cache.
UPDATE notes SET pdf_cache = NULL;
