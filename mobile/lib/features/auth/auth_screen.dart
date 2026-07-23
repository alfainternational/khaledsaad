import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';

/// يقابل resources/views/auth/login.blade.php وregister.blade.php
class AuthScreen extends StatefulWidget {
  const AuthScreen({super.key, required this.repository, required this.onAuthenticated});

  final PlatformRepository repository;
  final VoidCallback onAuthenticated;

  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();

  bool _isRegistering = true;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      if (_isRegistering) {
        await widget.repository.register(
          name: _name.text.trim(),
          email: _email.text.trim(),
          password: _password.text,
        );
      } else {
        await widget.repository.login(
          email: _email.text.trim(),
          password: _password.text,
        );
      }

      if (mounted) widget.onAuthenticated();
    } on ApiException catch (exception) {
      setState(() => _error = exception.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: BrandCard(
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      _isRegistering ? 'ابدأ تشخيص مشروعك' : 'أهلًا بعودتك',
                      style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      _isRegistering
                          ? 'الحساب يحفظ إجاباتك وتقاريرك ويتيح مقارنة تقدمك لاحقًا.'
                          : 'ادخل لتكمل من حيث توقفت. تقاريرك ومهامك محفوظة.',
                      style: const TextStyle(color: BrandColors.muted),
                    ),
                    const SizedBox(height: 20),

                    if (_error != null) ...[
                      ErrorNotice(message: _error!),
                      const SizedBox(height: 16),
                    ],

                    if (_isRegistering) ...[
                      TextFormField(
                        controller: _name,
                        decoration: const InputDecoration(labelText: 'الاسم'),
                        textInputAction: TextInputAction.next,
                        validator: (value) =>
                            (value == null || value.trim().isEmpty) ? 'الاسم مطلوب.' : null,
                      ),
                      const SizedBox(height: 14),
                    ],

                    TextFormField(
                      controller: _email,
                      decoration: const InputDecoration(labelText: 'البريد الإلكتروني'),
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      validator: (value) => (value == null || !value.contains('@'))
                          ? 'أدخل بريدًا صحيحًا.'
                          : null,
                    ),
                    const SizedBox(height: 14),

                    TextFormField(
                      controller: _password,
                      decoration: const InputDecoration(
                        labelText: 'كلمة المرور',
                        helperText: 'ثمانية أحرف على الأقل.',
                      ),
                      obscureText: true,
                      validator: (value) =>
                          (value == null || value.length < 8) ? 'ثمانية أحرف على الأقل.' : null,
                    ),
                    const SizedBox(height: 22),

                    FilledButton(
                      onPressed: _busy ? null : _submit,
                      child: _busy
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : Text(_isRegistering ? 'إنشاء الحساب' : 'دخول'),
                    ),
                    const SizedBox(height: 10),

                    TextButton(
                      onPressed: _busy
                          ? null
                          : () => setState(() {
                                _isRegistering = !_isRegistering;
                                _error = null;
                              }),
                      child: Text(
                        _isRegistering ? 'لديك حساب؟ سجّل الدخول' : 'ليس لديك حساب؟ أنشئ حسابًا',
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
