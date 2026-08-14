# Data Model — 001-go-patriot-rewrite (SurrealDB)

All tables SCHEMAFULL per spec FR-016 (41 records).

```surql
DEFINE TABLE roles SCHEMAFULL; DEFINE FIELD role ON roles TYPE string; DEFINE FIELD label ON roles TYPE string; DEFINE FIELD permissions ON roles TYPE array;
DEFINE TABLE staff SCHEMAFULL; DEFINE FIELD email ON staff TYPE string ASSERT string::is::email($value); DEFINE FIELD name ON staff TYPE string; DEFINE FIELD role ON staff TYPE string; DEFINE FIELD active ON staff TYPE bool;
DEFINE TABLE otp_codes SCHEMAFULL; DEFINE FIELD identity ON otp_codes TYPE string; DEFINE FIELD purpose ON otp_codes TYPE string; DEFINE FIELD code_hash ON otp_codes TYPE string; DEFINE FIELD expires_at ON otp_codes TYPE datetime;
DEFINE TABLE customers SCHEMAFULL; DEFINE FIELD fr_id ON customers TYPE option<string>; DEFINE FIELD district ON customers TYPE string; DEFINE FIELD is_no_call ON customers TYPE bool;
-- + 38 more: login_attempts, sessions, messages, tickets, cases, pest_photos(posts), posts, content_blocks, site_settings, audit_log, sms_logs, call_logs, voicemails, webhook_events, api_keys (key_prefix unique), facebook_leads(fingerprint), inbox_threads(channel logo), inbox_channel_configs(channel unique token_encrypted), email_threads, email_messages, kanban_boards(visibility), kanban_columns(wip_limit), kanban_cards(labels checklist assignee_ids), kanban_board_members(role), card_comments, card_activity, supplies{sku unique location hazmat sds osha}, supply_locations(warehouse|truck:tech_id|hazmat), supply_moves{photo_url gps who qty at reason} OSHA ledger, reactivation_*, unsubscribes, etc.
-- RAG+tech+Pokedex+messaging: kb_nodes(label embedding vector), kb_edges(RELATE), kb_memories(user_id summary), tech_locations(tech_id lat lng at), customer_notes(gps), internal_messages, notifications(read), pest_identifications(photo confidence tx_id)
DEFINE TABLE kb_nodes SCHEMAFULL; DEFINE FIELD embedding ON kb_nodes TYPE array<float>; DEFINE INDEX kb_vector ON kb_nodes FIELDS embedding MTREE DIMENSION 384;
DEFINE TABLE tech_locations SCHEMAFULL; DEFINE FIELD tech_id ON tech_locations TYPE record<staff>;
DEFINE INDEX key_prefix ON api_keys FIELDS key_prefix UNIQUE;
DEFINE INDEX customer_email ON customers FIELDS email;
DEFINE INDEX kanban_card_pos ON kanban_cards FIELDS column_id, position;
```

## Relations
- `kanban_cards -> customer_id -> customers.id`, `-> case_id -> cases.id`, `-> column_id -> kanban_columns.id`
- `supplies -> fieldroutes_id` optional link
- `inbox_threads.user_id -> customers.id` after OTP stitch

## Settings Encryption
- `inbox_channel_configs.token_encrypted = string::encrypt($value, $APP_KEY)` AES via `pkg/crypto`.
- Per-district FieldRoutes: multiple `inbox_channel_configs WHERE channel='fieldroutes'` rows, `+ Add district` inserts new row.
