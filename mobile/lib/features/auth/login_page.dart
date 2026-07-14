import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../data/repositories/auth_repository.dart';
import '../../data/services/session_service.dart';
import '../shared/widgets/animated_app_background.dart';
import '../shared/widgets/brand_mark.dart';
import 'login_controller.dart';
import 'widgets/social_login_buttons.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();

  late final LoginController _c = Get.put(
    LoginController(Get.find<AuthRepository>(), Get.find<SessionService>()),
  );

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  void _submit() {
    if (_formKey.currentState?.validate() ?? false) {
      _c.login(_email.text, _password.text);
    }
  }


  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: AnimatedAppBackground(
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Form(
                key: _formKey,
                child: AutofillGroup(
                  child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Center(child: BrandMark(size: 58)),
                    const SizedBox(height: 18),
                    Text(
                      'مرحباً بعودتك',
                      style: theme.textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'سجّل دخولك لمتابعة مشاريعك التسويقية.',
                      style: theme.textTheme.bodyMedium,
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 32),
                    Obx(() {
                      final err = _c.formError.value;
                      if (err == null) return const SizedBox.shrink();
                      return Container(
                        margin: const EdgeInsets.only(bottom: 16),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: theme.colorScheme.errorContainer,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Text(
                          err,
                          style: TextStyle(
                            color: theme.colorScheme.onErrorContainer,
                          ),
                        ),
                      );
                    }),
                    Obx(
                      () => TextFormField(
                        controller: _email,
                        keyboardType: TextInputType.emailAddress,
                        textInputAction: TextInputAction.next,
                        autofillHints: const [AutofillHints.email],
                        decoration: InputDecoration(
                          labelText: 'البريد الإلكتروني',
                          prefixIcon: const Icon(Icons.mail_outline),
                          errorText: _c.fieldErrors['email'],
                        ),
                        validator: (v) => (v == null || v.trim().isEmpty)
                            ? 'أدخل بريدك الإلكتروني'
                            : null,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Obx(
                      () => TextFormField(
                        controller: _password,
                        obscureText: _c.obscurePassword.value,
                        textInputAction: TextInputAction.done,
                        autofillHints: const [AutofillHints.password],
                        onFieldSubmitted: (_) => _submit(),
                        decoration: InputDecoration(
                          labelText: 'كلمة المرور',
                          prefixIcon: const Icon(Icons.lock_outline),
                          errorText: _c.fieldErrors['password'],
                          suffixIcon: IconButton(
                            tooltip: _c.obscurePassword.value
                                ? 'إظهار كلمة المرور'
                                : 'إخفاء كلمة المرور',
                            icon: Icon(
                              _c.obscurePassword.value
                                  ? Icons.visibility_outlined
                                  : Icons.visibility_off_outlined,
                            ),
                            onPressed: () => _c.obscurePassword.toggle(),
                          ),
                        ),
                        validator: (v) => (v == null || v.isEmpty)
                            ? 'أدخل كلمة المرور'
                            : null,
                      ),
                    ),
                    Align(
                      alignment: AlignmentDirectional.centerEnd,
                      child: TextButton(
                        onPressed: () => Get.toNamed(Routes.forgotPassword),
                        child: const Text('نسيت كلمة المرور؟'),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Obx(
                      () => FilledButton(
                        onPressed: _c.isLoading.value ? null : _submit,
                        child: _c.isLoading.value
                            ? const SizedBox(
                                height: 22,
                                width: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.4,
                                ),
                              )
                            : const Text('تسجيل الدخول'),
                      ),
                    ),
                    const SocialLoginButtons(),
                    const SizedBox(height: 8),
                    Wrap(
                      alignment: WrapAlignment.center,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        const Text('ليس لديك حساب؟'),
                        TextButton(
                          onPressed: () => Get.toNamed(Routes.register),
                          child: const Text('أنشئ حساباً'),
                        ),
                      ],
                    ),
                    Center(
                      child: TextButton.icon(
                        onPressed: () => Get.toNamed(Routes.explore),
                        icon: const Icon(Icons.explore_outlined, size: 18),
                        label: const Text('استكشف المنصة بلا تسجيل'),
                      ),
                    ),
                  ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
