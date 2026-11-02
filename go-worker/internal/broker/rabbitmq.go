package broker

import (
	"context"
	"fmt"
	"time"

	amqp "github.com/rabbitmq/amqp091-go"
	"github.com/rs/zerolog/log"
)

const (
	ExchangeEvents     = "ott.events"
	ExchangeDeadLetter = "ott.dlx"

	QueueMedia         = "ott.media.processing"
	QueueNotifications = "ott.notifications"
	QueueImport        = "ott.data.import"
	QueueCron          = "ott.cron.maintenance"
	QueueDeadLetter    = "ott.dead-letter"
)

type Broker struct {
	url  string
	conn *amqp.Connection
	ch   *amqp.Channel
}

func New(url string) *Broker {
	return &Broker{url: url}
}

// Connect establishes connection with automatic retry
func (b *Broker) Connect(ctx context.Context) error {
	var err error
	for i := 0; i < 15; i++ {
		b.conn, err = amqp.Dial(b.url)
		if err == nil {
			log.Info().Msg("Connected to RabbitMQ successfully")
			break
		}
		log.Warn().Err(err).Int("attempt", i+1).Msg("Failed to connect to RabbitMQ, retrying in 3s...")
		select {
		case <-ctx.Done():
			return ctx.Err()
		case <-time.After(3 * time.Second):
		}
	}
	if err != nil {
		return fmt.Errorf("could not connect to rabbitmq: %w", err)
	}

	b.ch, err = b.conn.Channel()
	if err != nil {
		return fmt.Errorf("could not open channel: %w", err)
	}

	return b.setupTopology()
}

// setupTopology declares exchanges, queues and bindings with Dead-Letter Exchange
func (b *Broker) setupTopology() error {
	// 1. Declare Primary Topic Exchange
	err := b.ch.ExchangeDeclare(
		ExchangeEvents,
		"topic",
		true,  // durable
		false, // auto-deleted
		false, // internal
		false, // no-wait
		nil,
	)
	if err != nil {
		return fmt.Errorf("exchange declare %s: %w", ExchangeEvents, err)
	}

	// 2. Declare Dead Letter Exchange
	err = b.ch.ExchangeDeclare(
		ExchangeDeadLetter,
		"topic",
		true,
		false,
		false,
		false,
		nil,
	)
	if err != nil {
		return fmt.Errorf("exchange declare %s: %w", ExchangeDeadLetter, err)
	}

	// 3. Declare Dead Letter Queue
	_, err = b.ch.QueueDeclare(
		QueueDeadLetter,
		true,
		false,
		false,
		false,
		nil,
	)
	if err != nil {
		return fmt.Errorf("queue declare %s: %w", QueueDeadLetter, err)
	}
	_ = b.ch.QueueBind(QueueDeadLetter, "#", ExchangeDeadLetter, false, nil)

	// DLX arguments for worker queues
	dlxArgs := amqp.Table{
		"x-dead-letter-exchange": ExchangeDeadLetter,
	}

	queues := []struct {
		name       string
		routingKey string
	}{
		{QueueMedia, "media.#"},
		{QueueNotifications, "notify.#"},
		{QueueImport, "import.#"},
		{QueueCron, "cron.#"},
	}

	for _, q := range queues {
		_, err := b.ch.QueueDeclare(
			q.name,
			true,  // durable
			false, // auto-delete
			false, // exclusive
			false, // no-wait
			dlxArgs,
		)
		if err != nil {
			return fmt.Errorf("queue declare %s: %w", q.name, err)
		}

		err = b.ch.QueueBind(
			q.name,
			q.routingKey,
			ExchangeEvents,
			false,
			nil,
		)
		if err != nil {
			return fmt.Errorf("queue bind %s to %s: %w", q.name, q.routingKey, err)
		}
	}

	log.Info().Msg("RabbitMQ topology initialized (exchanges, queues, DLX configured)")
	return nil
}

// Consume starts consuming messages from a queue with a concurrency worker pool
func (b *Broker) Consume(ctx context.Context, queue string, concurrency int, handler func([]byte) error) error {
	ch, err := b.conn.Channel()
	if err != nil {
		return fmt.Errorf("failed to open channel for %s: %w", queue, err)
	}
	// Fair dispatch prefetch
	_ = ch.Qos(concurrency*2, 0, false)

	msgs, err := ch.Consume(
		queue,
		"",    // consumer tag
		false, // auto-ack (manual ack for at-least-once reliability)
		false, // exclusive
		false, // no-local
		false, // no-wait
		nil,
	)
	if err != nil {
		return fmt.Errorf("failed to register consumer for %s: %w", queue, err)
	}

	log.Info().Str("queue", queue).Int("concurrency", concurrency).Msg("Started queue consumer")

	for i := 0; i < concurrency; i++ {
		go func(workerID int) {
			for {
				select {
				case <-ctx.Done():
					return
				case d, ok := <-msgs:
					if !ok {
						return
					}
					if err := handler(d.Body); err != nil {
						log.Error().Err(err).Str("queue", queue).Int("worker", workerID).Msg("Handler error, rejecting message to DLX")
						_ = d.Nack(false, false) // Send to DLX
					} else {
						_ = d.Ack(false)
					}
				}
			}
		}(i)
	}

	return nil
}

// Close closes channel and connection
func (b *Broker) Close() {
	if b.ch != nil {
		_ = b.ch.Close()
	}
	if b.conn != nil {
		_ = b.conn.Close()
	}
}

