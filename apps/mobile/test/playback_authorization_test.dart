import 'package:flutter_test/flutter_test.dart';
import 'package:swann_ott/screens/auth/model/login_response.dart';
import 'package:swann_ott/screens/content/model/content_model.dart';
import 'package:swann_ott/screens/content/model/playback_authorization.dart';
import 'package:swann_ott/screens/coming_soon/model/coming_soon_response.dart';

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

  test('content models preserve UUID server identifiers and numeric legacy IDs', () {
    const uuid = '01934f2a-7b5c-7abc-8def-123456789abc';
    final movie = ContentModel.fromContentJson({
      'id': uuid,
      'details': {'id': uuid, 'type': 'movie'},
    });
    final legacyPoster = PosterDataModel.fromPosterJson({
      'id': '42',
      'details': {'id': '42', 'type': 'movie'},
    });
    final episode = PosterDataModel.fromEpisodeJson({
      'id': uuid,
      'details': {'id': uuid, 'type': 'episode'},
    });

    expect(movie.id, -1);
    expect(movie.playbackContentId, uuid);
    expect(movie.toContentJson()['id'], uuid);
    expect(legacyPoster.id, 42);
    expect(legacyPoster.entertainmentServerId, '42');
    expect(episode.serverId, uuid);
    expect(episode.entertainmentServerId, uuid);
  });

  test('coming soon models retain UUID request IDs and numeric legacy IDs', () {
    const uuid = '01934f2a-7b5c-7abc-8def-123456789abc';
    final upcoming = ComingSoonModel.fromJson({'id': uuid, 'type': 'movie'});
    final legacy = ComingSoonModel.fromJson({'id': '42', 'type': 'movie'});

    expect(upcoming.id, -1);
    expect(upcoming.requestId, uuid);
    expect(upcoming.toJson()['id'], uuid);
    expect(legacy.id, 42);
    expect(legacy.requestId, '42');
  });
}
