package handlers

import (
	"context"
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"

	"github.com/rs/zerolog/log"
)

type transcodeRequest struct {
	AssetID int64 `json:"media_asset_id"`
	JobID   int64 `json:"job_id"`
}

type mediaTrack struct {
	ID       int64
	Kind     string
	Language string
	Path     string
}

type probeResult struct {
	Format struct {
		Duration string `json:"duration"`
		Format   string `json:"format_name"`
	} `json:"format"`
	Streams []struct {
		Index     int    `json:"index"`
		CodecType string `json:"codec_type"`
		CodecName string `json:"codec_name"`
		Width     int    `json:"width"`
		Height    int    `json:"height"`
		Language  string `json:"language"`
	} `json:"streams"`
}

func (h *MediaHandler) handleTranscode(payload json.RawMessage) error {
	var request transcodeRequest
	if err := json.Unmarshal(payload, &request); err != nil {
		return fmt.Errorf("decode transcode request: %w", err)
	}
	if h.db == nil || request.AssetID <= 0 || request.JobID <= 0 {
		return errors.New("transcode request requires a database, asset id, and job id")
	}

	var sourcePath, disk, status string
	err := h.db.QueryRow(`
		SELECT a.source_path, a.disk, j.status
		FROM media_processing_jobs j
		JOIN media_assets a ON a.id = j.media_asset_id
		WHERE j.id = ? AND a.id = ? AND j.type = 'transcode' AND a.deleted_at IS NULL
	`, request.JobID, request.AssetID).Scan(&sourcePath, &disk, &status)
	if err != nil {
		return fmt.Errorf("load transcode job: %w", err)
	}
	if status == "completed" || status == "failed" {
		return nil // at-least-once deliveries are idempotent
	}
	if status != "queued" && status != "processing" {
		return nil // another worker owns the active attempt
	}
	processingTokenBytes := make([]byte, 16)
	if _, err := rand.Read(processingTokenBytes); err != nil {
		return fmt.Errorf("create transcode ownership token: %w", err)
	}
	processingToken := hex.EncodeToString(processingTokenBytes)

	result, err := h.db.Exec(`
		UPDATE media_processing_jobs SET status = 'processing', processing_token = ?, attempts = attempts + 1,
			started_at = NOW(), failure_code = NULL, updated_at = NOW()
		WHERE id = ? AND (status = 'queued' OR (status = 'processing' AND updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)))
	`, processingToken, request.JobID)
	if err != nil {
		return fmt.Errorf("claim transcode job: %w", err)
	}
	claimed, _ := result.RowsAffected()
	if claimed != 1 {
		return nil
	}
	if _, err := h.db.Exec(`UPDATE media_assets SET status = 'processing', failure_code = NULL, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL`, request.AssetID); err != nil {
		return fmt.Errorf("mark media asset processing: %w", err)
	}

	outputDir := ""
	failure := func(code string, cause error) error {
		if outputDir != "" {
			_ = os.RemoveAll(outputDir)
		}
		result, updateErr := h.db.Exec(`
			UPDATE media_processing_jobs SET status = 'failed', processing_token = NULL, failure_code = ?, completed_at = NOW(), updated_at = NOW()
			WHERE id = ? AND status = 'processing' AND processing_token = ?
		`, code, request.JobID, processingToken)
		if updateErr != nil {
			return fmt.Errorf("mark failed transcode: %w (processing error: %v)", updateErr, cause)
		}
		owned, _ := result.RowsAffected()
		if owned != 1 {
			return nil // lease was reclaimed; this worker no longer owns the job
		}
		_, updateErr = h.db.Exec(`UPDATE media_assets SET status = 'failed', failure_code = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL`, code, request.AssetID)
		if updateErr != nil {
			return fmt.Errorf("mark failed media asset: %w", updateErr)
		}
		_, updateErr = h.db.Exec(`INSERT INTO admin_audit_logs (actor_id, action, resource_type, resource_id, request_id, after_values, outcome, created_at) VALUES (NULL, 'media.transcode.failed', ?, ?, ?, ?, 'failure', NOW())`, "App\\Models\\MediaAsset", fmt.Sprint(request.AssetID), fmt.Sprintf("worker-job-%d", request.JobID), fmt.Sprintf(`{"failure_code":%q}`, code))
		if updateErr != nil {
			return fmt.Errorf("record failed transcode audit: %w", updateErr)
		}
		log.Error().Err(cause).Int64("media_asset_id", request.AssetID).Int64("job_id", request.JobID).Msg("Media transcoding failed")
		return nil
	}

	if disk != "private" {
		return failure("unsupported_storage", errors.New("transcoding requires the private local storage disk"))
	}
	source, err := safeMediaPath(h.privateStoragePath, sourcePath)
	if err != nil {
		return failure("invalid_source_path", err)
	}
	info, err := os.Stat(source)
	if err != nil || !info.Mode().IsRegular() {
		return failure("source_unavailable", errors.New("private media source is unavailable"))
	}

	ctx, cancel := context.WithTimeout(context.Background(), 6*time.Hour)
	defer cancel()
	leaseDone := make(chan struct{})
	go func() {
		defer close(leaseDone)
		ticker := time.NewTicker(time.Minute)
		defer ticker.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-ticker.C:
				if _, heartbeatErr := h.db.Exec(`UPDATE media_processing_jobs SET updated_at = NOW() WHERE id = ? AND status = 'processing' AND processing_token = ?`, request.JobID, processingToken); heartbeatErr != nil {
					log.Error().Err(heartbeatErr).Int64("job_id", request.JobID).Msg("Media processing lease heartbeat failed")
				}
			}
		}
	}()
	defer func() { cancel(); <-leaseDone }()
	metadata, err := probeMedia(ctx, source)
	if err != nil {
		return failure("probe_failed", err)
	}
	if metadata.Format.Duration == "" || len(metadata.Streams) == 0 {
		return failure("invalid_media_metadata", errors.New("media source has no valid streams or duration"))
	}
	tracks, err := h.loadTracks(request.AssetID)
	if err != nil {
		return failure("track_load_failed", err)
	}
	for index := range tracks {
		tracks[index].Path, err = safeMediaPath(h.privateStoragePath, tracks[index].Path)
		if err != nil {
			return failure("invalid_track_path", err)
		}
		info, statErr := os.Stat(tracks[index].Path)
		if statErr != nil || !info.Mode().IsRegular() {
			return failure("track_unavailable", errors.New("a selected media track is unavailable"))
		}
	}
	outputRel := filepath.ToSlash(filepath.Join("media", "renditions", fmt.Sprint(request.AssetID), fmt.Sprint(request.JobID), processingToken))
	outputDir, err = safeMediaPath(h.privateStoragePath, outputRel)
	if err != nil {
		return failure("invalid_output_path", err)
	}
	if err := os.MkdirAll(outputDir, 0750); err != nil {
		return failure("output_unavailable", err)
	}
	if err := transcodeHLS(ctx, source, outputDir, metadata, tracks); err != nil {
		return failure("hls_packaging_failed", err)
	}
	if err := transcodeDASH(ctx, source, outputDir, metadata, tracks); err != nil {
		return failure("dash_packaging_failed", err)
	}
	if err := makePoster(ctx, source, outputDir); err != nil {
		return failure("poster_generation_failed", err)
	}
	encoded, err := json.Marshal(metadata)
	if err != nil {
		return failure("metadata_encoding_failed", err)
	}
	tx, err := h.db.Begin()
	if err != nil {
		return fmt.Errorf("begin media completion transaction: %w", err)
	}
	if _, err := tx.Exec(`
		UPDATE media_assets SET status = 'ready', metadata = ?, rendition_path = ?, ready_at = NOW(), failure_code = NULL, updated_at = NOW()
		WHERE id = ? AND status = 'processing' AND deleted_at IS NULL
	`, encoded, outputRel, request.AssetID); err != nil {
		_ = tx.Rollback()
		return fmt.Errorf("store media metadata: %w", err)
	}
	result, err = tx.Exec(`
		UPDATE media_processing_jobs SET status = 'completed', processing_token = NULL, completed_at = NOW(), failure_code = NULL, updated_at = NOW()
		WHERE id = ? AND status = 'processing' AND processing_token = ?
	`, request.JobID, processingToken)
	if err != nil {
		_ = tx.Rollback()
		return fmt.Errorf("complete media job: %w", err)
	}
	owned, _ := result.RowsAffected()
	if owned != 1 {
		_ = tx.Rollback()
		_ = os.RemoveAll(outputDir)
		return nil // a reclaimed job must not be completed by its previous worker
	}
	if _, err := tx.Exec(`INSERT INTO admin_audit_logs (actor_id, action, resource_type, resource_id, request_id, after_values, outcome, created_at) VALUES (NULL, 'media.transcode.completed', ?, ?, ?, ?, 'success', NOW())`, "App\\Models\\MediaAsset", fmt.Sprint(request.AssetID), fmt.Sprintf("worker-job-%d", request.JobID), `{"status":"ready"}`); err != nil {
		_ = tx.Rollback()
		return fmt.Errorf("record completed transcode audit: %w", err)
	}
	if err := tx.Commit(); err != nil {
		return fmt.Errorf("commit media processing state: %w", err)
	}
	return nil
}

func (h *MediaHandler) loadTracks(assetID int64) ([]mediaTrack, error) {
	rows, err := h.db.Query(`SELECT id, kind, language, path FROM media_asset_tracks WHERE media_asset_id = ? AND deleted_at IS NULL ORDER BY id`, assetID)
	if err != nil {
		return nil, fmt.Errorf("query media asset tracks: %w", err)
	}
	defer rows.Close()

	tracks := make([]mediaTrack, 0, 8)
	for rows.Next() {
		var track mediaTrack
		if err := rows.Scan(&track.ID, &track.Kind, &track.Language, &track.Path); err != nil {
			return nil, fmt.Errorf("read media asset track: %w", err)
		}
		if track.Kind != "audio" && track.Kind != "subtitle" {
			return nil, errors.New("media asset contains an unsupported track type")
		}
		tracks = append(tracks, track)
	}
	return tracks, rows.Err()
}

func safeMediaPath(root, relative string) (string, error) {
	if root == "" || relative == "" || filepath.IsAbs(relative) {
		return "", errors.New("media path must be relative to the configured private storage root")
	}
	clean := filepath.Clean(filepath.FromSlash(relative))
	if clean == "." || clean == ".." || strings.HasPrefix(clean, ".."+string(filepath.Separator)) {
		return "", errors.New("media path escapes the configured private storage root")
	}
	rootAbs, err := filepath.Abs(root)
	if err != nil {
		return "", err
	}
	pathAbs, err := filepath.Abs(filepath.Join(rootAbs, clean))
	if err != nil {
		return "", err
	}
	rel, err := filepath.Rel(rootAbs, pathAbs)
	if err != nil || rel == ".." || strings.HasPrefix(rel, ".."+string(filepath.Separator)) {
		return "", errors.New("media path escapes the configured private storage root")
	}
	return pathAbs, nil
}

func probeMedia(ctx context.Context, source string) (probeResult, error) {
	var result probeResult
	ctx, cancel := context.WithTimeout(ctx, 2*time.Minute)
	defer cancel()
	cmd := exec.CommandContext(ctx, "ffprobe", "-v", "error", "-show_entries", "format=duration,format_name:stream=index,codec_type,codec_name,width,height:stream_tags=language", "-of", "json", source)
	output, err := cmd.Output()
	if err != nil {
		return result, fmt.Errorf("ffprobe failed: %w", err)
	}
	if err := json.Unmarshal(output, &result); err != nil {
		return result, fmt.Errorf("decode ffprobe metadata: %w", err)
	}
	return result, nil
}

func transcodeHLS(ctx context.Context, source, outputDir string, metadata probeResult, tracks []mediaTrack) error {
	maxHeight := 0
	for _, stream := range metadata.Streams {
		if stream.CodecType == "video" && stream.Height > maxHeight {
			maxHeight = stream.Height
		}
	}
	if maxHeight < 1 {
		return errors.New("source has no video stream")
	}
	qualities := []struct{ height, bandwidth int }{{480, 900000}, {720, 2800000}, {1080, 5000000}}
	selected := make([]struct{ height, bandwidth int }, 0, len(qualities))
	for _, quality := range qualities {
		if quality.height <= maxHeight {
			selected = append(selected, quality)
		}
	}
	if len(selected) == 0 {
		selected = append(selected, struct{ height, bandwidth int }{maxHeight, 450000})
	}
	audioNames := make([]string, 0, len(tracks)+len(metadata.Streams))
	subtitleNames := make([]string, 0, len(tracks))
	for _, stream := range metadata.Streams {
		if stream.CodecType != "audio" {
			continue
		}
		name := fmt.Sprintf("audio-source-%d", stream.Index)
		if err := packageHLSAudio(ctx, source, filepath.Join(outputDir, name+".m3u8"), filepath.Join(outputDir, name+"_%05d.ts"), fmt.Sprintf("0:%d", stream.Index)); err != nil {
			return fmt.Errorf("package source audio stream: %w", err)
		}
		audioNames = append(audioNames, name)
	}
	for _, track := range tracks {
		name := fmt.Sprintf("track-%d", track.ID)
		switch track.Kind {
		case "audio":
			if err := packageHLSAudio(ctx, track.Path, filepath.Join(outputDir, name+".m3u8"), filepath.Join(outputDir, name+"_%05d.ts"), "0:a:0"); err != nil {
				return fmt.Errorf("package external audio track: %w", err)
			}
			audioNames = append(audioNames, name)
		case "subtitle":
			playlist := filepath.Join(outputDir, name+".m3u8")
			segments := filepath.Join(outputDir, name+"_%05d.vtt")
			if err := runMediaCommand(ctx, "ffmpeg", "-hide_banner", "-loglevel", "error", "-nostdin", "-y", "-i", track.Path, "-map", "0:s:0", "-c:s", "webvtt", "-f", "hls", "-hls_time", "6", "-hls_playlist_type", "vod", "-hls_segment_filename", segments, playlist); err != nil {
				return fmt.Errorf("package subtitle track: %w", err)
			}
			subtitleNames = append(subtitleNames, name)
		}
	}

	master := []string{"#EXTM3U", "#EXT-X-VERSION:3"}
	for index, name := range audioNames {
		language := "und"
		if index < len(metadata.Streams) {
			for _, stream := range metadata.Streams {
				if stream.CodecType == "audio" && fmt.Sprintf("audio-source-%d", stream.Index) == name && stream.Language != "" {
					language = stream.Language
					break
				}
			}
		}
		for _, track := range tracks {
			if track.Kind == "audio" && fmt.Sprintf("track-%d", track.ID) == name {
				language = track.Language
				break
			}
		}
		defaultAttr := "NO"
		if index == 0 {
			defaultAttr = "YES"
		}
		master = append(master, fmt.Sprintf("#EXT-X-MEDIA:TYPE=AUDIO,GROUP-ID=\"audios\",NAME=\"%s\",LANGUAGE=\"%s\",DEFAULT=%s,AUTOSELECT=YES,URI=\"%s.m3u8\"", language, language, defaultAttr, name))
	}
	for _, name := range subtitleNames {
		language := "und"
		for _, track := range tracks {
			if track.Kind == "subtitle" && fmt.Sprintf("track-%d", track.ID) == name {
				language = track.Language
				break
			}
		}
		master = append(master, fmt.Sprintf("#EXT-X-MEDIA:TYPE=SUBTITLES,GROUP-ID=\"subs\",NAME=\"%s\",LANGUAGE=\"%s\",DEFAULT=NO,AUTOSELECT=YES,URI=\"%s.m3u8\"", language, language, name))
	}
	for _, quality := range selected {
		name := fmt.Sprintf("%dp", quality.height)
		playlist := filepath.Join(outputDir, name+".m3u8")
		segments := filepath.Join(outputDir, name+"_%05d.ts")
		filter := fmt.Sprintf("scale=-2:%d:force_original_aspect_ratio=decrease", quality.height)
		args := []string{"-hide_banner", "-loglevel", "error", "-nostdin", "-y", "-i", source, "-map", "0:v:0", "-an", "-vf", filter, "-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p", "-b:v", fmt.Sprintf("%dk", quality.bandwidth/1000), "-f", "hls", "-hls_time", "6", "-hls_playlist_type", "vod", "-hls_segment_filename", segments, playlist}
		if err := runMediaCommand(ctx, "ffmpeg", args...); err != nil {
			return fmt.Errorf("encode %s rendition: %w", name, err)
		}
		sourceWidth, sourceHeight := 16, 9
		for _, stream := range metadata.Streams {
			if stream.CodecType == "video" && stream.Width > 0 && stream.Height > 0 {
				sourceWidth, sourceHeight = stream.Width, stream.Height
				break
			}
		}
		width := quality.height * sourceWidth / sourceHeight
		width -= width % 2
		attributes := fmt.Sprintf("BANDWIDTH=%d,RESOLUTION=%dx%d", quality.bandwidth, width, quality.height)
		if len(audioNames) > 0 {
			attributes += ",AUDIO=\"audios\""
		}
		if len(subtitleNames) > 0 {
			attributes += ",SUBTITLES=\"subs\""
		}
		master = append(master, "#EXT-X-STREAM-INF:"+attributes, name+".m3u8")
	}
	return os.WriteFile(filepath.Join(outputDir, "master.m3u8"), []byte(strings.Join(master, "\n")+"\n"), 0640)
}

func packageHLSAudio(ctx context.Context, source, playlist, segments, streamMap string) error {
	return runMediaCommand(ctx, "ffmpeg", "-hide_banner", "-loglevel", "error", "-nostdin", "-y", "-i", source, "-map", streamMap, "-vn", "-c:a", "aac", "-b:a", "128k", "-f", "hls", "-hls_time", "6", "-hls_playlist_type", "vod", "-hls_segment_filename", segments, playlist)
}

func transcodeDASH(ctx context.Context, source, outputDir string, metadata probeResult, tracks []mediaTrack) error {
	args := []string{"-hide_banner", "-loglevel", "error", "-nostdin", "-y", "-i", source}
	type externalAudioInput struct {
		index    int
		language string
	}
	audioInputs := make([]externalAudioInput, 0, len(tracks))
	inputIndex := 1
	for _, track := range tracks {
		if track.Kind != "audio" {
			continue
		}
		args = append(args, "-i", track.Path)
		audioInputs = append(audioInputs, externalAudioInput{index: inputIndex, language: track.Language})
		inputIndex++
	}
	args = append(args, "-map", "0:v:0", "-map", "0:a?", "-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p", "-c:a", "aac")
	sourceAudioCount := 0
	for _, stream := range metadata.Streams {
		if stream.CodecType == "audio" {
			sourceAudioCount++
		}
	}
	for i, audio := range audioInputs {
		args = append(args, "-map", fmt.Sprintf("%d:a:0", audio.index), fmt.Sprintf("-metadata:s:a:%d", sourceAudioCount+i), "language="+audio.language)
	}
	args = append(args, "-f", "dash", "-seg_duration", "6", filepath.Join(outputDir, "manifest.mpd"))
	return runMediaCommand(ctx, "ffmpeg", args...)
}

func makePoster(ctx context.Context, source, outputDir string) error {
	return runMediaCommand(ctx, "ffmpeg", "-hide_banner", "-loglevel", "error", "-nostdin", "-y", "-ss", "2", "-i", source, "-frames:v", "1", "-vf", "scale=640:-2", filepath.Join(outputDir, "poster.jpg"))
}

func runMediaCommand(parent context.Context, binary string, args ...string) error {
	ctx, cancel := context.WithTimeout(parent, 4*time.Hour)
	defer cancel()
	cmd := exec.CommandContext(ctx, binary, args...)
	cmd.Stdout = nil
	cmd.Stderr = nil // avoid logging source URLs, metadata, or provider credentials
	if err := cmd.Run(); err != nil {
		return fmt.Errorf("%s process failed: %w", binary, err)
	}
	return nil
}
