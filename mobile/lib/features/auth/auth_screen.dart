import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/i18n/app_strings.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import 'password_reset_request_screen.dart';

/// يقابل resources/views/auth/login.blade.php وregister.blade.php
class AuthScreen extends StatefulWidget {
  const AuthScreen({
    super.key,
    required this.repository,
    required this.onAuthenticated,
    this.onBack,
    this.registering = true,
    this.initialExperience = 'business',
  });

  final PlatformRepository repository;
  final VoidCallback onAuthenticated;
  final VoidCallback? onBack;
  final bool registering;
  final String initialExperience;

  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();

  late bool _isRegistering = widget.registering;
  bool _busy = false;
  String? _error;
  late String _experience;

  @override
  void initState() {
    super.initState();
    _experience = {'business', 'learning'}.contains(widget.initialExperience)
        ? widget.initialExperience
        : 'business';
  }

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
          experience: _experience,
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
    final strings = AppStrings.of(context);
    return AdaptiveScaffold(
      family: AdaptivePageFamily.form,
      appBar: widget.onBack == null
          ? null
          : AppBar(
              leading: IconButton(
                tooltip: strings.text('back'),
                onPressed: widget.onBack,
                icon: const Icon(Icons.arrow_back),
              ),
            ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: EdgeInsets.zero,
            child: BrandCard(
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      strings.text(
                        _isRegistering ? 'register_title' : 'login_title',
                      ),
                      style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      strings.text(
                        _isRegistering ? 'register_lead' : 'login_lead',
                      ),
                      style: const TextStyle(color: BrandColors.muted),
                    ),
                    const SizedBox(height: 20),

                    if (_error != null) ...[
                      ErrorNotice(message: _error!),
                      const SizedBox(height: 16),
                    ],

                    if (_isRegistering) ...[
                      Text(
                        strings.text('experience_question'),
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 8),
                      RadioGroup<String>(
                        groupValue: _experience,
                        onChanged: (value) {
                          if (value != null) {
                            setState(() => _experience = value);
                          }
                        },
                        child: Column(
                          children: [
                            RadioListTile<String>(
                              value: 'business',
                              title: Text(strings.text('business_choice')),
                              subtitle: Text(
                                strings.text('business_description'),
                              ),
                            ),
                            RadioListTile<String>(
                              value: 'learning',
                              title: Text(strings.text('learning_choice')),
                              subtitle: Text(
                                strings.text('learning_description'),
                              ),
                            ),
                          ],
                        ),
                      ),
                      Text(
                        strings.text('other_later'),
                        style: const TextStyle(color: BrandColors.muted),
                      ),
                      const SizedBox(height: 18),
                      TextFormField(
                        controller: _name,
                        decoration: InputDecoration(
                          labelText: strings.text('name'),
                        ),
                        textInputAction: TextInputAction.next,
                        validator: (value) =>
                            (value == null || value.trim().isEmpty)
                            ? strings.text('name_required')
                            : null,
                      ),
                      const SizedBox(height: 14),
                    ],

                    TextFormField(
                      controller: _email,
                      decoration: InputDecoration(
                        labelText: strings.text('email'),
                      ),
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      validator: (value) =>
                          (value == null || !value.contains('@'))
                          ? strings.text('email_invalid')
                          : null,
                    ),
                    const SizedBox(height: 14),

                    TextFormField(
                      controller: _password,
                      decoration: InputDecoration(
                        labelText: strings.text('password'),
                        helperText: strings.text('password_help'),
                      ),
                      obscureText: true,
                      validator: (value) => (value == null || value.length < 8)
                          ? strings.text('password_help')
                          : null,
                    ),
                    const SizedBox(height: 22),

                    FilledButton(
                      onPressed: _busy ? null : _submit,
                      child: _busy
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : Text(
                              _isRegistering
                                  ? strings.text('register_submit')
                                  : strings.text('login_submit'),
                            ),
                    ),
                    const SizedBox(height: 10),

                    if (!_isRegistering)
                      TextButton(
                        onPressed: _busy
                            ? null
                            : () => Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => PasswordResetRequestScreen(
                                    repository: widget.repository,
                                  ),
                                ),
                              ),
                        child: Text(strings.text('forgot_password')),
                      ),

                    TextButton(
                      onPressed: _busy
                          ? null
                          : () => setState(() {
                              _isRegistering = !_isRegistering;
                              _error = null;
                            }),
                      child: Text(
                        _isRegistering
                            ? strings.text('have_account')
                            : strings.text('need_account'),
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
