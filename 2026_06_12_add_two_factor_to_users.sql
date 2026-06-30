ALTER TABLE users
  ADD COLUMN twoFactorSecret VARCHAR(64) DEFAULT NULL AFTER payshapNumber,
  ADD COLUMN twoFactorEnabled TINYINT(1) NOT NULL DEFAULT 0 AFTER twoFactorSecret;
