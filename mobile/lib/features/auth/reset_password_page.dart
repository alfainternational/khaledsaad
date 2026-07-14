import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../data/repositories/auth_repository.dart';
import '../shared/widgets/animated_app_background.dart';
import '../shared/widgets/ui_feedback.dart';

/// إعادة تعيين كلمة المرور: يكمل مسار «نسيت كلمتي».
/// يُفتح عبر deep link (ksgrowth://auth/reset?token=&email=) أو يدوياً بإدخال الرمز.
class ResetPasswordPage extends StatefulWidget {
  const ResetPasswordPage({super.key});

  @override
  State<ResetPasswordPage> createState() => _ResetPasswordPageState();
}

class _ResetPasswordPageState extends State<ResetPasswordPage> {
  final _formKey = GlobalKey<FormState>();
  final _token = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();

  final _loading = false.obs;
  final _obscure = true.obs;

  @override
  void initState() {
    super.initState();
    // تعبئة مسبقة من deep link (token/email) إن مُرّرت.
    final args = Get.arguments;
    if (args is Map) {
      _token.text = args['token']?.toString() ?? '';
      _email.text = args['email']?.toString() ?? '';
    }
  }

  @override
  void dispose() {
    _token.dispose();
    _email.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false) || _loading.value) return;
    _loading.value = true;
    try {
      final message = await Get.find<AuthRepository>().resetPassword(
        token: _token.text.trim(),
        email: _email.text.trim(),
        password: _password.text,
        passwordConfirmation: _confirm.text,
      );
      UiFeedback.success(message, title: 'كلمة المرور');
      Get.offAllNamed(Routes.login);
    } on ApiException catch (e) {
      final fieldError = e.errors.isNotEmpty ? e.errors.values.first.first : null;
      UiFeedback.error(fieldError ?? e.message, title: 'كلمة المرور');
    } finally {
      _loading.value = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('تعيين كلمة مرور جديدة')),
      body: AnimatedAppBackground(
        child: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'أدخل الرمز الذي وصلك في البريد وكلمة المرور الجديدة.',
                    style: theme.textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 24),
                  TextFormField(
                    controller: _email,
                    keyboardType: TextInputType.emailAddress,
                    decoration: const InputDecoration(
                      labelText: 'البريد الإلكتروني',
                      prefixIcon: Icon(Icons.mail_outline),
                    ),
                    validator: (v) =>
                        (v == null || v.trim().isEmpty) ? 'أدخل بريدك' : null,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _token,
                    decoration: const InputDecoration(
                      labelText: 'رمز إعادة التعيين',
                      prefixIcon: Icon(Icons.vpn_key_outlined),
                    ),
                    validator: (v) =>
                        (v == null || v.trim().isEmpty) ? 'أدخل الرمز' : null,
                  ),
                  const SizedBox(height: 16),
                  Obx(
                    () => TextFormField(
                      controller: _password,
                      obscureText: _obscure.value,
                      decoration: InputDecoration(
                        labelText: 'كلمة المرور الجديدة',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          icon: Icon(_obscure.value
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined),
                          onPressed: () => _obscure.toggle(),
                        ),
                      ),
                      validator: (v) => (v == null || v.length < 8)
                          ? 'كلمة المرور 8 أحرف على الأقل'
                          : null,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Obx(
                    () => TextFormField(
                      controller: _confirm,
                      obscureText: _obscure.value,
                      textInputAction: TextInputAction.done,
                      onFieldSubmitted: (_) => _submit(),
                      decoration: const InputDecoration(
                        labelText: 'تأكيد كلمة المرور',
                        prefixIcon: Icon(Icons.lock_outline),
                      ),
                      validator: (v) =>
                          v != _password.text ? 'كلمتا المرور غير متطابقتين' : null,
                    ),
                  ),
                  const SizedBox(height: 24),
                  Obx(() => FilledButton(
                        onPressed: _loading.value ? null : _submit,
                        child: _loading.value
                            ? const SizedBox(
                                height: 22,
                                width: 22,
                                child:
                                    CircularProgressIndicator(strokeWidth: 2.4),
                              )
                            : const Text('تحديث كلمة المرور'),
                      )),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
