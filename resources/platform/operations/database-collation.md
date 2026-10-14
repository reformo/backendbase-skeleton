# Database Collation Procedures

Use MySQL 8.4 with `utf8mb4` and `utf8mb4_tr_0900_ai_ci` database and business-table defaults.
Keep UUID projections and normalized identity keys under their explicit `ascii_bin` overrides.
Keep binary identifiers independent of text collation.

## Fresh database

Docker Compose passes `--character-set-server=utf8mb4` and `--collation-server=utf8mb4_tr_0900_ai_ci` to MySQL.
The image creates the configured database with these defaults during fresh initialization.
Managed database provisioning must specify the same defaults explicitly:

```sql
CREATE DATABASE app_db CHARACTER SET utf8mb4 COLLATE utf8mb4_tr_0900_ai_ci;
```

Replace the sample database name with the approved target name before execution.
The DBAL connection uses `charset=utf8mb4`. Connection charset does not establish database or table collation.

## Existing database

Restarting MySQL with new server defaults does not convert an existing database or its tables.
Inventory the authorized target before preparing a conversion.
Inspect the database default, every text column, generated expressions, and unique indexes.
Check Turkish I variants, accents, Unicode composition, and trailing spaces against the selected business rules.
Retain the lowercase ASCII identity-key policy.

```sql
SELECT @@character_set_server, @@collation_server,
       @@character_set_database, @@collation_database,
       @@character_set_connection, @@collation_connection;
SELECT TABLE_NAME, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE();
```

Draft a separate forward migration when the target differs.
Changing the database default affects future objects. It does not convert existing text columns.
Review duplicate collisions, index rebuilds, lock duration, backup, and recovery on an isolated clone.
Preserve `ascii_bin` generated UUID and normalized-key overrides during conversion.
Apply changes only with explicit authority for the named database and their effects.

## Verification

`MySqlCollationTest` uses an isolated database to verify inherited table collation and native uniqueness behavior.
It tests Turkish case pairs, distinct dotted and dotless I, accent and Unicode equivalence, and NO PAD trailing spaces.
`MySqlMappingTest` verifies the identity metadata and virtual UUID schema parity.
Run the adoption checks in [migration consolidation](migration-consolidation.md) before upgrading an existing database.
