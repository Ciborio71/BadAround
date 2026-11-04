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

-- Proposed first-time identity enrollment — CI prototype, NOT a production migration.
-- encrypted profile uses AES-256-GCM with an external 32-byte key; needs key
-- versioning/rotation, data retention policy and audited deletion before launch.
CREATE TABLE ba_identity_pending (
  challenge_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  protected_profile TEXT NOT NULL,
  created_at BIGINT UNSIGNED NOT NULL,
  verified_at BIGINT UNSIGNED NULL,
  KEY idx_pending_email (email_lookup),
  KEY idx_pending_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ba_identities_ci (
  identity_id VARCHAR(128) NOT NULL PRIMARY KEY,
  email_lookup CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  protected_profile TEXT NOT NULL,
  verified_at BIGINT UNSIGNED NOT NULL,
  revoked_at BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CI-only proposed rate-limit table, never production DDL.
-- Prune expired windows with audited retention schedule.
CREATE TABLE ba_identity_rate_ci (
  purpose VARCHAR(24) NOT NULL,
  bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  window_start BIGINT UNSIGNED NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (purpose,bucket,window_start),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Explicit creator binding (review-only), UNIQUE resource prevents takeover.
-- No email matching can grant ownership. WordPress intake transaction/recovery
-- remains an integration requirement.
CREATE TABLE ba_identity_ownership_ci (
  identity_id VARCHAR(128) NOT NULL,
  resource_kind VARCHAR(32) NOT NULL,
  resource_id BIGINT UNSIGNED NOT NULL,
  relation VARCHAR(32) NOT NULL,
  verified_binding_at BIGINT UNSIGNED NOT NULL,
  revoked_at BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_resource (resource_kind,resource_id),
  KEY idx_identity (identity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
