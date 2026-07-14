import 'dart:convert';
import 'dart:io';

import 'package:crypto/crypto.dart';

/// تثبيت الشهادة (Certificate Pinning) لاتصال الـ API.
///
/// البصمات المسموح بها (SHA-256 لكامل شهادة الخادم، base64) تُضبط عبر:
///   flutter run --dart-define=CERT_PINS=pin1,pin2
///
/// افتراضياً فارغة ⇒ **التثبيت معطّل**، تفادياً لتعطّل التطبيق عند تجديد الشهادة
/// دون خطة تدوير بصمات (backup pin). فعّله فقط مع خطة تدوير واضحة.
///
/// القيم الحالية لـ khaledsaad.net (لحظة الإعداد — للتفعيل عند اعتماد الخطة):
///   • بصمة الشهادة الكاملة: IaibbN2+6I1Lj1iy4WKIWZIbExzgZRBXeBNQGUa+AbE=
///   • بصمة المفتاح العام (SPKI، لنهج أمتن مستقبلاً): rLaPSNYUmFfDf/vR13i3mB/z20Z14oTSLaJPCwp0pu8=
const String _certPinsRaw = String.fromEnvironment('CERT_PINS', defaultValue: '');

List<String> _pins() => _certPinsRaw
    .split(',')
    .map((e) => e.trim())
    .where((e) => e.isNotEmpty)
    .toList();

/// هل التثبيت مُفعّل (توجد بصمة واحدة على الأقل)؟
bool certPinningEnabled() => _pins().isNotEmpty;

/// يتحقّق أن بصمة شهادة الخادم ضمن البصمات المثبّتة.
/// يُستدعى لكل اتصال TLS (حتى للشهادات الصالحة) عبر IOHttpClientAdapter.
bool validatePinnedCertificate(X509Certificate? cert, String host, int port) {
  final pins = _pins();
  if (pins.isEmpty) return true; // معطّل → لا تثبيت
  if (cert == null) return false;
  final fingerprint = base64.encode(sha256.convert(cert.der).bytes);
  return pins.contains(fingerprint);
}
