package config

import "testing"

func TestLoadMediaConcurrency(t *testing.T) {
	tests := []struct {
		name  string
		value string
		want  int
	}{
		{name: "default", want: 4},
		{name: "single media job", value: "1", want: 1},
		{name: "maximum", value: "4", want: 4},
		{name: "zero", value: "0"},
		{name: "above maximum", value: "5"},
		{name: "non-numeric", value: "invalid"},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Setenv("WORKER_MEDIA_CONCURRENCY", tt.value)
			cfg, err := Load()
			if tt.want == 0 {
				if err == nil {
					t.Fatal("expected invalid media concurrency to fail")
				}
				return
			}
			if err != nil {
				t.Fatal(err)
			}
			if cfg.MediaConcurrency != tt.want {
				t.Errorf("media concurrency = %d; want %d", cfg.MediaConcurrency, tt.want)
			}
		})
	}
}
