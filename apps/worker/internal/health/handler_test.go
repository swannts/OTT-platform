package health

import (
	"net/http/httptest"
	"strings"
	"testing"
)

type broker struct{ healthy bool }

func (b broker) IsHealthy() bool { return b.healthy }

func TestHandlerReportsBrokerAndProcessHealth(t *testing.T) {
	response := httptest.NewRecorder()
	Handler(nil, broker{healthy: true}, "", true).ServeHTTP(response, httptest.NewRequest("GET", "/health", nil))
	if response.Code != 200 || !strings.Contains(response.Body.String(), `"rabbitmq":"ok"`) || !strings.Contains(response.Body.String(), `"consumers":"ok"`) || !strings.Contains(response.Body.String(), `"database":"not_configured"`) {
		t.Fatalf("unexpected health response: status=%d body=%s", response.Code, response.Body.String())
	}

	response = httptest.NewRecorder()
	Handler(nil, broker{healthy: false}, "", false).ServeHTTP(response, httptest.NewRequest("GET", "/health", nil))
	if response.Code != 503 || !strings.Contains(response.Body.String(), `"status":"degraded"`) {
		t.Fatalf("unexpected degraded response: status=%d body=%s", response.Code, response.Body.String())
	}
}
