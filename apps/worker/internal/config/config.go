package config

import (
	"fmt"
	"os"
	"strconv"
	"strings"
)

const maxMediaConcurrency = 4

type Config struct {
	MediaConcurrency   int
	AppEnv             string
	RabbitMQURL        string
	DBDSN              string
	RedisAddr          string
	StorageDriver      string
	StoragePath        string
	PrivateStoragePath string
	TranscodeHeights   []int
	TranscodeBitrates  map[int]int
	H264Preset         string
}

func Load() (*Config, error) {
	mediaConcurrency, err := strconv.Atoi(getEnv("WORKER_MEDIA_CONCURRENCY", "4"))
	if err != nil || mediaConcurrency < 1 || mediaConcurrency > maxMediaConcurrency {
		return nil, fmt.Errorf("WORKER_MEDIA_CONCURRENCY must be an integer from 1 to %d", maxMediaConcurrency)
	}
	heights, err := parseTranscodeHeights(getEnv("WORKER_TRANSCODE_HEIGHTS", "360,720,1080"))
	if err != nil {
		return nil, err
	}
	bitrates, err := parseTranscodeBitrates(getEnv("WORKER_TRANSCODE_BITRATES", "240=400,360=800,480=1400,720=2800,1080=5000,1440=10000,2160=20000"))
	if err != nil {
		return nil, err
	}
	preset := getEnv("WORKER_H264_PRESET", "veryfast")
	if !contains([]string{"ultrafast", "superfast", "veryfast", "faster", "fast", "medium", "slow"}, preset) {
		return nil, fmt.Errorf("WORKER_H264_PRESET is not an allowed H.264 encoder preset")
	}

	return &Config{
		MediaConcurrency:   mediaConcurrency,
		AppEnv:             getEnv("APP_ENV", "development"),
		RabbitMQURL:        getEnv("RABBITMQ_URL", "amqp://guest:guest@rabbitmq:5672/"),
		DBDSN:              getEnv("DB_DSN", "root:@tcp(mysql:3306)/swann?charset=utf8mb4&parseTime=True&loc=Local"),
		RedisAddr:          getEnv("REDIS_ADDR", "redis:6379"),
		StorageDriver:      getEnv("STORAGE_DRIVER", "local"),
		StoragePath:        getEnv("STORAGE_LOCAL_PATH", "/var/www/html/public/storage"),
		PrivateStoragePath: getEnv("PRIVATE_STORAGE_PATH", "/var/www/html/storage/app/private"),
		TranscodeHeights:   heights,
		TranscodeBitrates:  bitrates,
		H264Preset:         preset,
	}, nil
}

func parseTranscodeHeights(value string) ([]int, error) {
	allowed := map[int]bool{240: true, 360: true, 480: true, 720: true, 1080: true, 1440: true, 2160: true}
	seen := map[int]bool{}
	var heights []int
	for _, part := range strings.Split(value, ",") {
		height, err := strconv.Atoi(strings.TrimSpace(part))
		if err != nil || !allowed[height] || seen[height] {
			return nil, fmt.Errorf("WORKER_TRANSCODE_HEIGHTS must contain unique supported heights")
		}
		seen[height] = true
		heights = append(heights, height)
	}
	if len(heights) == 0 {
		return nil, fmt.Errorf("WORKER_TRANSCODE_HEIGHTS must not be empty")
	}
	return heights, nil
}

func parseTranscodeBitrates(value string) (map[int]int, error) {
	bitrates := make(map[int]int)
	for _, part := range strings.Split(value, ",") {
		pair := strings.SplitN(strings.TrimSpace(part), "=", 2)
		if len(pair) != 2 {
			return nil, fmt.Errorf("WORKER_TRANSCODE_BITRATES must use height=kbps entries")
		}
		height, heightErr := strconv.Atoi(strings.TrimSpace(pair[0]))
		bitrate, bitrateErr := strconv.Atoi(strings.TrimSpace(pair[1]))
		if heightErr != nil || bitrateErr != nil || height < 1 || bitrate < 100 || bitrate > 100000 {
			return nil, fmt.Errorf("WORKER_TRANSCODE_BITRATES contains an invalid height or bitrate")
		}
		bitrates[height] = bitrate
	}
	return bitrates, nil
}

func contains(values []string, value string) bool {
	for _, candidate := range values {
		if candidate == value {
			return true
		}
	}
	return false
}

func getEnv(key, defaultVal string) string {
	if val := os.Getenv(key); val != "" {
		return val
	}
	return defaultVal
}

func GetEnv(key, defaultVal string) string { return getEnv(key, defaultVal) }
