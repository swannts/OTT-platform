import 'package:flutter_test/flutter_test.dart';
import 'package:swann_ott/screens/auth/model/login_response.dart';
import 'package:swann_ott/screens/content/model/playback_authorization.dart';

void main() {
  group('PlaybackAuthorization', () {
    test('parses the server session and signed quality URLs', () {
      final authorization = PlaybackAuthorization.fromJson({
        'playback_session': {
          'session_id': 'session-123',
          'expires_at': '2026-12-01T12:00:00Z',
          'media_url': 'https://media.example.test/signed/master.m3u8',
        },
        'video_qualities': [
          {
            'id': 1,
            'url_type': 'HLS',
            'url': 'https://media.example.test/signed/master.m3u8',
            'quality': 'Auto',
          },
        ],
      });

      expect(authorization.sessionId, 'session-123');
      expect(authorization.expiresAt, '2026-12-01T12:00:00Z');
      expect(authorization.videoQualities.single['url'],
          'https://media.example.test/signed/master.m3u8');
    });

    test('rejects an incomplete session or empty quality list', () {
      expect(
        () => PlaybackAuthorization.fromJson({
          'playback_session': {'session_id': 'session-123'},
          'video_qualities': [],
        }),
        throwsFormatException,
      );
      expect(
        () => PlaybackAuthorization.fromJson({
          'playback_session': {'session_id': ''},
          'video_qualities': [
            {'url': 'https://media.example.test/video.m3u8'},
          ],
        }),
        throwsFormatException,
      );
    });
  });

  test('login response does not reuse or serialize a stale API token', () {
    final user = UserData.fromJson({'id': 12, 'email': 'viewer@example.test'});

    expect(user.apiToken, isEmpty);
    expect(user.toJson().containsKey('api_token'), isFalse);
  });
}
