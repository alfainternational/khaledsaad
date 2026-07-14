import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';

/// خدمة تسجيل صوتي رقيقة فوق حزمة record — لإدخال الصوت (تكلّم بدل الكتابة).
/// تنتج ملف m4a (AAC) في مجلد مؤقّت، يُرفَع ثم يُفرَّغ نصاً على الخادم.
class VoiceRecorderService {
  final AudioRecorder _recorder = AudioRecorder();

  /// هل مُنِح إذن الميكروفون؟ (يطلبه من المستخدم عند أول نداء.)
  Future<bool> hasPermission() => _recorder.hasPermission();

  Future<bool> get isRecording => _recorder.isRecording();

  /// يبدأ التسجيل ويعيد true عند النجاح (بعد التأكد من الإذن).
  Future<bool> start() async {
    if (!await _recorder.hasPermission()) return false;

    final dir = await getTemporaryDirectory();
    final path =
        '${dir.path}/voice_${DateTime.now().millisecondsSinceEpoch}.m4a';

    await _recorder.start(
      const RecordConfig(encoder: AudioEncoder.aacLc, numChannels: 1),
      path: path,
    );
    return true;
  }

  /// يوقف التسجيل ويعيد مسار الملف الناتج (أو null إن تعذّر).
  Future<String?> stop() => _recorder.stop();

  Future<void> cancel() async {
    if (await _recorder.isRecording()) {
      await _recorder.cancel();
    }
  }

  void dispose() => _recorder.dispose();
}
