package main

import (
	"context"
	"database/sql"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	_ "github.com/go-sql-driver/mysql"
	"github.com/robfig/cron/v3"
	"github.com/rs/zerolog"
	"github.com/rs/zerolog/log"

	"ott-worker/internal/broker"
	"ott-worker/internal/config"
	"ott-worker/internal/handlers"
	workerhealth "ott-worker/internal/health"
)

func main() {
	// 1. Setup Structured Logging
	zerolog.TimeFieldFormat = zerolog.TimeFormatUnix
	log.Logger = log.Output(zerolog.ConsoleWriter{Out: os.Stdout, TimeFormat: time.RFC3339})

	log.Info().Msg("Starting OTT Go Background Worker Service...")

	cfg, err := config.Load()
	if err != nil {
		log.Fatal().Err(err).Msg("Invalid worker configuration")
	}

	// 2. Database Connection
	var db *sql.DB
	if cfg.DBDSN != "" {
		db, err = sql.Open("mysql", cfg.DBDSN)
		if err != nil {
			log.Warn().Err(err).Msg("Database connection failed, running in broker-only mode")
		} else {
			db.SetMaxOpenConns(25)
			db.SetMaxIdleConns(5)
			db.SetConnMaxLifetime(5 * time.Minute)
			log.Info().Msg("MySQL database connection pool initialized")
		}
	}

	// 3. Broker Connection & Topology
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	b := broker.New(cfg.RabbitMQURL)
	if err := b.Connect(ctx); err != nil {
		log.Fatal().Err(err).Msg("Failed to connect to RabbitMQ broker")
	}
	defer b.Close()

	// 4. Initialize Handlers
	mediaH := handlers.NewMediaHandler(db, cfg.StoragePath, cfg.PrivateStoragePath)
	mediaH.SetTranscodeSettings(cfg.TranscodeHeights, cfg.TranscodeBitrates, cfg.H264Preset)
	notifyH := handlers.NewNotificationHandler()
	cronH := handlers.NewCronHandler(db)
	consumersReady := true

	// 5. Start Queue Consumers
	if err := b.Consume(ctx, broker.QueueMedia, cfg.MediaConcurrency, mediaH.Handle); err != nil {
		log.Error().Err(err).Msg("Failed to start media consumer")
		consumersReady = false
	}
	if err := b.Consume(ctx, broker.QueueNotifications, 16, notifyH.Handle); err != nil {
		log.Error().Err(err).Msg("Failed to start notification consumer")
		consumersReady = false
	}
	if err := b.Consume(ctx, broker.QueueCron, 2, cronH.Handle); err != nil {
		log.Error().Err(err).Msg("Failed to start cron consumer")
		consumersReady = false
	}

	// 6. Integrated Go Cron Scheduler (Option A: eliminates PHP cron overhead)
	c := cron.New()
	// Every minute: purge expired multi-device login sessions
	_, _ = c.AddFunc("* * * * *", func() {
		_ = cronH.Handle([]byte(`{"event":"cron.sessions","payload":{"task_name":"cron.sessions"}}`))
	})
	// Daily at 09:00: check expiring subscriptions
	_, _ = c.AddFunc("0 9 * * *", func() {
		_ = cronH.Handle([]byte(`{"event":"cron.subscriptions","payload":{"task_name":"cron.subscriptions"}}`))
	})
	c.Start()
	log.Info().Msg("Integrated Go scheduler started (session cleaner: every 1m, subscriptions: daily)")

	healthAddress := config.GetEnv("WORKER_HEALTH_ADDR", ":8081")
	healthServer := &http.Server{Addr: healthAddress, Handler: workerhealth.Handler(db, b, cfg.RedisAddr, consumersReady), ReadHeaderTimeout: 2 * time.Second}
	go func() {
		if err := healthServer.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Error().Err(err).Msg("Worker health server stopped")
		}
	}()

	// 7. Wait for Termination Signals
	sigCh := make(chan os.Signal, 1)
	signal.Notify(sigCh, os.Interrupt, syscall.SIGTERM)

	sig := <-sigCh
	log.Info().Str("signal", sig.String()).Msg("Received termination signal, shutting down gracefully...")

	c.Stop()
	cancel()
	shutdownCtx, shutdownCancel := context.WithTimeout(context.Background(), 2*time.Second)
	defer shutdownCancel()
	_ = healthServer.Shutdown(shutdownCtx)
	time.Sleep(500 * time.Millisecond)

	log.Info().Msg("OTT Go Background Worker Service exited cleanly.")
}
