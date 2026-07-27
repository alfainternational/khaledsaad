import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart'
    show TargetPlatform, defaultTargetPlatform, kIsWeb;

abstract final class DefaultFirebaseOptions {
  static FirebaseOptions get currentPlatform {
    if (kIsWeb) {
      throw UnsupportedError('لم يُعَدّ Firebase لنسخة الويب من التطبيق.');
    }

    return switch (defaultTargetPlatform) {
      TargetPlatform.android => android,
      TargetPlatform.iOS => ios,
      _ => throw UnsupportedError(
        'Firebase متاح في هذا المشروع على Android وiOS فقط.',
      ),
    };
  }

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'AIzaSyCgpJysaj-iK7c4Lh_OxtAhHEN7aNbY2S8',
    appId: '1:274149767180:android:462a480f59022977f2d629',
    messagingSenderId: '274149767180',
    projectId: 'khaledsaad-growth',
    storageBucket: 'khaledsaad-growth.firebasestorage.app',
  );

  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'AIzaSyBXpvI21HP2Cz0eVj9N2XuT_7hmCZur-LA',
    appId: '1:274149767180:ios:58cce9c677dea676f2d629',
    messagingSenderId: '274149767180',
    projectId: 'khaledsaad-growth',
    storageBucket: 'khaledsaad-growth.firebasestorage.app',
    iosBundleId: 'net.khaledsaad.ksgrowthMobile',
  );
}
