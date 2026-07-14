import 'dart:convert';

/// يبني مفتاح Idempotency ثابتاً (deterministic) من محتوى العملية.
///
/// نفس المدخلات تُنتج نفس المفتاح خلال الجلسة، فتتعرّف عليها بوابة الـ
/// idempotency في الخادم وتمنع تكرار الإنشاء عند إعادة المحاولة بعد انقطاع
/// مؤقّت. اختلاف المدخلات يُنتج مفتاحاً جديداً (عملية مختلفة فعلاً).
String stableIdempotencyKey(String prefix, Object? payload) {
  final encoded = jsonEncode(payload);
  // FNV-1a 64-bit (تُمثَّل ضمن حدود int في Dart بعمليات آمنة).
  var hash = 0xcbf29ce484222325;
  for (final unit in encoded.codeUnits) {
    hash ^= unit;
    hash = (hash * 0x100000001b3) & 0xFFFFFFFFFFFFFFFF;
  }
  return '$prefix-${hash.toRadixString(16).padLeft(16, '0')}';
}
