-- ZeroThink OpenZero shared gateway trial counter
-- Safe additive migration: creates a new table only.

CREATE TABLE IF NOT EXISTS zerothink_openzero_trial_usage (
    user_id INT NOT NULL PRIMARY KEY,
    used_count INT NOT NULL DEFAULT 0,
    first_used_at DATETIME NULL,
    last_used_at DATETIME NULL,
    KEY idx_zerothink_openzero_trial_last_used (last_used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
