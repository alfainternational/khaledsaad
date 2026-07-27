import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/config/app_environment.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import '../reports/report_screen.dart';
import 'models.dart';

/// يقابل resources/views/app/runs/status.blade.php
/// بنفس فترة الاستطلاع ونفس نصوص المراحل.
class RunStatusScreen extends StatefulWidget {
  const RunStatusScreen({
    super.key,
    required this.repository,
    required this.run,
  });

  final PlatformRepository repository;
  final ToolRunModel run;

  @override
  State<RunStatusScreen> createState() => _RunStatusScreenState();
}

class _RunStatusScreenState extends State<RunStatusScreen> {
  late ToolRunModel _run = widget.run;
  Timer? _timer;
  String? _error;
  bool _retrying = false;

  @override
  void initState() {
    super.initState();
    if (!_run.isTerminal) _startPolling();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _startPolling() {
    _timer = Timer.periodic(
      AppEnvironment.progressPollInterval,
      (_) => _poll(),
    );
    _poll();
  }

  Future<void> _poll() async {
    try {
      final progress = await widget.repository.progress(_run.uuid);

      if (!mounted) return;

      setState(() {
        _run = progress;
        _error = null;
      });

      if (progress.isTerminal) {
        _timer?.cancel();
        _openReport();
      }
    } catch (error) {
      // انقطاع الشبكة لا يوقف الاستطلاع؛ المحاولة التالية تكفي.
      if (mounted) setState(() => _error = error.toString());
    }
  }

  void _openReport() {
    final reportId = _run.reportId;
    if (reportId == null || !mounted) return;

    Navigator.of(context).pushReplacement(
      MaterialPageRoute(
        builder: (_) =>
            ReportScreen(repository: widget.repository, reportId: reportId),
      ),
    );
  }

  Future<void> _retry() async {
    setState(() => _retrying = true);

    try {
      final run = await widget.repository.retryRun(_run.uuid);
      setState(() {
        _run = run;
        _error = null;
      });
      _startPolling();
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _retrying = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AdaptiveScaffold(
      family: AdaptivePageFamily.form,
      appBar: AppBar(title: Text(_run.statusLabel)),
      body: ListView(
        padding: EdgeInsets.zero,
        children: [
          Text(
            '${_run.toolTitle} · ${_run.projectName}',
            style: const TextStyle(color: BrandColors.muted),
          ),
          const SizedBox(height: 6),
          const Text(
            'إجاباتك محفوظة. يمكنك إغلاق الشاشة والعودة لاحقًا.',
            style: TextStyle(color: BrandColors.muted),
          ),
          const SizedBox(height: 18),

          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: _run.progressPercent / 100,
              minHeight: 8,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            '${_run.progressPercent}% مكتمل',
            style: const TextStyle(color: BrandColors.muted, fontSize: 13),
          ),
          const SizedBox(height: 18),

          for (final stage in _run.stages) ...[
            BrandCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(child: Text(stage.label)),
                      Text(
                        stage.statusLabel,
                        style: TextStyle(
                          fontSize: 13,
                          color: switch (stage.status) {
                            'completed' => BrandColors.success,
                            'failed' => BrandColors.red,
                            'running' => BrandColors.blue,
                            _ => BrandColors.muted,
                          },
                        ),
                      ),
                    ],
                  ),
                  if (stage.error != null) ...[
                    const SizedBox(height: 6),
                    Text(
                      stage.error!,
                      style: const TextStyle(
                        color: BrandColors.red,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 10),
          ],

          if (_run.failureReason != null) ...[
            const SizedBox(height: 10),
            ErrorNotice(
              message: _run.failureReason!,
              onRetry: _retrying ? null : _retry,
            ),
          ],

          if (_error != null && _run.failureReason == null) ...[
            const SizedBox(height: 10),
            Text(
              _error!,
              style: const TextStyle(color: BrandColors.muted, fontSize: 12),
            ),
          ],
        ],
      ),
    );
  }
}
