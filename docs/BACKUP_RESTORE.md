# AQUASENSE+ Backup and Restore

## What must be backed up

Back up the MySQL/MariaDB database and the directory named by
`AQUASENSE_STORAGE_PATH` as one recovery set. Also retain the deployed Git commit,
`composer.lock`, environment-variable names (values in a secret manager), web
server configuration, cron definition, and TLS renewal procedure. Application logs
may use a separate operational retention policy.

## Backup procedure

1. Put review/distribution changes into a short maintenance window, or use a
   transaction-consistent database snapshot.
2. Create a single-transaction logical dump with routines/triggers disabled unless
   the host requires them:
   `mysqldump --single-transaction --quick --routines=false --triggers=false DB_NAME > aquasense-UTC.sql`
3. Archive the private evidence directory while recording its timestamp.
4. Encrypt the database dump and evidence archive, checksum both, copy them off the
   application host, and restrict access.
5. Record commit/version, database server version, file counts, sizes, checksums,
   start/end times, operator, and result.
6. Keep daily/weekly/monthly copies according to the approved policy. Monitor failed
   jobs and storage capacity.

Do not place backups inside the web document root or commit them to Git.

## Restore drill

1. Provision an isolated server with compatible PHP and MySQL/MariaDB versions.
2. Deploy the recorded Git commit and run `composer install --no-dev
   --classmap-authoritative`.
3. Create an empty database and restore the SQL dump.
4. Restore evidence to a private directory and set ownership to the PHP worker with
   directories 0750 and files 0640 (or the hosting equivalent).
5. Set test environment variables against the restored instance.
6. Verify table counts, foreign keys, latest telemetry, active alerts, surrender
   photos, incentives, Compliance Ledger, Audit Log, and owner isolation.
7. Run health and regression tests that do not mutate production data.
8. Document recovery time and data-loss window, then destroy the drill environment.

## Production recovery

Stop writes, preserve the failed system for investigation, select the last verified
recovery set, and restore database plus evidence from the same set. Apply only
numbered migrations newer than the restored schema. Validate with the production
checklist before changing DNS or returning traffic. Never test a restore by
overwriting the only production database.
