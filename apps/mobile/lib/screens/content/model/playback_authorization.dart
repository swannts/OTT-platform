/// Mobile contract returned by the protected playback authorization endpoint.
///
/// This DTO stays independent of the content presentation models so its
/// validation can be tested without bootstrapping the full Flutter app.
class PlaybackAuthorization {
  final String sessionId;
  final String expiresAt;
  final String? mediaUrl;
  final List<Map<String, dynamic>> videoQualities;

  const PlaybackAuthorization({
    required this.sessionId,
    required this.expiresAt,
    required this.videoQualities,
    this.mediaUrl,
  });

  factory PlaybackAuthorization.fromJson(Map<String, dynamic> json) {
    final session = json['playback_session'] is Map
        ? Map<String, dynamic>.from(json['playback_session'] as Map)
        : <String, dynamic>{};
    final qualities = json['video_qualities'] is List
        ? (json['video_qualities'] as List)
            .whereType<Map>()
            .map((quality) => Map<String, dynamic>.from(quality))
            .where((quality) =>
                quality['url'] is String &&
                (quality['url'] as String).isNotEmpty)
            .toList(growable: false)
        : <Map<String, dynamic>>[];
    final sessionId = session['session_id'];
    if (sessionId is! String || sessionId.isEmpty || qualities.isEmpty) {
      throw const FormatException(
          'Playback authorization response is incomplete.');
    }
    return PlaybackAuthorization(
      sessionId: sessionId,
      expiresAt: session['expires_at'] is String
          ? session['expires_at'] as String
          : '',
      mediaUrl: session['media_url'] is String
          ? session['media_url'] as String
          : null,
      videoQualities: qualities,
    );
  }
}
