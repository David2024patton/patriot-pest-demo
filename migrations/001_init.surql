-- 001_init.surql — 44 tables SCHEMAFULL per FR-016, SURREAL_* 12-factor
-- SurrealDB surrealdb.go: ws://surreal:8000 / mem://

DEFINE TABLE roles SCHEMAFULL;
DEFINE FIELD role ON roles TYPE string;
DEFINE FIELD label ON roles TYPE string;
DEFINE FIELD permissions ON roles TYPE array;

DEFINE TABLE staff SCHEMAFULL;
DEFINE FIELD email ON staff TYPE string ASSERT string::is::email($value);
DEFINE FIELD name ON staff TYPE string;
DEFINE FIELD role ON staff TYPE string;
DEFINE FIELD active ON staff TYPE bool;
DEFINE INDEX staff_email ON staff FIELDS email UNIQUE;

DEFINE TABLE otp_codes SCHEMAFULL;
DEFINE FIELD identity ON otp_codes TYPE string;
DEFINE FIELD purpose ON otp_codes TYPE string;
DEFINE FIELD code_hash ON otp_codes TYPE string;
DEFINE FIELD expires_at ON otp_codes TYPE datetime;
DEFINE FIELD attempts ON otp_codes TYPE int DEFAULT 0;

DEFINE TABLE customers SCHEMAFULL;
DEFINE FIELD fr_id ON customers TYPE option<string>;
DEFINE FIELD district ON customers TYPE string;
DEFINE FIELD is_no_call ON customers TYPE bool DEFAULT false;
DEFINE FIELD email ON customers TYPE option<string>;
DEFINE INDEX customer_email ON customers FIELDS email;
DEFINE INDEX customer_frid ON customers FIELDS fr_id UNIQUE;

DEFINE TABLE sessions SCHEMAFULL;
DEFINE FIELD identity ON sessions TYPE string;
DEFINE FIELD role ON sessions TYPE string;
DEFINE FIELD expires_at ON sessions TYPE datetime;

DEFINE TABLE api_keys SCHEMAFULL;
DEFINE FIELD key_prefix ON api_keys TYPE string;
DEFINE FIELD hash ON api_keys TYPE string;
DEFINE FIELD scopes ON api_keys TYPE array<string>;
DEFINE INDEX key_prefix ON api_keys FIELDS key_prefix UNIQUE;

DEFINE TABLE messages SCHEMAFULL;
DEFINE FIELD channel ON messages TYPE string;
DEFINE FIELD body ON messages TYPE string;

DEFINE TABLE inbox_threads SCHEMAFULL;
DEFINE FIELD channel ON inbox_threads TYPE string;
DEFINE FIELD user_id ON inbox_threads TYPE option<record<customers>>;

DEFINE TABLE inbox_channel_configs SCHEMAFULL;
DEFINE FIELD channel ON inbox_channel_configs TYPE string;
DEFINE FIELD token_encrypted ON inbox_channel_configs TYPE string;
DEFINE FIELD enabled ON inbox_channel_configs TYPE bool DEFAULT true;

DEFINE TABLE email_threads SCHEMAFULL;
DEFINE FIELD subject ON email_threads TYPE string;

DEFINE TABLE email_messages SCHEMAFULL;
DEFINE FIELD thread_id ON email_messages TYPE record<email_threads>;

DEFINE TABLE kanban_boards SCHEMAFULL;
DEFINE FIELD title ON kanban_boards TYPE string;
DEFINE FIELD visibility ON kanban_boards TYPE string;

DEFINE TABLE kanban_columns SCHEMAFULL;
DEFINE FIELD board_id ON kanban_columns TYPE record<kanban_boards>;
DEFINE FIELD wip_limit ON kanban_columns TYPE option<int>;

DEFINE TABLE kanban_cards SCHEMAFULL;
DEFINE FIELD column_id ON kanban_cards TYPE record<kanban_columns>;
DEFINE FIELD title ON kanban_cards TYPE string;
DEFINE FIELD labels ON kanban_cards TYPE array<string>;
DEFINE FIELD assignee_ids ON kanban_cards TYPE array<record<staff>>;
DEFINE FIELD position ON kanban_cards TYPE int;
DEFINE INDEX kanban_card_pos ON kanban_cards FIELDS column_id, position;

DEFINE TABLE kanban_board_members SCHEMAFULL;
DEFINE FIELD board_id ON kanban_board_members TYPE record<kanban_boards>;
DEFINE FIELD user_id ON kanban_board_members TYPE record<staff>;
DEFINE FIELD role ON kanban_board_members TYPE string ASSERT $value IN ["viewer","editor","admin"];

DEFINE TABLE supplies SCHEMAFULL;
DEFINE FIELD sku ON supplies TYPE string;
DEFINE FIELD location ON supplies TYPE string;
DEFINE FIELD hazmat ON supplies TYPE bool DEFAULT false;
DEFINE FIELD qty ON supplies TYPE int;
DEFINE INDEX supply_sku ON supplies FIELDS sku UNIQUE;

DEFINE TABLE supply_locations SCHEMAFULL;
DEFINE FIELD name ON supply_locations TYPE string;

DEFINE TABLE supply_moves SCHEMAFULL;
DEFINE FIELD supply_id ON supply_moves TYPE record<supplies>;
DEFINE FIELD who ON supply_moves TYPE record<staff>;
DEFINE FIELD qty ON supply_moves TYPE int;
DEFINE FIELD photo_url ON supply_moves TYPE option<string>;
DEFINE FIELD gps ON supply_moves TYPE option<string>;
DEFINE FIELD at ON supply_moves TYPE datetime;
DEFINE FIELD reason ON supply_moves TYPE string;

DEFINE TABLE kb_nodes SCHEMAFULL;
DEFINE FIELD label ON kb_nodes TYPE string;
DEFINE FIELD embedding ON kb_nodes TYPE array<float>;
DEFINE INDEX kb_vector ON kb_nodes FIELDS embedding MTREE DIMENSION 384;

DEFINE TABLE kb_edges SCHEMAFULL;

DEFINE TABLE tech_locations SCHEMAFULL;
DEFINE FIELD tech_id ON tech_locations TYPE record<staff>;
DEFINE FIELD lat ON tech_locations TYPE float;
DEFINE FIELD lng ON tech_locations TYPE float;
DEFINE FIELD at ON tech_locations TYPE datetime;

DEFINE TABLE internal_messages SCHEMAFULL;
DEFINE FIELD body ON internal_messages TYPE string;

DEFINE TABLE notifications SCHEMAFULL;
DEFINE FIELD read ON notifications TYPE bool DEFAULT false;

