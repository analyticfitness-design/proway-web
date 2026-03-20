#!/bin/bash
# =============================================================================
# ProWay Lab — MySQL Backup Script
# Runs mysqldump with gzip, retains 7 days, logs result
# =============================================================================

# --- Environment variables (with production fallbacks) -----------------------
DB_HOST="${DB_HOST:-wellcorefitness_wellcorefitness-mysql}"
DB_NAME="${DB_NAME:-prowaylab_db}"
DB_USER="${DB_USER:-wellcorefitness}"
DB_PASS="${DB_PASS:-}"

BACKUP_DIR="/backups"
LOG_FILE="${BACKUP_DIR}/backup.log"
RETENTION_DAYS=7

# --- Derived values -----------------------------------------------------------
DATE=$(date +"%Y-%m-%d")
TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")
FILENAME="prowaylab_${DATE}.sql.gz"
FILEPATH="${BACKUP_DIR}/${FILENAME}"

# --- Ensure backup directory exists ------------------------------------------
mkdir -p "${BACKUP_DIR}"

log() {
    echo "[${TIMESTAMP}] $*" >> "${LOG_FILE}"
}

log "INFO  Starting backup: ${FILENAME}"

# --- Run mysqldump ------------------------------------------------------------
if [ -n "${DB_PASS}" ]; then
    MYSQL_PWD="${DB_PASS}" mysqldump \
        --host="${DB_HOST}" \
        --user="${DB_USER}" \
        --single-transaction \
        --routines \
        --triggers \
        --no-tablespaces \
        "${DB_NAME}" | gzip -9 > "${FILEPATH}"
else
    mysqldump \
        --host="${DB_HOST}" \
        --user="${DB_USER}" \
        --single-transaction \
        --routines \
        --triggers \
        --no-tablespaces \
        "${DB_NAME}" | gzip -9 > "${FILEPATH}"
fi

DUMP_EXIT="${PIPESTATUS[0]}"

# --- Check result ------------------------------------------------------------
if [ "${DUMP_EXIT}" -ne 0 ]; then
    log "ERROR mysqldump failed (exit ${DUMP_EXIT}) — file: ${FILEPATH}"
    # Remove partial/empty file if dump failed
    rm -f "${FILEPATH}"
    exit 1
fi

# Verify the file was actually created and is non-empty
if [ ! -s "${FILEPATH}" ]; then
    log "ERROR Backup file is empty or missing: ${FILEPATH}"
    rm -f "${FILEPATH}"
    exit 1
fi

FILE_SIZE=$(du -sh "${FILEPATH}" | cut -f1)
log "OK    Backup created: ${FILEPATH} (${FILE_SIZE})"

# --- Delete backups older than RETENTION_DAYS --------------------------------
DELETED=$(find "${BACKUP_DIR}" -maxdepth 1 -name "prowaylab_*.sql.gz" \
    -mtime +${RETENTION_DAYS} -print -delete 2>&1)

if [ -n "${DELETED}" ]; then
    while IFS= read -r OLD_FILE; do
        [ -n "${OLD_FILE}" ] && log "PURGE Deleted old backup: ${OLD_FILE}"
    done <<< "${DELETED}"
fi

log "INFO  Backup complete."
exit 0
