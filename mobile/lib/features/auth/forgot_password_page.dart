import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
import '../../data/repositories/auth_repository.dart';

class ForgotPasswordPage extends StatefulWidget {
  const ForgotPasswordPage({super.key});

  @override
  State<ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends State<ForgotPasswordPage> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _loading = false.obs;
  final _sentMessage = RxnString();
  final _error = RxnString();

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false) || _loading.value) return;
    _loading.value = true;
    _error.value = null;
    try {
      final message = await Get.find<AuthRepository>().forgotPassword(_email.text.trim());
      _sentMessage.value = message;
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('استعادة كلمة المرور')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'أدخل بريدك وسنرسل لك رابطاً لإعادة التعيين.',
                  style: theme.textTheme.bodyMedium,
                ),
                const SizedBox(height: 24),
                Obx(() {
                  final sent = _sentMessage.value;
                  if (sent == null) return const SizedBox.shrink();
                  return Container(
                    margin: const EdgeInsets.only(bottom: 16),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: theme.colorScheme.primaryContainer,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(sent,
                        style:
                            TextStyle(color: theme.colorScheme.onPrimaryContainer)),
                  );
                }),
                Obx(() {
                  final err = _error.value;
                  if (err == null) return const SizedBox.shrink();
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(err, style: TextStyle(color: theme.colorScheme.error)),
                  );
                }),
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
                const SizedBox(height: 24),
                Obx(() => FilledButton(
                      onPressed: _loading.value ? null : _submit,
                      child: _loading.value
                          ? const SizedBox(
                              height: 22,
                              width: 22,
                              child: CircularProgressIndicator(strokeWidth: 2.4),
                            )
                          : const Text('إرسال الرابط'),
                    )),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
