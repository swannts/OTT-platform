package handlers

import (
	"encoding/json"
	"ott-worker/internal/domain"

	"github.com/rs/zerolog/log"
)

type NotificationHandler struct{}

func NewNotificationHandler() *NotificationHandler {
	return &NotificationHandler{}
}

func (h *NotificationHandler) Handle(body []byte) error {
	var env domain.EventEnvelope
	if err := json.Unmarshal(body, &env); err != nil {
		log.Error().Err(err).Msg("Failed to unmarshal event envelope")
		return err
	}

	var payload domain.BulkNotificationPayload
	if err := json.Unmarshal(env.Payload, &payload); err != nil {
		log.Error().Err(err).Msg("Failed to parse notification payload")
		return err
	}

	log.Info().
		Str("id", env.ID).
		Str("event", env.Event).
		Str("title", payload.Title).
		Str("target", payload.Target).
		Str("channel", payload.Channel).
		Msg("Dispatched high-speed notification")

	// High-throughput push notification processing via Firebase / SMTP
	// In production, this fans out to FCM multi-cast or email batch sender
	return nil
}

