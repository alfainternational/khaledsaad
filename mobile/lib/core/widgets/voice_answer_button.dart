import 'dart:io';

import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';

import '../api/api_exception.dart';
import '../api/platform_repository.dart';

/// مسجّل إجابة صوتية لسؤال مفتوح — نظير مسجّل الويب حرفيًّا.
///
/// **يملأ الخانة ولا يرسل النموذج.** المراجعة شرط لا تحسين: النسخ العربي يخطئ
/// في الأسماء والأرقام، وما يدخل الدماغ بلا مراجعة يصير حقيقةً مصدرها خطأ
/// نسخ — وهي أسوأ من فجوة معلنة.
///
/// المدة تُرسل مع الملف لأن الخادم يحجز بها من سقف التكلفة **قبل** الاستدعاء.
class VoiceAnswerButton extends StatefulWidget {
  const VoiceAnswerButton({
    super.key,
    required this.repository,
    required this.projectSlug,
    required this.onTranscribed,
  });

  final PlatformRepository repository;
  final String projectSlug;
  final ValueChanged<String> onTranscribed;

  @override
  State<VoiceAnswerButton> createState() => _VoiceAnswerButtonState();
}

class _VoiceAnswerButtonState extends State<VoiceAnswerButton> {
  /// خمس دقائق: نفس حدّ الخادم. تسجيل أطول تكلفة بلا فائدة.
  static const _maxSeconds = 300;

  final AudioRecorder _recorder = AudioRecorder();

  bool _recording = false;
  bool _busy = false;
  String? _status;
  DateTime? _startedAt;

  @override
  void dispose() {
    _recorder.dispose();
    super.dispose();
  }

  Future<void> _toggle() async {
    if (_recording) {
      await _stop();
      return;
    }

    await _start();
  }

  Future<void> _start() async {
    // الإذن يُطلب عند أول استعمال لا عند فتح الشاشة: طلبه بلا سبب ظاهر
    // يجعل المستخدم يرفضه، ثم لا يعود يُسأل.
    if (!await _recorder.hasPermission()) {
      setState(() => _status = 'لم يُسمح باستخدام الميكروفون. اكتب إجابتك بدلًا من ذلك.');

      return;
    }

    final directory = await getTemporaryDirectory();
    final path = '${directory.path}/voice-answer.m4a';

    await _recorder.start(const RecordConfig(), path: path);

    if (!mounted) return;

    setState(() {
      _recording = true;
      _startedAt = DateTime.now();
      _status = 'يسجّل الآن…';
    });
  }

  Future<void> _stop() async {
    final path = await _recorder.stop();
    final seconds = _startedAt == null
        ? 1
        : DateTime.now().difference(_startedAt!).inSeconds.clamp(1, _maxSeconds);

    if (!mounted) return;

    setState(() {
      _recording = false;
      _busy = true;
      _status = 'يُنسَخ التسجيل…';
    });

    if (path == null) {
      setState(() {
        _busy = false;
        _status = 'لم يُسجَّل شيء.';
      });

      return;
    }

    try {
      final text = await widget.repository.transcribeVoice(
        widget.projectSlug,
        path,
        seconds,
      );

      if (!mounted) return;

      widget.onTranscribed(text);
      setState(() => _status = 'راجع النص قبل الإرسال — النسخ قد يخطئ في الأسماء والأرقام.');
    } on ApiException catch (error) {
      // رسالة السقف تُعرض بنصّها: هي إرشاد بميزانية لا عطل يُبتلع.
      if (mounted) setState(() => _status = error.message);
    } catch (_) {
      if (mounted) {
        setState(() => _status = 'تعذّر نسخ التسجيل. حاول مرة أخرى أو اكتب إجابتك.');
      }
    } finally {
      // الملف المؤقت لا يبقى على جهاز المستخدم بعد رفعه.
      await File(path).delete().catchError((_) => File(path));

      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 8),
        TextButton.icon(
          onPressed: _busy ? null : _toggle,
          icon: Icon(_recording ? Icons.stop_circle : Icons.mic),
          label: Text(_recording ? 'أوقف التسجيل' : 'سجّل إجابتك صوتيًّا'),
        ),
        if (_status != null)
          Text(_status!, style: Theme.of(context).textTheme.bodySmall),
      ],
    );
  }
}
