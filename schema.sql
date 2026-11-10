-- Agent Notes schema. Import into the `agent_notes` database.

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(50) NULL UNIQUE,
    password_hash VARCHAR(255) NULL,
    email_verified_at DATETIME NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    last_login_user_agent VARCHAR(500) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    purpose VARCHAR(10) NOT NULL DEFAULT 'login',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_codes_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL DEFAULT 'default',
    token_hash CHAR(64) NOT NULL UNIQUE,
    prefix VARCHAR(12) NOT NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    slug VARCHAR(8) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    filename VARCHAR(120) NOT NULL DEFAULT 'note',
    content MEDIUMTEXT NOT NULL,
    size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    pdf_cache MEDIUMBLOB NULL,
    format VARCHAR(10) NOT NULL DEFAULT 'markdown',    -- FEATURE_HTML_NOTES: markdown | html
    visibility VARCHAR(10) NOT NULL DEFAULT 'tailnet', -- FEATURE_VISIBILITY: tailnet | public
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FEATURE_SHARE_LINKS: read links to one tailnet note. Only sha256(token) is stored.
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

-- Audit trail: created / opened / viewed / revoked, with time and a coarse client label ("Chrome on macOS").
-- No tokens, no IPs. Rows outlive their link and note.
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

CREATE TABLE rate_limits (
    bucket_key VARCHAR(120) NOT NULL,
    window_start DATETIME NOT NULL,
    count INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (bucket_key, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeded admin account (pre-verified; sets username/password via Account settings after OTP login)
INSERT INTO users (email, is_admin, email_verified_at) VALUES ('hurayraiit+admin@gmail.com', 1, NOW());
