package config

import (
	"os"
)

type Config struct {
	AppEnv         string
	RabbitMQURL    string
	DBDSN          string
	RedisAddr      string
	StorageDriver  string
	StoragePath    string
}

func Load() *Config {
	return &Config{
		AppEnv:        getEnv("APP_ENV", "development"),
		RabbitMQURL:   getEnv("RABBITMQ_URL", "amqp://guest:guest@rabbitmq:5672/"),
		DBDSN:         getEnv("DB_DSN", "root:@tcp(mysql:3306)/swann?charset=utf8mb4&parseTime=True&loc=Local"),
		RedisAddr:     getEnv("REDIS_ADDR", "redis:6379"),
		StorageDriver: getEnv("STORAGE_DRIVER", "local"),
		StoragePath:   getEnv("STORAGE_LOCAL_PATH", "/var/www/html/public/storage"),
	}
}

func getEnv(key, defaultVal string) string {
	if val := os.Getenv(key); val != "" {
		return val
	}
	return defaultVal
}

