import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/widgets/common.dart';

class PasswordResetScreen extends StatefulWidget {
  const PasswordResetScreen({
    super.key,
    required this.repository,
    required this.token,
    required this.email,
    required this.onComplete,
  });

  final PlatformRepository repository;
  final String token;
  final String email;
  final VoidCallback onComplete;

  @override
  State<PasswordResetScreen> createState() => _PasswordResetScreenState();
}

class _PasswordResetScreenState extends State<PasswordResetScreen> {
  final _form = GlobalKey<FormState>();
  final _password = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final message = await widget.repository.resetPassword(
        token: widget.token,
        email: widget.email,
        password: _password.text,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
      widget.onComplete();
    } on ApiException catch (exception) {
      if (mounted) setState(() => _error = exception.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('تعيين كلمة مرور جديدة')),
    body: ListView(
      padding: const EdgeInsets.all(20),
      children: [
        BrandCard(
          child: Form(
            key: _form,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(widget.email),
                const SizedBox(height: 16),
                if (_error != null) ...[
                  ErrorNotice(message: _error!),
                  const SizedBox(height: 12),
                ],
                TextFormField(
                  controller: _password,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'كلمة المرور الجديدة',
                  ),
                  validator: (value) => value == null || value.length < 8
                      ? 'ثمانية أحرف على الأقل.'
                      : null,
                ),
                const SizedBox(height: 18),
                FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: Text(_busy ? 'جارٍ الحفظ…' : 'حفظ كلمة المرور'),
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}
