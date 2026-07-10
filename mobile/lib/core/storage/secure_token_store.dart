import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// تخزين آمن للتوكن ومساحة العمل النشطة.
class SecureTokenStore {
  SecureTokenStore([FlutterSecureStorage? storage])
      : _storage = storage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(encryptedSharedPreferences: true),
            );

  final FlutterSecureStorage _storage;

  static const _kToken = 'auth_token';
  static const _kWorkspace = 'active_workspace_public_id';

  Future<void> saveToken(String token) => _storage.write(key: _kToken, value: token);

  Future<String?> readToken() => _storage.read(key: _kToken);

  Future<void> clearToken() => _storage.delete(key: _kToken);

  Future<void> saveActiveWorkspace(String publicId) =>
      _storage.write(key: _kWorkspace, value: publicId);

  Future<String?> readActiveWorkspace() => _storage.read(key: _kWorkspace);

  Future<void> clearActiveWorkspace() => _storage.delete(key: _kWorkspace);

  Future<void> clearAll() async {
    await clearToken();
    await clearActiveWorkspace();
  }
}
