package handlers

import (
	"encoding/json"
	"path/filepath"
	"testing"
)

func TestSafeMediaPathConstrainsSourcesAndOutputsToPrivateRoot(t *testing.T) {
	root := t.TempDir()
	tests := []struct {
		name    string
		path    string
		wantErr bool
	}{
		{name: "relative media key", path: "media/originals/42/source.mp4"},
		{name: "parent traversal", path: "../../etc/passwd", wantErr: true},
		{name: "absolute path", path: filepath.Join(root, "media", "source.mp4"), wantErr: true},
	}
	for _, test := range tests {
		t.Run(test.name, func(t *testing.T) {
			got, err := safeMediaPath(root, test.path)
			if (err != nil) != test.wantErr {
				t.Fatalf("safeMediaPath() error = %v, wantErr %v", err, test.wantErr)
			}
			if err == nil {
				rel, relErr := filepath.Rel(root, got)
				if relErr != nil || rel == ".." || len(rel) >= 3 && rel[:3] == ".."+string(filepath.Separator) {
					t.Fatalf("resolved path %q is outside root %q", got, root)
				}
			}
		})
	}
}

func TestTranscodeRequestRejectsMissingIdsBeforeDatabaseAccess(t *testing.T) {
	handler := NewMediaHandler(nil, "", "")
	if err := handler.handleTranscode(json.RawMessage(`{"media_asset_id":1}`)); err == nil {
		t.Fatal("expected missing job id/database to fail")
	}
}

func TestUnknownMediaEventsAreNotSilentlyAcknowledged(t *testing.T) {
	handler := NewMediaHandler(nil, "", "")
	if err := handler.Handle([]byte(`{"event":"media.unknown","payload":{}}`)); err == nil {
		t.Fatal("expected unsupported media event to fail")
	}
}
