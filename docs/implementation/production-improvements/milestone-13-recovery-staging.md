# Milestone 13 Make backup publication safe and prepare a restore rehearsal

## Existing implementation and gaps

The host backup script takes MySQL and media snapshots. Laravel also supports scheduled object-storage backups. Concurrent host invocations could publish over the same archive.

## Changes

Publish completed host backup archives atomically without replacing an existing archive. A collision leaves the winning archive and removes the losing temporary staging directory.

## Files, database, API, and compatibility

`scripts/production-backup.sh`, `scripts/tests/production-backup-test.sh`. The archive layout and verification-script contract are preserved. The backup destination must support hard links.

## Verification and measurements

Bash syntax checks passed. The regression failed against the baseline as expected and passed with the fix. The exercise used a stubbed database stream and encryption command. Actual encryption, archive provenance, MySQL restore, media restoration, and RPO/RTO remain unverified.

## Remaining work

Use a separate staging Compose project and volumes, staging HTTPS origins, provider sandbox credentials, isolated media and backup buckets, a pinned image set, and operators with explicit restore access. Rehearse restoring configuration, database, and media. Verify customer/admin auth, records, catalog, playback, and provider reconciliation. Record RPO/RTO and preserve deployment and recovery evidence.

## Rollback

Stop the backup job before reverting the script, or keep external scheduling serialized. Preserve archived files and all existing volumes.

## Commit references

`bd680b2`

## Staging and recovery checklist

- Select a separate Compose project and isolated application, database, search, queue, media, and backup resources. Use `.env.production.example` and the [existing deployment guide](../milestone-15-production-verification.md) as the inventory for environment variable names. Provision HTTPS, explicit origins and trusted proxy policy, release image digests, sandbox callback URLs, and scoped storage and backup credentials.
- Run the two Compose configuration checks with staging examples available, validate the merged production overlay, then build images, run additive migrations against isolated volumes, and verify health and internal service connectivity.
- Verify the stored archive using `scripts/verify-production-backup.sh` with the staging age identity. Preserve the encryption key separately. Restore configuration, database and media to disposable resources before pointing a staging application at them. Compare payment, subscription and catalog counts and verify sign-in and protected playback.
- Assign the operator responsible for the restore and the reviewer responsible for the acceptance checks. Record snapshot timestamps, restored checkpoints, end-to-end elapsed time, and test evidence. Roll back to the preceding compatible image and configuration while retaining volumes. Confirm the production backup filesystem supports atomic hard-link creation before adopting the script.
