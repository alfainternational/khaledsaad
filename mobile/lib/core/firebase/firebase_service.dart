import 'dart:async';

import 'package:firebase_analytics/firebase_analytics.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_crashlytics/firebase_crashlytics.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import '../../firebase_options.dart';
import '../api/platform_repository.dart';

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  if (Firebase.apps.isEmpty) {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
  }
}

final class FirebaseService {
  FirebaseService._();

  static final FirebaseService instance = FirebaseService._();

  StreamSubscription<String>? _refreshSubscription;
  String? _token;
  bool _ready = false;

  Future<void> initialize() async {
    if (_ready) return;

    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

    FlutterError.onError = FirebaseCrashlytics.instance.recordFlutterFatalError;
    PlatformDispatcher.instance.onError = (error, stack) {
      FirebaseCrashlytics.instance.recordError(error, stack, fatal: true);
      return true;
    };

    await FirebaseAnalytics.instance.logAppOpen();
    _ready = true;
  }

  Future<void> syncDevice(PlatformRepository repository) async {
    if (!_ready) return;

    await FirebaseMessaging.instance.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: defaultTargetPlatform == TargetPlatform.iOS,
    );

    final token = await FirebaseMessaging.instance.getToken();

    if (token != null) {
      _token = token;
      await _send(repository, token);
    }

    await _refreshSubscription?.cancel();
    _refreshSubscription = FirebaseMessaging.instance.onTokenRefresh.listen(
      (freshToken) async {
        _token = freshToken;
        await _send(repository, freshToken);
      },
      onError: (Object error, StackTrace stack) {
        FirebaseCrashlytics.instance.recordError(error, stack);
      },
    );
  }

  Future<void> removeDevice(PlatformRepository repository) async {
    final token = _token;

    if (token != null) {
      try {
        await repository.removeDevice(token);
      } catch (error, stack) {
        await FirebaseCrashlytics.instance.recordError(error, stack);
      }
    }

    await _refreshSubscription?.cancel();
    _refreshSubscription = null;
    _token = null;
  }

  Future<void> _send(PlatformRepository repository, String token) {
    final platform = defaultTargetPlatform == TargetPlatform.iOS
        ? 'ios'
        : 'android';

    return repository.registerDevice(
      token: token,
      platform: platform,
      deviceName: 'Khaled Saad Growth',
    );
  }
}
