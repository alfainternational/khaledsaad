package net.khaledsaad.ksgrowth_mobile

import io.flutter.embedding.android.FlutterFragmentActivity

// FlutterFragmentActivity (بدل FlutterActivity) مطلوب لعمل local_auth
// (المصادقة البيومترية تحتاج FragmentActivity).
class MainActivity : FlutterFragmentActivity()
