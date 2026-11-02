package handlers

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"path/filepath"
	"strings"

	"ott-worker/internal/domain"
	"ott-worker/internal/services"

	"github.com/rs/zerolog/log"
)

type MediaHandler struct {
	db          *sql.DB
	storagePath string
}

func NewMediaHandler(db *sql.DB, storagePath string) *MediaHandler {
	return &MediaHandler{
		db:          db,
		storagePath: storagePath,
	}
}

func (h *MediaHandler) Handle(body []byte) error {
	var env domain.EventEnvelope
	if err := json.Unmarshal(body, &env); err != nil {
		return fmt.Errorf("invalid envelope JSON: %w", err)
	}

	switch env.Event {
	case "media.thumbnail.blurhash", "thumbnail.blurhash":
		return h.handleThumbnailBlurHash(env.Payload)

	case "media.upload.process", "media.upload":
		return h.handleMediaUpload(env.Payload)

	case "media.blurhash.backfill":
		return h.handleBackfill()

	default:
		// Attempt fallback parsing
		var thumb domain.ThumbnailBlurHashPayload
		if err := json.Unmarshal(env.Payload, &thumb); err == nil && thumb.TargetTable != "" && thumb.ImageURL != "" {
			return h.handleThumbnailBlurHash(env.Payload)
		}

		var media domain.MediaUploadPayload
		if err := json.Unmarshal(env.Payload, &media); err == nil && media.FilemanagerID > 0 {
			return h.handleMediaUpload(env.Payload)
		}

		log.Warn().Str("event", env.Event).Msg("Unknown media event received, skipping")
		return nil
	}
}

func (h *MediaHandler) handleThumbnailBlurHash(payloadRaw json.RawMessage) error {
	var payload domain.ThumbnailBlurHashPayload
	if err := json.Unmarshal(payloadRaw, &payload); err != nil {
		return fmt.Errorf("failed to decode thumbnail payload: %w", err)
	}

	if payload.ImageURL == "" || payload.TargetID == "" || payload.TargetTable == "" {
		log.Warn().Interface("payload", payload).Msg("Incomplete thumbnail blurhash payload")
		return nil
	}

	log.Info().
		Str("table", payload.TargetTable).
		Str("id", payload.TargetID).
		Str("url", payload.ImageURL).
		Msg("Generating BlurHash for media thumbnail...")

	hash, err := services.GenerateBlurHashFromSource(payload.ImageURL, h.storagePath, 4, 3)
	if err != nil {
		log.Error().Err(err).
			Str("table", payload.TargetTable).
			Str("id", payload.TargetID).
			Str("url", payload.ImageURL).
			Msg("Failed to generate BlurHash")
		return err
	}

	log.Info().
		Str("table", payload.TargetTable).
		Str("id", payload.TargetID).
		Str("blurhash", hash).
		Msg("Successfully computed BlurHash")

	if h.db != nil {
		// Whitelist valid tables to prevent SQL injection
		validTables := map[string]bool{
			"entertainments": true,
			"episodes":       true,
			"banners":        true,
			"videos":         true,
			"filemanagers":   true,
		}

		if !validTables[payload.TargetTable] {
			return fmt.Errorf("invalid target table: %s", payload.TargetTable)
		}

		query := fmt.Sprintf("UPDATE %s SET blurhash = ?, updated_at = NOW() WHERE id = ?", payload.TargetTable)
		res, err := h.db.Exec(query, hash, payload.TargetID)
		if err != nil {
			log.Error().Err(err).Msg("Failed to update database with blurhash")
			return err
		}
		rows, _ := res.RowsAffected()
		log.Info().Int64("rows_affected", rows).Msg("Updated database record with BlurHash")
	}

	return nil
}

func (h *MediaHandler) handleMediaUpload(payloadRaw json.RawMessage) error {
	var payload domain.MediaUploadPayload
	if err := json.Unmarshal(payloadRaw, &payload); err != nil {
		return fmt.Errorf("failed to decode media upload payload: %w", err)
	}

	log.Info().
		Int64("filemanager_id", payload.FilemanagerID).
		Str("file_path", payload.FilePath).
		Str("disk_type", payload.DiskType).
		Str("page_type", payload.PageType).
		Str("file_type", payload.FileType).
		Msg("Processing async media file upload in Go worker")

	var blurhash string
	ext := strings.ToLower(filepath.Ext(payload.FilePath))
	isImage := payload.FileType == "image" || ext == ".jpg" || ext == ".jpeg" || ext == ".png" || ext == ".webp" || ext == ".gif"

	if isImage && payload.FilePath != "" {
		hash, err := services.GenerateBlurHashFromSource(payload.FilePath, h.storagePath, 4, 3)
		if err != nil {
			log.Warn().Err(err).Str("file_path", payload.FilePath).Msg("Could not generate blurhash for uploaded image")
		} else {
			blurhash = hash
			log.Info().
				Int64("filemanager_id", payload.FilemanagerID).
				Str("blurhash", blurhash).
				Msg("Generated BlurHash for uploaded thumbnail/image")
		}
	}

	if h.db != nil && payload.FilemanagerID > 0 {
		if blurhash != "" {
			_, _ = h.db.Exec(`
				UPDATE filemanagers 
				SET status = 1, blurhash = ?, updated_at = NOW() 
				WHERE id = ?
			`, blurhash, payload.FilemanagerID)
		} else {
			_, _ = h.db.Exec(`
				UPDATE filemanagers 
				SET status = 1, updated_at = NOW() 
				WHERE id = ?
			`, payload.FilemanagerID)
		}
	}

	return nil
}

func (h *MediaHandler) handleBackfill() error {
	if h.db == nil {
		return fmt.Errorf("database connection not available")
	}

	log.Info().Msg("Starting batch backfill of BlurHash for media items...")

	type itemToHash struct {
		table  string
		id     string
		name   string
		imgURL string
	}

	var items []itemToHash

	// 1. Entertainments
	rows, err := h.db.Query(`
		SELECT id, name, COALESCE(poster_url, thumbnail_url, poster_tv_url) as img_url 
		FROM entertainments 
		WHERE (blurhash IS NULL OR blurhash = '') 
		  AND COALESCE(poster_url, thumbnail_url, poster_tv_url) IS NOT NULL 
		  AND COALESCE(poster_url, thumbnail_url, poster_tv_url) != ''
	`)
	if err == nil {
		for rows.Next() {
			var it itemToHash
			it.table = "entertainments"
			if err := rows.Scan(&it.id, &it.name, &it.imgURL); err == nil && it.imgURL != "" {
				items = append(items, it)
			}
		}
		rows.Close()
	}

	// 2. Episodes
	epRows, err := h.db.Query(`
		SELECT id, name, COALESCE(poster_url, poster_tv_url) as img_url 
		FROM episodes 
		WHERE (blurhash IS NULL OR blurhash = '') 
		  AND COALESCE(poster_url, poster_tv_url) IS NOT NULL 
		  AND COALESCE(poster_url, poster_tv_url) != ''
	`)
	if err == nil {
		for epRows.Next() {
			var it itemToHash
			it.table = "episodes"
			if err := epRows.Scan(&it.id, &it.name, &it.imgURL); err == nil && it.imgURL != "" {
				items = append(items, it)
			}
		}
		epRows.Close()
	}

	// 3. Banners
	bRows, err := h.db.Query(`
		SELECT id, title, COALESCE(file_url, poster_url, poster_tv_url) as img_url 
		FROM banners 
		WHERE (blurhash IS NULL OR blurhash = '') 
		  AND COALESCE(file_url, poster_url, poster_tv_url) IS NOT NULL 
		  AND COALESCE(file_url, poster_url, poster_tv_url) != ''
	`)
	if err == nil {
		for bRows.Next() {
			var it itemToHash
			it.table = "banners"
			if err := bRows.Scan(&it.id, &it.name, &it.imgURL); err == nil && it.imgURL != "" {
				items = append(items, it)
			}
		}
		bRows.Close()
	}

	log.Info().Int("total_items_to_backfill", len(items)).Msg("Collected media items for BlurHash generation")

	count := 0
	for _, it := range items {
		hash, err := services.GenerateBlurHashFromSource(it.imgURL, h.storagePath, 4, 3)
		if err == nil && hash != "" {
			query := fmt.Sprintf("UPDATE %s SET blurhash = ? WHERE id = ?", it.table)
			_, _ = h.db.Exec(query, hash, it.id)
			count++
		}
	}

	log.Info().Int("completed", count).Msg("Batch BlurHash backfill finished")
	return nil
}
