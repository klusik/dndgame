CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(190) NOT NULL,
    role VARCHAR(40) NOT NULL DEFAULT 'admin',
    locale VARCHAR(10) NOT NULL DEFAULT 'en',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    description_file VARCHAR(500) NOT NULL DEFAULT '',
    storage_path VARCHAR(500) NOT NULL DEFAULT '',
    context_file VARCHAR(500) NULL,
    default_language VARCHAR(10) NOT NULL DEFAULT 'en',
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_campaigns_owner (owner_user_id),
    INDEX idx_campaigns_status (status),
    CONSTRAINT fk_campaigns_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_memberships (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role VARCHAR(40) NOT NULL DEFAULT 'owner',
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_campaign_user (campaign_id, user_id),
    INDEX idx_memberships_user (user_id),
    CONSTRAINT fk_memberships_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_memberships_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    sequence_number INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    raw_transcript_file VARCHAR(500) NOT NULL,
    narrative_file VARCHAR(500) NOT NULL,
    state_delta_file VARCHAR(500) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'stored',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_campaign_sequence (campaign_id, sequence_number),
    INDEX idx_sessions_campaign (campaign_id),
    CONSTRAINT fk_sessions_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS session_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    title VARCHAR(190) NOT NULL,
    description_file VARCHAR(500) NOT NULL,
    visibility VARCHAR(40) NOT NULL DEFAULT 'player',
    created_at DATETIME NOT NULL,
    INDEX idx_session_events_campaign (campaign_id),
    INDEX idx_session_events_session (session_id),
    CONSTRAINT fk_session_events_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_session_events_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    type_code VARCHAR(80) NOT NULL DEFAULT 'other',
    canonical_name VARCHAR(190) NOT NULL,
    display_name VARCHAR(190) NOT NULL,
    visibility VARCHAR(40) NOT NULL DEFAULT 'player',
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    notes_file VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_entities_campaign_name (campaign_id, canonical_name),
    INDEX idx_entities_campaign_type (campaign_id, type_code),
    CONSTRAINT fk_entities_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entity_aliases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_id INT UNSIGNED NOT NULL,
    alias VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_entity_alias (entity_id, alias),
    CONSTRAINT fk_entity_aliases_entity FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS facts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    subject_entity_id INT UNSIGNED NULL,
    category VARCHAR(80) NOT NULL DEFAULT 'other',
    subject_text VARCHAR(190) NOT NULL,
    predicate VARCHAR(190) NOT NULL,
    value_text TEXT NOT NULL,
    certainty VARCHAR(40) NOT NULL DEFAULT 'confirmed',
    visibility VARCHAR(40) NOT NULL DEFAULT 'player',
    source_session_id INT UNSIGNED NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_facts_campaign (campaign_id),
    INDEX idx_facts_subject_entity (subject_entity_id),
    INDEX idx_facts_source_session (source_session_id),
    INDEX idx_facts_lookup (campaign_id, category, subject_text, predicate),
    CONSTRAINT fk_facts_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_facts_subject_entity FOREIGN KEY (subject_entity_id) REFERENCES entities(id) ON DELETE SET NULL,
    CONSTRAINT fk_facts_source_session FOREIGN KEY (source_session_id) REFERENCES sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    parent_location_id INT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    summary_file VARCHAR(500) NULL,
    visibility VARCHAR(40) NOT NULL DEFAULT 'player',
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_locations_campaign_slug (campaign_id, slug),
    INDEX idx_locations_parent (parent_location_id),
    CONSTRAINT fk_locations_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_locations_parent FOREIGN KEY (parent_location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS location_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_location_id INT UNSIGNED NOT NULL,
    to_location_id INT UNSIGNED NOT NULL,
    relation_type VARCHAR(80) NOT NULL,
    distance_text VARCHAR(190) NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_location_link (from_location_id, to_location_id, relation_type),
    CONSTRAINT fk_location_links_from FOREIGN KEY (from_location_id) REFERENCES locations(id) ON DELETE CASCADE,
    CONSTRAINT fk_location_links_to FOREIGN KEY (to_location_id) REFERENCES locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    item_type VARCHAR(80) NOT NULL DEFAULT 'item',
    summary_file VARCHAR(500) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'available',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_items_campaign_name (campaign_id, name),
    CONSTRAINT fk_items_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entity_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
    status VARCHAR(40) NOT NULL DEFAULT 'held',
    notes_file VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_entity_item (entity_id, item_id),
    CONSTRAINT fk_entity_items_entity FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE,
    CONSTRAINT fk_entity_items_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mysteries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'open',
    visibility VARCHAR(40) NOT NULL DEFAULT 'player',
    summary_file VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_mysteries_campaign_slug (campaign_id, slug),
    CONSTRAINT fk_mysteries_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mystery_clues (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mystery_id INT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NULL,
    clue_text TEXT NOT NULL,
    certainty VARCHAR(40) NOT NULL DEFAULT 'confirmed',
    visibility VARCHAR(40) NOT NULL DEFAULT 'player',
    created_at DATETIME NOT NULL,
    INDEX idx_mystery_clues_session (session_id),
    CONSTRAINT fk_mystery_clues_mystery FOREIGN KEY (mystery_id) REFERENCES mysteries(id) ON DELETE CASCADE,
    CONSTRAINT fk_mystery_clues_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    rule_key VARCHAR(190) NOT NULL,
    title VARCHAR(190) NOT NULL,
    body_file VARCHAR(500) NOT NULL,
    priority INT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_rules_campaign_key (campaign_id, rule_key),
    CONSTRAINT fk_rules_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prompt_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    template_file VARCHAR(500) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_prompt_template_slug (slug),
    CONSTRAINT fk_prompt_templates_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS generated_prompts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    prompt_file VARCHAR(500) NOT NULL,
    prompt_hash CHAR(64) NOT NULL,
    estimated_length INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX idx_generated_prompts_campaign (campaign_id),
    CONSTRAINT fk_generated_prompts_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_generated_prompts_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chatgpt_responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    generated_prompt_id INT UNSIGNED NULL,
    response_file VARCHAR(500) NOT NULL,
    narrative_file VARCHAR(500) NULL,
    delta_json_file VARCHAR(500) NULL,
    parse_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    INDEX idx_responses_campaign (campaign_id),
    CONSTRAINT fk_responses_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_responses_prompt FOREIGN KEY (generated_prompt_id) REFERENCES generated_prompts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS state_deltas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    source_response_id INT UNSIGNED NOT NULL,
    applied_session_id INT UNSIGNED NULL,
    delta_file VARCHAR(500) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending_review',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_state_deltas_campaign (campaign_id),
    CONSTRAINT fk_state_deltas_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_state_deltas_response FOREIGN KEY (source_response_id) REFERENCES chatgpt_responses(id) ON DELETE CASCADE,
    CONSTRAINT fk_state_deltas_session FOREIGN KEY (applied_session_id) REFERENCES sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS state_snapshots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    snapshot_file VARCHAR(500) NOT NULL,
    snapshot_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_state_snapshots_campaign (campaign_id),
    CONSTRAINT fk_state_snapshots_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS context_exports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    export_file VARCHAR(500) NOT NULL,
    export_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_context_exports_campaign (campaign_id),
    CONSTRAINT fk_context_exports_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    campaign_id INT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    metadata_json JSON NULL,
    ip_address VARCHAR(64) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_campaign (campaign_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(64) NOT NULL,
    was_successful TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL,
    INDEX idx_login_attempts_email_time (email, attempted_at),
    INDEX idx_login_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(190) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
