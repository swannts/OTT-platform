package handlers

import (
	"database/sql"
	"encoding/json"
	"ott-worker/internal/domain"
	"time"

	"github.com/rs/zerolog/log"
)

type CronHandler struct {
	db *sql.DB
}

func NewCronHandler(db *sql.DB) *CronHandler {
	return &CronHandler{db: db}
}

func (h *CronHandler) Handle(body []byte) error {
	var env domain.EventEnvelope
	if err := json.Unmarshal(body, &env); err != nil {
		return err
	}

	var payload domain.CronPayload
	if err := json.Unmarshal(env.Payload, &payload); err != nil {
		return err
	}

	switch payload.TaskName {
	case "cron.sessions":
		return h.cleanExpiredSessions()
	case "cron.subscriptions":
		return h.checkSubscriptionExpiries()
	default:
		log.Info().Str("task", payload.TaskName).Msg("Executed maintenance cron task")
	}

	return nil
}

func (h *CronHandler) cleanExpiredSessions() error {
	if h.db == nil {
		return nil
	}

	// Purge stale multi-device login sessions older than session lifetime (e.g. 120 mins)
	cutoff := time.Now().Add(-120 * time.Minute)
	res, err := h.db.Exec(`
		DELETE FROM devices 
		WHERE session_id IS NOT NULL 
		AND last_activity < ?
	`, cutoff)
	if err != nil {
		log.Error().Err(err).Msg("Failed to clean expired device sessions")
		return err
	}

	rows, _ := res.RowsAffected()
	if rows > 0 {
		log.Info().Int64("deleted_sessions", rows).Msg("Cleaned expired device sessions in MySQL")
	}
	return nil
}

func (h *CronHandler) checkSubscriptionExpiries() error {
	if h.db == nil {
		return nil
	}

	// Query subscriptions expiring within 24 hours
	now := time.Now()
	nextDay := now.Add(24 * time.Hour)
	rows, err := h.db.Query(`
		SELECT id, user_id, end_date 
		FROM subscriptions 
		WHERE status = 'active' 
		AND end_date BETWEEN ? AND ?
		LIMIT 200
	`, now, nextDay)
	if err != nil {
		log.Warn().Err(err).Msg("Failed to query expiring subscriptions")
		return nil
	}
	defer rows.Close()

	count := 0
	for rows.Next() {
		count++
	}
	log.Info().Int("expiring_soon", count).Msg("Processed expiring subscriptions check")
	return nil
}

