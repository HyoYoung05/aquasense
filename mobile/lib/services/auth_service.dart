import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../models/auth_result.dart';
import '../models/user.dart';
import 'api_service.dart';

abstract class TokenStore {
  Future<String?> read();
  Future<void> write(String token);
  Future<void> clear();
}

class SecureTokenStore implements TokenStore {
  final FlutterSecureStorage _storage;
  final String _key;

  SecureTokenStore({
    required String serverBaseUrl,
    FlutterSecureStorage storage = const FlutterSecureStorage(),
  }) : _storage = storage,
       _key = keyForServer(serverBaseUrl);

  static String keyForServer(String serverBaseUrl) =>
      'aquasense_owner_token:$serverBaseUrl';

  @override
  Future<String?> read() => _storage.read(key: _key);

  @override
  Future<void> write(String token) => _storage.write(key: _key, value: token);

  @override
  Future<void> clear() => _storage.delete(key: _key);
}

enum AuthState { checking, signedOut, signedIn, unavailable }

class AuthService extends ChangeNotifier {
  final ApiService api;
  final TokenStore store;

  AuthService(this.api, this.store);

  AuthState state = AuthState.checking;
  OwnerUser? user;
  String? error;
  String? notice;
  String? _token;
  int _generation = 0;
  bool _mustClear = false;
  bool _restoring = false;
  bool _signingIn = false;

  Future<void> restore() async {
    if (_restoring) return;
    _restoring = true;
    try {
      if (_mustClear) {
        await _forget();
        notifyListeners();
        return;
      }
      state = AuthState.checking;
      error = null;
      notifyListeners();
      try {
        _token = await store.read();
        if (_token == null) {
          state = AuthState.signedOut;
        } else {
          final data = await api.get('profile.php', token: _token);
          user = OwnerUser.fromJson(data['user'] as Map<String, dynamic>);
          state = AuthState.signedIn;
        }
      } on ApiException catch (exception) {
        if (exception.type == ApiErrorType.unauthorized) {
          await _forget(notice: 'Your session expired. Please sign in again.');
        } else {
          error = exception.message;
          state = AuthState.unavailable;
        }
      } on FormatException {
        error = 'The server returned an unreadable owner profile.';
        state = AuthState.unavailable;
      } catch (_) {
        error = 'Could not restore your session. Please retry or sign out.';
        state = AuthState.unavailable;
      }
      notifyListeners();
    } finally {
      _restoring = false;
    }
  }

  Future<void> login(String email, String password) async {
    if (_signingIn) {
      throw const ApiException(
        'Sign-in is already in progress.',
        type: ApiErrorType.validationError,
      );
    }
    _signingIn = true;
    notice = null;
    try {
      final data = await api.post(
        'login.php',
        body: {'email': email.trim(), 'password': password},
      );
      final result = AuthResult.fromJson(data);
      try {
        await store.write(result.token);
      } catch (_) {
        try {
          await api.post('logout.php', token: result.token);
        } catch (_) {
          // The rejected token remains bounded by the server expiry.
        }
        throw const ApiException(
          'Could not save your secure session. Please try again.',
          type: ApiErrorType.serverError,
        );
      }
      _token = result.token;
      user = result.user;
      error = null;
      _generation++;
      state = AuthState.signedIn;
      notifyListeners();
    } on ApiException catch (exception) {
      if (exception.type == ApiErrorType.unauthorized) {
        throw const ApiException(
          'Incorrect email or password, or this account cannot access the owner app.',
          type: ApiErrorType.unauthorized,
          status: 401,
        );
      }
      rethrow;
    } on FormatException {
      throw const ApiException(
        'The server returned an unreadable sign-in response.',
        type: ApiErrorType.serverError,
      );
    } catch (_) {
      throw const ApiException(
        'Could not sign in. Please try again.',
        type: ApiErrorType.serverError,
      );
    } finally {
      _signingIn = false;
    }
  }

  Future<Map<String, dynamic>> get(String path) async {
    final generation = _generation;
    if (state != AuthState.signedIn || _token == null) {
      throw const ApiException.unauthorized('Please sign in to continue.');
    }
    try {
      final data = await api.get(path, token: _token);
      if (generation != _generation) {
        throw const ApiException.unauthorized('The session changed.');
      }
      return data;
    } on ApiException catch (exception) {
      if (exception.type == ApiErrorType.unauthorized &&
          generation == _generation) {
        await _forget(notice: 'Your session expired. Please sign in again.');
        notifyListeners();
      }
      rethrow;
    }
  }

  Future<Map<String, dynamic>> postMultipart(
    String path, {
    required Map<String, String> fields,
    required String fileField,
    required String filePath,
    required String filename,
    Uint8List? fileBytes,
  }) async {
    final generation = _generation;
    final token = _token;
    if (state != AuthState.signedIn || token == null) {
      throw const ApiException.unauthorized('Please sign in to continue.');
    }
    try {
      final data = await api.postMultipart(
        path,
        token: token,
        fields: fields,
        fileField: fileField,
        filePath: filePath,
        filename: filename,
        fileBytes: fileBytes,
      );
      if (generation != _generation) {
        throw const ApiException.unauthorized('The session changed.');
      }
      return data;
    } on ApiException catch (exception) {
      if (exception.type == ApiErrorType.unauthorized &&
          generation == _generation) {
        await _forget(notice: 'Your session expired. Please sign in again.');
        notifyListeners();
      }
      rethrow;
    }
  }

  Future<Uint8List> getBytes(String path) async {
    final generation = _generation;
    final token = _token;
    if (state != AuthState.signedIn || token == null) {
      throw const ApiException.unauthorized('Please sign in to continue.');
    }
    try {
      final bytes = await api.getBytes(path, token: token);
      if (generation != _generation) {
        throw const ApiException.unauthorized('The session changed.');
      }
      return bytes;
    } on ApiException catch (exception) {
      if (exception.type == ApiErrorType.unauthorized &&
          generation == _generation) {
        await _forget(notice: 'Your session expired. Please sign in again.');
        notifyListeners();
      }
      rethrow;
    }
  }

  Future<void> _forget({String? notice}) async {
    _generation++;
    _token = null;
    user = null;
    this.notice = notice;
    state = AuthState.signedOut;
    _mustClear = true;
    try {
      await store.clear();
      _mustClear = false;
    } catch (_) {
      state = AuthState.unavailable;
      error = 'Could not clear secure storage. Please retry signing out.';
    }
  }

  Future<String?> logout() async {
    final token = _token;
    await _forget();
    notifyListeners();
    if (token == null) return null;
    try {
      await api.post('logout.php', token: token);
      return null;
    } catch (_) {
      return 'Signed out on this device. Server sign-out could not be confirmed; the session expires within 24 hours.';
    }
  }
}
