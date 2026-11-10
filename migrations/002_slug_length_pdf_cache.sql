-- Longer slugs (SLUG_BYTES, up to 32 bytes = 43 base62 characters). Existing slugs are unchanged and
-- stay valid; the unique key on slug is kept. Needed only before setting SLUG_BYTES.
ALTER TABLE notes MODIFY slug VARCHAR(43) NOT NULL;

-- Cached PDFs are rebuilt on their next download, with this release's URL rules (a public note's PDF
-- prints its public URL). Clearing them once is safe: the cache is only a cache.
UPDATE notes SET pdf_cache = NULL;
