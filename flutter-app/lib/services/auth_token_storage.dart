import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Stores the Laravel Sanctum bearer token in the platform key store.
class AuthTokenStorage {
  AuthTokenStorage._();

  static const _key = 'ott_customer_access_token_v1';
  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
    iOptions: IOSOptions(
      accessibility: KeychainAccessibility.first_unlock_this_device,
    ),
  );

  static Future<String?> read() => _storage.read(key: _key);

  static Future<void> write(String token) async {
    if (token.isEmpty) {
      await clear();
      return;
    }
    await _storage.write(key: _key, value: token);
  }

  static Future<void> clear() => _storage.delete(key: _key);
}
