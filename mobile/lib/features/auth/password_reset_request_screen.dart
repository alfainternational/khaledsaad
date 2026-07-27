import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';

class PasswordResetRequestScreen extends StatefulWidget {
  const PasswordResetRequestScreen({super.key, required this.repository});

  final PlatformRepository repository;

  @override
  State<PasswordResetRequestScreen> createState() =>
      _PasswordResetRequestScreenState();
}

class _PasswordResetRequestScreenState
    extends State<PasswordResetRequestScreen> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController();
  bool _busy = false;
  String? _message;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _message = null;
      _error = null;
    });

    try {
      final message = await widget.repository.requestPasswordReset(
        _email.text.trim(),
      );
      if (mounted) setState(() => _message = message);
    } on ApiException catch (exception) {
      if (mounted) setState(() => _error = exception.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AdaptiveScaffold(
      family: AdaptivePageFamily.form,
      appBar: AppBar(title: const Text('استعادة كلمة المرور')),
      body: ListView(
        padding: EdgeInsets.zero,
        children: [
          BrandCard(
            child: Form(
              key: _form,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'استعد الوصول إلى حسابك',
                    style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'أدخل بريد حسابك. إذا كان مسجلًا، ستصلك رسالة لاختيار كلمة مرور جديدة.',
                  ),
                  const SizedBox(height: 18),
                  if (_message != null) ...[
                    Text(_message!),
                    const SizedBox(height: 12),
                  ],
                  if (_error != null) ...[
                    ErrorNotice(message: _error!),
                    const SizedBox(height: 12),
                  ],
                  TextFormField(
                    controller: _email,
                    keyboardType: TextInputType.emailAddress,
                    decoration: const InputDecoration(
                      labelText: 'البريد الإلكتروني',
                    ),
                    validator: (value) => value == null || !value.contains('@')
                        ? 'أدخل بريدًا صحيحًا.'
                        : null,
                  ),
                  const SizedBox(height: 18),
                  FilledButton(
                    onPressed: _busy ? null : _submit,
                    child: Text(
                      _busy ? 'جارٍ الإرسال…' : 'أرسل رابط الاستعادة',
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
