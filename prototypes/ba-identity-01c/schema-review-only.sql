-- BA-IDENTITY-01C — REVIEW-ONLY draft, DO NOT EXECUTE ON PRODUCTION.
-- Sample unprefixed identifiers; replace using WordPress prefix only during
-- controlled additive installer upgrade, after schema/privilege/retention QA.
-- MySQL 8 / InnoDB; BIGINT timestamps are Unix seconds (UTC).
CREATE TABLE ba_identity_challenges (
  challenge_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  code_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_at BIGINT UNSIGNED NOT NULL,
  expires_at BIGINT UNSIGNED NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  consumed_at BIGINT UNSIGNED NULL,
  version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_expiry (expires_at),
  KEY idx_email_lookup (email_lookup)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ba_identity_sessions (
  session_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  identity_id VARCHAR(128) NOT NULL,
  created_at BIGINT UNSIGNED NOT NULL,
  last_seen_at BIGINT UNSIGNED NOT NULL,
  expires_at BIGINT UNSIGNED NOT NULL,
  revoked_at BIGINT UNSIGNED NULL,
  KEY idx_identity (identity_id),
  KEY idx_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Future addition: verified ownership, identity lifecycle, and revision tables
-- need a separate detailed privacy/retention and rollback review.
-- This DDL is intentionally NOT included by BadAround_Installer.
