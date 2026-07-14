import 'package:flutter/material.dart';

import '../../../data/services/voice_recorder_service.dart';
import 'ui_feedback.dart';

/// زر إدخال صوتي عام (تكلّم بدل الكتابة): يسجّل صوتاً ثم يسلّم مسار الملف
/// للأب عبر [onRecorded] الذي يرفعه ويفرّغه. يدير حالات: خامل/يسجّل/يعالج.
class VoiceInputButton extends StatefulWidget {
  const VoiceInputButton({
    super.key,
    required this.onRecorded,
    this.idleLabel = 'تكلّم',
    this.compact = false,
  });

  /// يُستدعى بمسار الملف المسجّل؛ على الأب أن يرفعه ويعالج النتيجة.
  final Future<void> Function(String filePath) onRecorded;
  final String idleLabel;
  final bool compact;

  @override
  State<VoiceInputButton> createState() => _VoiceInputButtonState();
}

enum _VoiceState { idle, recording, processing }

class _VoiceInputButtonState extends State<VoiceInputButton> {
  final VoiceRecorderService _recorder = VoiceRecorderService();
  _VoiceState _state = _VoiceState.idle;

  @override
  void dispose() {
    _recorder.dispose();
    super.dispose();
  }

  Future<void> _toggle() async {
    if (_state == _VoiceState.processing) return;

    if (_state == _VoiceState.recording) {
      final path = await _recorder.stop();
      if (!mounted) return;
      if (path == null) {
        setState(() => _state = _VoiceState.idle);
        return;
      }
      setState(() => _state = _VoiceState.processing);
      try {
        await widget.onRecorded(path);
      } finally {
        if (mounted) setState(() => _state = _VoiceState.idle);
      }
      return;
    }

    // خامل → ابدأ التسجيل (بعد الإذن).
    final ok = await _recorder.start();
    if (!mounted) return;
    if (!ok) {
      UiFeedback.error('لم نتمكّن من الوصول إلى الميكروفون. فعّل الإذن وحاول مجدداً.');
      return;
    }
    setState(() => _state = _VoiceState.recording);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final recording = _state == _VoiceState.recording;
    final processing = _state == _VoiceState.processing;

    final Widget icon = processing
        ? const SizedBox(
            width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
        : Icon(recording ? Icons.stop_circle_outlined : Icons.mic_none_outlined,
            size: 18);

    final String label = processing
        ? 'جارٍ التحويل...'
        : recording
            ? 'أوقف'
            : widget.idleLabel;

    final Color color =
        recording ? theme.colorScheme.error : theme.colorScheme.primary;

    if (widget.compact) {
      return IconButton(
        onPressed: processing ? null : _toggle,
        icon: icon,
        color: color,
        tooltip: label,
      );
    }

    return TextButton.icon(
      onPressed: processing ? null : _toggle,
      icon: icon,
      label: Text(label),
      style: TextButton.styleFrom(foregroundColor: color),
    );
  }
}
