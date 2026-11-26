package config

import (
	"fmt"
	"os"
	"strconv"
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
}

func Load() (*Config, error) {
	mediaConcurrency, err := strconv.Atoi(getEnv("WORKER_MEDIA_CONCURRENCY", "4"))
	if err != nil || mediaConcurrency < 1 || mediaConcurrency > maxMediaConcurrency {
		return nil, fmt.Errorf("WORKER_MEDIA_CONCURRENCY must be an integer from 1 to %d", maxMediaConcurrency)
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
	}, nil
}

func getEnv(key, defaultVal string) string {
	if val := os.Getenv(key); val != "" {
		return val
	}
	return defaultVal
}

func GetEnv(key, defaultVal string) string { return getEnv(key, defaultVal) }
