package health

import (
	"context"
	"database/sql"
	"encoding/json"
	"net"
	"net/http"
	"time"
)

type BrokerStatus interface{ IsHealthy() bool }

func Handler(db *sql.DB, broker BrokerStatus, redisAddress string, consumersReady bool) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		checks := map[string]string{"process": "ok", "rabbitmq": "unavailable", "redis": "not_configured"}
		if broker != nil && broker.IsHealthy() {
			checks["rabbitmq"] = "ok"
		}
		if !consumersReady {
			checks["consumers"] = "unavailable"
		} else {
			checks["consumers"] = "ok"
		}
		if redisAddress != "" {
			connection, err := net.DialTimeout("tcp", redisAddress, 500*time.Millisecond)
			if err == nil {
				checks["redis"] = "ok"
				_ = connection.Close()
			} else {
				checks["redis"] = "unavailable"
			}
		}
		if db == nil {
			checks["database"] = "not_configured"
		} else {
			pingCtx, stop := context.WithTimeout(context.Background(), 500*time.Millisecond)
			defer stop()
			if db.PingContext(pingCtx) == nil {
				checks["database"] = "ok"
			} else {
				checks["database"] = "unavailable"
			}
		}
		status := http.StatusOK
		for _, value := range checks {
			if value == "unavailable" {
				status = http.StatusServiceUnavailable
				break
			}
		}
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(status)
		_ = json.NewEncoder(w).Encode(map[string]any{"status": map[bool]string{true: "ok", false: "degraded"}[status == http.StatusOK], "checks": checks, "checked_at": time.Now().UTC()})
	})
}
