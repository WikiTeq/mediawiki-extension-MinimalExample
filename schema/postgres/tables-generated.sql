-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/MinimalExample/schema/tables.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE me_syntaxhelp (
  mesh_content_model TEXT NOT NULL,
  mesh_help_page BIGINT NOT NULL,
  PRIMARY KEY(mesh_content_model)
);