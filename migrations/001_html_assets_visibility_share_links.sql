-- Upgrade for installs created from schema.sql before 2026-11 (HTML notes, visibility, share links).
-- Needed only before turning on FEATURE_HTML_NOTES, FEATURE_VISIBILITY or FEATURE_SHARE_LINKS;
-- with every flag off the app never reads these columns. Applied once by bin/migrate.php.
-- Assets need no schema: they live on disk under ASSET_STORAGE_PATH.

ALTER TABLE notes
    ADD COLUMN format VARCHAR(10) NOT NULL DEFAULT 'markdown' AFTER pdf_cache,
    ADD COLUMN visibility VARCHAR(10) NOT NULL DEFAULT 'tailnet' AFTER format;

CREATE TABLE share_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    note_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    created_by INT UNSIGNED NULL,
    created_via VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    last_used_at DATETIME NULL,
    use_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE share_link_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    share_link_id INT UNSIGNED NULL,
    note_id INT UNSIGNED NOT NULL,
    event VARCHAR(10) NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    client VARCHAR(60) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_share_events_link (share_link_id),
    FOREIGN KEY (share_link_id) REFERENCES share_links(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
