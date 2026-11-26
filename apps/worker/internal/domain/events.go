package domain

import "encoding/json"

// Standard Envelope for all OTT events published to RabbitMQ
type EventEnvelope struct {
	ID            string          `json:"id"`
	Event         string          `json:"event"`
	Timestamp     int64           `json:"timestamp"`
	CorrelationID string          `json:"correlation_id"`
	RetryCount    int             `json:"retry_count"`
	Payload       json.RawMessage `json:"payload"`
}

// Media Upload Process Payload
type MediaUploadPayload struct {
	FilemanagerID int64  `json:"filemanager_id"`
	FilePath      string `json:"file_path"`
	DiskType      string `json:"disk_type"`
	OriginalName  string `json:"original_name"`
	PageType      string `json:"page_type"`
	FileType      string `json:"file_type"`
}

// Thumbnail / Image BlurHash Generation Payload
type ThumbnailBlurHashPayload struct {
	TargetTable string `json:"target_table"` // entertainments, episodes, banners, videos, filemanagers
	TargetID    string `json:"target_id"`    // record id (uuid or int)
	ImageURL    string `json:"image_url"`    // remote URL or relative storage path
	PageType    string `json:"page_type,omitempty"`
}

// Bulk Push/Notification Payload
type BulkNotificationPayload struct {
	Title       string   `json:"title"`
	Description string   `json:"description"`
	ImageURL    string   `json:"image_url,omitempty"`
	Target      string   `json:"target"` // all, subscribed, users
	UserIDs     []int64  `json:"user_ids,omitempty"`
	Channel     string   `json:"channel"` // fcm, mail, database
}

// Scheduled Maintenance Payload
type CronPayload struct {
	TaskName  string `json:"task_name"`
	Timestamp int64  `json:"timestamp"`
}

// Bulk Data Import Payload
type ImportPayload struct {
	Type     string `json:"type"` // movies, shows, episodes, users
	FilePath string `json:"file_path"`
	UserID   int64  `json:"user_id"`
}
